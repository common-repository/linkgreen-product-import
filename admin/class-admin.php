<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.linkgreen.ca/
 * @since      1.0.0
 *
 * @package    Linkgreen_Product_Import
 * @subpackage Linkgreen_Product_Import/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Linkgreen_Product_Import
 * @subpackage Linkgreen_Product_Import/admin
 * @author     LinkGreen <admin@linkgreen.ca>
 */
class Linkgreen_Product_Import_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

    // todo: move all occurances of this to CONSTANTS file
    // todo: either finish this feature or axe it, but it's currently half-baked
	private $_transient = 'linkgreen-product-import-session-in-progress';

	
	/**
	 * The array of templates that this plugin tracks.
	 */
	public $templates;


	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		$this->load_dependencies();
	}

	/**
	 * Load the required dependencies for the Admin facing functionality.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Plugin_Admin_Settings. Registers the admin settings and page.
	 *
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'admin/class-plugin-settings.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/helpers.php';
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Linkgreen_Product_Import_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Linkgreen_Product_Import_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/linkgreen-product-import-admin.css', array(), rand(111,9999), 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Linkgreen_Product_Import_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Linkgreen_Product_Import_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/linkgreen-product-import-admin.js', array( 'jquery' ), $this->version, false );

	}


	// TODO we could use this feature to add defer / async to more of our resources for a speedy page load!
	public function add_async_defer_attribute($tag, $handle, $src) {
		// if not a google maps api call, return it
		if (! strpos( $src, '//maps.googleapis.com/maps/api' ))
			return $tag;

		// add async + defer to our custom googlemaps api script inclusion
		if ('lgpi-google-maps' === $handle)
			return "<script type='text/javascript' src='" . esc_url( $src ) . "' async defer></script>";

		log_debug( "decapitating $handle");

		// highlander any other included maps script (THERE CAN BE ONLY ONE)
		return "";
	}


    public function delete_cache() {
		if (current_user_can( 'manage_options' ))
            do_action( Linkgreen_Constants::RUN_CACHE_CLEAN_HOOK_NAME );

        if ( ! isset ( $_GET['_wp_http_referer'] ) )
			wp_die( 'Missing target.' );
			
		$url = add_query_arg( 'msg', 'cache-deleted-success', urldecode( $_GET['_wp_http_referer'] ) );

		wp_safe_redirect( $url );
		exit;
    }

	public function delete_log() {

		// TODO: check for nonce
        if (current_user_can( 'manage_options' ))
            do_action( Linkgreen_Constants::RUN_LOG_CLEAN_HOOK_NAME );

		if ( ! isset ( $_GET['_wp_http_referer'] ) )
			wp_die( 'Missing target.' );
			
		$url = add_query_arg( 'msg', 'log-deleted-success', urldecode( $_GET['_wp_http_referer'] ) );

		wp_safe_redirect( $url );
		exit;
	}

	public function show_attachments() {
		// TODO: check for nonce
		if ( ! current_user_can( 'manage_options' ))
			wp_die( 'Not authorized.');

		if ( ! isset ( $_GET['_wp_http_referer'] ) )
			wp_die( 'Missing target.' );
			
        lg_list_product_attachments();
        
		exit;	
    }
    
    public function delete_products() {
        if ( ! current_user_can( 'manage_options' )) {
			wp_die( 'Not authorized.');
        }

        $hook = Linkgreen_Constants::RUN_PRODUCT_CLEAN_HOOK_NAME;
		$msg = 'manual-delete-success';

		if ( wp_next_scheduled( $hook ) ) 
			wp_clear_scheduled_hook( $hook );
		
		// kick this off 10 seconds from meow
		$sched_result = wp_schedule_single_event( time() + 10, $hook, array() ); // in WP v5.0 this will return true if successful
		
		// unfortunately we can't check much else until WP v5 bool retrun val is working; as of 4.x it can return NULL 
		if ( $sched_result === false ) {  
			log_error( "Tried to schedule '$hook' but failed!" );
			$msg = 'manual-delete-failure';
		}

		// so we get all the cron jobs and check that ours is there (it should be for the next 9.75 seconds anyway)
		$cron_jobs = get_option( 'cron' );
		$found_job = false;
		foreach ($cron_jobs as $jobid => $job) {
			if (isset( $job[$hook] ))					
				$found_job = true;
		}
		if (! $found_job) {
			log_error( 'did not find job in cron list for product deletion task' );
			$msg = 'manual-delete-failure';
		} else 
			log_debug( 'product deletion is scheduled' );
		
		if ( ! isset ( $_GET['_wp_http_referer'] ) )
			wp_die( 'Missing target.' );

		$url = add_query_arg( 'msg', $msg, urldecode( $_GET['_wp_http_referer'] ) );

		wp_safe_redirect( $url );
		exit;		
    }

	public function add_weekly_cron_schedule() {
		$schedules['lgpi_weekly'] = array(
			'interval' => 604800,
			'display'  => __( 'Once Weekly' )
		);

		return $schedules;
	}

	/**
	 * Set an appropriate timeout for our API calls
	 */
	function lg_url_request_args($r, $url){
		if( preg_match("/linkgreen\.ca/", $url) ){
			$r["timeout"] = 20; // seconds
		}
		return $r;
	}

	/**
	 * Adds our template to the page dropdown for v4.7+
	 *
	 */
	public function add_new_template( $posts_templates ) {
		$posts_templates = array_merge( $posts_templates, $this->templates );

		return $posts_templates;
	}

	/**
	 * Adds our template to the pages cache in order to trick WordPress
	 * into thinking the template file exists where it doesn't really exist.
	 */
	public function register_project_templates( $atts ) {

		// Create the key used for the themes cache
		$cache_key = 'page_templates-' . md5( get_theme_root() . '/' . get_stylesheet() );

		// Retrieve the cache list. 
		// If it doesn't exist, or it's empty prepare an array
		$templates = wp_get_theme()->get_page_templates();
		if ( empty( $templates ) ) {
			$templates = array();
		} 

		// New cache, therefore remove the old one
		wp_cache_delete( $cache_key , 'themes');

		// Now add our template to the list of templates by merging our templates
		// with the existing templates array from the cache.
		$templates = array_merge( $templates, $this->templates );

		// Add the modified cache to allow WordPress to pick it up for listing
		// available templates
		wp_cache_add( $cache_key, $templates, 'themes', 1800 );

		return $atts;

	} 

	/**
	 * Checks if the template is assigned to the page
	 */
	public function view_project_template( $template ) {
		
		// Get global post
		global $post;

		// Return template if post is empty
		if ( ! $post ) {
			return $template;
		}

		// Return default template if we don't have a custom one defined
		if ( ! isset( $this->templates[get_post_meta( 
			$post->ID, '_wp_page_template', true 
		)] ) ) {
			return $template;
		} 

		$file = plugin_dir_path( __FILE__ ). get_post_meta( 
			$post->ID, '_wp_page_template', true
		);

		// Just to be safe, we check if the file exist first
		if ( file_exists( $file ) ) {
			return $file;
		} else {
			echo $file;
		}

		// Return template
		return $template;

	}
	
}
