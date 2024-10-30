<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.linkgreen.ca/
 * @since             1.0.0
 * @package           Linkgreen_Product_Import
 *
 * @wordpress-plugin
 * Plugin Name:       LinkGreen Product Import
 * Description:       Use your LinkGreen.io catalogs to feed WooCommerce with products including pictures, attributes & even store locations
 * Version:           1.0.8
 * Author:            LinkGreen
 * Author URI:        https://www.linkgreen.ca/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       linkgreen-product-import
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'LINKGREEN_PRODUCT_IMPORT_VERSION', '1.0.8' ); // don't forget to update the header summary comment at begining of this file

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/activator.php
 */
function activate_linkgreen_product_import() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/activator.php';
	Linkgreen_Product_Import_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/deactivator.php
 */
function deactivate_linkgreen_product_import() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/deactivator.php';
	Linkgreen_Product_Import_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_linkgreen_product_import' );
register_deactivation_hook( __FILE__, 'deactivate_linkgreen_product_import' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-linkgreen-product-import.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_linkgreen_product_import() {

	$plugin = new Linkgreen_Product_Import();
	$plugin->run();

}
run_linkgreen_product_import();
