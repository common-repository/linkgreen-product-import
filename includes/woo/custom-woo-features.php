<?php

/**
 * WooCommerce Customizations Class
 *
 * just a place to keep all the customization functions that will run through hooks
 * and on-demand
 *
 * @since      1.0.8
 * @package    Linkgreen_Product_Import
 * @subpackage Linkgreen_Product_Import/includes/woo
 * @author     LinkGreen <admin@linkgreen.ca>
 */
class Linkgreen_Custom_Woo_Features {

    public function __construct() {
        $this->load_dependencies();
	}

    private function load_dependencies() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) .  '../includes/helpers.php';        
    }


    /*** MODIFICATION THINGS ***/

    /**
     * Update line-items with any necessary meta data
     * 
     * Add the LG item id and mark it drop-ship if it is to be so
     * We have to copy over any of our product meta data into the line item b/c it's probable that
     * the product will be deleted in the future when we reference the order from the orders page
     */
    function checkout_create_order_line_item__update_metadata( &$item, $cart_item_key, $values, $order ) {

        $product = $item->get_product();
        $lg_id = $product->get_meta( '_linkgreen_item_id', true );
        $is_drop_ship = $product->get_meta( '_linkgreen_item_dropship', true );
        $lg_supplier_name = $product->get_meta( '_linkgreen_item_dropship_supplier' );

        $item->add_meta_data( '_linkgreen_item_dropship', $is_drop_ship, true );
        $item->add_meta_data( '_linkgreen_item_id', $lg_id, true );
        $item->add_meta_data( '_linkgreen_item_dropship_supplier', $lg_supplier_name, true );

        if ( $is_drop_ship ) {
            log_debug( "drop-ship item detected in cart: $lg_id" );
        }
    }


    /**
     * Add a shipping note if the order is for drop-ship
     */
    // OBSOLETE
    function checkout_create_order_shipping_item__add_dropship_note( &$item, $package_key, $package, $order ) {

        $fulfillment = $this->get_order_fulfillment( $order );

        // we will never know by this point whether it's single origin b/c that won't be known until order is complete!
        // also, in testing, setting meta_data here doesn't show it anywhere (this hook may be too late)

        if ( $fulfillment['partially'] || $fulfillment['fully'] ) {
          $item->add_meta_data( 'Delivery Notice', 'This order contains items that may be shipped seperately', true );
          log_debug("adding delivery notice");
        }
    }


    /**
     * Mark complete if a fully-fulfilled order
     * 
     * In WooCommercer's implementation they've only given us the thankyou hook which can be fired agin if user reloads the page
     * or worse, saves the link and comes back.
     * 
     * So, only if this is a new order will we update to 'complete' when fully fulfilled by LG
     */
    function thankyou__mark_full_dropship_as_complete( $order_id ) {

        $order = new WC_Order( $order_id );
        $order_status = $order->get_status();
        $is_fresh = strtotime( $order->get_date_created() ) > strtotime( "-2 minutes" );

        if ( ! $is_fresh || "completed" === $order_status ) return; // might be fresh, but if it's already marked complete then let's boogy

        $fulfillment = $this->get_order_fulfillment( $order );

        if ( $fulfillment['fully'] || $fulfillment['partially'] ) {
            $order->update_status( 'lg-fulfilled' );
            log_debug( "Order status for fully fulfilled order (ID $order_id) set to completed" );
        }
    }

    /**
     * Note on the receipt (thankyou) page that the items could be drop-shipped 
     */
    function thankyou__display_dropship_receipt( $order_id ) {

        $order = wc_get_order( $order_id ); // i wonder what's faster, new WC_Order or wc_get_order ?? TODO: test
        //$order = new WC_Order( $order_id ); 

        $count = 1;
    
        $fulfillment = $this->get_order_fulfillment( $order );

        if ( $fulfillment['partially'] || ( $fulfillment['fully'] && ! $fulfillment['single-origin'] ) ) {
            echo '<h4 style="padding-bottom:10px"><strong>Note:</strong> The items in this order may be shipped separately</h4>';
        }
    }

    /**
     * Create LinkGreen orders based on any drop-ship items
     * 
     * If we're submitting an order with drop-ship items on it, send the details to LG API to create orders 
     *
     * Update our order metadata with the details of the LG orders
     */
    function checkout_create_order__update_order_metadata( $order, $customer_checkout_data ) {

        // store that the order was a dropship
        $fulfillment = $this->get_order_fulfillment( $order );
        $ff_value = ($fulfillment['fully'] ? 'fully' : ($fulfillment['partially'] ? 'partially' : 'none'));
        $order->update_meta_data( '_linkgreen_fulfillment', $ff_value );
        log_debug( "Order fulfillment marked as $ff_value" );
    }


    /*** DISPLAY THINGS ***/

    function restrict_manage_posts__filter_orders_by_fulfillment() {
        global $typenow;

        $options = [ 'fully' => 'Fully Fulfilled by LinkGreen', 'partially' => 'Partially Fulfilled by LinkGreen' ];
        $chosen = isset( $_GET['_shop_order_fulfillment'] ) ? wc_clean( $_GET['_shop_order_fulfillment'] ) : '';

		if ( 'shop_order' === $typenow ) {
			?>
			<select name="_shop_order_fulfillment" id="dropdown_shop_order_fulfillment">
				<option value="">
					<?php esc_html_e( 'Any LinkGreen Fulfillment', 'wc-filter-orders-by-fulfillment' ); ?>
				</option>

				<?php foreach ( $options as $option => $label ) : /* was gonna use IDs but the word is just fine */ ?>
				<option value="<?php echo esc_attr( $option ); ?>" <?php echo esc_attr( selected( $option, $chosen, false ) ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
				<?php endforeach; ?>
            </select>
            
            <div class="lg-tooltip">Fulfillment?
                <span class="lg-tooltiptext">
                    Show only orders that are fully or partially fulfilled by LinkGreen; as in, all or some are drop-shipped
                </span>
            </div>
			<?php
		}
    }
	public function request__filter_orders_by_fulfillment( $vars ) {
        global $typenow;
        
		if ( 'shop_order' === $typenow && isset( $_GET['_shop_order_fulfillment'] ) ) {
			$vars['meta_key']   = '_linkgreen_fulfillment'; 
			$vars['meta_value'] = wc_clean( $_GET['_shop_order_fulfillment'] );
        }
        
		return $vars;
	}

    /**
     * Display drop-shippiness of the order on the order edit page
     * 
     * Show "Fully Fulfilled by LinkGreen" or "Partially Fulfilled by LinkGreen"
     */
    function admin_order_data_after_billing_address__display_dropship_admin( $order ){

        $fulfillment = $this->get_order_fulfillment( $order );

        if ( $fulfillment['fully'] )
            echo '<p id="lg-admin-orderfulfillment"><strong>'.__('Drop-ship Items').':</strong> <br/>' . __('This order is fully fulfilled by LinkGreen').'</p>';
        elseif ( $fulfillment['partially'] )
            echo '<p id="lg-admin-orderfulfillment"><strong>'.__('Drop-ship Items').':</strong> <br/>' . __('This order is partially fulfilled by LinkGreen').'</p>';
    }

    /**
     * Display the LG order details under any drop-shop items in the cart
     */
    function after_order_itemmeta__display_dropship_details_admin( $item_id, $item, $product ) {
        
        if ( ! is_admin() || ! $item->is_type('line_item') ) return;

        // TODO: the supplier name and whether it's a drop-ship item should be copied to $product->item on add to cart
        // b/c later, when the prod is updated the link is deleted
        //if ( $product === false ) return;

        $is_drop_ship = $item->get_meta( '_linkgreen_item_dropship', true );

        if (! $is_drop_ship ) return;

        $lg_supplier_name = $item->get_meta( '_linkgreen_item_dropship_supplier' );
        $lg_order_id = $item->get_meta( '_linkgreen_item_dropship_order_id' );
        $lg_order_url = 'https://app.linkgreen.ca/order/supplieredit/'.$lg_order_id; // TODO: get whether in dev/app

        echo '<div class="wc-order-item-sku">';
        echo __('This item fulfilled by').' <strong>'.$lg_supplier_name. '</strong> ';
        if ( $lg_order_id ) { 
            echo __('via LinkGreen order number'). ' <a href="'.$lg_order_url.'">'.$lg_order_id.'</a>';
        }
        echo '</div>';
    }

    /**
     * Hide the meta data for linkgreen orders because we've a custom line of details to show instead
     */
    function hidden_order_itemmeta__hide_metadata_from_orders_detail( $arr ) {
        array_push( $arr, 
            '_linkgreen_item_dropship_order_id', 
            '_linkgreen_item_dropship', 
            '_linkgreen_item_id',
            '_linkgreen_item_dropship_supplier' );
        return $arr;
    }

    /**
     * Display drop-ship messaging on line item details for the email
     */
    function get_item_data__dispaly_dropship_email( $item_data, $cart_item_data ) {

        $product = new WC_Product( $cart_item_data['data'] );
        $is_drop_ship = $product->get_meta( '_linkgreen_item_dropship', true );
        
        if ( $is_drop_ship ) {
            $message = 'item may ship separately'; // TODO: load messaging from admin

            $item_data[] = array(
                'key' => __( 'Drop Ship', 'linkgreen-product-import' ),
                'value' => wc_clean( $message ) 
            );            
        }

        return $item_data;
    }


    function init__custom_order_status() {
        register_post_status( 'wc-lg-fulfilled', array(
            'label'                     => 'Fulfilled by LinkGreen',
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Fulfilled by LinkGreen <span class="count">(%s)</span>', 'Fulfilled by LinkGreen <span class="count">(%s)</span>' )
        ) );
    }    
    function linkgreen_custom_order_status( $order_statuses ) {
        $order_statuses['wc-lg-fulfilled'] = _x( 'FulFilled by LinkGreen', 'Order status', 'woocommerce' ); 
        return $order_statuses;
    }


    /*** PRIVATE THINGIES ***/

    private function get_order_fulfillment( $order ) {
        $fully = true;
        $partially = false;
        $single = true;
        $supplier = '';

        foreach( $order->get_items() as $item_id => $item ) {

            // $product = $item->get_product();
            
            // // TODO: the supplier name and whether it's a drop-ship item should be copied to $product->item on add to cart
            // // b/c later, when the prod is updated the link is deleted
            // if ( $product === false ) continue;

            $is_drop_ship = $item->get_meta( '_linkgreen_item_dropship', true );
            $item_supplier = strtolower( $item->get_meta( '_linkgreen_item_dropship_supplier', true ) ); // this meta should be stored on the order item after call to LG for new order

            if ( $supplier !== '' && $item_supplier !== $supplier ) {
                $single = false;
            } 

            $supplier = $item_supplier;

            if ($is_drop_ship) {
                $partially = true;
            } else {
                $fully = false;
            }

        }
        
        if ( $fully ) $partially = false;

        return ['fully' => $fully, 'partially' => $partially, 'single-origin' => $single];
    }


    /*** TESTING THINGS ***/

    function add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
        // log_debug( array( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) );
    }

}