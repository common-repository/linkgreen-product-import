<?php include plugin_dir_path( dirname( __FILE__ ) ) . 'includes/wp-background-processing/wp-background-processing.php';

class Linkgreen_Product_Import_Task_Product_Callback extends WP_Background_Process {
    /**
	 * @var string
	 */
	protected $action = 'lgpi_task_process_product_callback';

    public function __construct() {
        parent::__construct();
        $this->init();
    }

    private function init() {
        $this->load_dependencies();
    }

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed info for the API call to LinkGreen about one-off product import completion
	 *
	 * @return mixed
	 */
	protected function task( $context ) {

        try {

            if (! isset($context) || $context == null) {
                log_error( 'that empty callback context tho' );
                return false; // remove from queue anyway
            }
            
            $setup = get_option('linkgreen_product_import_setup_options');

            if (isset( $setup['api_token'] ))
                $context['api_token'] = $setup['api_token'];

            $post_id = $context['post_id'];
            $permalink = get_post_permalink( $post_id );

            log_debug( "starting the callback to LinkGreen for $post_id to tell her about $permalink" );

            // build the call using our context
            $api_call = $this->build_api_url( $context );
            log_debug( "about to call $api_call" );

            // make the call
            $response = getSerializedApiResponse( $api_call, false, array(
                    'Url' => $permalink,
                    'WooCommerceId' => $post_id
                ), 
                true // validate "success": <bool> wrapper that normally occurs on LG API
            );

            // this endpoint should return success
            if ($response === null) {
                log_error( 'lgpi_task_process_product_callback has failed on notifying LinkGreen API - catastrophic');
            } elseif ( isset( $response->Error )) {
                log_error( 'lgpi_task_process_product_callback has failed on notifying LinkGreen API : ' . $response->Error );
            } elseif ( isset( $response->Success )) {
                log_debug( "successful call to LinkGreen API");
            }
    
        } catch (Exception $e) {
            log_error( 'Caught exception running callback task; queue item will be deleted. MSG: ' );
            log_error( $e->getMessage() );
        }

        // either way, we're not going to process this item again
		return false;
	}

	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();
	}

    private function build_api_url( $context ) {

        $is_live = boolval( $context['is_live'] ) === true;
        $itemId = $context['linkgreen_item_id'];
        $key = $context['api_token'] ?: 'not-set';

        $callback_api_url = ( boolval( $is_live ) === true 
            ? 'https://linkgreen-' 
            : 'https://linkgreen-coreapi-dev-' ) 
                . "coreapi.azurewebsites.net/api/Plugins/WooCommerce/Products/Link/$key/$itemId";

        return $callback_api_url;
    }

    private function load_dependencies() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/helpers.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/constants.php';
	}
}