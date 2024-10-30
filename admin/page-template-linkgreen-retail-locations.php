<?php /* Template Name: Retail Locations Template */ ?>

<?php
/**
 * Follow these instructions to use the plugin to create the template in the admin:
 * 	https://www.wpexplorer.com/wordpress-page-templates-plugin/
 */

get_header();


// if page was called with query string param ?product_sku={sku} then do the one call, otherwise use the getRelationships call (and cache it)
if (isset( $_GET["product_sku"] )) {	
	
	if (!empty( $_GET["product_sku"] )) {

        /**** TODO: NOTE: FUTURE CHANGE ****/
        // we are not engaging the specific product retailer search until a later time when there's more data
        // so, when that time comes, uncomment out this line
		//$sku = esc_url_raw( $_GET["product_sku"] );

	}
}

$locations = null;
$no_results = false;
$noresultsmsg = "No results found. Please try again later.";

// fallback to all results if none found for sku
if (isset( $sku )) {
    $locations = do_shortcode("[lgpi-render-map-locations sku='$sku']");

    if ($locations === null || count( json_decode( $locations ) ) < 1) {
        $noresultsmsg = "No results found for product $sku -- showing all retail locations.";
        $locations = do_shortcode("[lgpi-render-map-locations]");
    }

} else {
    $locations = do_shortcode("[lgpi-render-map-locations]");
}

if ($locations === null || count( json_decode( $locations ) ) < 1) {
    $no_results = true;
    $locations = '[]';
}

echo "<script>window.lgpi_locations = $locations;</script>";
?>



<div id="main-content">

	<div class="entry-content">
        <?php the_content(); ?>
	</div><!-- .entry-content -->

</div> <!-- #main-content -->



<?php

if ( $no_results === true ) { error_log('no results for retail locations!'); ?>
<script>
    jQuery(document).ready(function($) {
        $( '#results-notification-container' )
            .text( '<?php echo $noresultsmsg; ?>' )
            .show( "slow" );
    });
</script>
<?php }


get_footer();



/**
 * Originally the intent of the page template was to allow us to customize the map page but I was able to do so using strictly divi
 * Still, the following code represents the parts necessary and could be injected via [short_codes] or brute force str_replace() allowing us to 
 * use the divi builder but not rely on user to muck up the markup
 
<div id="lgpi-postal-container">
	<input id="postal-code" type="text" value="" placeholder="A2A 3B3">
	<input id="postal-code-submit" type="button" value="Use my postal code">
</div>

<div id="linkgreen-retailers-map">
	<div id="map" style="height:100%; width:100%" data-mouse-wheel="on" data-mobile-dragging="on"></div>
</div>

<div id="list-container">
	<ul id="results-list"></ul>
</div>
 */

?>
