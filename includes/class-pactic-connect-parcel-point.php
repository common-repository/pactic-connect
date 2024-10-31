<?php

class Pactic_Connect_Parcel_Point extends WC_Shipping_Method {
	
	private $shipping_cost = 0;
	
	public function __construct( $instance_id = 0 ) {
		  
		$this->id = 'pactic_connect_parcel_point';
		$this->instance_id = absint( $instance_id );
		$this->method_title = __( 'Pactic parcel point', 'pactic-connect' );
		$this->method_description = __( 'Pactic parcel point', 'pactic-connect' );
		$this->enabled = "yes";

		$this->supports = array(
			'shipping-zones',
			'instance-settings', 
			'instance-settings-modal',
		);
	 
		$this->init();
		
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'save_custom_options' ) );
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

	}  

	function init() { 

		$this->init_form_fields();
		$this->init_settings();

		$this->title = $this->get_option( 'title' );
		
	}
	 
	public function init_form_fields() {
		
		$this->instance_form_fields = array(
			'title' => array(
				'title'       => __( 'Title', 'pactic-connect' ),
				'type'        => 'text',
				'description' => __( 'The name customers see at the checkout.', 'pactic-connect' ),
				'default'     => __( 'Pactic parcel point', 'pactic-connect' ),
				'desc_tip'    => true,
			),
			
			'tax_status' => array(
				'title'   => __( 'Tax status', 'pactic-connect' ),
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'default' => 'taxable',
				'options' => array(
					'taxable' => __( 'Taxable', 'pactic-connect' ),
					'none'    => _x( 'Not taxable', 'Tax status', 'pactic-connect' ),
				),
			),

			'cost' => array(
				'title'       => __( 'Default shipping cost', 'pactic-connect' ),
				'type'        => 'number',
				'description' => __( 'Shipping cost', 'pactic-connect' ),
				'desc_tip'    => true,
				'default' => 0,
			), 
			
			'service' => array(
				'title'   => __( 'Service', 'pactic-connect' ),
				'type'    => 'select',
				'default' => 'all',
				'class'   => 'szolgaltato wc-enhanced-select',
				'options'     => $this->get_parcel_point_services(),
			),

			'detailed_pricing' => array(
				'title' => __( 'Detailed pricing', 'pactic-connect' ).'<br/>',
				'type' => 'button',
				'class'   => 'button button-primary button-large pactic_connect__detailed_pricing_modal_open ',
				'custom_attributes'  => array('instance_id' => $this->instance_id, 'method_name' => 'parcel_point' ),
				'default'     => __( 'Open detailed pricing settings', 'pactic-connect' ),
			),

		);
		
	}

	public function get_parcel_point_services(){
		
		$parcel_point_settings = get_option('pactic_connect__parcel_point_settings');

		$parcel_point_services = array();
		
		if( !empty( $parcel_point_settings ) ){
		
			foreach ( $parcel_point_settings as $parcel_point_setting_id => $parcel_point_setting ){
				
				if( 'yes' == $parcel_point_setting['enable'] ){
					
					$parcel_point_services[ $parcel_point_setting['service_id']  ] = $parcel_point_setting['name'];
					
				}
				
			}
			
		}
		
		return $parcel_point_services;
	  
	}
	
	public function calculate_shipping( $package = array() ) {
		
		$cost = $this->get_cost( $this->instance_id );

		$rate = array(
			'label'   => $this->title,
			'cost'    => $cost,
			'package' => $package,
			'taxes'          => '',
			'calc_tax'       => 'per_order',
		);

		$this->add_rate( $rate );

	}
	
	public function admin_options() {
		
		if ( ! $this->instance_id ) { 
			echo '<h3>';
				echo esc_html( $this->get_method_title() );
			echo '</h3>';
		}
		
		echo'<p>';
			echo esc_html( $this->get_method_description() );
		echo'</p>';

		echo esc_html( $this->get_admin_options_html() );
		
	} 

	public function get_cost( $instance_id = 0 ) {
		
		$instance_settings = get_option('woocommerce_pactic_connect_parcel_point_'.$instance_id.'_settings');
		
		$cost = 0;
		
		if( $instance_settings ){

			$cart_subtotal = WC()->cart->subtotal;

			$cart_weight = WC()->cart->get_cart_contents_weight();

			$shipping_cost = intval( $instance_settings['cost'] );
			$shipping_order_value_costs = array();
			$shipping_order_weight_costs = array();
			
			$is_shipping_price_settings = false;
			$is_shipping_cod_price_settings = false;
			
			$saved_pactic_connect__shipping_price_settings = get_option('woocommerce_pactic_connect_'.$instance_id.'_shipping_price_settings');
			
			if( !empty( $saved_pactic_connect__shipping_price_settings ) ){
				
				$is_shipping_price_settings = true;
				
				$pricing_logic = $saved_pactic_connect__shipping_price_settings['pricing_logic'];
				unset( $saved_pactic_connect__shipping_price_settings['pricing_logic'] );
										
				foreach ( $saved_pactic_connect__shipping_price_settings as $shipping_price_setting_id => $shipping_price_setting ){
										
					if( $shipping_price_setting['enable'] == 'yes' ){
						
						if( $shipping_price_setting['condition'] == 'order_value' ){
							
							if( $shipping_price_setting['comparison'] == 'smaller' ){
								
								if( $cart_subtotal < $shipping_price_setting['value'] ){
									
									$shipping_order_value_costs[ $shipping_price_setting['cost'] ] = $shipping_price_setting['cost'];
									
								}
								
							}
							else if( $shipping_price_setting['comparison'] == 'greater' ){
								
								if( $cart_subtotal > $shipping_price_setting['value'] ){
									
									$shipping_order_value_costs[ $shipping_price_setting['cost'] ] = $shipping_price_setting['cost'];
									
								}
								
							}
							else if( $shipping_price_setting['comparison'] == 'equal_or_smaller' ){
								
								if( $cart_subtotal <= $shipping_price_setting['value'] ){
									
									$shipping_order_value_costs[ $shipping_price_setting['cost'] ] = $shipping_price_setting['cost'];
									
								}
								
							}
							else if( $shipping_price_setting['comparison'] == 'equal_or_greater' ){
								
								if( $cart_subtotal >= $shipping_price_setting['value'] ){
									
									$shipping_order_value_costs[ $shipping_price_setting['cost'] ] = $shipping_price_setting['cost'];
									
								}
								
							}
							
						}
						else if( $shipping_price_setting['condition'] == 'order_weight' ){
							
							if( $shipping_price_setting['comparison'] == 'smaller' ){
								
								if( $cart_weight < $shipping_price_setting['value'] ){
									
									$shipping_order_weight_costs[ $shipping_price_setting['cost'] ] = $shipping_price_setting['cost'];
									
								}
								
							}
							else if( $shipping_price_setting['comparison'] == 'greater' ){
								
								if( $cart_weight > $shipping_price_setting['value'] ){
									
									$shipping_order_weight_costs[ $shipping_price_setting['cost'] ] = $shipping_price_setting['cost'];
									
								}
								
							}
							else if( $shipping_price_setting['comparison'] == 'equal_or_smaller' ){
								
								if( $cart_weight <= $shipping_price_setting['value'] ){
									
									$shipping_order_weight_costs[ $shipping_price_setting['cost'] ] = $shipping_price_setting['cost'];
									
								}
								
							}
							else if( $shipping_price_setting['comparison'] == 'equal_or_greater' ){
								
								if( $cart_weight >= $shipping_price_setting['value'] ){
									
									$shipping_order_weight_costs[ $shipping_price_setting['cost'] ] = $shipping_price_setting['cost'];
									
								}
								
							}
							
						}
						
					}
					
				} 
				
			}
			else{
				
				$is_shipping_price_settings = false;
				
			}
			
			if( $is_shipping_price_settings == false && $is_shipping_cod_price_settings == false ){
				
				$cost = $shipping_cost;
				
			}
			else{
					
				if( $shipping_order_value_costs && $shipping_order_weight_costs ){
					
					if( $pricing_logic == 'cheapest' ){
							
						$cost = min( array_merge( $shipping_order_value_costs, $shipping_order_weight_costs ) );
					
					}
					else if( $pricing_logic == 'expensive' ){
						
						$cost = max( array_merge( $shipping_order_value_costs, $shipping_order_weight_costs ) );
						
					}
					
				}
				else if( !$shipping_order_value_costs && $shipping_order_weight_costs ){
					
					if( $pricing_logic == 'cheapest' ){
							
						$cost = min( $shipping_order_weight_costs );
					
					}
					else if( $pricing_logic == 'expensive' ){
						
						$cost = max( $shipping_order_weight_costs );
						
					}
					
				}
				else if( $shipping_order_value_costs && !$shipping_order_weight_costs ){
					
					if( $pricing_logic == 'cheapest' ){
							
						$cost = min( $shipping_order_value_costs );
					
					}
					else if( $pricing_logic == 'expensive' ){
						
						$cost = max( $shipping_order_value_costs );
						
					}
					
				}
				else if( !$shipping_order_value_costs && !$shipping_order_weight_costs ){

					$cost = $shipping_cost;
					
				}

			}
		
		}
		
		return $cost;
		
	}
	
}

?>