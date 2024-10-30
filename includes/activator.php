<?php

/**
 * Fired during plugin activation
 *
 * @link       https://www.linkgreen.ca/
 * @since      1.0.0
 *
 * @package    Linkgreen_Product_Import
 * @subpackage Linkgreen_Product_Import/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Linkgreen_Product_Import
 * @subpackage Linkgreen_Product_Import/includes
 * @author     LinkGreen <admin@linkgreen.ca>
 */
class Linkgreen_Product_Import_Activator {

    public function __construct() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/constants.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/helpers.php';
	}

	public static function activate() {

        // setup cron job to delete old json cache 
        $hook = Linkgreen_Constants::RUN_CACHE_CLEAN_HOOK_NAME;
        //$interval = Linkgreen_Constants::CRON_SCHEDULE_CACHE_CLEAN;
        $when = strtotime( "+7 day" );
        $how_often = "weekly";

        if (! self::try_schedule_recurring_cron( $when, $how_often, $hook ) ) 
            log_error( "failed setting up the cache cleaning job with interval $how_often" );


        // setup cron job to delete old log files
        $hook = Linkgreen_Constants::RUN_LOG_CLEAN_HOOK_NAME;
        
        if (! self::try_schedule_recurring_cron( $when, $how_often, $hook ) ) 
            log_error( "failed setting up the log cleaning job with interval $how_often" );

    }
    

    // hokay, we've used this a couple of times now, time for it to be in helpers [TODO]
    private static function try_schedule_recurring_cron($first_time, $schedule, $hook) {
		if ( wp_next_scheduled( $hook ) ) 
			wp_clear_scheduled_hook( $hook );
		
		$sched_result = wp_schedule_event( $first_time, $schedule, $hook, array() ); // in WP v5.0 this will return true if successful
		
		// unfortunately we can't check much else until WP v5 bool retrun val is working; as of 4.x it can return NULL 
		if ( $sched_result === false ) 
			log_error( "Tried to schedule '$hook' but failed!" );

		// so we get all the cron jobs and check that ours is there
		$cron_jobs = get_option( 'cron' );
		$found_job = false;
		foreach ($cron_jobs as $jobid => $job) {
			if (isset( $job[$hook] ))					
				$found_job = true;
		}

		return $found_job;
	}


}
