<?php

/**
 * The settings of the plugin.
 *
 * @link       http://devinvinson.com
 * @since      1.0.0
 *
 * @package    linkgreen_product_import_Plugin
 * @subpackage linkgreen_product_import_Plugin/admin
 */

/**
 * Class WordPress_Plugin_Template_Settings
 *
 */
class Linkgreen_Product_Import_Admin_Settings {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	private $_redirect_url;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->_redirect_url = urlencode( remove_query_arg( 'msg', $_SERVER['REQUEST_URI'] ) );

		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/constants.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) .  'includes/helpers.php';
	}

	public function setup_plugin_options_menu() {

		//Add the menu to the Plugins set of menu items
		$page_id = add_management_page(
			'LinkGreen Product Import Admin', 					// The title to be displayed in the browser window for this page.
			'LinkGreen Product Import Admin',					// The text to be displayed for this menu item
			'manage_options',									// Which type of users can see this menu item
			'linkgreen_product_import_options',					// The unique ID - that is, the slug - for this menu item
			array( $this, 'render_settings_page_content')		// The name of the function to call when rendering this menu's page
		);

        add_action( "load-$page_id", array ( $this, 'parse_message' ) );
    }

    public function parse_message()
    {
        if ( ! isset ( $_GET['msg'] ) )
            return;

        if ( 'log-deleted-success' === $_GET['msg'] )
			$this->msg_text = 'Log file deleted!';
			
		if ( 'manual-import-success' === $_GET['msg'] )
			$this->msg_text = 'Successfully queued a manual import!';
			
		if ( 'manual-import-failure' === $_GET['msg'] )
			$this->msg_text = 'Failed to queue a manual import!';

		if ( 'cache-deleted-success' === $_GET['msg'] )
			$this->msg_text = 'Cleared the local API cache!';

        if ( 'products-deleted-success' === $_GET['msg'] )
			$this->msg_text = 'All products were deleted!';
            

		if ( $this->msg_text )
            add_action( 'admin_notices', array ( $this, 'render_msg' ) );
    }

	public function render_msg()
    {
		$msg = $_GET['msg'];
		$class = "notice-info";

		if (strpos( $msg, "success") !== false )
			$class = "notice-success is-dismissible";
		elseif ( strpos( $msg, "fail") !== false )
			$class = "notice-error";

        echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . $this->msg_text . '</p></div>';
    }

	/**
	 * Provides default values for the Display Options.
	 *
	 * @return array
	 */
	public function default_setup_options() {

		$defaults = array(
			'lg_api_token'		=>	'',
			'google_maps_token' =>  '',
			'import_schedule'	=>	'daily',
			// 'dev_api'		=>	'',
		);

		return $defaults;

	}

	/**
	 * Provide default values for the Social Options.
	 *
	 * @return array
	 */
	public function default_debug_options() {

		$defaults = array(
			'plugin_dev_mode'		=>	'0',
			'plugin_dev_api'		=>  '0',
			// 'facebook'		=>	'',
			// 'googleplus'	=>	'',
		);

		return  $defaults;

	}

	/**
	 * Provides default values for the Input Options.
	 *
	 * @return array
	 */
	public function default_input_options() {

		$defaults = array(
			'input_example'		=>	'default input example',
			'textarea_example'	=>	'',
			'checkbox_example'	=>	'',
			'radio_example'		=>	'2',
			'time_options'		=>	'default'
		);

		return $defaults;

	}
	
	public function is_dev_mode_on() {
		$options = get_option('linkgreen_product_import_debug_options');
		if (isset( $options['dev_mode'] ))
			return true;
		else
			return false;
	}
	

	/**
	 * Renders a simple page to display for the theme menu defined above.
	 */
	public function render_settings_page_content( $active_tab = '' ) {

		// even though we only validate this value and never output $active_tab, let's go ahead and sanitize
		if( isset( $_GET[ 'tab' ] ) ) {
			$active_tab = esc_html( $_GET[ 'tab' ] );
		}

		if ( '' == $active_tab || 
				 ( $active_tab !== 'setup_options' && $active_tab !== 'manual_import' && $active_tab !== 'debug_options' ) ) {
			$active_tab = 'setup_options';
		}
		?>

		<!-- Create a header in the default WordPress 'wrap' container -->
		<div class="wrap" fun="<?=$active_tab?>">

			<h2><?php _e( 'LinkGreen Product Import - Options', 'linkgreen-product-import-plugin' ); ?></h2>
			<?php settings_errors(); ?>

			<h2 class="nav-tab-wrapper">
				<a href="?page=linkgreen_product_import_options&tab=setup_options" class="nav-tab <?php echo $active_tab == 'setup_options' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Setup Options', 'linkgreen-product-import-plugin' ); ?></a>
				<a href="?page=linkgreen_product_import_options&tab=manual_import" class="nav-tab <?php echo $active_tab == 'manual_import' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Manual Import', 'linkgreen-product-import-plugin' ); ?></a>
				<a href="?page=linkgreen_product_import_options&tab=debug_options" class="nav-tab <?php echo $active_tab == 'debug_options' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Debug', 'linkgreen-product-import-plugin' ); ?></a>
			</h2>
			
			<?php

			// i don't think u have to do this manually... TODO: confirm the nonce hidden field is in the form
			//wp_nonce_field( 'options-options' ); //Linkgreen_Constants::SETTINGS_NONCE );

			if( $active_tab == 'setup_options' ) {

				$this->options_form_render();
				settings_fields( 'linkgreen_product_import_setup_options' );
				do_settings_sections( 'linkgreen_product_import_setup_options' );
				$this->options_form_close_render();

			} elseif( $active_tab == 'debug_options' ) { 
				
				$this->options_form_render(); ?>

				<div id="debug-container">
					<div id="debug-options"><?php

				settings_fields( 'linkgreen_product_import_debug_options' );
				do_settings_sections( 'linkgreen_product_import_debug_options' );
				$logfile = get_logfile(); ?>

					</div>
					<div id="debug-log">
						<pre><?php echo esc_html( $logfile ); ?></pre>
					</div>
					
					<div>
						<button type="button" onclick="window.location = '<?php 
							echo esc_url( admin_url( 'admin-post.php?action=delete_log&_wp_http_referer=' . $this->_redirect_url ) ); 
						?>';">Clear Log</button>

						<button type="button" onclick="window.location = '<?php 
							echo esc_url( admin_url( 'admin-post.php?action=delete_cache&_wp_http_referer=' . $this->_redirect_url ) ); 
						?>';">Clear API Cache</button>

                        <button type="button" onclick="if (confirm('but do you REALLY want to do this?')) window.location = '<?php 
							echo esc_url( admin_url( 'admin-post.php?action=delete_products&_wp_http_referer=' . $this->_redirect_url ) ); 
						?>';">Delete all products</button>

						<button type="button" onclick="window.location = '<?php 
							echo esc_url( admin_url( 'admin-post.php?action=show_attachments&_wp_http_referer=' . $this->_redirect_url ) ); 
						?>';">Show all product image data</button>
					</div> <?php // TODO: should add a nonce to any delete actions
				
				$this->options_form_close_render();

			} elseif ( $active_tab == 'manual_import') {

				$this->manual_import_render();

			} else {

				$this->options_form_render();
				settings_fields( 'linkgreen_product_import_input_examples' );
				do_settings_sections( 'linkgreen_product_import_input_examples' );
				$this->options_form_close_render();

			} // end if/else

			?>

		</div><!-- /.wrap -->
	<?php
	}

	private function options_form_render() {
		?><form method="post" action="options.php"><?php
	}
	private function options_form_close_render() {
		submit_button();
		?></form><?php
	}

	private function manual_import_render() { ?>

		<div>
			<p>Run a manual import by pressing the button below. This will run a background task to import all your products and categories from LinkGreen.</p>
			To see the progress, you can visit the DEBUG tab and view the log.
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			
			<input type="hidden" name="action" value="<?php echo Linkgreen_Constants::RUN_IMPORT_HOOK_NAME; ?>" />
			<input type="hidden" name="_wp_http_referer" value="<?php echo $this->_redirect_url; ?>"> <?php
						
			wp_nonce_field( Linkgreen_Constants::RUN_IMPORT_NONCE, Linkgreen_Constants::RUN_IMPORT_NONCE_FIELD );
			submit_button( 'Run manual import' ); ?>

		</form>
		<?php
	}

	/**
	 * This function provides a simple description for the General Options page.
	 *
	 * It's called from the 'wppb-demo_initialize_theme_options' function by being passed as a parameter
	 * in the add_settings_section function.
	 */
	public function general_options_callback() {
		$options = get_option('linkgreen_product_import_setup_options');
		echo '<p>' . __( 'Note that the schedule run time for the import will be at 12:00 on the recurring schedule you choose.', 'linkgreen-product-import-plugin' ) . '</p>';
	} // end general_options_callback

	/**
	 * This function provides a simple description for the Social Options page.
	 *
	 * It's called from the 'wppb-demo_theme_initialize_social_options' function by being passed as a parameter
	 * in the add_settings_section function.
	 */
	public function debug_options_callback() {
		$options = get_option('linkgreen_product_import_debug_options');
		echo '<p>' . __( 'Debugging options to assist developers with troubleshooting', 'linkgreen-product-import-plugin' ) . '</p>';
	} // end general_options_callback

	/**
	 * This function provides a simple description for the Input Examples page.
	 *
	 * It's called from the 'wppb-demo_theme_initialize_input_examples_options' function by being passed as a parameter
	 * in the add_settings_section function.
	 */
	public function input_examples_callback() {
		$options = get_option('linkgreen_product_import_input_examples');
		var_dump($options);
		echo '<p>' . __( 'Provides examples of the five basic element types.', 'linkgreen-product-import-plugin' ) . '</p>';
	} // end general_options_callback


	/**
	 * Initializes the theme's display options page by registering the Sections,
	 * Fields, and Settings.
	 *
	 * This function is registered with the 'admin_init' hook.
	 */
	public function initialize_setup_options() {

		// If the theme options don't exist, create them.
		if( false == get_option( 'linkgreen_product_import_setup_options' ) ) {
			$default_array = $this->default_setup_options();
			add_option( 'linkgreen_product_import_setup_options', $default_array );
		}


		add_settings_section(
			'general_settings_section',			            				// ID used to identify this section and with which to register options
			__( 'Setup Options', 'linkgreen-product-import-plugin' ),       // Title to be displayed on the administration page
			array( $this, 'general_options_callback'),	    				// Callback used to render the description of the section
			'linkgreen_product_import_setup_options'		                // Page on which to add this section of options
		);

		add_settings_field(
			'lg_api_token',						        					// ID used to identify the field throughout the theme
			__( 'API Token', 'linkgreen-product-import-plugin' ),			// The label to the left of the option interface element
			array( $this, 'update_apitoken_callback'),						// The name of the function responsible for rendering the option interface
			'linkgreen_product_import_setup_options',	            		// The page on which this option will be displayed
			'general_settings_section',			        					// The name of the section to which this field belongs
			array(								        					// The array of arguments to pass to the callback. In this case, just a description.
				__( 'Paste your API TOKEN from the profile page of your LinkGreen account.' . 
					'<p>To retrieve this, log in with your supplier login, click your name in the top-right corner and change the tab to API Key</p>', 'linkgreen-product-import-plugin' ),
			)
		);

		add_settings_field(
			'google_maps_token',
			__( 'Google Maps API Token', 'linkgreen-product-import-plugin' ),			
			array( $this, 'update_googlemapstoken_callback'),						
			'linkgreen_product_import_setup_options',	            		
			'general_settings_section',			        					
			array(								        					
				__( 'Paste your Google Maps API token.' . 
					'<p>To retrieve this, visit the following website and sign up: <a target="_blank" href="' . 
					'https://developers.google.com/maps/documentation/javascript/get-api-key">Google Get-API-Key</a></p>', 'linkgreen-product-import-plugin' ),
			)
		);

		add_settings_field(
			'import_schedule',
			__( 'Schedule Product Import', 'linkgreen-product-import-plugin' ),
			array( $this, 'import_schedule_callback'),
			'linkgreen_product_import_setup_options',
			'general_settings_section'
		);


		// Finally, we register the fields with WordPress
		register_setting(
			'linkgreen_product_import_setup_options',
			'linkgreen_product_import_setup_options',
			array( $this, 'sanitize_options_values')
		);

	} // end wppb-demo_initialize_theme_options


	/**
	 * This function is registered with the 'admin_init' hook.
	 */
	public function initialize_debug_options() {
		
		if( false == get_option( 'linkgreen_product_import_debug_options' ) ) {
			$default_array = $this->default_debug_options();
			add_option( 'linkgreen_product_import_debug_options', $default_array );
		} // end if

		add_settings_section(
			'debug_settings_section',										// ID used to identify this section and with which to register options
			__( 'Debug Options', 'linkgreen-product-import-plugin' ),		// Title to be displayed on the administration page
			array( $this, 'debug_options_callback'),						// Callback used to render the description of the section
			'linkgreen_product_import_debug_options'						// Page on which to add this section of options
		);

		add_settings_field(
			'plugin_dev_mode',
			__( 'Development Mode', 'linkgreen-product-import-plugin' ),
			array( $this, 'toggle_devmode_callback'),
			'linkgreen_product_import_debug_options',
			'debug_settings_section',
			array(
				__( 'Turn on developer/debugging?', 'linkgreen-product-import-plugin' ),
			)
		);

		add_settings_field(
			'plugin_dev_api',
			__( 'Use DEV API', 'linkgreen-product-import-plugin' ),
			array( $this, 'toggle_devapi_callback'),
			'linkgreen_product_import_debug_options',
			'debug_settings_section',
			array(
				__( 'Use the DEV API instead of the LIVE one?', 'linkgreen-product-import-plugin' ),
			)
		);

		register_setting(
			'linkgreen_product_import_debug_options',
			'linkgreen_product_import_debug_options');
	}


	/**
	 * Initializes the theme's input example by registering the Sections,
	 * Fields, and Settings. This particular group of options is used to demonstration
	 * validation and sanitization.
	 *
	 * This function is registered with the 'admin_init' hook.
	 */
	public function initialize_input_examples() {
		//delete_option('linkgreen_product_import_input_examples');
		if( false == get_option( 'linkgreen_product_import_input_examples' ) ) {
			$default_array = $this->default_input_options();
			update_option( 'linkgreen_product_import_input_examples', $default_array );
		} // end if

		add_settings_section(
			'input_examples_section',
			__( 'Input Examples', 'linkgreen-product-import-plugin' ),
			array( $this, 'input_examples_callback'),
			'linkgreen_product_import_input_examples'
		);

		add_settings_field(
			'Input Element',
			__( 'Input Element', 'linkgreen-product-import-plugin' ),
			array( $this, 'input_element_callback'),
			'linkgreen_product_import_input_examples',
			'input_examples_section'
		);

		add_settings_field(
			'Textarea Element',
			__( 'Textarea Element', 'linkgreen-product-import-plugin' ),
			array( $this, 'textarea_element_callback'),
			'linkgreen_product_import_input_examples',
			'input_examples_section'
		);

		add_settings_field(
			'Checkbox Element',
			__( 'Checkbox Element', 'linkgreen-product-import-plugin' ),
			array( $this, 'checkbox_element_callback'),
			'linkgreen_product_import_input_examples',
			'input_examples_section'
		);

		add_settings_field(
			'Radio Button Elements',
			__( 'Radio Button Elements', 'linkgreen-product-import-plugin' ),
			array( $this, 'radio_element_callback'),
			'linkgreen_product_import_input_examples',
			'input_examples_section'
		);

		add_settings_field(
			'Select Element',
			__( 'Select Element', 'linkgreen-product-import-plugin' ),
			array( $this, 'select_element_callback'),
			'linkgreen_product_import_input_examples',
			'input_examples_section'
		);

		register_setting(
			'linkgreen_product_import_input_examples',
			'linkgreen_product_import_input_examples',
			array( $this, 'validate_input_examples')
		);

	}

	/**
	 * It accepts an array or arguments and expects the first element in the array to be the description
	 * to be displayed next to the checkbox.
	 */
	public function update_apitoken_callback($args) {

		// First, we read the options collection
		$options = get_option('linkgreen_product_import_setup_options');

		// Next, we update the name attribute to access this element's ID in the context of the display options array
		$html = '<input type="text" id="api_token" name="linkgreen_product_import_setup_options[api_token]" value="' . $options['api_token'] . '"/>';

		// Here, we'll take the first argument of the array and add it to a label next to the checkbox
		$html .= '<label for="api_token">&nbsp;'  . $args[0] . '</label>';

		echo $html;

	} // end update_apitoken_callback


	/**
	 * It accepts an array or arguments and expects the first element in the array to be the description
	 * to be displayed next to the checkbox.
	 */
	public function update_googlemapstoken_callback($args) {

		// First, we read the options collection
		$options = get_option('linkgreen_product_import_setup_options');

		// Next, we update the name attribute to access this element's ID in the context of the display options array
		$html = '<input type="text" id="google_maps_token" name="linkgreen_product_import_setup_options[google_maps_token]" value="' . $options['google_maps_token'] . '"/>';

		// Here, we'll take the first argument of the array and add it to a label next to the checkbox
		$html .= '<label for="google_maps_token">&nbsp;'  . $args[0] . '</label>';

		echo $html;

	} // end update_googlemapstoken_callback
	

	/**
	 * Change the frequency of the scheduled task for product import
	 * so, delete or deactivate the cron item on callback???
	 */
	public function import_schedule_callback() {
		
		$options = get_option( 'linkgreen_product_import_setup_options' );

		$html = '<select id="schedule_frequency" name="linkgreen_product_import_setup_options[schedule_frequency]">';

		$html .= '<option value="default">' . __( 'Select a recurrance frequency...', 'linkgreen-product-import-plugin' ) . '</option>';
		$html .= '<option value="never"' . selected( $options['schedule_frequency'], 'never', false) . '>' . __( 'Never', 'linkgreen-product-import-plugin' ) . '</option>';
		$html .= '<option value="daily"' . selected( $options['schedule_frequency'], 'daily', false) . '>' . __( 'Daily (midnight)', 'linkgreen-product-import-plugin' ) . '</option>';
		$html .= '<option value="twicedaily"' . selected( $options['schedule_frequency'], 'twicedaily', false) . '>' . __( 'Twice, Daily (noon/midnight)', 'linkgreen-product-import-plugin' ) . '</option>';
		$html .= '<option value="weekly"' . selected( $options['schedule_frequency'], 'weekly', false) . '>' . __( 'Weekly (Monday at midnight)', 'linkgreen-product-import-plugin' ) . '</option>';	

		$html .= '</select>';

		echo $html;

	} // end select_element_callback

	private function schedule_recurring_import($first_time, $schedule, $hook) {
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

	public function update_import_schedule($old, $new) {
		$new_freq = $new['schedule_frequency'];
		$old_freq = $old['schedule_frequency'];
		$hook = Linkgreen_Constants::RUN_IMPORT_HOOK_NAME;

		if (isset($new_freq)) {
			switch ($new_freq) {
				case 'default':
				case 'never':
					if ( wp_next_scheduled( $hook ) ) {
						wp_clear_scheduled_hook( $hook );
					}
					return;
					break;

				case 'daily': 
				case 'twicedaily': 
					$timestamp = strtotime('today midnight');
					break;

				case 'weekly': 
					$timestamp = strtotime('monday midnight');
					break;

				default:
					wp_die( 'trying to do the haxxing eh?' );
					break;
			}

			$success = $this->schedule_recurring_import( $timestamp, $new_freq, $hook );

			if (! $success)
				log_error( 'failed to set recurring scheduled background session' );
			else 
				log_debug( "recurring background session is scheduled as $new_freq" );

		}
	}
	

	public function toggle_devmode_callback($args) {

		$options = get_option('linkgreen_product_import_debug_options');

		$html = '<input type="checkbox" id="dev_mode" name="linkgreen_product_import_debug_options[dev_mode]" value="1" ' . checked( 1, isset( $options['dev_mode'] ) ? $options['dev_mode'] : 0, false ) . '/>';
		$html .= '<label for="dev_mode">&nbsp;'  . $args[0] . '</label>';

		echo $html;

	} // end toggle_devmode_callback

	public function toggle_devapi_callback($args) {

		$options = get_option('linkgreen_product_import_debug_options');

		$html = '<input type="checkbox" id="dev_api" name="linkgreen_product_import_debug_options[dev_api]" value="1" ' . checked( 1, isset( $options['dev_api'] ) ? $options['dev_api'] : 0, false ) . '/>';
		$html .= '<label for="dev_api">&nbsp;'  . $args[0] . '</label>';

		echo $html;

	} // end toggle_devapi_callback


	/* EXAMPLES */
	public function twitter_callback() {

		// First, we read the social options collection
		$options = get_option( 'linkgreen_product_import_debug_options' );

		// Next, we need to make sure the element is defined in the options. If not, we'll set an empty string.
		$url = '';
		if( isset( $options['twitter'] ) ) {
			$url = esc_url( $options['twitter'] );
		} // end if

		// Render the output
		echo '<input type="text" id="twitter" name="linkgreen_product_import_debug_options[twitter]" value="' . $url . '" />';

	} // end twitter_callback

	public function facebook_callback() {

		$options = get_option( 'linkgreen_product_import_debug_options' );

		$url = '';
		if( isset( $options['facebook'] ) ) {
			$url = esc_url( $options['facebook'] );
		} // end if

		// Render the output
		echo '<input type="text" id="facebook" name="linkgreen_product_import_debug_options[facebook]" value="' . $url . '" />';

	} // end facebook_callback

	public function googleplus_callback() {

		$options = get_option( 'linkgreen_product_import_debug_options' );

		$url = '';
		if( isset( $options['googleplus'] ) ) {
			$url = esc_url( $options['googleplus'] );
		} // end if

		// Render the output
		echo '<input type="text" id="googleplus" name="linkgreen_product_import_debug_options[googleplus]" value="' . $url . '" />';

	} // end googleplus_callback

	public function input_element_callback() {

		$options = get_option( 'linkgreen_product_import_input_examples' );

		// Render the output
		echo '<input type="text" id="input_example" name="linkgreen_product_import_input_examples[input_example]" value="' . $options['input_example'] . '" />';

	} // end input_element_callback

	public function textarea_element_callback() {

		$options = get_option( 'linkgreen_product_import_input_examples' );

		// Render the output
		echo '<textarea id="textarea_example" name="linkgreen_product_import_input_examples[textarea_example]" rows="5" cols="50">' . $options['textarea_example'] . '</textarea>';

	} // end textarea_element_callback

	public function checkbox_element_callback() {

		$options = get_option( 'linkgreen_product_import_input_examples' );

		$html = '<input type="checkbox" id="checkbox_example" name="linkgreen_product_import_input_examples[checkbox_example]" value="1"' . checked( 1, $options['checkbox_example'], false ) . '/>';
		$html .= '&nbsp;';
		$html .= '<label for="checkbox_example">This is an example of a checkbox</label>';

		echo $html;

	} // end checkbox_element_callback

	public function radio_element_callback() {

		$options = get_option( 'linkgreen_product_import_input_examples' );

		$html = '<input type="radio" id="radio_example_one" name="linkgreen_product_import_input_examples[radio_example]" value="1"' . checked( 1, $options['radio_example'], false ) . '/>';
		$html .= '&nbsp;';
		$html .= '<label for="radio_example_one">Option One</label>';
		$html .= '&nbsp;';
		$html .= '<input type="radio" id="radio_example_two" name="linkgreen_product_import_input_examples[radio_example]" value="2"' . checked( 2, $options['radio_example'], false ) . '/>';
		$html .= '&nbsp;';
		$html .= '<label for="radio_example_two">Option Two</label>';

		echo $html;

	} // end radio_element_callback

	public function select_element_callback() {

		$options = get_option( 'linkgreen_product_import_input_examples' );

		$html = '<select id="time_options" name="linkgreen_product_import_input_examples[time_options]">';
		$html .= '<option value="default">' . __( 'Select a time option...', 'linkgreen-product-import-plugin' ) . '</option>';
		$html .= '<option value="never"' . selected( $options['time_options'], 'never', false) . '>' . __( 'Never', 'linkgreen-product-import-plugin' ) . '</option>';
		$html .= '<option value="sometimes"' . selected( $options['time_options'], 'sometimes', false) . '>' . __( 'Sometimes', 'linkgreen-product-import-plugin' ) . '</option>';
		$html .= '<option value="always"' . selected( $options['time_options'], 'always', false) . '>' . __( 'Always', 'linkgreen-product-import-plugin' ) . '</option>';	$html .= '</select>';

		echo $html;

	} // end select_element_callback


	/**
	 * this function loops through the incoming option and strips all tags and slashes from the value
	 * before serializing it.
	 *
	 * @params	$input	The unsanitized collection of options.
	 *
	 * @returns			The collection of sanitized values.
	 */
	public function sanitize_options_values( $input ) {

		// Define the array for the updated options
		$output = array();

		// Loop through each of the options sanitizing the data
		foreach( $input as $key => $val ) {
			if( isset ( $input[$key] ) ) {
				$output[$key] = strip_tags( stripslashes( trim( $input[$key] ) ) );
			} // end if

		} // end foreach

		// Return the new collection
		return apply_filters( 'sanitize_options_values', $output, $input );

	} // end sanitize_options_values

	public function validate_input_examples( $input ) {

		// Create our array for storing the validated options
		$output = array();

		// Loop through each of the incoming options
		foreach( $input as $key => $value ) {

			// Check to see if the current option has a value. If so, process it.
			if( isset( $input[$key] ) ) {

				// Strip all HTML and PHP tags and properly handle quoted strings
				$output[$key] = strip_tags( stripslashes( $input[ $key ] ) );

			} // end if

		} // end foreach

		// Return the array processing any additional functions filtered by this action
		return apply_filters( 'validate_input_examples', $output, $input );

	} // end validate_input_examples

}