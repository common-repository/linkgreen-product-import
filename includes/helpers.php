<?php
/**
 * Helper functions to include where needed
 */


/**
 * LOGGING
 * 
 * really only used by our actual import class so, the only place where the ENABLED constant is defined is in the session class constructor
 * session ctor also decides whether or not to turn on logging based on the "dev_mode" setting
 */
require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/logger.php';
define( 'LINKGREEN_PRODUCT_IMPORT_DEBUG_LOGFILE_PATH', plugin_dir_path( dirname( __FILE__ ) ) .  'logs/linkgreen-product-import-debug.log' );


function log_error( $message ) {
    
    LgLogger::setFileName( constant( 'LINKGREEN_PRODUCT_IMPORT_DEBUG_LOGFILE_PATH' ) );
    LgLogger::writeIf( constant( 'LINKGREEN_PRODUCT_IMPORT_DEBUG_LOGGING_ENABLED' ) === true, $message, LgLogger::ERROR);

    if ( WP_DEBUG === true ) { // also log to WP error log if enabled

        $prefix = 'LG: ';

        if ( is_wp_error( $message ) ) {
            $error_string = $message->get_error_message();
            error_log( $prefix . $error_string );
        } else if ( is_array($message) || is_object($message) ) {
            error_log( $prefix . print_r($message, true) );
        } else {
            // sanitize, because... technically error_log could send an email, even though we're not using it thusly..? can some hosts configure their PHP to email errors?
            $sanitized = esc_html( $message );
            error_log( $prefix . $sanitized );
        }
    }

}

// this writes to file, does not require sanitization/validation/escaping
// we escape the contents when viewing in the debug tab of the admin section
function log_debug( $message ) {

    LgLogger::setFileName( constant( 'LINKGREEN_PRODUCT_IMPORT_DEBUG_LOGFILE_PATH' ) );
    LgLogger::writeIf( constant( 'LINKGREEN_PRODUCT_IMPORT_DEBUG_LOGGING_ENABLED' ) === true, $message, LgLogger::DEBUG);

}

function get_logfile() {

    $file = constant( 'LINKGREEN_PRODUCT_IMPORT_DEBUG_LOGFILE_PATH' );
    
    if (file_exists( $file )) {
        return file_get_contents( $file );
    } else {
        error_log( 'couldn\'t read the log file ' . $file);
        return null;
    }

}


/**
 * API RETRIEVAL AND CACHING
 */
define( 'LINKGREEN_PRODUCT_IMPORT_CACHE_PATH', plugin_dir_path( dirname( __FILE__ ) ) .  'cache' );


function getSerializedApiResponse($api_url, $call_is_cachable = false, $body = null, $validate_success_wrapper = true) {

    $file = "";
    $args = array( 'headers' => array(
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'sslverify' => false
        )
    );
    
    log_debug( "Calling " . $api_url );
    
    if ($call_is_cachable) {        
        $filename = murmurhash( $api_url );
        $file = constant( 'LINKGREEN_PRODUCT_IMPORT_CACHE_PATH' ) . '/' . $filename;

        if (is_file( $file ) && is_readable( $file )) {
            log_debug( 'reading json from file cache!' );

            $contents = file_get_contents( $file );

            if (! empty( $contents )) return json_decode( $contents ); // return serialized result, not the text
        }
    }

    if (isset( $body )) {
        $args['body'] = json_encode( $body );
        $response = wp_remote_post ( $api_url, $args );
    }
    else {
        $response = wp_remote_get( $api_url, $args );
    }
    
    log_debug( "..completed call" );
    
    if (is_wp_error( $response ) ) {
        // let's try once more, the api, sometimes she gets cranky
        $response = wp_remote_get( $api_url, $args );
        
        if( is_wp_error( $response ) ) {
            log_error( "Cannot continue: error in API communication" );
        }
    }

    if (is_array( $response ) ) {
        $header = $response['headers']; // array of http header lines
        $result = $response['body']; // use the content

        if (! is_array( $response['response'] ) || $response['response']['code'] > 299 ) {
            log_error( $response['response'] );
            return null;
        }

    } else {
        log_error( $response );
        return null;
    }

    if (isset($result)) {
        $json = json_decode($result);
    } else {
        log_error( "Cannot continue: empty body in response" );
        return null;
    }
    
    if ($validate_success_wrapper) {
        $lowered = array_change_key_case( (array) $json );

        if ( ! isset($lowered) || ! array_key_exists( 'success', $lowered ) ) {
            log_error( "Cannot continue: API response was in an invalid format" );
            return null;
        }

        if ($lowered['success'] != "true") {
            log_error( "Cannot continue: API response indicates a failure" );
            log_error( $lowered );
            return null;
        }
    }

    if ($call_is_cachable) {
        log_debug( 'writing json to file cache! ' );

        $handle = fopen($file, 'w');

        if ($handle === false) {
            log_error( "couldn't write cache file for API call: $file" );
        }

        fwrite($handle, $result);
        fclose($handle);
    }

    return $json;
}


/**
 * WP TERM <==> LG CAT MAPPING
 */

function get_term_id_from_cat_id( $cat_id ) {
    $_cat_transient = 'lgpi-category-mapping';

    if ( false === ( $existing_cats = get_transient( $_cat_transient ) ) ) {
        log_error( 'tried to get term when category mapping was not set' );
        return null;
    }

    if (! isset( $existing_cats ) || ! is_array( $existing_cats ) || count( $existing_cats ) < 1) 
        return null; // maybe there are no categories (?)
    
    return $existing_cats[ $cat_id ];
}

function set_cat_id_with_term_id( $cat_id, $term_id ) {
    $_cat_transient = 'lgpi-category-mapping';

    if ( false === ( $existing_cats = get_transient( $_cat_transient ) ) ) {
        log_error( var_dump( $existing_cats ) );
        $existing_cats = array();
    }

    $existing_cats[ $cat_id ] = $term_id;
    
    set_transient( $_cat_transient, $existing_cats, 60 * MINUTE_IN_SECONDS );
}

/**
 * WP QUERYING
 */
function lg_list_product_attachments() {  
    $query = new WP_Query(  
      array(  
        'post_type' => 'product', // adjust your custom post type name here  
        'posts_per_page' => -1,  
        'fields' => 'ids'  
      )  
    );  

    $image_query = new WP_Query(  
      array(  
        'post_type' => 'attachment',  
        'post_status' => 'inherit',  
        'post_mime_type' => 'image',  
        'posts_per_page' => -1,  
        'post_parent__in' => $query->posts,  
        'order' => 'DESC'  
      )  
    );  

    $output = '';

    if( $image_query->have_posts() ){  
      while( $image_query->have_posts() ) {  
          $image_query->the_post();  
          $imgurl = wp_get_attachment_url( get_the_ID() );  
          $attachment_path = get_attached_file( get_the_ID() ); 
          
          // wp_get_attachment_url already sanitizes; just output
          $output .= '<a href="'.$imgurl.'" alt="'.$attachment_path.'"><img src="'. $imgurl.'"></a>';  
      }  
  
    }  
    if(empty($output)) $output = 'Sorry, no attachments found.';  
    echo $output;  
}  
 

/**
 * UTILITIY
 */

// a quick hash method from http://innvo.com/php-murmurhash-extensionless/
if ( ! function_exists( 'murmurhash' ) ) {
	function murmurhash($key, $seed = 0) {
		$m = 0x5bd1e995;
		$r = 24;
		$len = strlen($key);
		$h = $seed ^ $len;
		$o = 0;
		
		while($len >= 4) {
			$k = ord($key[$o]) | (ord($key[$o+1]) << 8) | (ord($key[$o+2]) << 16) | (ord($key[$o+3]) << 24);
			$k = ($k * $m) & 4294967295;
			$k = ($k ^ ($k >> $r)) & 4294967295;
			$k = ($k * $m) & 4294967295;
 
			$h = ($h * $m) & 4294967295;
			$h = ($h ^ $k) & 4294967295;
 
			$o += 4;
			$len -= 4;
		}
 
		$data = substr($key,0 - $len,$len);
	
		switch($len) {
			case 3: $h = ($h ^ (ord($data[2]) << 16)) & 4294967295;
			case 2: $h = ($h ^ (ord($data[1]) << 8)) & 4294967295;
			case 1: $h = ($h ^ (ord($data[0]))) & 4294967295;
			$h = ($h * $m) & 4294967295;
		};
		$h = ($h ^ ($h >> 13)) & 4294967295;
		$h = ($h * $m) & 4294967295;
		$h = ($h ^ ($h >> 15)) & 4294967295;
	
	 return $h;
	}
}



// oh.. this..?  uhh... I think I was going to do singleton pattern to cache the categories but ended up using transient variables which is easier 
/*/
// General singleton class.
class Categories_Cache {

    // Hold the class instance.
    private static $instance = null;    
    private $_cat_transient = 'lgpi-category-mapping';

    // The constructor is private
    // to prevent initiation with outer code.
    private function __construct()
    {
      $this->categories = get_transient( $_cat_transient );
    }
   
    // The object is created from within the class itself
    // only if the class has no instance.
    public static function getInstance()
    {
      if (self::$instance == null)
      {
        self::$instance = new Categories_Cache();
      }
   
      return self::$instance;
    }
  }
*/