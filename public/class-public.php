<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.linkgreen.ca/
 * @since      1.0.0
 *
 * @package    Linkgreen_Product_Import
 * @subpackage Linkgreen_Product_Import/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Linkgreen_Product_Import
 * @subpackage Linkgreen_Product_Import/public
 * @author     LinkGreen <admin@linkgreen.ca>
 */
class Linkgreen_Product_Import_Public {

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

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		$this->load_dependencies();
	}

	/**
	 * Load the required dependencies
	 *
	 * @since    1.0.1
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/helpers.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/store-locator.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/rest-controller-products.php';
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/linkgreen-product-import-public.css', array(), $this->version, 'all' );

		// retail locations page temlate
		if ( is_page_template( 'page-template-linkgreen-retail-locations.php' ) ) {

			// use the version, not the time (for dev use only) .. a step further might be to wrap this conditionally on whether is_debug_on === true
			wp_enqueue_style( 'lgpi-googlemaps-styles', plugin_dir_url( __FILE__ ) . 'css/retail-locations/styles-for-googlemaps.css', array(), $this->version, 'all' );
			//wp_enqueue_style( 'lgpi-googlemaps-styles', plugin_dir_url( __FILE__ ) . 'css/retail-locations/styles-for-googlemaps.css', array(), time(), 'all' );
		} 
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/linkgreen-product-import-public.js', array( 'jquery' ), $this->version, false );

		// retail locations page temlate
		if ( is_page_template( 'page-template-linkgreen-retail-locations.php' ) ) {
			
			// use the version, not the time (for dev use only) .. a step further might be to wrap this conditionally on whether is_debug_on === true
			//wp_enqueue_script( 'lgpi-setup-googlemaps-script', plugin_dir_url( __FILE__ ) . 'js/retail-locations/setup-googlemaps.js', array( 'jquery' ), $this->version, false );
			wp_enqueue_script( 'lgpi-setup-googlemaps-script', plugin_dir_url( __FILE__ ) . 'js/retail-locations/setup-googlemaps.js', array( 'jquery' ), time(), false );

			// retrieve users' google map key from settings page
			$options = get_option('linkgreen_product_import_setup_options');
			if (is_array( $options ) && array_key_exists( 'google_maps_token', $options )) {
				$map_api_key = $options['google_maps_token'];
				// ensure our inclusion of gmaps is dependent on our setup script
				wp_enqueue_script('lgpi-google-maps', esc_url( add_query_arg( 'key', $map_api_key.'&callback=initMap&libraries=places,geometry', '//maps.googleapis.com/maps/api/js' )), array('lgpi-setup-googlemaps-script'), null, true );
			}
		} 
	}

	public function register_shortcodes() {
		add_shortcode( 'lgpi-render-map-locations', array( $this, 'shortcode_render_map_locations') );
	}

	public function register_rest_api_routes() {
		$rest_api_route = new Product_Import_Rest_Controller_Products();
		$rest_api_route->register_routes();	
	}

	public function shortcode_render_map_locations($attr) {
		$a = shortcode_atts( array(
			'sku' => '',
			// ...etc
		), $attr );

		$location_loader = new Linkgreen_Product_Import_Store_Locator( $a['sku'] );

		return $location_loader->run();
	}

}
