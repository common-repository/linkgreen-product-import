<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://www.linkgreen.ca/
 * @since      1.0.0
 *
 * @package    Linkgreen_Product_Import
 * @subpackage Linkgreen_Product_Import/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Linkgreen_Product_Import
 * @subpackage Linkgreen_Product_Import/includes
 * @author     LinkGreen <admin@linkgreen.ca>
 */
class Linkgreen_Product_Import {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Linkgreen_Product_Import_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;


	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'LINKGREEN_PRODUCT_IMPORT_VERSION' ) ) {
			$this->version = LINKGREEN_PRODUCT_IMPORT_VERSION;
		} else {
			$this->version = '1.0.0'; // is this even necessary? how would this happen?
		}

		$this->plugin_name = 'linkgreen-product-import';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->setup_debugging();
		$this->define_background_task_hooks();
		$this->define_woo_hooks();
        
        if ( ! wp_mkdir_p( plugin_dir_path( dirname( __FILE__ ) ) . 'cache' )) {
            log_error( 'could not create CACHE folder to store cached json responses' );
        }
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Linkgreen_Product_Import_Loader. Orchestrates the hooks of the plugin.
	 * - Linkgreen_Product_Import_i18n. Defines internationalization functionality.
	 * - Linkgreen_Product_Import_Admin. Defines all hooks for the admin area.
	 * - Linkgreen_Product_Import_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-public.php';

		/**
		 * The class responsible for running the actual product import process
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/import-session.php';

		/**
		 * The class responsible for running the cleanup processes (deleting cache and logs)
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/cleanup.php';

        /**
		 * constants
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/constants.php';

		/**
		 * helper monkeys
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/helpers.php';

		/**
		 * The class responsible for listening for api web requests (push updates from linkgreen connect)
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/rest-controller-products.php';

		/**
		 * The class responsible for all WooCommerce customizations/interhooking
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/woo/custom-woo-features.php';

		/**
		 * The task processors need to be new'd up every request so WP_CRON can be used to offload the work and 
		 * continue to process the queues
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/task-product-api.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/task-product-import.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/task-product-delete.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/task-product-callback.php';


		$this->loader = new Linkgreen_Product_Import_Loader();
	}
	
	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Linkgreen_Product_Import_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Linkgreen_Product_Import_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register any WooCommerce hooks for customization business
	 * 
	 * @since	1.0.8
	 * @access	private
	 */
	private function define_woo_hooks() {

		$woo_custom_funcs = new Linkgreen_Custom_Woo_Features();

		// when a new order is created, find any drop-ship items and call LG to let her know about these items
		$this->loader->add_action( 'woocommerce_checkout_create_order', $woo_custom_funcs, 'checkout_create_order__update_order_metadata',  10, 2  );

		// when a new order is created, mark it as complete if it is fully fulfilled by LG
		$this->loader->add_action( 'woocommerce_thankyou', $woo_custom_funcs, 'thankyou__mark_full_dropship_as_complete',  10, 1  );
				
		// display the drop-ship info on the thankyou page
		$this->loader->add_action( 'woocommerce_thankyou', $woo_custom_funcs, 'thankyou__display_dropship_receipt',  10, 1  );

		// run on every line item added to order
		$this->loader->add_action( 'woocommerce_checkout_create_order_line_item', $woo_custom_funcs, 'checkout_create_order_line_item__update_metadata', 20, 4 );

		// add shipping note if order contains partial / multi-supplier shipping sources
		// OBSOLETE: $this->loader->add_action( 'woocommerce_checkout_create_order_shipping_item', $woo_custom_funcs, 'checkout_create_order_shipping_item__add_dropship_note', 20, 4 );

		// hide the meta data fields we don't want to see in the order details page
		$this->loader->add_filter('woocommerce_hidden_order_itemmeta', $woo_custom_funcs, 'hidden_order_itemmeta__hide_metadata_from_orders_detail', 10, 1);

		// just for testing probably b/c not sure we need to do anything on add to cart
		//$this->loader->add_action( 'woocommerce_add_to_cart', $woo_custom_funcs, 'add_to_cart',  10, 6  );

		// add filtering to the admin orders list
		$this->loader->add_action( 'restrict_manage_posts', $woo_custom_funcs, 'restrict_manage_posts__filter_orders_by_fulfillment' , 20 );
		$this->loader->add_filter( 'request', $woo_custom_funcs, 'request__filter_orders_by_fulfillment' );	

		// decorate the admin screen w/ a drop-ship message in the order meta data area
		$this->loader->add_action( 'woocommerce_admin_order_data_after_billing_address', $woo_custom_funcs, 'admin_order_data_after_billing_address__display_dropship_admin',  10, 1  );

		// decorate the cart page w/ a drop-ship message
		//$this->loader->add_filter( 'woocommerce_get_item_data', $woo_custom_funcs, 'get_item_data__dispaly_dropship_cart', 10, 2 );

		// decorate the email line items w/ a drop-ship message
		$this->loader->add_filter( 'woocommerce_order_item_name', $woo_custom_funcs, 'get_item_data__dispaly_dropship_email', 10, 2 );

		// decorate the admin page line item details w/ LG order details
		$this->loader->add_action( 'woocommerce_after_order_itemmeta', $woo_custom_funcs, 'after_order_itemmeta__display_dropship_details_admin', 20, 3 );

		// custom order status
		$this->loader->add_action( 'init', $woo_custom_funcs, 'init__custom_order_status' );
		$this->loader->add_filter( 'wc_order_statuses', $woo_custom_funcs, 'linkgreen_custom_order_status', 10, 1 );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Linkgreen_Product_Import_Admin( $this->get_plugin_name(), $this->get_version() );
		$plugin_settings = new Linkgreen_Product_Import_Admin_Settings( $this->get_plugin_name(), $this->get_version() );
		$import_session = new Linkgreen_Product_Import_Session(); //todo: could we pass $plugin_settings here and let that grab is_debug_on etc ?
        $cleanup_logs = new Linkgreen_Product_Import_Cleanup("logs");
        $cleanup_cache = new Linkgreen_Product_Import_Cleanup("cache");
        $cleanup_products = new Linkgreen_Product_Import_Cleanup("products");

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		$this->loader->add_action( 'admin_menu', $plugin_settings, 'setup_plugin_options_menu' );
		$this->loader->add_action( 'admin_init', $plugin_settings, 'initialize_setup_options' );
		$this->loader->add_action( 'admin_init', $plugin_settings, 'initialize_debug_options' );
		$this->loader->add_action( 'admin_init', $plugin_settings, 'initialize_input_examples' );

		// when user updates the import schedule frequency we need to add/remove cron job
		$this->loader->add_action( 'update_option_linkgreen_product_import_setup_options', $plugin_settings, 'update_import_schedule', 10, 2 );

		// this is the hook, for running from the CRON job
		$this->loader->add_action( Linkgreen_Constants::RUN_IMPORT_HOOK_NAME, $import_session, 'run' );

		// and this hook, is for when we post back from clicking "Run manual Import"
		$this->loader->add_action( 'admin_post_' . Linkgreen_Constants::RUN_IMPORT_HOOK_NAME, $import_session, 'run_in_background' );

        // this is the hook, for running the CRON job to clean the cache folder
        $this->loader->add_action( Linkgreen_Constants::RUN_CACHE_CLEAN_HOOK_NAME, $cleanup_cache, 'run' );

        // this is the hook, for running the CRON job to clean the logs folder
        $this->loader->add_action( Linkgreen_Constants::RUN_LOG_CLEAN_HOOK_NAME, $cleanup_logs, 'run' );

        // this is the hook, you like it. delete all the products
		$this->loader->add_action( Linkgreen_Constants::RUN_PRODUCT_CLEAN_HOOK_NAME, $cleanup_products, 'run' );

        // delete the cache manually
		$this->loader->add_action( 'admin_post_delete_cache', $plugin_admin, 'delete_cache' );

		// delete the log file manually
		$this->loader->add_action( 'admin_post_delete_log', $plugin_admin, 'delete_log' );

        // show (possibly delete?) the product page images
		$this->loader->add_action( 'admin_post_show_attachments', $plugin_admin, 'show_attachments' );

        // delete the products & images
		$this->loader->add_action( 'admin_post_delete_products', $plugin_admin, 'delete_products' );

        // TODO : i don't think this is working
		// setup our weekly cron sched
		$this->loader->add_filter( 'admin_init', $plugin_admin, 'add_weekly_cron_schedule' );

		// defer and async our google maps api script include
		$this->loader->add_filter( 'script_loader_tag', $plugin_admin, 'add_async_defer_attribute', 10, 3 );

		/**
		 * TEMPLATE MANAGEMENT
		 */
		$plugin_admin->templates = array();

		// Add a filter to the attributes metabox to inject template into the cache.
		if ( version_compare( floatval( get_bloginfo( 'version' ) ), '4.7', '<' ) ) {

			// 4.6 and older
			$this->loader->add_filter( 'page_attributes_dropdown_pages_args', $plugin_admin, 'register_project_templates' );

		} else {

			// Add a filter to the wp 4.7 version attributes metabox
			$this->loader->add_filter( 'theme_page_templates', $plugin_admin, 'add_new_template' );

		}

		// Add a filter to the save post to inject our template into the page cache
		$this->loader->add_filter( 'wp_insert_post_data', $plugin_admin, 'register_project_templates' );

		// technically a public hook .. (?)
		// Add a filter to the template include to determine if the page has our 
		// template assigned and return it's path
		$this->loader->add_filter( 'template_include', $plugin_admin, 'view_project_template');

		// allow a longer timeout for our API calls
		$this->loader->add_filter( 'http_request_args', $plugin_admin, 'lg_url_request_args', 10, 2 );

		// Add your templates to this array.
		$plugin_admin->templates = array(
			'page-template-linkgreen-retail-locations.php' => 'Retail Locations',
		);
			
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Linkgreen_Product_Import_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		$this->loader->add_action( 'init', $plugin_public, 'register_shortcodes' );
  
		$this->loader->add_action('rest_api_init', $plugin_public, 'register_rest_api_routes' ); 
	
	}

	/**
	 * Register all our task workers; they need to run on every page load to ensure queues are being processed
	 */
	private function define_background_task_hooks() {
		
		$task_product_api = new Linkgreen_Product_Import_Task_Product_Api();
		$task_product_import = new Linkgreen_Product_Import_Task_Product_Import();
		$task_product_delete = new Linkgreen_Product_Import_Task_Product_Delete();
		$task_product_callback = new Linkgreen_Product_Import_Task_Product_Callback();
		
		// we're just calling this base method for no reason; the inherited base constructor is what handles queue processing and task spawning
		$this->loader->add_action( 'plugins_loaded', $task_product_api, 'get_info' );
		$this->loader->add_action( 'plugins_loaded', $task_product_import, 'get_info' );
		$this->loader->add_action( 'plugins_loaded', $task_product_delete, 'get_info' );
		$this->loader->add_action( 'plugins_loaded', $task_product_callback, 'get_info' );		
	}

	private function setup_debugging() {
		$debug = get_option('linkgreen_product_import_debug_options');
		if (isset( $debug['dev_mode'] )) {
			
			$debug_mode_on = ( $debug['dev_mode'] == '1' ? true : false );

			if ( ! wp_mkdir_p( plugin_dir_path( dirname( __FILE__ ) ) . 'logs' )) {
				log_error( 'could not create LOGS folder to store debugging info ');
				$debug_mode_on = false;
			}
			
			define( 'LINKGREEN_PRODUCT_IMPORT_DEBUG_LOGGING_ENABLED', $debug_mode_on );
		} else {
			define( 'LINKGREEN_PRODUCT_IMPORT_DEBUG_LOGGING_ENABLED', false );
		}
	}


	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Linkgreen_Product_Import_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
