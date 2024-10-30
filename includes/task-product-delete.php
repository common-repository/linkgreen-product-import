<?php include plugin_dir_path( dirname( __FILE__ ) ) . 'includes/wp-background-processing/wp-background-processing.php';

class Linkgreen_Product_Import_Task_Product_Delete extends WP_Background_Process {
    /**
	 * @var string
	 */
	protected $action = 'lgpi_task_process_product_delete';

    protected $cron_interval = 1; // minutes to re-up this task queue processing

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
	 * @param mixed API call to download products
	 *
	 * @return mixed
	 */
	protected function task( $context ) {
        $post_id = $context['post_id'];

        $attachments = get_posts( array(
            'post_type' => 'attachment',						
            'numberposts' => -1,	 	  								
            'fields' => 'ids', 
            'post_parent' => $post_id,
        ));
        
        log_debug( "[DELETE] Deleting product id $post_id with " . count( $attachments ) . " images");

        if ($attachments) {		 		
            foreach ($attachments as $attachmentID) {
                $attachment_path = get_attached_file( $attachmentID ); 
                $deleted = wp_delete_attachment( $attachmentID, true ); // ($force_delete = true) Delete attachment from database and from file system 

                if ( $deleted === false || $deleted === null)
                    log_error( "failed to delete image '$attachment_path'");
                else
                    log_debug( "deleted image '$attachment_path'");
            }
        }

        wp_delete_post( $post_id, true ); 

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

    private function load_dependencies() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/helpers.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/constants.php';
	}
}