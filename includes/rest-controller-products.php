<?php
 
class Product_Import_Rest_Controller_Products extends WP_REST_Controller {

  protected $product_queue;
  protected $is_debug_mode = false;
	protected $is_live_api = true;
	protected $api_key = null;


    public function __construct() {
      
      $this->load_dependencies();
  
      $this->product_api_queue = new Linkgreen_Product_Import_Task_Product_Api();
  
      $setup = get_option('linkgreen_product_import_setup_options');
      $debug = get_option('linkgreen_product_import_debug_options');
      
      if (isset( $debug['dev_mode'] ))
        $this->is_debug_mode = boolval( $debug['dev_mode'] );
      
      if (isset( $debug['dev_api'] ))
        $this->is_live_api = ! boolval( $debug['dev_api'] );

      if (isset( $setup['api_token'] ))
        $this->api_key = $setup['api_token'];            
    }

    private function load_dependencies() {
      require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/helpers.php';
      require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/constants.php';
      require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/task-product-api.php';
    }

  /**
   * take a list of IDs that should be updated/added and queue said operations
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|WP_REST_Response
   */
  public function add_or_update( $request ) {

    // loop through all complex objs sent to us
    foreach ($request['items'] as $item) {

      $itemId = $item['itemid'];
      $catIds = $item['catids'];
      
      // call linkgreen about this prod
      $api_call = $this->get_product_api_call( $itemId );
      log_debug( "Calling linkgreen about product $itemId using: $api_call" );
      $response_prod = getSerializedApiResponse( $api_call );

      if ($response_prod === null || ! property_exists( $response_prod, 'Result' )) {
        log_error( "bad response from LinkGreen API call to $api_call" );
        return new WP_Error( 'cant-add-or-update', 'could not get product from LinkGreen', array( 'status' => 500 ) );
      }

      if (! is_array( $response_prod->Result ) || empty( $response_prod->Result)) {
        log_error( "empty response from LinkGreen API call to $api_call" );
        return new WP_Error( 'cant-add-or-update', 'product seems empty', array( 'status' => 500 ) );
      }

      /*if (is_array( $catIds ) && ! empty( $catIds ))
        $term_id = $catIds[0]; // hard-coded to accept only one category for the time being
      else
        $term_id = 0;*/

      // queue the product add
      $task = array(
          'term_id' => $catIds,  // wooCommerce category id (comes from request)
          'product' => $response_prod->Result[0], // this is a call to a specific product, but the results are stored in array anyway
          'islive' => $this->is_live_api,
          'update_if_exists' => true,  
          'reorder_sort' => false, // could make this an option user can set in the admin area
          'linkgreen_callback' => true
      );

      // dispatch the queue
      $this->product_queue = new Linkgreen_Product_Import_Task_Product_Import();
      $this->product_queue->push_to_queue( $task );
      $this->product_queue->save()->dispatch();
    
      log_debug( "one-off product-queue dispatched from REST call for item $itemId" );
      // return result
    }

    $data = array( 'success' => true );
    $response = rest_ensure_response( $data );

    return new WP_REST_Response( $response, 200 ); 
  }
 
  /**
   * Delete one item from the collection
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|WP_REST_Request
   */
  public function delete_item( $request ) {

    if ( ! isset( $request['itemid'])) {
      log_error( "bad request to delete item" );
      return new WP_Error( 'cant-delete', 'invalid request', array( 'status' => 400 ) );
    }
    
    $itemid = $request['itemid'];
    $cleanup_products = new Linkgreen_Product_Import_Cleanup( "product", $itemid );
    $cleanup_products->run();

    $data = array( 'success' => true );
    $response = rest_ensure_response( $data );

    return new WP_REST_Response( $response, 200 ); 
  }
 
  /**
   * Get the full list of nested categories
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|WP_REST_Request
   */
  public function get_nested_categories( $request ) {

    $args = [
        'taxonomy' => 'product_cat',
        'hide_empty' => 0,
        'parent' => 0
    ];
    
    $cat_list = $this->_get_kittens( get_terms( $args ) );
      
    $data = array( 'success' => true, 'results' => $cat_list );
    $response = rest_ensure_response( $data );

    return new WP_REST_Response( $response, 200 ); 
  }
 
  //private function _get_child_cats( $items ) {
  private function _get_kittens( $items ) {
      foreach ( $items as $item ) {
        $item->children = get_terms( 'product_cat', array( 'child_of' => $item->term_id, 'hide_empty' => 0 ) );
        if ( $item->children ) $this->_get_kittens( $item->children ); 
      }

      return $items;
  }

  /**
   * Check if a given request has access to call for adding a product
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|bool
   */
  public function add_or_update_permissions_check( $request ) {

    $headers = $request->get_headers();

    if ( ! isset( $headers['x_auth_token'] ) ) 
        return new WP_Error( 'permission-check-auth', 'unauthorized', array( 'status' => 401 ) );

    $key = mb_strtolower( $headers['x_auth_token'][0] ); 
    $setup = get_option('linkgreen_product_import_setup_options');

    if ( ! isset( $setup['api_token'] )) 
        return new WP_Error( 'permission-check-token', 'invalid plugin setup', array( 'status' => 500 ) );

    return $key === mb_strtolower( $setup['api_token'] );
  }

  /**
   * Check if a given request has access to delete a specific item
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|bool
   */
  public function delete_item_permissions_check( $request ) {
    return $this->add_or_update_permissions_check( $request );
  }

  /**
   * Check if a given request has access to get a list of categories
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|bool
   */
  public function get_categories_permissions_check( $request ) {
    return $this->add_or_update_permissions_check( $request );
  }
  
  private function get_product_api_call( $product_id ) {

    $base = ($this->is_live_api ? 'https://app.linkgreen.ca' : 'http://dev.linkgreen.ca');

    return "$base/SupplierInventorySearchService/rest/GetItemVerbose/" .$this->api_key. "?productId=$product_id";
  } 

  /**
   * Register the routes for the objects of the controller.
   */
  public function register_routes() {
    $version = '1';
    $namespace = 'linkgreen/v' . $version;
    $base = 'products';

    register_rest_route( $namespace, '/' . $base, array(
      array(
        'methods'             => WP_REST_Server::DELETABLE,
        'callback'            => array( $this, 'delete_item' ),
        'permission_callback' => array( $this, 'delete_item_permissions_check' ),
        'args'                => array(
          'itemid' => array(
            'validate_callback' => function($product_id, $request, $key) {
                return is_numeric( $product_id ) && absint( $product_id ) > 0;
              }
          )
        ),
      ),
    ) );

    register_rest_route( $namespace, '/categories', array(
      array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => array( $this, 'get_nested_categories' ),
        'permission_callback' => array( $this, 'get_categories_permissions_check' )
        )
      )
    );

    register_rest_route( $namespace, '/' . $base, array(
        'methods'  => array( WP_REST_Server::CREATABLE, WP_REST_Server::EDITABLE ),
        'permission_callback' => array( $this, 'add_or_update_permissions_check' ),
        'args' => array(
            'items' => array(
              'validate_callback' => function($param, $request, $key) {
                // will have an itemid and an optional array of catids

                if ( ! is_array( $param )) return false;

                foreach ($param as $item) {

                    if ( ! isset( $item['itemid'] ) || absint( $item['itemid'] ) < 1) return false;

                    if ( isset( $item['catids'] ) && is_array( $item['catids'] ) ) {
                        foreach ($item['catids'] as $id) {
                            if ( ! is_numeric( $id ) || absint( $id ) < 1) return false;
                        } 
                    }
                }

                return true;
              }
            ),
          ),
        'callback' => array( $this, 'add_or_update' ),
      ) );
  }


  /**
   * Get the query params for collections
   *
   * @return array
   */
  public function get_collection_params() {
    return array(
      'page'     => array(
        'description'       => 'Current page of the collection.',
        'type'              => 'integer',
        'default'           => 1,
        'sanitize_callback' => 'absint',
      ),
      'per_page' => array(
        'description'       => 'Maximum number of items to be returned in result set.',
        'type'              => 'integer',
        'default'           => 10,
        'sanitize_callback' => 'absint',
      ),
      'search'   => array(
        'description'       => 'Limit results to those matching a string.',
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
      ),
    );
  }
}