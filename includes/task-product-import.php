<?php include plugin_dir_path( dirname( __FILE__ ) ) . 'includes/wp-background-processing/wp-background-processing.php';

class Linkgreen_Product_Import_Task_Product_Import extends WP_Background_Process {

    /**
	 * @var string
	 */
	protected $action = 'lgpi_task_process_product_import';

    protected $cron_interval = 1; // minutes to re-up this task queue processing

    protected $should_update_sort_order = true; 

    protected $is_live_api = true;

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed product obj from LinkGreen to import into WordPress
	 *
	 * @return mixed
	 */
	protected function task( $product_task ) {
        
        if (! isset( $product_task ) || ! is_array( $product_task )) return false;
        if (! isset( $product_task['term_id'] ) || ! isset( $product_task['product'] ) ) return false;

        $should_check_if_exists = false;
        $should_call_linkgreen_when_done = false;

        try {

            $product = $product_task['product'];
            $term_id = $product_task['term_id'];
            $is_live = boolval( $product_task['islive'] === true); 
            
            if (isset( $product_task['reorder_sort'] ))
                $this->should_update_sort_order = boolval( $product_task['reorder_sort'] );

            if (isset( $product_task['update_if_exists'] ))
                $should_check_if_exists = boolval( $product_task['update_if_exists'] );
                
            if (isset( $product_task['linkgreen_callback'] ))
                $should_call_linkgreen_when_done = boolval( $product_task['linkgreen_callback'] );

            $this->load_dependencies();
            
            $itemId = $product->ItemId;

            if ($itemId < 1) {
                log_error( "[PRODUCT IMPORT] product ID was found to be invalid! aborting..." );
                return false; 
            }
            
            log_debug( "[PRODUCT IMPORT] product $itemId with catagory id $term_id will now be processed..." );

            // base product entry
            $item = [
                'post_title' => $product->Description,
                'post_status' => 'publish', // would be nice to set as draft then once operation was completed succesfully, set them all to published after deleting old ones
                'post_type' => 'product',
                'post_content' => $product->Comments
            ];

            // see if the product exists
            if ($should_check_if_exists === true) {
                $args = array(
                    'posts_per_page' => 1,
                    'post_type' => 'product',
                    'meta_query' => array(
                        array(
                            'key' => '_linkgreen_item_id',
                            'value' => $itemId,
                            'compare' => '=',
                        )
                    )
                );
                $products = new WP_Query($args);

                // The Loop
                if ( $products->have_posts() ): while ( $products->have_posts() ):
                    
                    $products->the_post();
                    $item['ID'] = $products->post->ID;

                    break;
                endwhile;
                    wp_reset_postdata();
                endif;
            }
            
            $postId = wp_insert_post($item);
            
            log_debug( "[PRODUCT IMPORT] new post with ID $postId was created" );

            $this->insert_images( $postId, $product, $is_live );
            
            // Product Details
            update_post_meta( $postId, '_sku', $product->SKU );
            update_post_meta( $postId, '_visibility', 'visible' );
            update_post_meta( $postId, '_stock_status', 'instock');
            update_post_meta( $postId, '_linkgreen_item_id', $itemId );

            // if this is a linked product (ie, my buyer product is actually a supplier product that I resell)
            // then we should see if the source linked product supports drop-ship
            // (I think we should be using drop-ship price from our buyer product but Rob says no, retail price, so no change to that...)
            if ( ! empty( $product->SupplierInformation ) ) {
                $linked_product = $product->SupplierInformation[0];

                update_post_meta( $postId, '_linkgreen_item_dropship', $linked_product->DropShipSell );

                if ( $linked_product->DropShipSell ) {
                    update_post_meta( $postId, '_linkgreen_item_dropship_supplier', $linked_product->Company->Name );
                    update_post_meta( $postId, '_linkgreen_item_dropship_supplier_id', $linked_product->SupplierId );
                }
            }

            // Pricing Details
            $price = $product->NetPrice;

            // todo: do some people not want to put prices on? this should be configurable (vs. letting them hide the price?)
            // to be fair, we could use hooks to not display price at all anywhere in woo, or just not import price here
/* this would be set somewhere else like in admin settings:
            // remove the prices and the add to cart button
            remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
            add_filter( 'woocommerce_is_purchasable', '__return_false');
            add_filter( 'woocommerce_get_price_html', 'react2wp_woocommerce_hide_product_price' );
            function react2wp_woocommerce_hide_product_price( $price ) {
                return '';
            }
            // END remove...
*/            
            if ($product->RetailSalePrice > 0) {
                update_post_meta( $postId, '_sale_price', $product->RetailSalePrice );                
                
                $price = $product->RetailSalePrice;

                if ($product->RetailPrice > 0) {
                    update_post_meta( $postId, '_regular_price', $product->RetailPrice );
                } else {
                    update_post_meta( $postId, '_regular_price', $product->NetPrice );
                }
            } elseif ($product->RetailPrice > 0) {
                $price = $product->RetailPrice;
            }
            
            update_post_meta( $postId, '_price', $price );
            
            //update_post_meta( $postId, '_weight', $product['weight'] );
            //update_post_meta( $postId, '_stock', $product['stock_quantity'] );
            //update_post_meta( $postId, '_manage_stock', 'yes' );

            wp_set_object_terms( $postId, 'simple', 'product_type' );

            // set category(ies)
            if (is_array( $term_id )) {
                foreach($term_id as $term) {
                    wp_set_object_terms( $postId, get_term_by('id', $term, 'product_cat')->term_id, 'product_cat', true );
                }
            } else {
                wp_set_object_terms( $postId, get_term_by('id', $term_id, 'product_cat')->term_id, 'product_cat' );
            }
            
            // TODO: have a flag in the setup which states whether or not to add another attribute called "Our Product Number" and assign it to ItemId (or wouldn't that be SKU?)
            // Product Features (attributes)
            $product_attributes = array();	
            foreach ($product->ProductFeatures as $feature) {
                $attributeName = $feature->FeatureGroup->Name;
                $attributeValue = $feature->Value; 
                
                // TODO: add an option in admin to have a list of features to ignore
                // HACK: (for pinebush) Skip this particular one
                if ($attributeName === "Features & Benefits")
                    continue;

                if (!empty( $attributeName ) && !empty( $attributeValue )) {
                    if (array_key_exists( $attributeName, $product_attributes )) 
                        array_push($product_attributes[$attributeName], $attributeValue);
                    else
                        $product_attributes += [$attributeName => array( $attributeValue )];
                }
            }	

            log_debug( "[PRODUCT IMPORT:ATTRIBUTES] processing attributes for product $itemId" );
            
            if (count( $product_attributes ) > 0)
                $this->add_or_update_attributes( $postId, $product_attributes );

            log_debug( "[PRODUCT IMPORT] $itemId import is complete was successfully inserted with " . count( $product_attributes ) . " attributes" );

            if ($should_call_linkgreen_when_done === true) {

                log_debug ("[PRODUCT IMPORT] will now let LG know about this product $itemId (postID $postId)");

                $task = array(
                    // 'product' => $product, 
                    'is_live' => $is_live,
                    'post_id' => $postId,
                    'linkgreen_item_id' => $itemId,
                );

                // TODO: should we get this from an option setting in the admin pages?
                $this->should_update_sort_order = false;

                // dispatch the queue
                $queue = new Linkgreen_Product_Import_Task_Product_Callback();
                $queue->push_to_queue( $task );
                $queue->save()->dispatch();
            }

        } catch (Exception $e) {
            log_error( '[PRODUCT IMPORT] Caught exception running import task; queue item will be deleted. MSG: ' );
            log_error( $e->getMessage() );
        }

		return false; // either way, kill the queue item (processed or cannot be processed)
	}

        
    function add_or_update_attributes($product_id, $attributes) {
        
        $product_attributes = array();

        foreach( $attributes as $key => $terms ) {
            $taxonomy = wc_attribute_taxonomy_name($key); // The taxonomy slug
            $attr_label = ucfirst($key); // attribute label name
            $attr_name = ( wc_sanitize_taxonomy_name($key)); // attribute slug

            // NEW Attributes: Register and save them
            if( ! taxonomy_exists( $taxonomy ) )
                $this->save_product_attribute_from_name( $attr_name, $attr_label );

            $product_attributes[$taxonomy] = array (
                'name'         => $taxonomy,
                'value'        => '',
                'position'     => '',
                'is_visible'   => 1,
                'is_variation' => 0,
                'is_taxonomy'  => 1
            );

            foreach( $terms as $value ){
                $term_name = ucfirst($value);
                $term_slug = sanitize_title($value);

                // Check if the Term name exist and if not we create it.
                if( ! term_exists( $value, $taxonomy ) )
                    wp_insert_term( $term_name, $taxonomy, array('slug' => $term_slug ) ); // Create the term

                // Set attribute values
                wp_set_post_terms( $product_id, $term_name, $taxonomy, true );
            }
        }

        update_post_meta( $product_id, '_product_attributes', $product_attributes );
    }


	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
        parent::complete();
        if ($this->should_update_sort_order === true)
            $this->set_menu_order_of_new_products();
	}


    private function insert_images( $postId, $product, $is_live ) {

        foreach ($product->Images as $image)
        {
            $url = ( $is_live === true 
                ? $image->Path 
                : str_replace( "://app.", "://dev.", str_replace( "://images1.", "://dev.", $image->Path ) ) );

            $this->insert_image( $postId, $url, boolval( $image->Primary || count( $product->Images) === 1 ) === true );
        }

    }

    private function insert_image( $postId, $img_url, $is_featured ) {
        
        // let's get a biggun        
        $img_url .= "?size=1024";


        // TODO: remove temp hacks
        // temp hack
        $img_url .= "#image.png"; // trick wordpress into thinking she's downloading an actual image file
        // temp hack
        $img_url = str_replace( "/GetImage/", "/GetImageAtSize/", $img_url );
        // END: TODO                

        
        log_debug( "[PRODUCT IMPORT:IMAGES] will now attach this " . ($is_featured ? "PRIMARY" : "SECONDARY") . " image: $img_url" );

        $image_upload_id = media_sideload_image( $img_url, $postId, sanitize_file_name( $product->Description ), 'id' ); // return the attachment ID
        $image_upload_success = true;
        
        if ( ( empty( $image_upload_id ) || is_wp_error( $image_upload_id ) ) && strpos( $img_url, "images1." )) {

            log_debug( "[PRODUCT IMPORT:IMAGES] failed to get the image LG told us about: $img_url - going to try app.linkgreen.ca instead of images1.linkgreen.ca" );

            // try again going directly to server
            $img_url = str_replace( "images1.linkgreen", "app.linkgreen", $img_url );
            $image_upload_id = media_sideload_image( $img_url, $postId, sanitize_file_name( $product->Description ), 'id' ); // return the attachment ID
    
        } 
        
        if ( empty( $image_upload_id ) || is_wp_error( $image_upload_id )) {

            $image_upload_success = false;
            log_error( ( empty( $image_upload_id ) ? "[PRODUCT IMPORT:IMAGES] Side-load failed miserably" : $image_upload_id ) );

        } else {

            /*/ update thumbnails and other sizes as defined by media settings
            // this bit of code causes an image meta data corruption for some unknown raisin, but apparently is how you're supposed to do it *shrug*
            $filepath = get_attached_file( $attachment_id );
            $attach_data = wp_generate_attachment_metadata( $image_upload_id, $filepath );
            $thumb_generate_success = wp_update_attachment_metadata( $image_upload_id,  $attach_data );

            if ( ! $thumb_generate_success )
                log_debug( "[PRODUCT IMPORT:IMAGES] failure updating thumbnails and whatnot" );
            */            

            // only want one image as featured
            if (boolval( $is_featured ) === true) {
    
                log_debug( "[PRODUCT IMPORT:IMAGES] image (id $image_upload_id) attached to post, now setting thumbnail");

                $image_upload_success = (set_post_thumbnail( $postId, $image_upload_id ) !== false);
    
            } else { // add to the gallery
    
                log_debug( "[PRODUCT IMPORT:IMAGES] secondary image (id $image_upload_id) attached to post as a gallery image");

                update_post_meta( $postId, '_product_image_gallery', $image_upload_id );
    
            }
            
        }

        // was featured image associated with post?
        if (! $image_upload_success && boolval( $is_featured ) === true) {

            log_error( "[PRODUCT IMPORT:IMAGES] Product image for ItemID $itemId failed to be set as featured image on post, falling back to FeatureImageByUrl for location $img_url" );
            update_post_meta( $postId, '_knawatfibu_url', $img_url);

        }
    }

    // custom pinebush implementation; set the custom ordering of products so that NEW items are shown before others
    private function set_menu_order_of_new_products() {
        // The query
        $products = new WP_Query( array(
            'post_type'      => array('product'),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'tax_query'      => array( array(
                'taxonomy'        => 'pa_promo',
                'field'           => 'slug',
                'terms'           =>  array('new'),
                'operator'        => 'IN',
            ) )
        ) );
        
        // The Loop
        if ( $products->have_posts() ): while ( $products->have_posts() ):
            $products->the_post();
            $product_ids[] = $products->post->ID;
        endwhile;
            wp_reset_postdata();
        endif;

        global $wpdb;
        $list = join(', ', $product_ids);
        $table = $wpdb->prefix . "posts";
        $result = $wpdb->query(
            "UPDATE $table SET menu_order = -1
            WHERE ID IN ( $list );"
        );

        log_debug( "[PRODUCT IMPORT] updated all NEW products with custom sort order" );
    }

    private function load_dependencies() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/helpers.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/constants.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/task-product-callback.php';

        // i guess in order to use sideload function you have to include these
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
    }
    
    /**
     * Save a new product attribute from his name (slug).
     *
     * @since 1.1.0
     * @param string $name  | The product attribute name (slug).
     * @param string $label | The product attribute label (name).
     */
    private function save_product_attribute_from_name( $name, $label='', $set=true ){
        global $wpdb;

        $label = $label == '' ? ucfirst($name) : $label;
        $attribute_id = $this->get_attribute_id_from_name( $name );

        if( empty($attribute_id) ){
            $attribute_id = NULL;
        } else {
            $set = false;
        }
        $args = array(
            'attribute_id'      => $attribute_id,
            'attribute_name'    => $name,
            'attribute_label'   => $label,
            'attribute_type'    => 'select',
            'attribute_orderby' => 'menu_order',
            'attribute_public'  => 0,
        );

        if( empty($attribute_id) )
            $wpdb->insert( "{$wpdb->prefix}woocommerce_attribute_taxonomies", $args );

        if( $set ){
            log_debug( "[PRODUCT IMPORT:ATTRIBUTES] attribute $label does not exist, inserting + updating cache..." );

            $attributes = wc_get_attribute_taxonomies();
            $args['attribute_id'] = $this->get_attribute_id_from_name( $name );
            $attributes[] = (object) $args;
            //print_r($attributes);
            set_transient( 'wc_attribute_taxonomies', $attributes );
        } else {
            return;
        }
    }

    /**
     * Get the product attribute ID from the name.
     *
     * @since 3.0.0
     * @param string $name | The name (slug).
     */
    private function get_attribute_id_from_name( $name ){
        global $wpdb;
        $attribute_id = $wpdb->get_col("SELECT attribute_id
        FROM {$wpdb->prefix}woocommerce_attribute_taxonomies
        WHERE attribute_name LIKE '$name'");
        return reset($attribute_id);
    }

    /**
     * Create a new variable product (with new attributes if they are).
     * (Needed functions:
     *
     * @since 3.0.0
     * @param array $data | The data to insert in the product.
     */

    private function create_product_variation( $data ){
        if( ! function_exists ('save_product_attribute_from_name') ) return;

        $postname = sanitize_title( $data['title'] );
        $author = empty( $data['author'] ) ? '1' : $data['author'];

        $post_data = array(
            'post_author'   => $author,
            'post_name'     => $postname,
            'post_title'    => $data['title'],
            'post_content'  => $data['content'],
            'post_excerpt'  => $data['excerpt'],
            'post_status'   => 'publish',
            'ping_status'   => 'closed',
            'post_type'     => 'product',
            'guid'          => home_url( '/product/'.$postname.'/' ),
        );

        // Creating the product (post data)
        $product_id = wp_insert_post( $post_data );

        // Get an instance of the WC_Product_Variable object and save it
        $product = new WC_Product_Variable( $product_id );
        $product->save();

        ## ---------------------- Other optional data  ---------------------- ##
        ##     (see WC_Product and WC_Product_Variable setters methods)

        // THE PRICES (No prices yet as we need to create product variations)

        // IMAGES GALLERY
        if( ! empty( $data['gallery_ids'] ) && count( $data['gallery_ids'] ) > 0 )
            $product->set_gallery_image_ids( $data['gallery_ids'] );

        // SKU
        if( ! empty( $data['sku'] ) )
            $product->set_sku( $data['sku'] );

        // STOCK (stock will be managed in variations)
        $product->set_stock_quantity( $data['stock'] ); // Set a minimal stock quantity
        $product->set_manage_stock(true);
        $product->set_stock_status('');

        // Tax class
        if( empty( $data['tax_class'] ) )
            $product->set_tax_class( $data['tax_class'] );

        // WEIGHT
        if( ! empty($data['weight']) )
            $product->set_weight(''); // weight (reseting)
        else
            $product->set_weight($data['weight']);

        $product->validate_props(); // Check validation

        ## ---------------------- VARIATION ATTRIBUTES ---------------------- ##

        $product_attributes = array();

        foreach( $data['attributes'] as $key => $terms ){
            $taxonomy = wc_attribute_taxonomy_name($key); // The taxonomy slug
            $attr_label = ucfirst($key); // attribute label name
            $attr_name = ( wc_sanitize_taxonomy_name($key)); // attribute slug

            // NEW Attributes: Register and save them
            if( ! taxonomy_exists( $taxonomy ) )
                save_product_attribute_from_name( $attr_name, $attr_label );

            $product_attributes[$taxonomy] = array (
                'name'         => $taxonomy,
                'value'        => '',
                'position'     => '',
                'is_visible'   => 0,
                'is_variation' => 1,
                'is_taxonomy'  => 1
            );

            foreach( $terms as $value ){
                $term_name = ucfirst($value);
                $term_slug = sanitize_title($value);

                // Check if the Term name exist and if not we create it.
                if( ! term_exists( $value, $taxonomy ) )
                    wp_insert_term( $term_name, $taxonomy, array('slug' => $term_slug ) ); // Create the term

                // Set attribute values
                wp_set_post_terms( $product_id, $term_name, $taxonomy, true );
            }
        }
        update_post_meta( $product_id, '_product_attributes', $product_attributes );
        $product->save(); // Save the data
    }

}