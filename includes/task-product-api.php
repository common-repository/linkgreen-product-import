<?php include plugin_dir_path( dirname( __FILE__ ) ) . 'includes/wp-background-processing/wp-background-processing.php';

class Linkgreen_Product_Import_Task_Product_Api extends WP_Background_Process {
    /**
	 * @var string
	 */
	protected $action = 'lgpi_task_process_product_api';

    protected $product_queue;

    private $_context;
    private $_finished_fetching = true;
	private $_cat_transient = 'lgpi-category-mapping';

    public function __construct() {
        parent::__construct();
        //add_action( 'plugins_loaded', array( $this, 'init' ) );
        $this->init();
    }

    private function init() {
        $this->load_dependencies();
        $this->product_queue = new Linkgreen_Product_Import_Task_Product_Import();
    }

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed API call to download products
	 *
	 * @return mixed
	 */
	protected function task( $context ) {

        if (! isset($context) || $context == null) {
            log_error( 'that empty context tho' );
            // b/c finished_fetching = true we won't be coming back for seconds... infinite loops abound otherwise
            return false; // remove from queue anyway
            //wp_die( 'horible death' );
        }
        
        $this->_context = $context;
        $page = (($context['start_at'] - 1) / $context['per_page']) + 1;


        /* NOTE: !important */
        // this is part of the product import nuke and pave workflow; it will only continue if products = null


        // okay, before we start adding new products, just check if product_delete is complete yet...
        if ($page === 1) {
            $allProductPosts = get_posts( array( 
                'post_type' => 'product', 
                'numberposts' => 1,
                'post_status' => array('publish') //, 'pending', 'draft')
            ));

            if (count( $allProductPosts ) > 0) {
                log_debug( "Waiting for products to be deleted... sleep" );
                sleep( 10 );
                return $context;
            }
        }

        log_debug( "starting the API task for page $page" );
        
        // build the call using our context
        $api_call = $this->build_api_url();
        log_debug( "about to call $api_call" );

        // make the call
        $response_prod = getSerializedApiResponse( $api_call );

        if ($response_prod === null) {
            log_error( 'run_import_session has failed on getting products from API and will exit prematurely');
            
            // cancel the remaining queue; an abort/cleanup
            $this->cancel_process();
            
            //delete_transient( $this->_transient ); 
            return false;
        }

        $numberOfProducts = count($response_prod->Result);
        log_debug( "Page $page retrieved with $numberOfProducts products..." );
        
        if ($numberOfProducts == 0) {
            log_debug( "End of records reached; finishing fetch" );
            return false;
        }

        $is_live_mode = true;
        $debug = get_option('linkgreen_product_import_debug_options');
		
		if (isset( $debug['dev_api'] ) || array_key_exists( 'dev_api', $debug ))
			$is_live_mode = ( ! boolval( $debug['dev_api'] ) );

        $this->_finished_fetching = false; // so, we know there are product records in the result, thus there could be more...
        $found_product = false;
        foreach ($response_prod->Result as $prod) {
            
            if ((bool)$prod->RetailSell === false) {

                log_debug( "product " . $prod->SKU ." skipped because it's explicitly set in LinkGreen as 'Do not show on my web store'" );

            } elseif ($prod->ItemId > 0) {

                $term_id = get_term_id_from_cat_id( $prod->Category->Id );

                log_debug( "found prod #" . $prod->ItemId . " with woocommerce category id $term_id" );
                
                $task = array(
                    'term_id' => $term_id,
                    'product' => $prod,
                    'islive' => $is_live_mode
                );
            
                $this->product_queue->push_to_queue( $task );

                $found_product = true;
                
            } else {

                log_debug( "product ". $prod->SKU ." skipped because it's itemID is " . $prod->ItemId );

            }
        }

        if ($found_product) {
            log_debug( 'because products were found, we will now dispatch the queue' );
            $this->product_queue->save()->dispatch();
        }

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

        if ($this->_finished_fetching) return;

        // add next queue item onto queue
        $next_context = $this->_context;
        $next_context['start_at'] += $next_context['per_page'];

// TODO: a debug option for "only process the first ___ product records
//if ( $next_context['start_at'] >= 5 ) return;

        $queue = new Linkgreen_Product_Import_Task_Product_Api();
        $queue->push_to_queue( $next_context );
        $queue->save()->dispatch();
	}

    private function build_api_url() {
        $start_at = $this->_context['start_at'];
        $per_page = $this->_context['per_page'];
        $base_api = $this->_context['base_api'];
    
        // TODO: try increasing the prod/pg var by one each call and then back off some once timedout (catchable?), persist that as our usual ppp value
        return $base_api . ($per_page > 1 ? "&pageSize=$per_page&startAt=$start_at" : "");
    }

    private function load_dependencies() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/helpers.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/constants.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/task-product-import.php';
	}
}