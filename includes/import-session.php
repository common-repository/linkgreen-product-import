<?php

/**
 * Define the product import session
 *
 * Load product data from LinkGreen API and stick it into WordPress
 * so that it can be used by WooCommerce
 *
 * @link       https://www.linkgreen.ca/
 * @since      1.0.0
 *
 * @package    Linkgreen_Product_Import
 * @subpackage Linkgreen_Product_Import/includes
 */
class Linkgreen_Product_Import_Session {

	protected $items_per_page = 5;
	protected $starting_page = 1;
	protected $is_debug_mode = false;
	protected $is_live_api = true;
	protected $api_key = null;
	protected $product_api_queue;

	private $_transient = 'linkgreen-product-import-session-in-progress';
	private $_cat_transient = 'lgpi-category-mapping';

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}	


	public function init() {
		
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

		// if ($this->api_key === null || empty( $this->api_key) )
		// 	wp_die("no API key specified");

	}

	private function load_dependencies() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/helpers.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/constants.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/task-product-api.php';
	}

	/**
	 * Run an import session
	 *
	 * called by the scheduled cron job only
	 * @since    1.0.0
	 */
	public function run() {
		$this->run_import_session();
	}

	/**
	 * Run an import session manually once
	 *
	 * called by settings page -> manual import tab
	 * @since    1.0.0
	 */
	public function run_in_background() {		
		if (! $this->is_valid_request()) 
			wp_die( "invalid request" );

		$hook = Linkgreen_Constants::RUN_IMPORT_HOOK_NAME;
		$msg = 'manual-import-success';

		if ( wp_next_scheduled( $hook ) ) 
			wp_clear_scheduled_hook( $hook );
		
		// kick this off 10 seconds from meow
		$sched_result = wp_schedule_single_event( time() + 10, $hook, array() ); // in WP v5.0 this will return true if successful
		
		// unfortunately we can't check much else until WP v5 bool retrun val is working; as of 4.x it can return NULL 
		if ( $sched_result === false ) {  
			log_error( "Tried to schedule '$hook' but failed!" );
			$msg = 'manual-import-failure';
		}

		// so we get all the cron jobs and check that ours is there (it should be for the next 9.75 seconds anyway)
		$cron_jobs = get_option( 'cron' );
		$found_job = false;
		foreach ($cron_jobs as $jobid => $job) {
			if (isset( $job[$hook] ))					
				$found_job = true;
		}
		if (! $found_job) {
			log_error( 'did not find job in cron list for one-time background session' );
			$msg = 'manual-import-failure';
		} else 
			log_debug( 'one-time background session is scheduled' );
		
		if ( ! isset ( $_POST['_wp_http_referer'] ) )
			wp_die( 'Missing target.' );

		$url = add_query_arg( 'msg', $msg, urldecode( $_POST['_wp_http_referer'] ) );

		wp_safe_redirect( $url );
		exit;		
	}

	private function is_valid_request() {

		$nonce_field = Linkgreen_Constants::RUN_IMPORT_NONCE_FIELD;
		$nonce_name = Linkgreen_Constants::RUN_IMPORT_NONCE;

		check_admin_referer( $nonce_name, $nonce_field );

		// do not allow concurrent running, or running by someone who's unauthorized
		return ( current_user_can( 'manage_options' ) & ! get_transient( $this->_transient ) );
	}

	private function run_import_session() {

        $mode = $this->is_debug_mode ? "DEBUG" : "LIVE";
        $api = $this->is_live_api ? "LIVE" : "DEV";
		log_debug("Linkgreen Product Import Session is starting now in $mode mode using the $api API");

        // UNUSED: never did finish this implementation
		set_transient( $this->_transient, 'locked', 60 ); // TODO: lol make it 600 but really, track how long the import takes over last 7 runs and add 10% to the max run of those


		$api_key = $this->api_key;
		$api_sku = ""; //"304291"; //"10094"; //10480 //"88182";
		$api_products_url   = $this->_api_base() . "/SupplierInventorySearchService/rest/GetItemVerbose/$api_key?sku=$api_sku&activeOnly=1&ownOnly=1";
		$api_categories_url = $this->_api_base() . "/categoryservice/rest/getall/" . $api_key;
		
		
		/******* CATEGORIES *******/
		// get all categories first and create them (nested) then keep track of their ids so you can attribute a product to that category
		$response_cat = getSerializedApiResponse( $api_categories_url );
		
		// gotta reset that transient tho
		set_transient( $this->_cat_transient, array(), 60*60 ); // cache for one hour

		if ($response_cat === null) {
			log_error( 'run_import_session has failed on getting categories from API and will exit prematurely');
			delete_transient( $this->_transient ); 
			return -1;
		}

		$cat_from_linkgreen = $response_cat->Result;
		$cat_depth_hash = array();
		
		if (count( $cat_from_linkgreen ) < 1) {
			log_error( 'run_import_session has failed to retrieve any categories from API and will exit prematurely');
			delete_transient( $this->_transient ); 
			return -1;
		}
		
		
		// TODO: (change) 
		// delete existing data (although, a nicer way would be to fetch everything and insert as a draft then mark published if successful)
		//$this->cleanupCategories(); 
		$this->cleanupProductsAndImages();
        

		// create layers of category depth, all 0 depth categories, 1 depth, 2 depth... etc
		foreach ($cat_from_linkgreen as $api_cat)
			$cat_depth_hash[$api_cat->Depth][] = $api_cat; 
		
		// loop through the depths sequentially, first 0, then 1, 2, etc and import so depth 1 cats can be assigned to their depth 0 parents
		foreach ($cat_depth_hash as $categoriesInSingleDepth)
			foreach ($categoriesInSingleDepth as $category)
				$this->importCategory($category);
		
		// this is potentially a lot of RAM we're using here
		unset($response_cat);
		unset($cat_from_linkgreen);
		/******* CATEGORIES *******/
		

        log_debug( 'finished importing categories, now starting products' );
        log_debug( "Categories that will be used while importing products:" );
        log_debug( get_transient( 'lgpi-category-mapping' ) );

        
		/******* PRODUCTS *******/
		// TODO: (change)
		// nuke + pave the products
		// we will offload each API call page (fetch 5 products) to it's own background task, which will, in turn, 
		// offload the actual prod import to yet another background task
		$api_context = array(
			'start_at' => 1,
			'per_page' => $this->items_per_page,
			'base_api' => $api_products_url,
		);

		$this->product_api_queue->push_to_queue( $api_context );
		$this->product_api_queue->save()->dispatch();
		log_debug( 'dispatched the first task in the product queue' );
		/******* PRODUCTS *******/

		//todo
		//delete_transient( $this->_transient ); //? need to re-do the concurrency stuff; prolly add a way to cancel remaining queue
	}
	


	private function importCategory($cat) {
        
        $slug = sanitize_title(sanitize_title($cat->Name, '', 'save'), '', 'query');

		$options = array(
	/*		'description'=> 'Category description', */
			'slug' => $slug . "-" . $cat->Id
		);
		
		if ($cat->ParentCategoryId != null) {

			$mappedParentTerm = get_term_id_from_cat_id( $cat->ParentCategoryId );
			//$mappedParentTerm = self::$cat_mapping[$cat->ParentCategoryId];
			$parentTerm = term_exists( $mappedParentTerm, 'product_cat' );
			
			if ($parentTerm != null && $parentTerm != 0) {
				$options += ['parent' => $parentTerm['term_id']];
			}
		}

        // check if the term already exists
        if ( version_compare( floatval( get_bloginfo( 'version' ) ), '4.5', '<' ) ) {
            $terms = get_terms( 'product_cat', array(
                'hide_empty' => false,
            ) );
        } else {
            $terms = get_terms( array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
            ) );
        }

        $term = null;
        // need to get all terms and loop through b/c the name could've changed so can't match on slug
        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ){
        //if ($terms != null && count($terms) > 0) {
            
            foreach ($terms as $the_term) {
                
                // loop through getting the linkgreen catId (Z) from the slug xxx-yyy-Z
                $words = explode('-', $the_term->slug);
                $id = array_pop($words);

                if ($id == $cat->Id) {
                    log_debug( "found existing cat " . $the_term->slug );
                    
                    // once we have that, check that the cat name is still the same (maybe it changed in LinkGreen?)
                    if ($term["name"] != $cat->Name) {
                        // update the name of the term to match the new name .. but don't update the slug, that's an SEO/web no-no
                        $updated = wp_update_term( $the_term->term_id, 'product_cat', array(
                            'name' => $cat->Name
                        ) );
                         
                        if ( is_wp_error( $updated ) ) {
                            log_error( "could not update product category with new name" );
                            log_error( $updated );
                        }
                    }                

                    $term = $the_term;
                    break;
                }
            }
        }

        // if not found, insert the new category
        if ($term == null) {
            $term = wp_insert_term(
                $cat->Name, // the term 
                'product_cat', // the taxonomy
                $options // optional stuffs
            );
            $cat_name = /*fluffy*/ $cat->Name;
            log_debug( "Inserted new category: $cat_name" );
            $term_id = $term['term_id'];
        } else {
            $term_id = $term->term_id;
        }

		if (is_wp_error($term)) {
            log_error( "... failed importing term: " );
            log_error( var_dump($term) );
		} else {
			set_cat_id_with_term_id( $cat->Id, $term_id );
		}
	}

	
	private function cleanupCategories() {
		$terms = get_terms( 'product_cat', array( 'fields' => 'ids', 'hide_empty' => false ) );
		foreach ( $terms as $value ) {
			wp_delete_term( $value, 'product_cat' );
		}
	}

	private function cleanupProductsAndImages() {
        do_action( Linkgreen_Constants::RUN_PRODUCT_CLEAN_HOOK_NAME );
    }

	private function _api_base() {
		return ($this->is_live_api ? 'https://app.linkgreen.ca' : 'http://dev.linkgreen.ca');
	} 

}
