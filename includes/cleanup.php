<?php

/**
 * Clean up transient files as to be kind to our hosting environment
 *
 *
 * @since      1.0.1
 * @package    Linkgreen_Product_Import
 * @subpackage Linkgreen_Product_Import/includes
 * @author     LinkGreen <admin@linkgreen.ca>
 */
class Linkgreen_Product_Import_Cleanup {

    protected $_type;
    protected $product_queue;
    protected $item_to_delete = 0;

    public function __construct($type, $itemId = NULL) {
        if ($type === "logs" || $type === "cache" || $type === "products" || ($type === "product" && $itemId <> NULL))
            $this->_type = $type;
        else
            wp_die( "invalid cleanup routine defined" ); // call me paranoid
        
        $this->load_dependencies();

        if ($type === "products" || $type === "product")
            $this->product_queue = new Linkgreen_Product_Import_Task_Product_Delete();
        
        if ($type === "product" && absint( $itemId ) > 0)
            $this->item_to_delete = $itemId;        
    }

    private function load_dependencies() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/constants.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/helpers.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/task-product-delete.php';
    }

    public function run() {
        log_debug( "running clean process on " . $this->_type );

        if ($this->_type === "products" || $this->_type === "product") {
            $this->delete_all_products_and_images();
        } else {
            self::delete_folder_contents( $this->_type );
        }
        
        // should also prolly delete the php log file when deleting logs .. ? (you'd get that from WP constants I guess ..)
    }

    private function delete_all_products_and_images() {
               
        log_debug( "[DELETE] ==running delete_all_products_and_images==" );

        $args = array( 
            'post_type' => 'product', 
            'numberposts' => -1,
            'post_status' => array('publish') //, 'pending', 'draft')
        );

        if ($this->item_to_delete > 0) {
            $args['meta_key'] = '_linkgreen_item_id';
            $args['meta_value'] = $this->item_to_delete;
            $args['numberposts'] = 1;

            log_debug( "[DELETE] only process the SINGLE item " . $this->item_to_delete );
        }

        $allProductPosts = get_posts( $args );

        foreach( $allProductPosts as $productPost ) {
            $post_id = $productPost->ID;
            
            $task = array(
                'post_id' => $post_id
            );

            $this->product_queue->push_to_queue( $task );
        }

        $count_of_products = count( $allProductPosts );
        if ($count_of_products > 0) {
            log_debug( "[DELETE] because $count_of_products products were found, we will now dispatch the delete queue" );
            $this->product_queue->save()->dispatch();
        }

        log_debug( "[DELETE] completed main call for product deletion" );
    }

    private static function delete_folder_contents($folder) {
        $path = plugin_dir_path( dirname( __FILE__ ) ) . $folder;

        log_debug( "deleting all contents of $folder by looking here $path");

        self::delete_all_directory_contents( $path );
        
        return;

        $dir = opendir( $path );
        
        log_debug( $dir );

        if ($dir) {
            // Read directory contents
            while (false !== ($file = readdir($dir))) {
                log_debug( "deleting $file" );

                // Check the create time of each file (older than 5 days)
                //if (filemtime($file) < (time() - 60*60*24*5)) {
                //    unlink ( $file );
                //}

                wp_delete_file( $file );
            }
        } else {
            log_error( "unable to clean folder $path" );
        }
    }

    private static function delete_all_directory_contents($dirPath) {
        if (! is_dir($dirPath)) {
            return false;
        }
    
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }
    
        if ($handle = opendir($dirPath)) {
    
            while (false !== ($sub = readdir($handle))) {
                if ($sub != "." && $sub != ".." && $sub != "Thumb.db") {
                    $file = $dirPath . $sub;
    
                    if (is_dir($file)) {
                        self::delete_all_directory_contents($file);
                        rmdir($dirPath);
                    } else {
                        unlink($file);
                    }
                }
            }
    
            closedir($handle);
        }
    }

}