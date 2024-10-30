<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://www.linkgreen.ca/
 * @since      1.0.0
 *
 * @package    Linkgreen_Product_Import
 * @subpackage Linkgreen_Product_Import/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Linkgreen_Product_Import
 * @subpackage Linkgreen_Product_Import/includes
 * @author     LinkGreen <admin@linkgreen.ca>
 */
class Linkgreen_Product_Import_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'linkgreen-product-import',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
