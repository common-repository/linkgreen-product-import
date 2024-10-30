<?php

/**
 * Define the store locations
 *
 * Load store location data from LinkGreen API and stick it into WordPress via [shortcode]
 * so that it can be used by Google Maps API
 *
 * @link       https://www.linkgreen.ca/
 * @since      1.0.1
 *
 * @package    Linkgreen_Product_Import
 * @subpackage Linkgreen_Product_Import/includes
 */

 class Linkgreen_Product_Import_Store_Locator {
    
    protected $is_debug_mode = false;
	protected $is_live_api = true;
	protected $api_key = null;

    protected $months_included = 12;
    protected $product_item_id = null;
    protected $api_get_all = "/relationshipservice/rest/publicretailers/"; 
    protected $api_by_item_id  = "/OrderService/rest/GetInventoryOrderCompanies/id/";

	public function __construct($item_id) {
        if (isset( $item_id ) && ! empty( $item_id ))
            $this->product_item_id = $item_id;
        
        $this->init();
	}


	private function init() {
        $this->load_dependencies();   

        $setup = get_option('linkgreen_product_import_setup_options');
		$debug = get_option('linkgreen_product_import_debug_options');
		
		if (isset( $debug['dev_mode'] ))
			$this->is_debug_mode = boolval( $debug['dev_mode'] );
		
		if (isset( $debug['dev_api'] ))
			$this->is_live_api = ! boolval( $debug['dev_api'] );

		if (isset( $setup['api_token'] ))
			$this->api_key = $setup['api_token'];

		// if ($this->api_key === null || empty( $this->api_key) )
		// 	wp_die("no API key specified");

    }


    private function load_dependencies() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/helpers.php';
    }


    /**
     * Do the actual rendering, return the content output
     */
    public function run() {
        $item_id = $this->product_item_id;
        $months = $this->months_included;
        $stores = array();

        if ($item_id === null) {
            $api_url = $this->_api_base() . $this->api_get_all . $this->api_key;
        } else {
            $api_url = $this->_api_base() . $this->api_by_item_id . $this->api_key . "/$item_id/$months";
        }
        
        $companies = getSerializedApiResponse($api_url, true);

        if ($companies === null) {
            log_debug( "bad result from API call, returning no results to view" );
            return '[]';
        }

        foreach ($companies->Result as $company) {
            if (empty( $company->Latitide ) || empty( $company->Longitude ))
                continue;
            
            $stores[] = (object) array( 
                "name" => $company->Name, 
                "place_id" => $company->GeocachingLocationId,
                "formatted_address" => $company->GeocachingFormattedAddress,
                "formatted_phone_number" => $company->FormattedPhone1,
                "website" => $company->Web,
                "latitude" => $company->Latitide,
                "longitude" => $company->Longitude
            );
        }

        // sort by the first array value (the company name)
        usort( $stores, function($a, $b) {
            return strcasecmp($a->name, $b->name);
        });

        return json_encode( $stores );
    }

    // so, this method was from session class, i feel like both that and this class could have a parent class that includes this and some of the other
    // plumbing but this tech debt will exist until we can get around to that
    private function _api_base() {
		return ($this->is_live_api ? 'https://app.linkgreen.ca' : 'http://dev.linkgreen.ca');	
    } 
    
}
