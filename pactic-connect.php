<?php
/*
  Plugin Name: Pactic Connect
  Plugin URI: https://pactic.com/
  Description: Adding this plugin will let you show Pactic partners in the shipping method list – including their parcel points and lockers so your clients have the flexibility to choose the best option. It allows you to offer different pricing based on the delivery method.
  Version: 1.0
  Author: Pactic.com
  Author URI:  https://furgefutar.hu/
  Text Domain: pactic-connect
  Domain Path: /languages
  License: GPL v2 or later
  License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

/*************************************************************************************************************************************************************************************************************************************/
/*************************************************************************************************************************************************************************************************************************************/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! defined( 'PACTIC_CONNECT_PLUGIN_DIR_URL' ) ) {
	define( 'PACTIC_CONNECT_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'PACTIC_CONNECT_PLUGIN_DIR_PATH' ) ) {
	define( 'PACTIC_CONNECT_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
}

/*************************************************************************************************************************************************************************************************************************************/
/*************************************************************************************************************************************************************************************************************************************/

register_activation_hook( __FILE__, 'pactic_connect__activate' );

function pactic_connect__activate(){

    set_transient( 'pactic_connect__welcome_notice', true, 5 );
    
}

add_action( 'admin_notices', 'pactic_connect__welcome_notice' );

function pactic_connect__welcome_notice(){
    
    if( get_transient( 'pactic_connect__welcome_notice' ) ){
        
        echo '<div class="notice notice-info is-dismissible"><p>';

            printf( 
				/* translators: text in bold, settings page link */
                esc_html__( '%1$sThank you for using Pactic Connect!%2$s %3$sGo to the settings page and enter your details.%4$s', 'pactic-connect' ),
                '<strong>',
                '</strong>',
                '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=pactic_connect' ) ) . '">',
                '</a>' 
            );
            
                
		echo '</p></div>';

        delete_transient( 'pactic_connect__welcome_notice' );
        
    }
    
}

if ( pactic_connect__is_plugin_activated() ) {

    Pactic_Connect::get_instance();

} 
else {
    
    add_action( 'admin_notices', function (){
        
        if ( current_user_can( 'activate_plugins' ) ) {
         
  		    echo '<div class="notice notice-error"><p>';
                
                printf( 
					/* translators: text in bold, plugins page link */
                    esc_html__( '%1$sPactic Connect is currently inactive!%2$s %3$sPlease install or activate Woocommerce to use Pactic Connect!%4$s', 'pactic-connect' ),
                    '<strong>',
                    '</strong>',
                    '<a href="' . esc_url( admin_url( 'plugin-install.php?s=WooCommerce&tab=search&type=term' ) ) . '">',
                    '</a>' 
                );
                
    		echo '</p></div>';
        
        }

    });
        
}

function pactic_connect__is_plugin_activated() {
   
	$active_plugins = (array) get_option( 'active_plugins', array() );

	if ( is_multisite() ) {
		$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
	}

	return ( in_array( 'woocommerce/woocommerce.php', $active_plugins ) || array_key_exists( 'woocommerce/woocommerce.php', $active_plugins ) );

}

/*************************************************************************************************************************************************************************************************************************************/
/*************************************************************************************************************************************************************************************************************************************/

class Pactic_Connect {

	private static $instance = null;
	
	private $days = array();
	private $shipping_conditions = array();
	private $shipping_comparisons = array();
		
	public static function get_instance() {
	   
		if ( ! self::$instance )
			self::$instance = new self;
		return self::$instance;
        
	} 
    
    public function __construct() {
		
		$this->id = 'pactic_connect';
		
		$this->days = array(
			1 => __('Monday', 'pactic-connect' ),
			2 => __('Tuesday', 'pactic-connect' ),
			3 => __('Wednesday', 'pactic-connect' ),
			4 => __('Thursday', 'pactic-connect' ),
			5 => __('Friday', 'pactic-connect' ),
			6 => __('Saturday', 'pactic-connect' ),
			7 => __('Sunday', 'pactic-connect' )
		);

		$this->shipping_conditions = array(
			'order_value' => __('Order value', 'pactic-connect' ),
			'order_weight' => __('Order weight', 'pactic-connect' )
		);	

		$this->shipping_comparisons = array(
			'smaller' => __('Smaller than', 'pactic-connect' ),
			'greater' => __('Greater than', 'pactic-connect' ),
			'equal_or_smaller' => __('Equal or smaller than', 'pactic-connect' ),
			'equal_or_greater' => __('Equal or greater than', 'pactic-connect' ),
		);	

		global $wp_filesystem;
		
		if( !class_exists( 'WP_Filesystem' ) ) {
			
			require_once ( ABSPATH . 'wp-admin/includes/file.php' );

		}
		
		WP_Filesystem();

		register_activation_hook( __FILE__, array( $this, 'on_activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'on_deactivation' ) );
        
		add_action( 'admin_init', array( $this, 'check_parcel_points' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'pactic_connect__save_parcel_points', array( $this, 'save_parcel_points' ) );
		add_action( 'pactic_connect__country_codes', array( $this, 'save_country_codes' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_css_js' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_css_js' ) );
		add_action( 'woocommerce_admin_field_home_delivery_settings', array( $this, 'home_delivery_settings' ) );
		add_action( 'woocommerce_admin_field_parcel_point_settings', array( $this, 'parcel_point_settings' ) );
		add_action( 'woocommerce_admin_field_deletion_settings', array( $this, 'deletion_settings' ) );
		add_action( 'woocommerce_admin_field_country_codes_sync_description', array( $this, 'country_codes_sync_description' ) );
		add_action( 'woocommerce_admin_field_parcel_point_sync_description', array( $this, 'parcel_point_sync_description' ) );
        add_action( 'woocommerce_settings_tabs_'.$this->id, array( $this, 'settings_tab' ) );
        add_action( 'woocommerce_update_options_'.$this->id, array( $this, 'update_settings' ) );
        add_action( 'woocommerce_shipping_init', array( $this, 'shipping_init' ) );
		add_action( 'woocommerce_settings_save_'.$this->id, array( $this, 'save_settings' ) );
		add_action( 'woocommerce_review_order_after_shipping', array( $this, 'checkout_point_data'));
		add_action( 'wp_footer', array( $this, 'checkout_map_modal'));
		add_action( 'admin_footer', array( $this, 'detailed_pricing_modal'));
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_checkout_order_meta' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'save_block_checkout_order_meta' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout' ), 10, 2);
		
		add_action( 'wp_ajax_pactic_connect__save_parcel_point', array( $this, 'ajax_save_parcel_point' ) );
		add_action( 'wp_ajax_nopriv_pactic_connect__save_parcel_point', array( $this, 'ajax_save_parcel_point' ) );
		add_action( 'wp_ajax_pactic_connect__get_pricing_modal_content', array( $this, 'get_pricing_modal_content' ) );
		add_action( 'wp_ajax_nopriv_pactic_connect__get_pricing_modal_content', array( $this, 'get_pricing_modal_content' ) );
		add_action( 'wp_ajax_pactic_connect__save_shipping_prices', array( $this, 'save_shipping_prices' ) );
		add_action( 'wp_ajax_nopriv_pactic_connect__save_shipping_prices', array( $this, 'save_shipping_prices' ) );
		add_action( 'wp_ajax_pactic_connect__select_parcel_point', array( $this, 'ajax_select_parcel_point' ) );
		add_action( 'wp_ajax_nopriv_pactic_connect__select_parcel_point', array( $this, 'ajax_select_parcel_point' ) );
		add_action( 'wp_ajax_pactic_connect__delete_settings', array( $this, 'ajax_delete_settings' ) );
		
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_filter( 'woocommerce_shipping_methods', array( $this, 'add_method' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_settings_button' ) );
		
		add_action(
			'woocommerce_blocks_loaded',
			function() {
				woocommerce_store_api_register_update_callback(
					array(
						'namespace' => 'pactic-connect-checkout-block',
						'callback'  => array( $this, 'update_cart' ),
					)
				);

			}
		);
		
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_cod_fee' ), 20, 1 );

	}
			
	public function init(){
	   
        $this->load_plugin_textdomain();
		
	}
	
	public function on_activate() {
		
		$this->create_directory();
		
		$this->save_country_codes( true );
		
		wp_clear_scheduled_hook( 'pactic_connect__save_parcel_points' );
		wp_schedule_event( time(), "pactic_connect__every_4_hours", "pactic_connect__save_parcel_points" );

		wp_clear_scheduled_hook( 'pactic_connect__country_codes' );
		wp_schedule_event( time(), "pactic_connect__once_a_day", "pactic_connect__country_codes" );
		
	}
	
	public function create_directory() {
		
		$upload_dir = wp_upload_dir();

		if (!is_dir( $upload_dir['basedir'].'/pactic_connect' )) {
			
			global $wp_filesystem;
			
			$wp_filesystem->mkdir( $upload_dir['basedir'].'/pactic_connect', 0755 );
			
		}
		
	}
	 
	public function on_deactivation() {

		wp_clear_scheduled_hook( 'pactic_connect__save_parcel_points' );
		wp_clear_scheduled_hook( 'pactic_connect__country_codes' );

	}
	
	public function cron_schedules( $cron_schedules ) {
	
        $cron_schedules["pactic_connect__every_4_hours"] = array(
            "display" => __('Every four hours (Pactic Connect)', 'pactic-connect' ),
            "interval" => 4 * 60 * MINUTE_IN_SECONDS,
        );
		
		$cron_schedules["pactic_connect__once_a_day"] = array(
            "display" => __('Once a day (Pactic Connect)', 'pactic-connect' ),
            "interval" =>  24 * 60 * MINUTE_IN_SECONDS,
        );
		
        return $cron_schedules;
         
	}

	public function is_enabled() {
		
		return get_option( 'pactic_connect__status', false );
	
	}
	
	public function is_debug_enabled() {
		
		return get_option( 'pactic_connect__debug', false );
	
	}
	
	public function plugin_action_settings_button( $links ) {
		
		$action_links = array(
			'settings' => '<a href="' . esc_url(admin_url( 'admin.php?page=wc-settings&tab=pactic_connect' )) . '">' . __( 'Settings', 'pactic-connect' ) . '</a>',
		);

		return array_merge( $action_links, $links );
		
	}
	
	public function admin_css_js() {
		
		wp_enqueue_script( 'pactic_connect__arrive_js', PACTIC_CONNECT_PLUGIN_DIR_URL.'assets/js/arrive.js', array('jquery'), filemtime( PACTIC_CONNECT_PLUGIN_DIR_PATH.'assets/js/arrive.js' ), false );
		wp_enqueue_script( 'pactic_connect__serializejson_js', PACTIC_CONNECT_PLUGIN_DIR_URL.'assets/js/jquery.serializejson.js', array('jquery'), filemtime( PACTIC_CONNECT_PLUGIN_DIR_PATH.'assets/js/jquery.serializejson.js' ), false );
		wp_enqueue_script( 'pactic_connect__admin_toast_js', PACTIC_CONNECT_PLUGIN_DIR_URL.'assets/js/toast.js', array('jquery'), filemtime( PACTIC_CONNECT_PLUGIN_DIR_PATH.'assets/js/toast.js' ), false );
		wp_enqueue_style( 'pactic_connect__admin_toast_css', PACTIC_CONNECT_PLUGIN_DIR_URL.'assets/css/toast.css', '', filemtime( PACTIC_CONNECT_PLUGIN_DIR_PATH.'assets/css/toast.css' ), 'all' );
		wp_enqueue_style( 'pactic_connect__admin_css', PACTIC_CONNECT_PLUGIN_DIR_URL.'assets/css/admin.css', '', filemtime( PACTIC_CONNECT_PLUGIN_DIR_PATH.'assets/css/admin.css' ), 'all' );
        wp_enqueue_script( 'pactic_connect__admin_js', PACTIC_CONNECT_PLUGIN_DIR_URL.'assets/js/admin.js', array( 'jquery', 'pactic_connect__serializejson_js', 'pactic_connect__admin_toast_js', 'pactic_connect__arrive_js' ), filemtime( PACTIC_CONNECT_PLUGIN_DIR_PATH.'assets/js/admin.js' ), false );
		
		wp_localize_script( 'pactic_connect__admin_js', 'pactic_connect', 
			array( 
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'currency_symbol' => esc_html( get_woocommerce_currency_symbol() ),
				'save_parcel_point_nonce'   => wp_create_nonce( 'save_parcel_point_nonce' ),
				'detailed_pricing_modal_nonce'   => wp_create_nonce( 'detailed_pricing_modal_nonce' ),
				'save_shipping_prices_nonce'   => wp_create_nonce( 'save_shipping_prices_nonce' ),
				'delete_settings_nonce'   => wp_create_nonce( 'delete_settings_nonce' ),
			) 
		);  
		
		wp_set_script_translations( 
			 'pactic_connect__admin_js',
			 'pactic-connect',
			 PACTIC_CONNECT_PLUGIN_DIR_PATH . 'languages/'
		);
	
    } 
	
	public function frontend_css_js() {
		
		$selected_parcel_point_code = '';
		
		$pactic_connect__selected_parcel_point = WC()->session->get( 'pactic_connect__selected_parcel_point' );
		
		if( $pactic_connect__selected_parcel_point ){
			$selected_parcel_point_code = $pactic_connect__selected_parcel_point['parcel_point_code']	 ;
		}

		$pactic_connect__parcel_point_display_type = get_option('pactic_connect__parcel_point_display_type');
		
		if( $pactic_connect__parcel_point_display_type == 'map' ){
			
			$pactic_connect__google_map_api_key = get_option('pactic_connect__google_map_api_key');
			
			if( $pactic_connect__google_map_api_key ){
				
				// description of the file: https://developers.google.com/maps/documentation/javascript/marker-clustering and https://github.com/googlemaps/js-markerclusterer
				wp_enqueue_script( 'pactic_connect__gmap_markerclusterer', PACTIC_CONNECT_PLUGIN_DIR_URL.'assets/js/markerClusterer.js', array( 'jquery' ), '1.0', false );
				
				wp_enqueue_script( 'pactic_connect__gmap', 'https://maps.googleapis.com/maps/api/js?key='.$pactic_connect__google_map_api_key.'&loading=async', array( 'jquery', 'pactic_connect__gmap_markerclusterer' ), '1.0', false );

			}
			
		}
		
		$upload_dir = wp_upload_dir();
	
		wp_enqueue_script( 'pactic_connect__arrive_js', PACTIC_CONNECT_PLUGIN_DIR_URL.'assets/js/arrive.js', array('jquery'), filemtime( PACTIC_CONNECT_PLUGIN_DIR_PATH.'assets/js/arrive.js' ), false );
		wp_enqueue_style( 'pactic_connect__frontend_css', PACTIC_CONNECT_PLUGIN_DIR_URL.'assets/css/frontend.css', '', filemtime( PACTIC_CONNECT_PLUGIN_DIR_PATH.'assets/css/frontend.css' ), 'all' );
		wp_enqueue_script( 'pactic_connect__frontend_js', PACTIC_CONNECT_PLUGIN_DIR_URL.'assets/js/frontend.js', array('jquery', 'pactic_connect__arrive_js'), filemtime( PACTIC_CONNECT_PLUGIN_DIR_PATH.'assets/js/frontend.js' ), false );
		wp_localize_script( 'pactic_connect__frontend_js', 'pactic_connect', 
			array( 
				'ajax_url' => admin_url( 'admin-ajax.php' ), 
				'parcel_point_display_type' => $pactic_connect__parcel_point_display_type,
				'plugin_dir_url' => PACTIC_CONNECT_PLUGIN_DIR_URL,
				'selected_parcel_point_code' => $selected_parcel_point_code,
				'upload_dir_baseurl' => $upload_dir['baseurl'],
				'parcel_point_settings' => $this->get_parcel_point_settings(),
				'days' => $this->days,
				'select_parcel_point_nonce'   => wp_create_nonce( 'select_parcel_point_nonce' ),
			) 
		);

		wp_set_script_translations( 
			 'pactic_connect__frontend_js',
			 'pactic-connect',
			 PACTIC_CONNECT_PLUGIN_DIR_PATH . 'languages/' 
		);	
		
    }
	
	public function shipping_init() {
		
		if( $this->is_enabled() == 'yes' ){
			
			if( $this->is_parcel_point_configured() ){
				
				include_once( PACTIC_CONNECT_PLUGIN_DIR_PATH . 'includes/class-pactic-connect-parcel-point.php' );
				
			}
			
			if( $this->is_home_delivery_configured() ){
				
				include_once( PACTIC_CONNECT_PLUGIN_DIR_PATH . 'includes/class-pactic-connect-home-delivery.php' );
				
			}
		
		}
	
    }
    
    public function load_plugin_textdomain() {
	
    	$locale = determine_locale();
		$locale = apply_filters( 'plugin_locale', $locale, 'pactic-connect' );

		load_textdomain( 'pactic-connect', basename( dirname( __FILE__ ) ) . '/languages/pactic-connect-' . $locale . '.mo' );
		load_plugin_textdomain( 'pactic-connect', false, basename( dirname( __FILE__ ) ) . '/languages/' );

	}

    public function add_settings_tab( $settings_tabs ) {
    
        $settings_tabs['pactic_connect'] = __('Pactic Connect', 'pactic-connect' );
        
        return $settings_tabs;
        
    }
    
    public function update_settings() {
        
        woocommerce_update_options( $this->get_settings() );
        
    }
        
    public function settings_tab() {
		
		wp_nonce_field( 'settings_tab', 'settings_tab_nonce' );
        
        echo '<table class="form-table">';
        
            woocommerce_admin_fields( $this->get_settings() );
            
        echo '</table>';

    }
		
	public function get_settings() {
      
        $settings = array(
            'pactic_connect__section_1' => array(
                'name'     => __( 'Pactic Connect settings', 'pactic-connect' ),
                'type'     => 'title',
            ),
            
			'pactic_connect__status' => array(
                'title'   => __( 'Enable / Disable', 'pactic-connect' ),
                'type'    => 'checkbox',
                'default' => 'no',
                'desc' => __( 'Pactic Connect plugin', 'pactic-connect' ),
                'desc_tip'    => true,
                'id'      => 'pactic_connect__status',
            ),
            
			'pactic_connect__section_1_end' => array(
                 'type' => 'sectionend'
            ),
			
			'pactic_connect__section_2' => array(
                'name'     => __( 'Home delivery settings', 'pactic-connect' ),
                'type'     => 'title',
            ),
			
			'pactic_connect__home_delivery' => array(
				'title' => __( 'Services', 'pactic-connect' ),
				'type' => 'home_delivery_settings',
			),
            
            'pactic_connect__section_2_end' => array(
                 'type' => 'sectionend'
            ), 
			
			'pactic_connect__section_3' => array(
                'name'     => __( 'Parcel point settings', 'pactic-connect' ),
                'type'     => 'title',
            ),
			
            'pactic_connect__parcel_point' => array(
				'title' => __( 'Services', 'pactic-connect' ),
				'type' => 'parcel_point_settings',
			),
			
			'pactic_connect__parcel_point_display_type' => array(
				'title'   => __( 'Display', 'pactic-connect' ),
				'type'    => 'radio',
				'default' => 'dropdown',
				'options' => array( 
					'dropdown' => __( 'Dropdown', 'pactic-connect' ), 
					'map' => __( 'Map', 'pactic-connect' ) 
				),
				'desc_tip'    => true,
				'id'      => 'pactic_connect__parcel_point_display_type',
				'desc' => __( 'If you choose the map view, you need a Google Map Api key!', 'pactic-connect' ),
			),
			
			'pactic_connect__google_map_api_key' => array(
				'title'   => __( 'Google Map Api Key', 'pactic-connect' ),
				'type'    => 'text',
				'desc_tip'    => true,
				'default' => '',
				'id'   => 'pactic_connect__google_map_api_key',
				'desc' => __( 'https://console.cloud.google.com: Create credentials, and enable Maps JavaScript API.', 'pactic-connect' ),
			),
			
            'pactic_connect__section_3_end' => array(
                 'type' => 'sectionend'
            ), 
		
			'pactic_connect__section_4' => array(
                'name'     => __( 'Cash on delivery payment', 'pactic-connect' ),
                'type'     => 'title',
            ),
			
			'pactic_connect__cod_payment_methods' => array(
				'title'       => __( 'Additional COD payment methods', 'pactic-connect' ),
				'type'        => 'multiselect',
				'options'     => $this->available_payment_methods(),
				'desc'        => __( 'The extension automatically selects the default COD payment method:', 'pactic-connect' ).' <strong>'.$this->available_payment_methods( 'cod' ).'</strong>.'.
								 '<br/>'.esc_html__('If additional payment methods are considered cash on delivery in your online store, you can set them up here.', 'pactic-connect' ),
				'id'      => 'pactic_connect__cod_payment_methods',
			),
			
			'pactic_connect__section_4_end' => array(
                 'type' => 'sectionend'
            ), 
  
			'pactic_connect__section_5' => array(
                'name'     => __( 'Synchronization', 'pactic-connect' ),
                'type'     => 'title',
            ),
			
			'pactic_connect__country_codes_sync_description' => array(
                'name'     => __( 'Country codes', 'pactic-connect' ),
                'type' => 'country_codes_sync_description',
            ),
			
			'pactic_connect__parcel_point_sync_description' => array(
                'name'     => __( 'Parcel points', 'pactic-connect' ),
                'type' => 'parcel_point_sync_description',
            ),

			'pactic_connect__section_5_end' => array(
                 'type' => 'sectionend'
            ), 
			
			'pactic_connect__section_6' => array(
                'name'     => __( 'Debug', 'pactic-connect' ),
                'type'     => 'title',
            ),
			
			'pactic_connect__debug' => array(
                'title'   => __( 'Enable / Disable', 'pactic-connect' ),
                'type'    => 'checkbox',
                'default' => 'no',
                'desc' => '<a target="_blank" href="'.esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&source=pactic_connect__&paged=1' ) ).'">'.esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&source=pactic_connect__&paged=1' ) ).'</a>',
                'desc_tip'    => true,
                'id'      => 'pactic_connect__debug',
            ),
			
			'pactic_connect__deletion' => array(
				'title'   => __( 'Delete stored data', 'pactic-connect' ),
				'type' => 'deletion_settings',
			),
			
			'pactic_connect__section_6_end' => array(
                 'type' => 'sectionend'
            ), 

        );

        return apply_filters( 'pactic_connect__settings', $settings );
        
    }
	
	public function log( $data ) {
		
		if( $this->is_debug_enabled() == 'yes' ){
		
			$logger = wc_get_logger();
			
			$logger->debug(
				wp_json_encode( $data ),
				array( 'source' => 'pactic_connect__log' )
			);
		
		}

	}
	
	public function cron_log( $data ) {
		
		if( $this->is_debug_enabled() == 'yes' ){
		
			$logger = wc_get_logger();
			
			$logger->debug(
				wp_json_encode( $data ),
				array( 'source' => 'pactic_connect__cron_log' )
			);
		
		}

	}
	
	function get_parcel_point_country_codes(){
				
		$pactic_connect__country_codes = get_option('pactic_connect__country_codes');
		
		return $pactic_connect__country_codes;
		
    }

	public function validate_checkout($fields, $errors) {

		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
		$pactic_connect__selected_parcel_point = WC()->session->get( 'pactic_connect__selected_parcel_point' );
		
		$is_pactic_connect_parcel_point_selected = false;
		 
		foreach ( $chosen_methods as $method ) {
			
			if( strpos( $method, 'pactic_connect_parcel_point') !== false ) {
			
				$is_pactic_connect_parcel_point_selected = true;
			
			}
			
		}
		
		if( $is_pactic_connect_parcel_point_selected && ( !$pactic_connect__selected_parcel_point || $chosen_methods[0] != $pactic_connect__selected_parcel_point['method_id'] ) ) {
			
			$errors->add( 
				'validation', 
				__( 'Please select a parcel point or choose a different shipping method.', 'pactic-connect' )
			);
		
		}
		
	}

	public function save_checkout_order_meta( $order_id ) {
		
		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
	
		if( strpos( $chosen_methods[0], 'pactic_connect_parcel_point') !== false ) {
			
			$pactic_connect__selected_parcel_point = WC()->session->get( 'pactic_connect__selected_parcel_point' );
			
			if( $chosen_methods[0] == $pactic_connect__selected_parcel_point['method_id'] ){
				
				$parcel_point_info = $this->get_parcel_point_info( $pactic_connect__selected_parcel_point );

				$order = wc_get_order( $order_id );
					
				$order->set_address( 
					array(
						'first_name' => $order->get_billing_first_name(),
						'last_name'  => $order->get_billing_last_name(),
						'company'    => $parcel_point_info['Name'],
						'email'      => '',
						'phone'      => '',
						'address_1'  => $parcel_point_info['Address'],
						'address_2'  => '',
						'city'       => $parcel_point_info['City'],
						'state'      => '',
						'postcode'   => $parcel_point_info['PostCode'],
						'country'    => $parcel_point_info['cdCountry'],
					), 
					'shipping' 
				);
				
				$order->update_meta_data( 'pactic_connect__parcel_point_cdcountry', $parcel_point_info['cdCountry'] );
				$order->update_meta_data( 'pactic_connect__parcel_point_idcarrier', $parcel_point_info['idCarrier'] );
				$order->update_meta_data( 'pactic_connect__parcel_point_idservice', $parcel_point_info['idService'] );
				$order->update_meta_data( 'pactic_connect__parcel_point_code', $parcel_point_info['Code'] );
				$order->update_meta_data( 'pactic_connect__parcel_point_name', $parcel_point_info['Name'] );
				$order->update_meta_data( 'pactic_connect__parcel_point_address', $parcel_point_info['Address'] );
				$order->update_meta_data( 'pactic_connect__parcel_point_city', $parcel_point_info['City'] );
				$order->update_meta_data( 'pactic_connect__parcel_point_postcode', $parcel_point_info['PostCode'] );
				
				$order->update_meta_data( 'IdCarrier', $parcel_point_info['idCarrier'] );
				$order->update_meta_data( 'IdService', $parcel_point_info['idService'] );
				$order->update_meta_data( 'Parcel point ID', $parcel_point_info['Code'] );
				
				$order->save();
				
				WC()->session->set('pactic_connect__selected_parcel_point', '' );
			
			}

		} 
		else if( strpos( $chosen_methods[0], 'pactic_connect_home_delivery') !== false ) {
			
			list( $name, $id ) = explode( ':', $chosen_methods[0] );
			
			$choosed_home_delivery_setting = get_option( 'woocommerce_'.$name.'_'.$id.'_settings', false );
			
			$home_delivery_settings = get_option( 'pactic_connect__home_delivery_settings', false );
			
			if( $home_delivery_settings ){
				
				foreach( $home_delivery_settings as $home_delivery_setting ){
					
					if( $home_delivery_setting['service_id'] == $choosed_home_delivery_setting['service'] ){
						
						$order = wc_get_order( $order_id );
						
						$order->update_meta_data( 'pactic_connect__home_delivery_idcarrier', $home_delivery_setting['idCarrier'] );
						$order->update_meta_data( 'pactic_connect__home_delivery_idservice', $home_delivery_setting['idService'] );
						
						$order->update_meta_data( 'IdCarrier', $home_delivery_setting['idCarrier'] );
						$order->update_meta_data( 'IdService', $home_delivery_setting['idService'] );
	
						$order->save();
						
					}
					
				}
				
			}
			
		}

	}
	
	public function save_block_checkout_order_meta( $order ) {
		
		$order_id = $order->get_id();

		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
	
		if( strpos( $chosen_methods[0], 'pactic_connect_parcel_point') !== false ) {

			$pactic_connect__selected_parcel_point = WC()->session->get( 'pactic_connect__selected_parcel_point' );
				
			if( $chosen_methods[0] == $pactic_connect__selected_parcel_point['method_id'] ){
				
				$parcel_point_info = $this->get_parcel_point_info( $pactic_connect__selected_parcel_point );

				$order = wc_get_order( $order_id );
					
				$order->set_address( 
					array(
						'first_name' => $order->get_billing_first_name(),
						'last_name'  => $order->get_billing_last_name(),
						'company'    => $parcel_point_info['Name'],
						'email'      => '',
						'phone'      => '',
						'address_1'  => $parcel_point_info['Address'],
						'address_2'  => '',
						'city'       => $parcel_point_info['City'],
						'state'      => '',
						'postcode'   => $parcel_point_info['PostCode'],
						'country'    => $parcel_point_info['cdCountry'],
					), 
					'shipping' 
				);
				
				$order->update_meta_data( 'pactic_connect__parcel_point_cdcountry', $parcel_point_info['cdCountry'] );
				$order->update_meta_data( 'pactic_connect__parcel_point_idcarrier', $parcel_point_info['idCarrier'] );
				$order->update_meta_data( 'pactic_connect__parcel_point_idservice', $parcel_point_info['idService'] );
				$order->update_meta_data( 'pactic_connect__parcel_point_code', $parcel_point_info['Code'] );
				$order->update_meta_data( 'pactic_connect__parcel_point_name', $parcel_point_info['Name'] );
				$order->update_meta_data( 'pactic_connect__parcel_point_address', $parcel_point_info['Address'] );
				$order->update_meta_data( 'pactic_connect__parcel_point_city', $parcel_point_info['City'] );
				$order->update_meta_data( 'pactic_connect__parcel_point_postcode', $parcel_point_info['PostCode'] );
				
				$order->update_meta_data( 'IdCarrier', $parcel_point_info['idCarrier'] );
				$order->update_meta_data( 'IdService', $parcel_point_info['idService'] );
				$order->update_meta_data( 'Parcel point ID', $parcel_point_info['Code'] );
				
				$order->save();
				
				WC()->session->set('pactic_connect__selected_parcel_point', '' );
				WC()->session->set('block_payment_method', '' );
			
			}

		} 
		else if( strpos( $chosen_methods[0], 'pactic_connect_home_delivery') !== false ) {
			
			list( $name, $id ) = explode( ':', $chosen_methods[0] );
			
			$choosed_home_delivery_setting = get_option( 'woocommerce_'.$name.'_'.$id.'_settings', false );
			
			$home_delivery_settings = get_option( 'pactic_connect__home_delivery_settings', false );
			
			if( $home_delivery_settings ){
				
				foreach( $home_delivery_settings as $home_delivery_setting ){
					
					if( $home_delivery_setting['service_id'] == $choosed_home_delivery_setting['service'] ){
						
						$order = wc_get_order( $order_id );
						
						$order->update_meta_data( 'pactic_connect__home_delivery_idcarrier', $home_delivery_setting['idCarrier'] );
						$order->update_meta_data( 'pactic_connect__home_delivery_idservice', $home_delivery_setting['idService'] );
						
						$order->update_meta_data( 'IdCarrier', $home_delivery_setting['idCarrier'] );
						$order->update_meta_data( 'IdService', $home_delivery_setting['idService'] );
	
						$order->save();
						
						WC()->session->set('block_payment_method', '' );
						
					}
					
				}
				
			}
			
		}

	}
	
	public function ajax_select_parcel_point() {

		if ( isset( $_REQUEST['select_parcel_point_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST['select_parcel_point_nonce'] ) ) , 'select_parcel_point_nonce' ) ){
		
			if ( isset($_REQUEST) ) {
				
				WC()->session->set('pactic_connect__selected_parcel_point', sanitize_text_field( $_REQUEST['parcel_point_data'] ) );

			}
		
		}
		
	}
		
	public function checkout_map_modal() {
			
		if( is_checkout() ) {
		
			if( $this->is_enabled() == 'yes' ){
			
				if( $this->is_parcel_point_configured() ){
					
					echo '<div class="pactic_connect__modal_bg"></div>';
			
					echo '<div class="pactic_connect__modal" >';
					
						echo '<div class="pactic_connect__modal_top">';
						
							echo '<div class="pactic_connect__modal_search_box">';
						
								echo '<span class="pactic_connect__modal_search_icon"></span>';
								
								echo '<input type="text" placeholder="'.esc_html__('Cím vagy hely keresése...', 'pactic-connect' ).'" class="pactic_connect__modal_search_field"  >';

								echo '<span class="pactic_connect__modal_search_remove"></span>';
								
								echo '<button class="pactic_connect__modal_search_field_button" type="button">';
									echo esc_html__('Search', 'pactic-connect' );
								echo '</button>';
						
							echo '</div>';
						
							echo '<div class="pactic_connect__modal_close"></div>';

						echo '</div>';
						
						echo '<div class="pactic_connect__modal_middle">';
					
						echo '</div>';
						
					echo '</div>';
			
				}

			}

		}
		
	}
	
	public function get_parcel_point_info( $parcel_point_setting ) {
		
		$upload_dir = wp_upload_dir();
		
		$service_data = wp_remote_get( $upload_dir['baseurl'].'/pactic_connect/'.$parcel_point_setting['cdcountry'].'_'.$parcel_point_setting['idcarrier'].'.json' );
		
		$service_data = wp_remote_retrieve_body( $service_data );
		
		$service_data = json_decode( $service_data, true);
		
		$parcel_points = $service_data[0]['ParcelPoints'];
		
		if( $parcel_points ){
				
			foreach( $parcel_points as $parcel_point ){

				if( $parcel_point_setting['parcel_point_code'] == $parcel_point['Code'] ){

					return array(
						'cdCountry' => $parcel_point_setting['cdcountry'],
						'idCarrier' => $parcel_point_setting['idcarrier'],
						'idService' => $parcel_point_setting['idservice'],
						'Code' => $parcel_point_setting['parcel_point_code'],
						'Name' => $parcel_point['Name'],
						'PostCode' => $parcel_point['Location']['PostCode'],
						'City' => $parcel_point['Location']['City'],
						'Address' => $parcel_point['Location']['Address']
					);
				 
				}
				
			}
			
		}				
		
	}
	
	public function checkout_point_data() {
		
		if( is_checkout() ) {
			
			if( $this->is_enabled() == 'yes' ){
			
				if( $this->is_parcel_point_configured() ){
		
					$chosen_methods = WC()->session->chosen_shipping_methods;
					$pactic_connect__selected_parcel_point = WC()->session->get( 'pactic_connect__selected_parcel_point' );
					
					$is_pactic_connect_parcel_point_selected = false;
					
					foreach ( $chosen_methods as $method ) {
						
						if( strpos( $method, 'pactic_connect_parcel_point') !== false ) {
						
							$is_pactic_connect_parcel_point_selected = true;
						
						}
						
					}
					
					if( $is_pactic_connect_parcel_point_selected ){
						
						echo '<tr class="pactic_connect__parcel_point_info_row">';
			 
							echo '<th>';
								echo esc_html__('Pickup location', 'pactic-connect' );
							echo '</th>';

							echo '<td >'; 

								if( !$pactic_connect__selected_parcel_point  ){
									
									echo '<button type="button" id="pactic_connect__modal_open" class="pactic_connect__modal_open_button">';
										echo esc_html__('Choosing a parcel point', 'pactic-connect' );
									echo '</button>';
								
								}
								else{
									
									if( $chosen_methods[0] == $pactic_connect__selected_parcel_point['method_id'] ){
										
										$parcel_point_info = $this->get_parcel_point_info( $pactic_connect__selected_parcel_point );
									
										echo '<div class="pactic_connect__selected_parcel_point_info">';

											echo '<div class="pactic_connect__selected_parcel_point_name">';
												echo esc_html( $parcel_point_info['Name'] );
											echo '</div>';
											
											echo '<div class="pactic_connect__selected_parcel_point_address">';
												echo esc_html( $parcel_point_info['PostCode'] ).' '.esc_html( $parcel_point_info['City'] ).',<br/>'.esc_html( $parcel_point_info['Address'] );
											echo '</div>';
											
								
										echo '</div>';
										
										echo '<button type="button" id="pactic_connect__modal_open" class="pactic_connect__modal_open_button">';
											echo esc_html__('Change parcel point', 'pactic-connect' );
										echo '</button>';
									
									}
									else{
										
										echo '<button type="button" id="pactic_connect__modal_open" class="pactic_connect__modal_open_button">';
											echo esc_html__('Choosing a parcel point', 'pactic-connect' );
										echo '</button>';
										
									}

								}

							echo '</td>';
							
						echo '</tr>';

					}
					
				}
				
			}
			
		}
	
	}
		
	public function ajax_save_parcel_point() {
		
		if ( isset( $_REQUEST['save_parcel_point_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST['save_parcel_point_nonce'] ) ) , 'save_parcel_point_nonce' ) ){

			print_r( $this->save_parcel_point( sanitize_text_field( $_REQUEST['cdCountry'] ), sanitize_text_field( $_REQUEST['idCarrier'] ), 'manual' ) );
			
		}

		die();
		
	}
		
	public function is_parcel_point_configured() {
		
		$enabled = false;
		
		$parcel_point_settings = get_option( 'pactic_connect__parcel_point_settings', false );

		if( $parcel_point_settings ){
			
			foreach( $parcel_point_settings as $parcel_point_setting ){
				
				if( $parcel_point_setting['enable'] == 'yes' ){
					
					$enabled = true;
					
				}
				
			}
			
		}
		
		return $enabled;
		
	}
	
	public function is_home_delivery_configured() {
		
		$enabled = false;
		
		$home_delivery_settings = get_option( 'pactic_connect__home_delivery_settings', false );
		
		if( $home_delivery_settings ){
			
			foreach( $home_delivery_settings as $home_delivery_setting ){
				
				if( $home_delivery_setting['enable'] == 'yes' ){
					
					$enabled = true;
					
				}
				
			}
			
		}
		
		return $enabled;
		
	}
        
    public function add_method( $methods ) {
		
		if( $this->is_enabled() == 'yes' ){
			
			if( $this->is_parcel_point_configured() ){
				
				$methods['pactic_connect_parcel_point'] = 'Pactic_Connect_Parcel_Point';
				
			}
			
			if( $this->is_home_delivery_configured() ){
				
				$methods['pactic_connect_home_delivery'] = 'Pactic_Connect_Home_Delivery';
				
			}
		
		}
		
        return $methods;
        
	}
    	
	public function save_parcel_points( $enabled = false, $run_type = "automatic" ) {
		
		update_option( 'pactic_connect__save_parcel_points_sync_date', gmdate('Y-m-d H:i:s') );

        $upload_dir = wp_upload_dir();
		
		if( $this->is_enabled() == 'yes' || $enabled ){
			
			$parcel_point_settings = get_option( 'pactic_connect__parcel_point_settings', false );
 
			if( $parcel_point_settings ){
				
				foreach( $parcel_point_settings as $parcel_point_setting ){
					
					if( $parcel_point_setting['enable'] == 'yes' && $parcel_point_setting['cdCountry'] && $parcel_point_setting['idCarrier'] ){

						$this->save_parcel_point( $parcel_point_setting['cdCountry'], $parcel_point_setting['idCarrier'], $run_type, $enabled );
						
					}
					
				}
				
			}
			
		}
		
	}
	
	public function save_parcel_point( $cdCountry = '', $idCarrier = 0, $run_type = "", $enabled = false ) {
			
		$upload_dir = wp_upload_dir();
		
		if( $this->is_enabled() == 'yes' || $enabled ){
			
			if( $cdCountry && $idCarrier  ){

				$url = 'https://api.pactic.com/webservices/shipment/parcelpoints_v2/downloadparcelpoints.ashx?cdCountry='.$cdCountry.'&idCarrier='.$idCarrier;
				
				$args = array(
					'headers' => array(
						'Accept' => 'Application/json',
						'Apikey' => 'dj22ecsN-S4Dq-5PFp-nkGu-36oyK6mxt2ya',
						'Content-Type' => 'application/x-www-form-urlencoded',
						'Accept-Encoding' => 'gzip, deflate, br',
					)
				);

				$response = wp_remote_get( $url, $args );

				$http_code = wp_remote_retrieve_response_code( $response );

				if( $http_code == 200 ){
					
					$response_body = wp_remote_retrieve_body( $response );
					
					$response_data = json_decode( $response_body, true);
												
					if( array_key_exists( 0, $response_data ) ){
						
						if( array_key_exists( 'ParcelPoints', $response_data[0] ) ){
						
							$parcel_points = $response_data[0]['ParcelPoints'];
							
							if( $parcel_points ){
								
								global $wp_filesystem;
							
								$wp_filesystem->put_contents( $upload_dir['basedir'].'/pactic_connect/'.$cdCountry.'_'.$idCarrier.'.json', $response_body );
								
								update_option( 'pactic_connect__cron_'.$cdCountry.'_'.$idCarrier.'_last_sync_date', gmdate('Y-m-d H:i:s') );
								
								update_option( 'pactic_connect__cron_'.$cdCountry.'_'.$idCarrier.'_last_sync_status', 'success' );
								
								$this->cron_log( 
									array(
										'run_type' => $run_type,
										'type' => 'parcel_points',
										'cdCountry' => $cdCountry,
										'idCarrier' => $idCarrier,
										'status' => '200',
										'http_code' => $http_code,
										'error' => ''
									) 
								);
								
								return wp_json_encode( 
									array(
										'status' => '200'
									)
								);

							}
							else{
							
								update_option( 'pactic_connect__cron_'.$cdCountry.'_'.$idCarrier.'_last_sync_status', 'failed' );
															
								$this->cron_log( 
									array(
										'run_type' => $run_type,
										'type' => 'parcel_points',
										'cdCountry' => $cdCountry,
										'idCarrier' => $idCarrier,
										'status' => '1008',
										'http_code' => $http_code,
										'error' => ''
									) 
								);
								
								return wp_json_encode( 
									array(
										'status' => '1008'
									)
								);
								
							}

						}
						else{
							
							update_option( 'pactic_connect__cron_'.$cdCountry.'_'.$idCarrier.'_last_sync_status', 'failed' );
														
							$this->cron_log( 
								array(
									'run_type' => $run_type,
									'type' => 'parcel_points',
									'cdCountry' => $cdCountry,
									'idCarrier' => $idCarrier,
									'status' => '1009',
									'http_code' => $http_code,
									'error' => ''
								) 
							);
							
							return wp_json_encode( 
								array(
									'status' => '1009'
								)
							);

						}

					}
					else{
							
						update_option( 'pactic_connect__cron_'.$cdCountry.'_'.$idCarrier.'_last_sync_status', 'failed' );
						
						$this->cron_log( 
							array(
								'run_type' => $run_type,
								'type' => 'parcel_points',
								'cdCountry' => $cdCountry,
								'idCarrier' => $idCarrier,
								'status' => '1010',
								'http_code' => $http_code,
								'error' => ''
							) 
						);
						
						return wp_json_encode( 
							array(
								'status' => '1010'
							)
						);
													
					}
					
				}
				else{
					
					update_option( 'pactic_connect__cron_'.$cdCountry.'_'.$idCarrier.'_last_sync_status', 'failed' );
					
					$this->cron_log( 
						array(
							'run_type' => $run_type,
							'type' => 'parcel_points',
							'cdCountry' => $cdCountry,
							'idCarrier' => $idCarrier,
							'status' => '1001',
							'http_code' => $http_code,
							'error' => ''
						) 
					);
					
					return wp_json_encode( 
						array(
							'status' => '1001'
						)
					);
											
				}
				
			}
			else{
				
				$this->cron_log( 
					array(
						'run_type' => $run_type,
						'type' => 'parcel_points',
						'cdCountry' => $cdCountry,
						'idCarrier' => $idCarrier,
						'status' => '1006',
						'http_code' => $http_code,
						'error' => ''
					) 
				);
						
				return wp_json_encode( 
					array(
						'status' => '1006'
					)
				);

			}
			
			$this->cron_log( 
				array(
					'run_type' => $run_type,
					'type' => 'parcel_points',
					'cdCountry' => $cdCountry,
					'idCarrier' => $idCarrier,
					'status' => '1007',
					'http_code' => $http_code,
					'error' => ''
				) 
			);
			
			return wp_json_encode( 
				array(
					'status' => '1007'
				)
			);

		}

	}
	
	public function save_country_codes( $enabled = false ){
		
		if( $this->is_enabled() == 'yes' || $enabled ){
		
			$country_codes = array();

			$url = 'https://api.pactic.com/webservices/shipment/parcelpoints_v2/countries.ashx';
				
			$args = array(
				'headers' => array(
					'Accept' => 'Application/json',
					'Apikey' => 'dj22ecsN-S4Dq-5PFp-nkGu-36oyK6mxt2ya',
					'Content-Type' => 'application/x-www-form-urlencoded',
					'Accept-Encoding' => 'gzip, deflate, br',
				)
			);

			$response = wp_remote_get( $url, $args );

			$http_code = wp_remote_retrieve_response_code( $response );

			if( $http_code == 200 ){
				
				$response_body = wp_remote_retrieve_body( $response );
				
				$response_data = json_decode( $response_body, true);
											
				foreach( $response_data as $country ){
								
					$country_codes[$country] = $country;
					
				}
				
				$this->cron_log( 
					array(
						'run_type' => 'automatic',
						'type' => 'country_codes',
						'status' => '200',
						'http_code' => $http_code,
						'error' => ''
					) 
				);

			}
			else{
				
				$this->cron_log( 
					array(
						'run_type' => 'automatic',
						'type' => 'country_codes',
						'status' => '1001',
						'http_code' => $http_code,
						'error' => ''
					) 
				);
				
			}

			if( $country_codes ){
				
				update_option( 'pactic_connect__country_codes_sync_date', gmdate('Y-m-d H:i:s') );
				update_option( 'pactic_connect__country_codes_sync_status', 'success' );
				update_option( 'pactic_connect__country_codes', $country_codes );
				
			}
			else{

				update_option( 'pactic_connect__country_codes_sync_status', 'failed' );
				
			}
			
		}

    }

	public function home_delivery_settings( $data ) {
			
		$saved_pactic_connect__home_delivery_settings = get_option('pactic_connect__home_delivery_settings');

		?>
		<tr valign="top">
			
			<th scope="row" class="titledesc"><?php echo esc_html( $data['title'] ); ?></th>
			
			<td class="forminp">
				
				<div class="pactic_connect__home_delivery_list">
				
					<table class="widefat striped pactic_connect__home_delivery_list_table">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Enable/Disable', 'pactic-connect' ); ?></th>
								<th><?php echo esc_html__( 'Name', 'pactic-connect' ); ?></th>
								<th><?php echo esc_html__( 'idCarrier', 'pactic-connect' ); ?></th>
								<th><?php echo esc_html__( 'idService ', 'pactic-connect' ); ?></th>
								<th><?php echo esc_html__( 'Remove', 'pactic-connect' ); ?></th>
							</tr>
						</thead>
						<tbody>
						
							<?php 
							
								if( !empty( $saved_pactic_connect__home_delivery_settings ) ){
							
									foreach ( $saved_pactic_connect__home_delivery_settings as $home_delivery_setting_id => $home_delivery_setting ){

										echo '<tr class="home_delivery_setting_row" row_id="home_delivery_setting_id">';

											echo '<td>';
											
												echo '<input type="hidden" name="pactic_connect__home_delivery_settings[home_delivery_setting_id][service_id]" value="'.esc_html( $home_delivery_setting['service_id'] ).'" />';


												if( 'yes' == $home_delivery_setting['enable'] ){
										
													echo '<input type="checkbox" checked="checked" class="pactic_connect__home_delivery_settings_switch" name="pactic_connect__home_delivery_settings[home_delivery_setting_id][switch]" value="" />';
												}   
												else{
													echo '<input type="checkbox" class="pactic_connect__home_delivery_settings_switch" name="pactic_connect__home_delivery_settings[home_delivery_setting_id][switch]" value="" />';
												}
												
												echo '<input type="hidden" class="pactic_connect__home_delivery_settings_enable" name="pactic_connect__home_delivery_settings[home_delivery_setting_id][enable]" value="'.esc_html( $home_delivery_setting['enable'] ).'" />';
											echo '</td>';
											
											echo '<td><input type="text" name="pactic_connect__home_delivery_settings[home_delivery_setting_id][name]" value="'.esc_html( $home_delivery_setting['name'] ).'" /></td>';
											
											 
											echo '<td>';
												echo '<input type="number" name="pactic_connect__home_delivery_settings[home_delivery_setting_id][idCarrier]" value="'.esc_html( $home_delivery_setting['idCarrier'] ).'" />';
											echo '</td>';
											
											echo '<td>';
												echo '<input type="number" name="pactic_connect__home_delivery_settings[home_delivery_setting_id][idService]" value="'.esc_html( $home_delivery_setting['idService'] ).'" />';
											echo '</td>';
											
											echo '<td>';
												echo '<button class="pactic_connect__remove_home_delivery_button" row_id="home_delivery_setting_id" type="button">';
													echo '<span class="dashicons dashicons-minus"></span> <span>'.esc_html__('Remove', 'pactic-connect' ).'</span>';
												echo '</button>';
											echo '</td>';
										echo '</tr>';

									}
								}
		 
							?>
							
						</tbody>
					</table>
					
					<table class="pactic_connect__home_delivery_sample_list_table">
						<?php
						echo '<tr class="home_delivery_setting_row" row_id="home_delivery_setting_id">';
		
							echo '<td>';
								echo '<input type="hidden" sample_name="pactic_connect__home_delivery_settings[home_delivery_setting_id][service_id]" value="sample_service_id" />';
								echo '<input type="checkbox" class="pactic_connect__home_delivery_settings_switch" sample_name="pactic_connect__home_delivery_settings[home_delivery_setting_id][switch]" value="" />';
								echo '<input type="hidden" class="pactic_connect__home_delivery_settings_enable" sample_name="pactic_connect__home_delivery_settings[home_delivery_setting_id][enable]" value="" />';
							echo '</td>';
											
							echo '<td>';
								echo '<input type="text" sample_name="pactic_connect__home_delivery_settings[home_delivery_setting_id][name]" value="" />';
							echo '</td>';
											
							echo '<td>';
								echo '<input type="number" sample_name="pactic_connect__home_delivery_settings[home_delivery_setting_id][idCarrier]" value="" />';
							echo '</td>';
								
							echo '<td>';
								echo '<input type="number" sample_name="pactic_connect__home_delivery_settings[home_delivery_setting_id][idService]" value="" />';
							echo '</td>';
							
							echo '<td>';
								echo '<button class="pactic_connect__remove_home_delivery_button" row_id="home_delivery_setting_id" type="button">';
									echo '<span class="dashicons dashicons-minus"></span> <span>'.esc_html__('Remove', 'pactic-connect' ).'</span>';
								echo '</button>';
							echo '</td>';
							
						echo '</tr>';    
											
						?>
					</table>
					
				</div>
				
				<button class="pactic_connect__add_home_delivery_button" type="button">
					<span class="dashicons dashicons-plus-alt"></span> <span><?php echo esc_html__('Add a new service', 'pactic-connect' ); ?></span>
				</button>

			</td>
			
		</tr>
		
		<?php
	}
	
	public function parcel_point_settings( $data ) {
		
		$upload_dir = wp_upload_dir();
        
		$parcel_point_country_codes = $this->get_parcel_point_country_codes();
		
		$saved_pactic_connect__parcel_point_settings = get_option('pactic_connect__parcel_point_settings');

		?>
		<tr valign="top">
			
			<th scope="row" class="titledesc"><?php echo esc_html( $data['title'] ); ?></th>
			
			<td class="forminp">
				
				<div class="pactic_connect__parcel_point_list">
				
					<table class="widefat striped pactic_connect__parcel_point_list_table">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Enable/Disable', 'pactic-connect' ); ?></th>
								<th><?php echo esc_html__( 'Name', 'pactic-connect' ); ?></th>
								<th><?php echo esc_html__( 'Country', 'pactic-connect' ); ?></th>
								<th><?php echo esc_html__( 'idCarrier', 'pactic-connect' ); ?></th>
								<th><?php echo esc_html__( 'idService ', 'pactic-connect' ); ?></th>
								<th><?php echo esc_html__( 'Sync ', 'pactic-connect' ); ?></th>
								<th><?php echo esc_html__( 'Last sync date', 'pactic-connect' ); ?></th>
								<th><?php echo esc_html__( 'Last sync status', 'pactic-connect' ); ?></th>
								<th><?php echo esc_html__( 'Remove', 'pactic-connect' ); ?></th>
							</tr>
						</thead>
						<tbody>
						
							<?php 
							
								if( !empty( $saved_pactic_connect__parcel_point_settings ) ){
							
									foreach ( $saved_pactic_connect__parcel_point_settings as $parcel_point_setting_id => $parcel_point_setting ){

										echo '<tr class="parcel_point_setting_row" row_id="parcel_point_setting_id">';
										
											echo '<td>';
											
												echo '<input type="hidden" class="" name="pactic_connect__parcel_point_settings[parcel_point_setting_id][service_id]" value="'.esc_html( $parcel_point_setting['service_id'] ).'" />';

												if( 'yes' == $parcel_point_setting['enable'] ){
										
													echo '<input type="checkbox" checked="checked" class="pactic_connect__parcel_point_settings_switch" name="pactic_connect__parcel_point_settings[parcel_point_setting_id][switch]" value="" />';
												}   
												else{
													echo '<input type="checkbox" class="pactic_connect__parcel_point_settings_switch" name="pactic_connect__parcel_point_settings[parcel_point_setting_id][switch]" value="" />';
												}
												
												echo '<input type="hidden" class="pactic_connect__parcel_point_settings_enable" name="pactic_connect__parcel_point_settings[parcel_point_setting_id][enable]" value="'.esc_html( $parcel_point_setting['enable'] ).'" />';
												
											echo '</td>';
											
											echo '<td><input type="text" name="pactic_connect__parcel_point_settings[parcel_point_setting_id][name]" value="'.esc_html( $parcel_point_setting['name'] ).'" /></td>';
											
											echo '<td>';
												echo '<select name="pactic_connect__parcel_point_settings[parcel_point_setting_id][cdCountry]">';
													foreach ( $parcel_point_country_codes as $parcel_point_country_code => $parcel_point_country_name ){
														
														if( $parcel_point_country_code == $parcel_point_setting['cdCountry'] ){
															echo '<option selected="selected" value="'.esc_html( $parcel_point_country_code ).'">'.esc_html( $parcel_point_country_name ).'</option>';
														}   
														else{
															echo '<option value="'.esc_html( $parcel_point_country_code ).'">'.esc_html( $parcel_point_country_name ).'</option>';
														} 
														 
													}
												echo '</select>';
											echo '</td>';
											
											echo '<td>';
												echo '<input type="number" name="pactic_connect__parcel_point_settings[parcel_point_setting_id][idCarrier]" value="'.esc_html( $parcel_point_setting['idCarrier'] ).'" />';
											echo '</td>';
											
											echo '<td>';
												echo '<input type="number" name="pactic_connect__parcel_point_settings[parcel_point_setting_id][idService]" value="'.esc_html( $parcel_point_setting['idService'] ).'" />';
											echo '</td>';
											
											echo '<td>';
												
												if( $parcel_point_setting['cdCountry'] && $parcel_point_setting['idCarrier'] && $parcel_point_setting['idService'] ){
													
													echo '<button class="pactic_connect__sync_button" row_id="parcel_point_setting_id" type="button" cdCountry="'.esc_html( $parcel_point_setting['cdCountry'] ).'" idCarrier="'.esc_html( $parcel_point_setting['idCarrier'] ).'" idService="'.esc_html( $parcel_point_setting['idService'] ).'" >';
													
														echo '<span class="dashicons dashicons-update-alt"></span> <span>'.esc_html__('Sync', 'pactic-connect' ).'</span>';
														
													echo '</button>';
													
												}	
												else{
													
													echo '<button disabled="disabled" class="pactic_connect__sync_button" row_id="parcel_point_setting_id" type="button">';
													
														echo '<span class="dashicons dashicons-update-alt"></span> <span>'.esc_html__('Sync', 'pactic-connect' ).'</span>';
														
													echo '</button>';
													
												}												
													
											echo '</td>';
											
											echo '<td>';
											
												$last_sync_date = get_option( 'pactic_connect__cron_'.$parcel_point_setting['cdCountry'].'_'.$parcel_point_setting['idCarrier'].'_last_sync_date' );

												if( $last_sync_date ){
													
													if( file_exists( $upload_dir['basedir'].'/pactic_connect/'.$parcel_point_setting['cdCountry'].'_'.$parcel_point_setting['idCarrier'].'.json' ) ){
						
														echo '<a target="_blank" class="button button-secondary" title="'.esc_html__( 'View', 'pactic-connect' ).'" href="'.esc_html( $upload_dir['baseurl'] ).'/pactic_connect/'.esc_html( $parcel_point_setting['cdCountry'] ).'_'.esc_html( $parcel_point_setting['idCarrier'] ).'.json">';
															echo esc_html( $last_sync_date );
														echo '</a>';
													
													}
									
												}
										
											echo '</td>';
											
											echo '<td>';
											
												$last_sync_status = get_option( 'pactic_connect__cron_'.$parcel_point_setting['cdCountry'].'_'.$parcel_point_setting['idCarrier'].'_last_sync_status' );
												
												if( $last_sync_status ){
													
													if( $last_sync_status == 'failed' ){
														echo '<span class="pactic_connect__last_sync_status_red">'.esc_html__('Failed', 'pactic-connect' ).'</span>';
													}
													else if( $last_sync_status == 'success' ){
														echo '<span class="pactic_connect__last_sync_status_green">'.esc_html__('Success', 'pactic-connect' ).'</span>';
													}
													
												}
										
											echo '</td>';
											
											echo '<td>';
												echo '<button class="pactic_connect__remove_parcel_point_button" row_id="parcel_point_setting_id" type="button">';
													echo '<span class="dashicons dashicons-minus"></span> <span>'. esc_html__('Remove', 'pactic-connect' ).'</span>';
												echo '</button>';
											echo '</td>';
											
										echo '</tr>';

									}
								}
		 
							?>
							
						</tbody>
					</table>
					 
					<table class="pactic_connect__parcel_point_sample_list_table">
						<?php
						echo '<tr class="parcel_point_setting_row" row_id="parcel_point_setting_id">';
						
							echo '<td>';
								echo '<input type="hidden" sample_name="pactic_connect__parcel_point_settings[parcel_point_setting_id][service_id]" value="sample_service_id" />';
								echo '<input type="checkbox" class="pactic_connect__parcel_point_settings_switch" sample_name="pactic_connect__parcel_point_settings[parcel_point_setting_id][switch]" value="" />';
								echo '<input type="hidden" class="pactic_connect__parcel_point_settings_enable" sample_name="pactic_connect__parcel_point_settings[parcel_point_setting_id][enable]" value="" />';
							echo '</td>';
											
							echo '<td><input type="text" sample_name="pactic_connect__parcel_point_settings[parcel_point_setting_id][name]" value="" />';
							echo '</td>';

							echo '<td>';
								echo '<select sample_name="pactic_connect__parcel_point_settings[parcel_point_setting_id][cdCountry]">';
									foreach ( $parcel_point_country_codes as $parcel_point_country_code => $parcel_point_country_name ){
										echo '<option value="'.esc_html( $parcel_point_country_code ).'">'.esc_html( $parcel_point_country_name ).'</option>';
									}
								echo '</select>';
							echo '</td>';
							
							echo '<td>';
								echo '<input type="number" sample_name="pactic_connect__parcel_point_settings[parcel_point_setting_id][idCarrier]" value="" />';
							echo '</td>';
							
							echo '<td>';
								echo '<input type="number" sample_name="pactic_connect__parcel_point_settings[parcel_point_setting_id][idService]" value="" />';
							echo '</td>';
							
							echo '<td>';
							
							echo '</td>';
							
							echo '<td>';
							
							echo '</td>';
							
							echo '<td>';
							
							echo '</td>';
							
							echo '<td>';
								echo '<button class="pactic_connect__remove_parcel_point_button" row_id="parcel_point_setting_id" type="button">';
									echo '<span class="dashicons dashicons-minus"></span> <span>'.esc_html__('Remove', 'pactic-connect' ).'</span>';
								echo '</button>';
							echo '</td>';

						echo '</tr>';    
											
						?>
					</table>
					
				</div>
				
				<button class="pactic_connect__add_parcel_point_button" type="button">
					<span class="dashicons dashicons-plus-alt"></span> <span><?php echo esc_html__('Add a new service', 'pactic-connect' ); ?></span>
				</button>

			</td>
			
		</tr>

		<?php
	}
	
	public function save_settings() {
		
		if ( isset( $_REQUEST['settings_tab_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST['settings_tab_nonce'] ) ) , 'settings_tab' ) ){

			$saved_pactic_connect__parcel_point_settings = get_option('pactic_connect__parcel_point_settings');

			$pactic_connect__parcel_point_settings = array();
			
			if ( isset( $_POST['pactic_connect__parcel_point_settings'] ) ) {

				$pactic_connect__parcel_point_settings = map_deep( wp_unslash( $_POST['pactic_connect__parcel_point_settings'] ), 'sanitize_text_field' );
				
				foreach ( $pactic_connect__parcel_point_settings as $settings_id => $settings ) {
					
					if( ( array_key_exists( 'idCarrier', $settings ) && $settings['idCarrier'] == '' ) || ( array_key_exists( 'idService', $settings ) && $settings['idService'] == '' ) ){
						
						unset( $pactic_connect__parcel_point_settings[$settings_id] );
						
					}
					
				}
				
			}
			
			unset( $pactic_connect__parcel_point_settings['parcel_point_setting_id'] );
			
			update_option( 'pactic_connect__parcel_point_settings', $pactic_connect__parcel_point_settings );
			 
			$pactic_connect__home_delivery_settings = array();
			
			if ( isset( $_POST['pactic_connect__home_delivery_settings'] ) ) {
				
				$pactic_connect__home_delivery_settings = map_deep( wp_unslash( $_POST['pactic_connect__home_delivery_settings'] ), 'sanitize_text_field' );
				
				foreach ( $pactic_connect__home_delivery_settings as $settings_id => $settings ) {
					
					if( ( array_key_exists( 'idCarrier', $settings ) && $settings['idCarrier'] == '' ) || ( array_key_exists( 'idService', $settings ) && $settings['idService'] == '' ) ){
						
						unset( $pactic_connect__home_delivery_settings[$settings_id] );
						
					}
					
				}
				
			}
			
			unset( $pactic_connect__home_delivery_settings['home_delivery_setting_id'] );
			
			update_option( 'pactic_connect__home_delivery_settings', $pactic_connect__home_delivery_settings );
			
			$compare_array_1 = array();
			foreach( $saved_pactic_connect__parcel_point_settings as $key => $value ) {
				
				array_push( $compare_array_1, $value );

			}
			
			$compare_array_2 = array();
			foreach( $pactic_connect__parcel_point_settings as $key => $value ) {
				
				array_push( $compare_array_2, $value ); 

			}

			if( $compare_array_1 != $compare_array_2 ){
				
				$this->save_parcel_points( true, 'settings_update' );
				
			}
		
		} 
		else {

			die( esc_html__('Security check', 'pactic-connect' ) ); 

		}

	}
	
	public function deletion_settings( $data ){
		
		echo '<tr valign="top">';
			
			echo '<th scope="row" class="titledesc">'.esc_html( $data['title'] ).'</th>';
			
			echo '<td class="forminp">';
								
				echo '<button type="button" class="button button-secondary pactic_connect__deletion_button" delete_option="plugin_settings" title="'.esc_html__( 'Delete all settings and files', 'pactic-connect' ).'" >';
					echo '<span class="dashicons dashicons-trash"></span> <span>'. esc_html__('Delete all settings and files', 'pactic-connect' ).'</span>';
				echo '</button>';
				
				echo '<button type="button" class="button button-secondary pactic_connect__deletion_button" delete_option="shipping_methods" title="'.esc_html__( 'Delete related shipping methods', 'pactic-connect' ).'" >';
					echo '<span class="dashicons dashicons-trash"></span> <span>'. esc_html__('Delete related shipping methods', 'pactic-connect' ).'</span>';
				echo '</button>';
				
				echo '<button type="button" class="button button-secondary pactic_connect__deletion_button" delete_option="log_files" title="'.esc_html__( 'Delete related log files', 'pactic-connect' ).'" >';
					echo '<span class="dashicons dashicons-trash"></span> <span>'. esc_html__('Delete related log files', 'pactic-connect' ).'</span>';
				echo '</button>';
				
				echo '<button type="button" class="button button-secondary pactic_connect__deletion_button" delete_option="files" title="'.esc_html__( 'Delete parcel point files', 'pactic-connect' ).'" >';
					echo '<span class="dashicons dashicons-trash"></span> <span>'. esc_html__('Delete parcel point files', 'pactic-connect' ).'</span>';
				echo '</button>';

			echo '</td>';
			
		echo '</tr>';

	}
		
	public function detailed_pricing_modal() {
			
		echo '<div class="pactic_connect__detailed_pricing_modal_bg"></div><div class="pactic_connect__detailed_pricing_modal"></div>';

	}
	
	public function get_pricing_modal_content() {
		
		if ( isset( $_REQUEST['detailed_pricing_modal_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST['detailed_pricing_modal_nonce'] ) ) , 'detailed_pricing_modal_nonce' ) ){

			if ( isset($_REQUEST) ) {
				
				$pactic_connect__method_settings = get_option('woocommerce_pactic_connect_'.sanitize_text_field( $_REQUEST['method_name'] ).'_'.sanitize_text_field($_REQUEST['instance_id'] ).'_settings');
				
				$saved_pactic_connect__shipping_price_settings = get_option('woocommerce_pactic_connect_'.sanitize_text_field( $_REQUEST['instance_id'] ).'_shipping_price_settings');
					
				$pricing_logic = $saved_pactic_connect__shipping_price_settings['pricing_logic'];
				unset( $saved_pactic_connect__shipping_price_settings['pricing_logic'] );

				$saved_pactic_connect__shipping_cod_price_settings = get_option('woocommerce_pactic_connect_'.sanitize_text_field( $_REQUEST['instance_id'] ).'_shipping_cod_price_settings');
			
				ob_start();
				
				echo '<div class="pactic_connect__detailed_pricing_modal_top">';
				
					echo '<h2>';
								
						echo esc_html( $pactic_connect__method_settings['title'] ) . ' - ' . esc_html__('Detailed pricing', 'pactic-connect' ); 
						
					echo '</h2>';

					echo '<div class="pactic_connect__detailed_pricing_modal_close"></div>';
					
				echo '</div>'; 
				
				echo '<div class="pactic_connect__detailed_pricing_modal_middle">';
				
					echo '<form class="pactic_connect__detailed_pricing_form" instance_id="'.esc_html( sanitize_text_field( $_REQUEST['instance_id'] ) ).'" >'; 
					
						echo '<div class="pactic_connect__detailed_pricing_block">';
						
							echo '<h3 class="pactic_connect__detailed_pricing_block_header">';
								
								echo esc_html__( 'Pricing', 'pactic-connect' ); 
								
							echo '</h3>';
						
							echo '<div class="pactic_connect__detailed_pricing_logic">';
						
								echo esc_html__('In case of conflict prices, choose:', 'pactic-connect' );
							
								echo '<select name="pactic_connect__shipping_price_settings[pricing_logic]">';
				
									echo '<option '.selected( $pricing_logic, 'cheapest', false ).' value="cheapest">'.esc_html__('Cheapest', 'pactic-connect' ).'</option>';
									echo '<option '.selected( $pricing_logic, 'expensive', false ).' value="expensive">'.esc_html__('Most expensive', 'pactic-connect' ).'</option>';
				
								echo '</select>';
							
							echo '</div>';

							echo '<div class="pactic_connect__shipping_pricing">';
											
								echo '<table class="widefat striped pactic_connect__shipping_pricing_table">';
								
									echo '<thead>';
										echo '<tr>';
											echo '<th colspan="8">';
												echo esc_html__( 'Conditions', 'pactic-connect' );
											echo '</th>';
										echo '</tr>';
									echo '</thead>';
													
									echo '<tbody>';
									
										$empty_setting_row_class = 'show';
									
										if( !empty( $saved_pactic_connect__shipping_price_settings ) ){
											
											$empty_setting_row_class = '';
									
											foreach ( $saved_pactic_connect__shipping_price_settings as $shipping_price_setting_id => $shipping_price_setting ){

												echo '<tr class="shipping_price_setting_row" row_id="shipping_price_setting_id">';
												
													echo '<td>';
														
														echo '<input type="hidden" class="" name="pactic_connect__shipping_price_settings[shipping_price_setting_id][price_id]" value="'.esc_html( $shipping_price_setting['price_id'] ).'" />';
														
														echo '<input type="checkbox" '.checked( $shipping_price_setting['enable'], 'yes', false ).' class="pactic_connect__shipping_price_settings_switch" name="pactic_connect__shipping_price_settings[shipping_price_setting_id][switch]" value="" />';
														echo '<input type="hidden" class="pactic_connect__shipping_price_settings_enable" name="pactic_connect__shipping_price_settings[shipping_price_setting_id][enable]" value="'.esc_html( $shipping_price_setting['enable'] ).'" />';
													
													echo '</td>';
												
													echo '<td>';
														
														echo esc_html__('Apply', 'pactic-connect' );
														
													echo '</td>';
												
													echo '<td>';
														echo '<div class="pactic_connect__cost_box">';
															echo '<input type="text" class="" placeholder="'.esc_html__('Shipping cost(net)', 'pactic-connect' ).'" name="pactic_connect__shipping_price_settings[shipping_price_setting_id][cost]" value="'.esc_html( $shipping_price_setting['cost'] ).'" />';
															echo '<span>';
																echo esc_html(get_woocommerce_currency_symbol());
															echo '</span>';
														echo '</div>';
													echo '</td>';
													
													echo '<td>';
														echo esc_html__('net cost if', 'pactic-connect' );
													echo '</td>';
													
													echo '<td>';
														echo '<select name="pactic_connect__shipping_price_settings[shipping_price_setting_id][condition]" class="pactic_connect__shipping_price_condition" row_id="shipping_price_setting_id">';
															foreach ( $this->shipping_conditions as $shipping_condition_value => $shipping_condition_name ){
																
																echo '<option '.selected( $shipping_price_setting['condition'], $shipping_condition_value, false ).' value="'.esc_html( $shipping_condition_value ).'">'.esc_html( $shipping_condition_name ).'</option>';
									
															}
														echo '</select>';
													echo '</td>';
													
													echo '<td>';
														echo '<select name="pactic_connect__shipping_price_settings[shipping_price_setting_id][comparison]">';
															foreach ( $this->shipping_comparisons as $shipping_comparison_value => $shipping_comparison_name ){
																
																echo '<option '.selected( $shipping_price_setting['comparison'], $shipping_comparison_value, false ).' value="'.esc_html( $shipping_comparison_value ).'">'.esc_html( $shipping_comparison_name ).'</option>';
									 
															}
														echo '</select>';
													echo '</td>';

													echo '<td>';
							
														echo '<div class="pactic_connect__cost_box">';
															
															if( $shipping_price_setting['condition'] == 'order_value' ){
																$value_placeholder = __('value', 'pactic-connect' );
															}
															else if( $shipping_price_setting['condition'] == 'order_weight' ){
																$value_placeholder = __('weight', 'pactic-connect' );
															}
															
															echo '<input type="text" class="pactic_connect__shipping_value" placeholder="'.esc_html( $value_placeholder ).'" name="pactic_connect__shipping_price_settings[shipping_price_setting_id][value]" value="'.esc_html( $shipping_price_setting['value'] ).'" />';
															echo '<span class="pactic_connect__shipping_value_suffix" row_id="shipping_price_setting_id">';
																if( $shipping_price_setting['condition'] == 'order_value' ){
																	echo esc_html(get_woocommerce_currency_symbol());
																}
																else if( $shipping_price_setting['condition'] == 'order_weight' ){
																	echo esc_html__('kg', 'pactic-connect' );
																}
															echo '</span>';
														echo '</div>';
													
													echo '</td>';

													echo '<td>';
														echo '<button class="button button-secondary pactic_connect__remove_shipping_prices_button" row_id="shipping_price_setting_id" type="button">';
															echo '<span class="dashicons dashicons-minus"></span> <span>'.esc_html__('Remove', 'pactic-connect' ).'</span>';
														echo '</button>';
													echo '</td>';
													
												echo '</tr>';

											}
										}

									echo '</tbody>';
									
									echo '<tfoot>';
									
										echo '<tr class="shipping_price_empty_setting_row '.esc_html( $empty_setting_row_class ).'">';
											echo '<td colspan="8">';
												echo esc_html__( 'There are no settings.', 'pactic-connect' );
											echo '</td>'; 
										echo '</tr>';
									
									echo '</tfoot>';
									
								echo '</table>';
									
							echo '</div>';
							
							echo '<button class="button button-secondary pactic_connect__add_shipping_prices_button" type="button">';
								echo '<span class="dashicons dashicons-plus-alt"></span> <span>'.esc_html__('Add a new row', 'pactic-connect' ).'</span>';
							echo '</button>';	

						echo '</div>';
						
					echo '</form>'; 
		
					echo '<form class="pactic_connect__detailed_cod_pricing_form" instance_id="'.esc_html( sanitize_text_field( $_REQUEST['instance_id'] ) ).'" >'; 
						
						echo '<div class="pactic_connect__detailed_pricing_block">';

							echo '<h3 class="pactic_connect__detailed_pricing_block_header">';
								
								echo esc_html__( 'COD pricing', 'pactic-connect' ); 
								
							echo '</h3>'; 

							echo '<div class="pactic_connect__shipping_cod_pricing">';
											
								echo '<table class="widefat striped pactic_connect__shipping_cod_pricing_table">';
								
									echo '<thead>';
										echo '<tr>';
											echo '<th colspan="8">';
												echo esc_html__( 'Price ranges', 'pactic-connect' );
											echo '</th>';
										echo '</tr>';
									echo '</thead>';
													
									echo '<tbody>';
									
										$empty_setting_row_class = 'show';
									
										if( !empty( $saved_pactic_connect__shipping_cod_price_settings ) ){
											
											$empty_setting_row_class = '';

											foreach ( $saved_pactic_connect__shipping_cod_price_settings as $shipping_cod_price_setting_id => $shipping_cod_price_setting ){

												echo '<tr class="shipping_cod_price_setting_row" row_id="shipping_cod_price_setting_id">';
												
													echo '<td>';
														
														echo '<input type="hidden" class="" name="pactic_connect__shipping_cod_price_settings[shipping_cod_price_setting_id][price_id]" value="'.esc_html( $shipping_cod_price_setting['price_id'] ).'" />';
														
														echo '<input type="checkbox" '.checked( $shipping_cod_price_setting['enable'], 'yes', false ).' class="pactic_connect__shipping_cod_price_settings_switch" name="pactic_connect__shipping_cod_price_settings[shipping_cod_price_setting_id][switch]" value="" />';
														echo '<input type="hidden" class="pactic_connect__shipping_cod_price_settings_enable" name="pactic_connect__shipping_cod_price_settings[shipping_cod_price_setting_id][enable]" value="'.esc_html( $shipping_cod_price_setting['enable'] ).'" />';
													
													echo '</td>';
												
													echo '<td>';
														
														echo esc_html__('Apply', 'pactic-connect' );
														
													echo '</td>';
												
													echo '<td>';
														echo '<div class="pactic_connect__cost_box">';
															echo '<input type="text" class="" placeholder="'.esc_html__('Shipping cost(net)', 'pactic-connect' ).'" name="pactic_connect__shipping_cod_price_settings[shipping_cod_price_setting_id][cost]" value="'.esc_html( $shipping_cod_price_setting['cost'] ).'" />';
															echo '<span>';
																echo esc_html(get_woocommerce_currency_symbol());
															echo '</span>';
														echo '</div>';
													echo '</td>';
													
													echo '<td>';
														echo esc_html__('net cost if order value equal or greater then', 'pactic-connect' );
													echo '</td>';
													
													echo '<td>';
														echo '<div class="pactic_connect__cost_box">';
															echo '<input type="text" class="" placeholder="'.esc_html__('Order value from', 'pactic-connect' ).'" name="pactic_connect__shipping_cod_price_settings[shipping_cod_price_setting_id][value_from]" value="'.esc_html( $shipping_cod_price_setting['value_from'] ).'" />';
															echo '<span>';
																echo esc_html(get_woocommerce_currency_symbol());
															echo '</span>';
														echo '</div>';
													echo '</td>';
													
													echo '<td>';
														echo esc_html__('and smaller then', 'pactic-connect' );
													echo '</td>';
													
													echo '<td>';
														echo '<div class="pactic_connect__cost_box">';
															echo '<input type="text" class="" placeholder="'.esc_html__('Order value to', 'pactic-connect' ).'" name="pactic_connect__shipping_cod_price_settings[shipping_cod_price_setting_id][value_to]" value="'.esc_html( $shipping_cod_price_setting['value_to'] ).'" />';
															echo '<span>';
																echo esc_html(get_woocommerce_currency_symbol());
															echo '</span>';
														echo '</div>';
													echo '</td>';
													
													echo '<td>';
														echo '<button class="button button-secondary pactic_connect__remove_shipping_cod_prices_button" row_id="shipping_cod_price_setting_id" type="button">';
															echo '<span class="dashicons dashicons-minus"></span> <span>'.esc_html__('Remove', 'pactic-connect' ).'</span>';
														echo '</button>';
													echo '</td>';
													
												echo '</tr>';

											}
										}

									echo '</tbody>';
									
									echo '<tfoot>';
									
										echo '<tr class="shipping_cod_price_empty_setting_row '.esc_html( $empty_setting_row_class ).'">';
											echo '<td colspan="8">';
												echo esc_html__( 'There are no settings.', 'pactic-connect' );
											echo '</td>'; 
										echo '</tr>';
									
									echo '</tfoot>';
									
								echo '</table>';
								
							echo '</div>';
							
							echo '<button class="button button-secondary pactic_connect__add_shipping_cod_prices_button" type="button">';
								echo '<span class="dashicons dashicons-plus-alt"></span> <span>'.esc_html__('Add a new row', 'pactic-connect' ).'</span>';
							echo '</button>';
								
						echo '</div>';
							
					echo '</form>'; 
						 
					echo '<table class="pactic_connect__shipping_pricing_sample_table">';

						echo '<tr class="shipping_price_setting_row" row_id="shipping_price_setting_id">';
						
							echo '<td>';
								echo '<input type="hidden" class="" sample_name="pactic_connect__shipping_price_settings[shipping_price_setting_id][price_id]" value="sample_price_id" />';
								echo '<input type="checkbox" class="pactic_connect__shipping_price_settings_switch" sample_name="pactic_connect__shipping_price_settings[shipping_price_setting_id][switch]" value="" />';
								echo '<input type="hidden" class="pactic_connect__shipping_price_settings_enable" sample_name="pactic_connect__shipping_price_settings[shipping_price_setting_id][enable]" value="" />';
							echo '</td>';

							echo '<td>';
								
								echo esc_html__('Apply', 'pactic-connect' );
								
							echo '</td>';
						
							echo '<td>';

								echo '<div class="pactic_connect__cost_box">';
									echo '<input type="text" class="" placeholder="'.esc_html__('Shipping cost(net)', 'pactic-connect' ).'" sample_name="pactic_connect__shipping_price_settings[shipping_price_setting_id][cost]"  />';
									echo '<span>';
										echo esc_html(get_woocommerce_currency_symbol());
									echo '</span>';
								echo '</div>';
							
							echo '</td>';

							echo '<td>';
								echo esc_html__('net cost if', 'pactic-connect' );
							echo '</td>';
							
							echo '<td>';
								echo '<select sample_name="pactic_connect__shipping_price_settings[shipping_price_setting_id][condition]" class="pactic_connect__shipping_price_condition" row_id="shipping_price_setting_id">';
									foreach ( $this->shipping_conditions as $shipping_condition_value => $shipping_condition_sample_name ){
										
										echo '<option value="'.esc_html( $shipping_condition_value ).'">'.esc_html( $shipping_condition_sample_name ).'</option>';
					 
									}
								echo '</select>';
							echo '</td>';
							
							echo '<td>';
								echo '<select sample_name="pactic_connect__shipping_price_settings[shipping_price_setting_id][comparison]">';
									foreach ( $this->shipping_comparisons as $shipping_comparison_value => $shipping_comparison_sample_name ){
										
										echo '<option value="'.esc_html( $shipping_comparison_value ).'">'.esc_html( $shipping_comparison_sample_name ).'</option>';
					 
									}
								echo '</select>';
							echo '</td>';
		
							echo '<td>';

								echo '<div class="pactic_connect__cost_box">';
									echo '<input type="text" class="pactic_connect__shipping_value" placeholder="'.esc_html__('value', 'pactic-connect' ).'" sample_name="pactic_connect__shipping_price_settings[shipping_price_setting_id][value]" />';
									echo '<span class="pactic_connect__shipping_value_suffix" row_id="shipping_price_setting_id">';
										echo esc_html(get_woocommerce_currency_symbol());
									echo '</span>';
								echo '</div>';
												
							echo '</td>';

							echo '<td>';
								echo '<button class="button button-secondary pactic_connect__remove_shipping_prices_button" row_id="shipping_price_setting_id" type="button">';
									echo '<span class="dashicons dashicons-minus"></span> <span>'.esc_html__('Remove', 'pactic-connect' ).'</span>';
								echo '</button>';
							echo '</td>';
							
						echo '</tr>';    
											
					echo '</table>';

					echo '<table class="pactic_connect__shipping_cod_pricing_sample_table">';

						echo '<tr class="shipping_cod_price_setting_row" row_id="shipping_cod_price_setting_id">';
						
							echo '<td>';
								echo '<input type="hidden" class="" sample_name="pactic_connect__shipping_cod_price_settings[shipping_cod_price_setting_id][price_id]" value="sample_price_id" />';
								echo '<input type="checkbox" class="pactic_connect__shipping_cod_price_settings_switch" sample_name="pactic_connect__shipping_cod_price_settings[shipping_cod_price_setting_id][switch]" value="" />';
								echo '<input type="hidden" class="pactic_connect__shipping_cod_price_settings_enable" sample_name="pactic_connect__shipping_cod_price_settings[shipping_cod_price_setting_id][enable]" value="" />';
							echo '</td>';

							echo '<td>';
								
								echo esc_html__('Apply', 'pactic-connect' );
								
							echo '</td>';
						
							echo '<td>';
								echo '<div class="pactic_connect__cost_box">';
									echo '<input type="text" class="" placeholder="'.esc_html__('Shipping cost(net)', 'pactic-connect' ).'" sample_name="pactic_connect__shipping_cod_price_settings[shipping_cod_price_setting_id][cost]" value="" />';
									echo '<span>';
										echo esc_html(get_woocommerce_currency_symbol());
									echo '</span>';
								echo '</div>';
							echo '</td>';
							
							echo '<td>';
								echo esc_html__('net cost if order value equal or greater then', 'pactic-connect' );
							echo '</td>';
							
							echo '<td>';
								echo '<div class="pactic_connect__cost_box">';
									echo '<input type="text" class="" placeholder="'.esc_html__('Order value from', 'pactic-connect' ).'" sample_name="pactic_connect__shipping_cod_price_settings[shipping_cod_price_setting_id][value_from]" value="" />';
									echo '<span>';
										echo esc_html(get_woocommerce_currency_symbol());
									echo '</span>';
								echo '</div>';
							echo '</td>';
							
							echo '<td>';
								echo esc_html__('and smaller then', 'pactic-connect' );
							echo '</td>';
							
							echo '<td>';
								echo '<div class="pactic_connect__cost_box">';
									echo '<input type="text" class="" placeholder="'.esc_html__('Order value to', 'pactic-connect' ).'" sample_name="pactic_connect__shipping_cod_price_settings[shipping_cod_price_setting_id][value_to]" value="" />';
									echo '<span>';
										echo esc_html(get_woocommerce_currency_symbol());
									echo '</span>';
								echo '</div>';
							echo '</td>';

							echo '<td>';
								echo '<button class="button button-secondary pactic_connect__remove_shipping_cod_prices_button" row_id="shipping_cod_price_setting_id" type="button">';
									echo '<span class="dashicons dashicons-minus"></span> <span>'.esc_html__('Remove', 'pactic-connect' ).'</span>';
								echo '</button>';
							echo '</td>';
							
						echo '</tr>';    
											
					echo '</table>';

				echo '</div>';
				
				echo '<div class="pactic_connect__detailed_pricing_modal_bottom">';
				
					echo '<div class="pactic_connect__spinner save_shipping_prices_button__spinner"></div>';

					echo '<button class="button button-primary button-large pactic_connect__save_shipping_prices_button" type="button" instance_id="'.esc_html( sanitize_text_field(  $_REQUEST['instance_id'] ) ).'">';
						echo '<span>'.esc_html__('Save', 'pactic-connect' ).'</span>';
					echo '</button>';
					
				echo '</div>';
					
				$contents = ob_get_contents();
				
				ob_end_clean();
				
				if( $contents ){
					
					echo wp_json_encode( 
						array(
							'status' => 200,
							'contents' => $contents
						) 
					);
				
				}
				else{
					
					$this->log( 
						array(
							'type' => 'get_pricing_modal_content',
							'status' => '1000',
						) 
					);
				
					echo wp_json_encode( 
						array(
							'status' => 1000,
						) 
					);
					
				}

			}
			
			die();
		
		}		
		else {

			die( esc_html__('Security check', 'pactic-connect' ) ); 

		}
		
	}

	public function save_shipping_prices() {
		
		if ( isset( $_REQUEST['save_shipping_prices_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST['save_shipping_prices_nonce'] ) ) , 'save_shipping_prices_nonce' ) ){

			if ( isset($_REQUEST) ) {

				$detailed_pricing = json_decode( stripslashes( map_deep( wp_unslash( $_REQUEST['detailed_pricing'] ), 'sanitize_text_field' ) ), true );
				update_option( 'woocommerce_pactic_connect_'.sanitize_text_field( $_REQUEST['instance_id'] ).'_shipping_price_settings', $detailed_pricing['pactic_connect__shipping_price_settings'] );
				
				$detailed_cod_pricing = json_decode( stripslashes( map_deep( wp_unslash( $_REQUEST['detailed_cod_pricing'] ), 'sanitize_text_field' ) ), true );
				update_option( 'woocommerce_pactic_connect_'.sanitize_text_field( $_REQUEST['instance_id'] ).'_shipping_cod_price_settings', $detailed_cod_pricing['pactic_connect__shipping_cod_price_settings'] );
				
				echo wp_json_encode( 
					array(
						'status' => 200,
					) 
				);

			}
			else{
				
				$this->log( 
					array(
						'type' => 'save_shipping_prices',
						'status' => '1000',
					) 
				);
				
				echo wp_json_encode( 
					array(
						'status' => 1000,
					) 
				);
				
			}
			
			die();
			
		}		
		else {

			die( esc_html__('Security check', 'pactic-connect' ) ); 

		}
		
	}
	
	public function ajax_delete_settings() {
		
		if ( isset( $_REQUEST['delete_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST['delete_settings_nonce'] ) ) , 'delete_settings_nonce' ) ){
				
			global $wpdb;
			
			$upload_dir = wp_upload_dir();
			
			if ( isset($_REQUEST) ) {
				
				if( sanitize_text_field( $_REQUEST['delete_option'] ) == 'plugin_settings' ){
					
					// @codingStandardsIgnoreStart
					$wpdb->query( 'DELETE FROM '.$wpdb->prefix.'options WHERE option_name LIKE "pactic_connect__%" AND option_name NOT LIKE "pactic_connect__country_codes%"' );
					$wpdb->query( 'DELETE FROM '.$wpdb->prefix.'options WHERE option_name LIKE "woocommerce_pactic_connect_%_settings"' );
					$wpdb->query( 'DELETE FROM '.$wpdb->prefix.'woocommerce_shipping_zone_methods WHERE method_id LIKE "pactic_connect_%"' );
					// @codingStandardsIgnoreEnd
					
					if ( is_dir( $upload_dir['basedir'].'/pactic_connect' ) ) {
						
						$files = glob( $upload_dir['basedir'].'/pactic_connect/*' ); 
						
						foreach( $files as $file ){ 
						
							if( is_file( $file ) ) {
								
								wp_delete_file( $file ); 
							
							}
						
						}
						
						// @codingStandardsIgnoreStart
						$wpdb->query( 'DELETE FROM '.$wpdb->prefix.'options WHERE option_name LIKE "pactic_connect__cron_%_last_sync_date"' );
						$wpdb->query( 'DELETE FROM '.$wpdb->prefix.'options WHERE option_name LIKE "pactic_connect__cron_%_last_sync_status"' );
						// @codingStandardsIgnoreEnd
										
					}
					
					$this->log( 
						array(
							'type' => 'delete_settings',
							'delete_option' => sanitize_text_field( $_REQUEST['delete_option'] ),
							'status' => '200',
						) 
					);
					
					echo wp_json_encode( 
						array(
							'status' => 200,
						) 
					);
					
				}
				else if( sanitize_text_field( $_REQUEST['delete_option'] ) == 'shipping_methods' ){
					
					// @codingStandardsIgnoreStart
					$wpdb->query( 'DELETE FROM '.$wpdb->prefix.'options WHERE option_name LIKE "woocommerce_pactic_connect_%_settings"' );
					$wpdb->query( 'DELETE FROM '.$wpdb->prefix.'woocommerce_shipping_zone_methods WHERE method_id LIKE "pactic_connect_%"' );
					// @codingStandardsIgnoreEnd
					
					$this->log( 
						array(
							'type' => 'delete_settings',
							'delete_option' => sanitize_text_field( $_REQUEST['delete_option'] ),
							'status' => '200',
						) 
					);
					
					echo wp_json_encode( 
						array(
							'status' => 200,
						) 
					);
					
				}
				else if( sanitize_text_field( $_REQUEST['delete_option'] ) == 'files' ){

					if ( is_dir( $upload_dir['basedir'].'/pactic_connect' ) ) {
						
						$files = glob( $upload_dir['basedir'].'/pactic_connect/*' ); 
						
						foreach( $files as $file ){ 
						
							if( is_file( $file ) ) {
								
								wp_delete_file( $file ); 
							
							}
						
						}
						
						// @codingStandardsIgnoreStart
						$wpdb->query( 'DELETE FROM '.$wpdb->prefix.'options WHERE option_name LIKE "pactic_connect__cron_%_last_sync_date"' );
						$wpdb->query( 'DELETE FROM '.$wpdb->prefix.'options WHERE option_name LIKE "pactic_connect__cron_%_last_sync_status"' );
						// @codingStandardsIgnoreEnd
						
						$this->log( 
							array(
								'type' => 'delete_settings',
								'delete_option' => sanitize_text_field( $_REQUEST['delete_option'] ),
								'status' => '200',
							) 
						);
						
						echo wp_json_encode( 
							array(
								'status' => 200,
							) 
						);
					
					}
					else{
						
						$this->log( 
							array(
								'type' => 'delete_settings',
								'status' => '1003',
							) 
						);
				
						echo wp_json_encode( 
							array(
								'status' => 1003,
							) 
						);
					
					}
					
					
				}
				else if( sanitize_text_field( $_REQUEST['delete_option'] ) == 'log_files' ){

					if ( is_dir( $upload_dir['basedir'].'/wc-logs' ) ) {
						
						$files = glob( $upload_dir['basedir'].'/wc-logs/pactic_connect__*' ); 
						
						foreach( $files as $file ){ 
						
							if( is_file( $file ) ) {
								
								wp_delete_file( $file ); 
							
							}
						
						}
						
						echo wp_json_encode( 
							array(
								'status' => 200,
							) 
						);
					
					}
					else{
						
						$this->log( 
							array(
								'type' => 'delete_settings',
								'status' => '1002',
							) 
						);
				
						echo wp_json_encode( 
							array(
								'status' => 1002,
							) 
						);
					
					}
					
					
				}
				else{
					
					$this->log( 
						array(
							'type' => 'delete_settings',
							'status' => '1001',
						) 
					);
				
					echo wp_json_encode( 
						array(
							'status' => 1001,
						) 
					);
				
				}
				
			}
			else{
				
				$this->log( 
					array(
						'type' => 'delete_settings',
						'status' => '1000',
					) 
				);
				
				echo wp_json_encode( 
					array(
						'status' => 1000,
					) 
				);
			
			}
			
			die();
			
		}		
		else {

			die( esc_html__('Security check', 'pactic-connect' ) ); 

		}
		
	}
	
	public function available_payment_methods( $filter = '' ){
    
		$payment_methods = WC()->payment_gateways->payment_gateways();

		$available_payment_methods = array();

		foreach ( $payment_methods as $payment_method ) {
			
			if( $filter != '' && $payment_method->id == $filter ){
				
				return $payment_method->title;
				
			}
			
			if( $payment_method->id != 'cod' ){
			
				if( $payment_method->enabled == 'yes' ){
					
					$available_payment_methods[ $payment_method->id ] = $payment_method->title.' ('.esc_html__('Enabled', 'pactic-connect' ).')';
				
				}
				else{
					
					$available_payment_methods[ $payment_method->id ] = $payment_method->title.' ('.esc_html__('Disabled', 'pactic-connect' ).')';
				
				}

			}
		
		}
		
		return $available_payment_methods;
	  
	}
	
	public function country_codes_sync_description( $data ){
		
		$pactic_connect__country_codes_sync_date = get_option( 'pactic_connect__country_codes_sync_date', true );
		$pactic_connect__country_codes_sync_status = get_option( 'pactic_connect__country_codes_sync_status', true );
		
		if( $pactic_connect__country_codes_sync_date || $pactic_connect__country_codes_sync_status ){
	
			echo '<tr valign="top">';
				
				echo '<th scope="row" class="titledesc">'.esc_html( $data['title'] ).'</th>';
				
				echo '<td class="forminp">';
					
					if( $pactic_connect__country_codes_sync_date ){	
					
						echo esc_html__( 'Last automatic sync date', 'pactic-connect' ).': '.esc_html( $pactic_connect__country_codes_sync_date );	
						echo '<br/>';
										
					}
					
					if( $pactic_connect__country_codes_sync_status ){	
					
						echo esc_html__( 'Last sync status', 'pactic-connect' ).':';	
						
						if( $pactic_connect__country_codes_sync_status == 'failed' ){
							echo esc_html__('Failed', 'pactic-connect' );
						}
						else if( $pactic_connect__country_codes_sync_status == 'success' ){
							echo esc_html__('Success', 'pactic-connect' );
						}
					
					}

				echo '</td>';
				
			echo '</tr>';
			
		}		

	}
	
	public function parcel_point_sync_description( $data ){
		
		$pactic_connect__save_parcel_points_sync_date = get_option( 'pactic_connect__save_parcel_points_sync_date', true );
		
		if( $pactic_connect__save_parcel_points_sync_date ){

			echo '<tr valign="top">';
				
				echo '<th scope="row" class="titledesc">'.esc_html( $data['title'] ).'</th>';
				
				echo '<td class="forminp">';
									
					echo esc_html__( 'Last automatic sync date', 'pactic-connect' ).': '.esc_html( $pactic_connect__save_parcel_points_sync_date );	

				echo '</td>';
				
			echo '</tr>';
		
		}
		
	}
	
	public function check_parcel_points(){
		
		global $wpdb;
		
		$upload_dir = wp_upload_dir();
		
		// @codingStandardsIgnoreStart
		$active_shipping_zone_methods = $wpdb->get_results( 'SELECT * FROM '.$wpdb->prefix.'woocommerce_shipping_zone_methods WHERE method_id LIKE "pactic_connect_parcel_point" AND is_enabled="1" ' );
		// @codingStandardsIgnoreEnd
		
		if( $active_shipping_zone_methods ){

			foreach( $active_shipping_zone_methods as $active_shipping_zone_method ){

				$parcel_point_instance_settings = get_option('woocommerce_pactic_connect_parcel_point_'.$active_shipping_zone_method->instance_id.'_settings' );
				
				$parcel_point_settings = get_option( 'pactic_connect__parcel_point_settings', false );
				
				if( $parcel_point_settings ){
					
					foreach( $parcel_point_settings as $parcel_point_setting ){
						
						if( $parcel_point_setting['service_id'] == $parcel_point_instance_settings['service'] ){

							if( file_exists( $upload_dir['basedir'].'/pactic_connect/'.$parcel_point_setting['cdCountry'].'_'.$parcel_point_setting['idCarrier'].'.json' ) ){
                
								$service_data = wp_remote_get( $upload_dir['baseurl'].'/pactic_connect/'.$parcel_point_setting['cdCountry'].'_'.$parcel_point_setting['idCarrier'].'.json' );
							
								$service_data = wp_remote_retrieve_body( $service_data );
		
								$service_data = json_decode( $service_data, true);
								
								if( array_key_exists( 0, $service_data ) ){
									
									if( array_key_exists( 'ParcelPoints', $service_data[0] ) ){
										
										$parcel_points = $service_data[0]['ParcelPoints'];
										
										if( !$parcel_points ){
											
											// @codingStandardsIgnoreStart
											$wpdb->query(
												$wpdb->prepare(
													'UPDATE '.$wpdb->prefix.'woocommerce_shipping_zone_methods SET is_enabled="0" WHERE method_id LIKE %s AND instance_id=%d ',
													"pactic_connect_parcel_point",
													$active_shipping_zone_method->instance_id
												)
											);
											// @codingStandardsIgnoreEnd
										
										}
										
									}
									
								}
								
							}
							else{
								
								// @codingStandardsIgnoreStart
								$wpdb->query(
									$wpdb->prepare(
										'UPDATE '.$wpdb->prefix.'woocommerce_shipping_zone_methods SET is_enabled="0" WHERE method_id LIKE %s AND instance_id=%d ',
										"pactic_connect_parcel_point",
										$active_shipping_zone_method->instance_id
									)
								);
								// @codingStandardsIgnoreEnd
											
							}	

						}
						
					}
					
				}
				
			}
			
		}
		
	}

	public function get_parcel_point_settings(){
		
		global $wpdb;
		
		$data = array();
		
		// @codingStandardsIgnoreStart
		$parcel_point_names = $wpdb->get_results( 'SELECT option_name FROM '.$wpdb->prefix.'options WHERE option_name LIKE "woocommerce_pactic_connect_parcel_point_%_settings" ' );
		// @codingStandardsIgnoreEnd
		
		if( $parcel_point_names ){

			foreach( $parcel_point_names as $parcel_point_name ){
	
				$name_1 = str_replace( "woocommerce_", "", $parcel_point_name->option_name );
				$name_1 = str_replace( "_settings", "", $name_1 );
				
				$name_2 = preg_replace( '/(_(?!.*_))/', ':', $name_1 );

				// @codingStandardsIgnoreStart
				$parcel_point_setting = $wpdb->get_row(
					$wpdb->prepare(
						'SELECT * FROM '.$wpdb->prefix.'options WHERE option_name=%s',
						$parcel_point_name->option_name
					)
				);
				// @codingStandardsIgnoreEnd

				$setting = unserialize( $parcel_point_setting->option_value );
				
				$parcel_point_settings = get_option( 'pactic_connect__parcel_point_settings', false );
				
				$cdCountry = '';
				$idCarrier = '';
				$idService = '';
				
				if( $parcel_point_settings ){
					
					foreach( $parcel_point_settings as $parcel_point_setting ){
						
						if( $parcel_point_setting['service_id'] == $setting['service'] ){

							list( $name, $id ) = explode( ':', $name_2 );
		
							$data[ $name_2 ]['name'] = $name;
							$data[ $name_2 ]['id'] = $id;
							$data[ $name_2 ]['service_id'] = $parcel_point_setting['service_id'];
							$data[ $name_2 ]['method_id'] = $name_2;
						
							$data[ $name_2 ]['cdCountry'] = $parcel_point_setting['cdCountry'];
							$data[ $name_2 ]['idCarrier'] = $parcel_point_setting['idCarrier'];
							$data[ $name_2 ]['idService'] = $parcel_point_setting['idService'];
							
						}
						
					}
					
				}
				
			
			}
			
		}

		return $data;
		
	}
	
	public function add_cod_fee( $cart ) {

		global $woocommerce;
		
		if ( is_admin() && ! defined( 'DOING_AJAX' ) )
			return;
		
		$cod_cost = 0;
		
		$fees = $cart->get_fees();
		
		foreach ($fees as $key => $fee) {
			
			if( $fees[$key]->name === __( 'Cash on delivery fee', 'pactic-connect' ) ) {
				
				unset($fees[$key]);
			
			}
			
		} 
		
		$cart->fees_api()->set_fees($fees);
		
		$chosen_methods = WC()->session->chosen_shipping_methods;

		$is_pactic_connect_selected = false;
		
		$type = '';
		
		foreach ( $chosen_methods as $method ) {
			
			if( strpos( $method, 'pactic_connect_parcel_point' ) !== false ) {
			
				$is_pactic_connect_selected = true;
				
				$type = 'pactic_connect_parcel_point';
			
			}
			else if( strpos( $method, 'pactic_connect_home_delivery' ) !== false ) {
			
				$is_pactic_connect_selected = true;
				
				$type = 'pactic_connect_home_delivery';
			
			}
			
		}

		if( $is_pactic_connect_selected ){ 
			
			list( $name, $instance_id ) = explode( ':', $chosen_methods[0] );
			
			$instance_settings = get_option('woocommerce_'.$type.'_'.$instance_id.'_settings');
			
			$cart_subtotal = $cart->subtotal;
			
			$payment_method = WC()->session->get('chosen_payment_method');
			$block_payment_method  = WC()->session->get( 'block_payment_method' );

			if ( ( $payment_method == 'cod' || in_array( $payment_method, get_option( 'pactic_connect__cod_payment_methods' ) ) ) || ( $block_payment_method == 'cod' || in_array( $block_payment_method, get_option( 'pactic_connect__cod_payment_methods' ) ) ) ) {	

				$saved_pactic_connect__shipping_cod_price_settings = get_option('woocommerce_pactic_connect_'.$instance_id.'_shipping_cod_price_settings');

				if( !empty( $saved_pactic_connect__shipping_cod_price_settings ) ){
					
					$is_shipping_cod_price_settings = true;
											
					foreach ( $saved_pactic_connect__shipping_cod_price_settings as $shipping_cod_price_setting_id => $shipping_cod_price_setting ){
						
						if( $shipping_cod_price_setting['enable'] == 'yes' ){
													
							if( $cart_subtotal >= $shipping_cod_price_setting['value_from'] &&  $cart_subtotal < $shipping_cod_price_setting['value_to'] ){
								
								$cod_cost = $shipping_cod_price_setting['cost'];
								
							}
							
						}
						
					} 
					
				}
										
			}		

		}
		
		if( $cod_cost ){
					
			if( $instance_settings['tax_status'] == 'taxable' ){
			
				$cart->add_fee( __( 'Cash on delivery fee', 'pactic-connect' ), $cod_cost, true );

			}
			else {
				
				$cart->add_fee( __( 'Cash on delivery fee', 'pactic-connect' ), $cod_cost, false );

			}

		}
		else{

			$fees = $cart->get_fees();

			foreach ($fees as $key => $fee) {
				
				if( $fees[$key]->name === __( 'Cash on delivery fee', 'pactic-connect' ) ) {
					
					unset($fees[$key]);
				
				}
				
			} 
			
			$cart->fees_api()->set_fees($fees);
			
		}
		
	}
		
	public function update_cart( $data ) {
		
		if ( isset( $data['shipping_method'] ) ) {
			WC()->session->set( 'shipping_method', $data['shipping_method'] );
		}
		
		if ( isset( $data['block_checkout'] ) ) {
			WC()->session->set( 'block_checkout', $data['block_checkout'] );
		}
		else{
			WC()->session->set( 'block_checkout', false );
		}	
		
		if ( isset( $data['payment_method'] ) ) {
			WC()->session->set( 'payment_method', $data['payment_method'] );
			WC()->session->set( 'block_payment_method', $data['payment_method'] );
		}
		
		WC()->cart->calculate_totals();
		
	}
	
}

?>