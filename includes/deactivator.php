<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://www.linkgreen.ca/
 * @since      1.0.0
 *
 * @package    Linkgreen_Product_Import
 * @subpackage Linkgreen_Product_Import/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Linkgreen_Product_Import
 * @subpackage Linkgreen_Product_Import/includes
 * @author     LinkGreen <admin@linkgreen.ca>
 */
class Linkgreen_Product_Import_Deactivator {

	public function __construct( $plugin_name, $version ) {
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/constants.php';
	}

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		// delete our cron job for product import
        wp_clear_scheduled_hook(Linkgreen_Constants::RUN_IMPORT_HOOK_NAME);
        
        // delete our cron job for log cleanup
        wp_clear_scheduled_hook(Linkgreen_Constants::RUN_LOG_CLEAN_HOOK_NAME);

        // delete our cron job for cache cleanup
        wp_clear_scheduled_hook(Linkgreen_Constants::RUN_CACHE_CLEAN_HOOK_NAME);
	}

}
