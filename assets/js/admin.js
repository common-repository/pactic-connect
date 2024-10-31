const { __, _x, _n, _nx } = wp.i18n;

jQuery(document).ready(function($) {
	
	if( jQuery( '#pactic_connect__status' ).length > 0 ){

		pactic_connect__settings.init();

    }
	
	if( jQuery('.pactic_connect__detailed_pricing_modal').length > 0 ) { 

		pactic_connect__detailed_pricing.init();

	}

}); 

var pactic_connect__settings = {
	
	init: function() {

		jQuery( 'body' ).on('click', '.pactic_connect__remove_parcel_point_button', this.remove_parcel_point );
		jQuery( 'body' ).on('click', '.pactic_connect__add_parcel_point_button', this.add_parcel_point );
		jQuery( 'body' ).on('click', '.pactic_connect__parcel_point_settings_switch', this.parcel_point_switch );
		jQuery( 'body' ).on('click', '.pactic_connect__sync_button', this.parcel_point_sync );
		jQuery( 'body' ).on('click', '.pactic_connect__remove_home_delivery_button', this.remove_home_delivery );
		jQuery( 'body' ).on('click', '.pactic_connect__add_home_delivery_button', this.add_home_delivery );
		jQuery( 'body' ).on('click', '.pactic_connect__home_delivery_settings_switch', this.home_delivery_switch );
		jQuery( 'body' ).on('change', 'input[name="pactic_connect__parcel_point_display_type"]', this.toggle_google_map_api_key );
		jQuery( 'body' ).on('click', '.pactic_connect__deletion_button', this.delete_settings );
		
		pactic_connect__settings.renumbering_parcel_point_fields();
		pactic_connect__settings.renumbering_home_delivery_fields();
        pactic_connect__settings.toggle_google_map_api_key();
		
	},
	
	delete_settings: function(){
		
		var delete_button = jQuery( this );
		
		var delete_option = delete_button.attr( 'delete_option' );
		
		jQuery( '.pactic_connect__deletion_button' ).prop( "disabled", true );
		
		delete_button.addClass( 'pactic_connect__deletion_in_progress' );
		
		delete_button.find( '.dashicons' ).removeClass( 'dashicons-trash' ).addClass( 'dashicons-update-alt' );

		jQuery.ajax({
			type: 'POST',
			url: pactic_connect.ajax_url,
			async: true,
			dataType: 'json',
			data: {
				'action' : 'pactic_connect__delete_settings',
				'delete_option': delete_option,
				'delete_settings_nonce': pactic_connect.delete_settings_nonce
			},
			success:function( response ) { 

				if( response.status == '200' ){

					pactic_connect__settings.toast_message( 'success', __( 'Deletion is successful!', 'pactic-connect' ), 3000 ); 
					
					delete_button.removeClass( 'pactic_connect__deletion_in_progress' );
						
					jQuery( '.pactic_connect__deletion_button' ).prop( "disabled", false );
					
					delete_button.find( '.dashicons' ).removeClass( 'dashicons-update-alt' ).addClass( 'dashicons-trash' );
					
					if( delete_option == 'plugin_settings' || delete_option == 'files' ){
						
						pactic_connect__settings.toast_message( 'info', __( 'The page will reload!', 'pactic-connect' ), 3000 ); 

						setTimeout(
							function() {
								location.reload();
							}
						, 3000);
						
					}

				}
				else{

					pactic_connect__settings.toast_message( 'error', __( 'Delete was unsuccessful!', 'pactic-connect' ) + ' ' +__( 'Error code:', 'pactic-connect' ) + ' ' + response.status ); 
					
					delete_button.removeClass( 'pactic_connect__deletion_in_progress' );
							
					jQuery( '.pactic_connect__deletion_button' ).prop( "disabled", false );
					
				}

			} 
			
		});

	},
	
	toggle_google_map_api_key: function(){
		
		var display_type = jQuery('input[name="pactic_connect__parcel_point_display_type"]:checked').val();

		if( display_type == 'map' ){
			
			jQuery('#pactic_connect__google_map_api_key').parent('td').parent('tr').show();
			jQuery('#pactic_connect__google_map_api_key').prop('required',true);
		
		}
		else if( display_type == 'dropdown' ){
			
			jQuery('#pactic_connect__google_map_api_key').parent('td').parent('tr').hide();
			jQuery('#pactic_connect__google_map_api_key').prop('required',false);
			
		}
		
	},

	parcel_point_sync: function(){
		
		if ( !jQuery( this ).hasClass( "pactic_connect__sync_in_progress" ) ) {
			
			jQuery('.pactic_connect__sync_button').prop( "disabled", true );
			
			var pactic_connect__sync_button = jQuery( this );
			
			pactic_connect__sync_button.addClass( 'pactic_connect__sync_in_progress' );
			
			var cdCountry = jQuery(this).attr( 'cdCountry' );
			var idCarrier = jQuery(this).attr( 'idCarrier' );
			var idService = jQuery(this).attr( 'idService' );

			if( cdCountry && idCarrier && idService ){
				
				jQuery.ajax({
					type: 'POST',
					url: pactic_connect.ajax_url,
					async: true,
					dataType: 'json',
					data: {
						'action' : 'pactic_connect__save_parcel_point',
						'cdCountry': cdCountry,
						'idCarrier': idCarrier,
						'idService': idService,
						'save_parcel_point_nonce': pactic_connect.save_parcel_point_nonce,

					},
					success:function( response ) { 

						if( response.status == '200' ){

							pactic_connect__settings.toast_message( 'success', __( 'Successful sync!', 'pactic-connect' ), 3000 ); 
							pactic_connect__settings.toast_message( 'info', __( 'The page will reload!', 'pactic-connect' ), 3000 ); 
							
							pactic_connect__sync_button.removeClass( 'pactic_connect__sync_in_progress' );
							
							setTimeout(
								function() {
									location.reload();
								}
							, 3000);

						}
						else{

							pactic_connect__settings.toast_message( 'error', __( 'Synchronization failed!', 'pactic-connect' ) + ' ' +__( 'Error code:', 'pactic-connect' ) + ' ' + response.status ); 
							
							pactic_connect__sync_button.removeClass( 'pactic_connect__sync_in_progress' );
							
							jQuery('.pactic_connect__sync_button').prop( "disabled", false );
							
						}

					} 
					
				});
			
			}
			else{

				pactic_connect__settings.toast_message( 'error', __( 'Please fill in all data!', 'pactic-connect' ) ); 

			}
			
		}

    },
	
	parcel_point_switch: function(){
        
		if(	jQuery( this ).prop('checked') ) {
			
			jQuery( this ).next( '.pactic_connect__parcel_point_settings_enable' ).val( 'yes' );
		
		} 
		else {
			
			jQuery( this ).next( '.pactic_connect__parcel_point_settings_enable' ).val( 'no' );
		
		}
        
    },
	
	remove_parcel_point: function(){
        
        var row_id = jQuery( this ).attr( 'row_id' );
        
        jQuery( '.pactic_connect__parcel_point_list_table tr[row_id="' + row_id + '"]' ).remove();
        
        pactic_connect__settings.renumbering_parcel_point_fields();
        
    },
	
	add_parcel_point: function(){
        
        var parcel_point_setting_row_sample_html = jQuery( '.pactic_connect__parcel_point_sample_list_table' ).html();
        
        parcel_point_setting_row_sample_html = parcel_point_setting_row_sample_html.replace('<tbody>', '' );
        parcel_point_setting_row_sample_html = parcel_point_setting_row_sample_html.replace('</tbody>', '' );
        parcel_point_setting_row_sample_html = parcel_point_setting_row_sample_html.replace(/sample_name/g, 'name' );
		
		var random_service_id = Math.floor( Math.random() * Date.now() * 2 );
		
		parcel_point_setting_row_sample_html = parcel_point_setting_row_sample_html.replace(/sample_service_id/g, random_service_id );
         
        jQuery( '.pactic_connect__parcel_point_list_table tr:last').after( parcel_point_setting_row_sample_html );
        
        pactic_connect__settings.renumbering_parcel_point_fields();
        
    },
	
	renumbering_parcel_point_fields: function() {
		
        jQuery( '.pactic_connect__parcel_point_list_table tr.parcel_point_setting_row').each(function( row_id ){
            
            var random = Math.floor( Math.random() * Date.now() );
            
            jQuery(this).attr( 'row_id', random );

    		jQuery( this ).find('select, input').each(function(){
    			
                var name = jQuery(this).attr('name');

    			name = name.replace('parcel_point_setting_id', random );
    			
                jQuery(this).attr( 'name', name );
    		
            });
            
            jQuery( this ).find('button').each(function(){
                jQuery(this).attr( 'row_id', random );
    		
            });
        
        });
        
    },
	
	remove_home_delivery: function(){
        
        var row_id = jQuery( this ).attr( 'row_id' );
        
        jQuery( '.pactic_connect__home_delivery_list_table tr[row_id="' + row_id + '"]' ).remove();
        
        pactic_connect__settings.renumbering_home_delivery_fields();
        
    },
	
	add_home_delivery: function(){
        
        var home_delivery_setting_row_sample_html = jQuery( '.pactic_connect__home_delivery_sample_list_table' ).html();
        
        home_delivery_setting_row_sample_html = home_delivery_setting_row_sample_html.replace('<tbody>', '' );
        home_delivery_setting_row_sample_html = home_delivery_setting_row_sample_html.replace('</tbody>', '' );
        home_delivery_setting_row_sample_html = home_delivery_setting_row_sample_html.replace(/sample_name/g, 'name' );
		
		var random_service_id = Math.floor( Math.random() * Date.now() * 2 );
		
		home_delivery_setting_row_sample_html = home_delivery_setting_row_sample_html.replace(/sample_service_id/g, random_service_id );

        jQuery( '.pactic_connect__home_delivery_list_table tr:last').after( home_delivery_setting_row_sample_html );
        
        pactic_connect__settings.renumbering_home_delivery_fields();
        
    },
	
	renumbering_home_delivery_fields: function(){
		
        jQuery( '.pactic_connect__home_delivery_list_table tr.home_delivery_setting_row').each(function( row_id ){
            
            var random = Math.floor( Math.random() * Date.now() );
            
            jQuery(this).attr( 'row_id', random );

    		jQuery( this ).find('select, input').each(function(){
    			
                var name = jQuery(this).attr('name');

    			name = name.replace('home_delivery_setting_id', random );
    			
                jQuery(this).attr( 'name', name );
    		
            });
            
            jQuery( this ).find('button').each(function(){
                jQuery(this).attr( 'row_id', random );
    		
            });
        
        });
        
    },
	
	home_delivery_switch: function(){
        
		if(	jQuery( this ).prop('checked') ) {
			
			jQuery( this ).next( '.pactic_connect__home_delivery_settings_enable' ).val( 'yes' );
		
		} 
		else {
			
			jQuery( this ).next( '.pactic_connect__home_delivery_settings_enable' ).val( 'no' );
		
		}
        
    },
	
	toast_message: function( type, message, time = 4000 ){

		var toast = jQuery.toast({
			icon: type,
			text: message,
			position: 'mid-center',
			hideAfter: time,
			showHideTransition: 'fade',
			allowToastClose: true,
			stack: 20,
			loader: false, 
		});
		
		return toast;
		
	}
	
}

var pactic_connect__detailed_pricing = {
	
	init: function() {
		
		jQuery( 'body' ).on('click', '.pactic_connect__detailed_pricing_modal_open', this.modal_open );
		jQuery( 'body' ).on('click', '.pactic_connect__detailed_pricing_modal_close', this.modal_close );
		jQuery( 'body' ).on('change', '.pactic_connect__shipping_price_condition', this.price_condition_change );
		jQuery( 'body' ).on('click', '.pactic_connect__remove_shipping_prices_button', this.remove_shipping_prices );
		jQuery( 'body' ).on('click', '.pactic_connect__add_shipping_prices_button', this.add_shipping_prices );
		jQuery( 'body' ).on('click', '.pactic_connect__shipping_price_settings_switch', this.shipping_price_switch );
		jQuery( 'body' ).on('click', '.pactic_connect__save_shipping_prices_button', this.save_prices );
		jQuery( 'body' ).on('change', '.pactic_connect__shipping_cod_price_condition', this.cod_price_condition_change );
		jQuery( 'body' ).on('click', '.pactic_connect__remove_shipping_cod_prices_button', this.remove_shipping_cod_prices );
		jQuery( 'body' ).on('click', '.pactic_connect__add_shipping_cod_prices_button', this.add_shipping_cod_prices );
		jQuery( 'body' ).on('click', '.pactic_connect__shipping_cod_price_settings_switch', this.shipping_cod_price_switch );
		jQuery( 'body' ).arrive( '#wc-backbone-modal-dialog', this.detailed_pricing_button_text );
				
		if( jQuery( '.pactic_connect__shipping_pricing_table tr.shipping_price_setting_row' ).length > 0 ){
    
			pactic_connect__detailed_pricing.renumbering_shipping_prices_fields();
			
		}
		
		if( jQuery( '.pactic_connect__shipping_cod_pricing_table tr.shipping_cod_price_setting_row' ).length > 0 ){
		
			pactic_connect__detailed_pricing.renumbering_shipping_cod_prices_fields();
		}
		

	},
	
	shipping_cod_price_switch: function(){
		
		if(	jQuery( this ).prop('checked') ) {
			
			jQuery( this ).next( '.pactic_connect__shipping_cod_price_settings_enable' ).val( 'yes' );
		
		} else {
			
			jQuery( this ).next( '.pactic_connect__shipping_cod_price_settings_enable' ).val( 'no' );
		
		}
		
	},
	
	add_shipping_cod_prices: function(){
		
		var shipping_cod_price_setting_row_sample_html = jQuery( '.pactic_connect__shipping_cod_pricing_sample_table' ).html();
		
		shipping_cod_price_setting_row_sample_html = shipping_cod_price_setting_row_sample_html.replace('<tbody>', '' );
		shipping_cod_price_setting_row_sample_html = shipping_cod_price_setting_row_sample_html.replace('</tbody>', '' );
		shipping_cod_price_setting_row_sample_html = shipping_cod_price_setting_row_sample_html.replace(/sample_name/g, 'name' );
		
		var random_shipping_cod_prices_id = Math.floor( Math.random() * Date.now() * 2 );
		
		shipping_cod_price_setting_row_sample_html = shipping_cod_price_setting_row_sample_html.replace(/sample_price_id/g, random_shipping_cod_prices_id );
		
		jQuery( '.pactic_connect__shipping_cod_pricing_table tbody').append( shipping_cod_price_setting_row_sample_html );
		
		pactic_connect__detailed_pricing.renumbering_shipping_cod_prices_fields();
		
	},
	
	remove_shipping_cod_prices: function(){
		
		var row_id = jQuery( this ).attr( 'row_id' );
		

		jQuery( '.pactic_connect__shipping_cod_pricing_table tr[row_id="' + row_id + '"]' ).remove();
		
		pactic_connect__detailed_pricing.renumbering_shipping_cod_prices_fields();
		
	},
	
	cod_price_condition_change: function(){
	
		var selected_condition = jQuery( this ).val();
		
		var row_id = jQuery( this ).attr( 'row_id' );

		if( selected_condition == 'order_value' ){
			
			new_text = pactic_connect.currency_symbol;
			new_placeholder = __( 'value', 'pactic-connect' );
			
		}
		else if( selected_condition == 'order_weight' ){
			new_text = __( 'kg', 'pactic-connect' );
			new_placeholder = __( 'weight', 'pactic-connect' );
		}

		jQuery( '.pactic_connect__shipping_cod_pricing_table tr[row_id="' + row_id + '"] .pactic_connect__shipping_value_suffix' ).text( new_text );
		jQuery( '.pactic_connect__shipping_cod_pricing_table tr[row_id="' + row_id + '"] .pactic_connect__shipping_value' ).attr( 'placeholder', new_placeholder );

	},
	
	renumbering_shipping_cod_prices_fields: function(){
		
		var counter = 0;

		jQuery( '.pactic_connect__shipping_cod_pricing_table tr.shipping_cod_price_setting_row').each(function( row_id ){
			
			var random = Math.floor( Math.random() * Date.now() );
			
			jQuery(this).attr( 'row_id', random );

			jQuery( this ).find('select, input').each(function(){
				
				var name = jQuery(this).attr('name');

				name = name.replace('shipping_cod_price_setting_id', random );
				
				jQuery(this).attr( 'name', name );
			
			});
			 
			jQuery( this ).find('button').each(function(){
				jQuery(this).attr( 'row_id', random );
			
			});
			
			jQuery( this ).find('.pactic_connect__shipping_cod_price_condition').each(function(){
				jQuery(this).attr( 'row_id', random );
			
			});
			
			jQuery( this ).find('.pactic_connect__shipping_value_suffix').each(function(){
				jQuery(this).attr( 'row_id', random );
			
			});
			
			counter++;
		
		});
		
		if( counter ){
			jQuery( '.shipping_cod_price_empty_setting_row' ).removeClass( 'show' );	
		}
		else{
			jQuery( '.shipping_cod_price_empty_setting_row' ).addClass( 'show' );
		}
		
	},
	
	save_prices: function(){

		var save_shipping_prices_button = jQuery( this );
		var instance_id = jQuery( this ).attr( 'instance_id' );
		
		save_shipping_prices_button.prev( '.save_shipping_prices_button__spinner' ).addClass( 'show' );
		save_shipping_prices_button.prop( "disabled", true );
		
		jQuery.ajax({
			url: pactic_connect.ajax_url,
			async: true,
			dataType: 'json', 
			data: {
				'action':'pactic_connect__save_shipping_prices',
				'instance_id' : instance_id, 
				'detailed_pricing': JSON.stringify( jQuery( '.pactic_connect__detailed_pricing_form[instance_id="' + instance_id + '"]' ).serializeJSON()  ),
				'detailed_cod_pricing': JSON.stringify( jQuery( '.pactic_connect__detailed_cod_pricing_form[instance_id="' + instance_id + '"]' ).serializeJSON()  ),
				'save_shipping_prices_nonce': pactic_connect.save_shipping_prices_nonce
			},
			success:function( response ) {
				
				if( response.status == '200' ){
					
					pactic_connect__detailed_pricing.toast_message( 'success', __( 'Successful saving!', 'pactic-connect' ) ); 
					
				}
				else{

					pactic_connect__detailed_pricing.toast_message( 'error',  __( 'Save failed!', 'pactic-connect' ) + ' ' + __( 'Error code:', 'pactic-connect' ) + ' ' + respone.status );

				}

				save_shipping_prices_button.prev( '.save_shipping_prices_button__spinner' ).removeClass( 'show' );
				save_shipping_prices_button.prop( "disabled", false );
			   
			}
			 
		});
	
    },
	
	shipping_price_switch: function(){
        
		if(	jQuery( this ).prop('checked') ) {
			
			jQuery( this ).next( '.pactic_connect__shipping_price_settings_enable' ).val( 'yes' );
		
		} else {
			
			jQuery( this ).next( '.pactic_connect__shipping_price_settings_enable' ).val( 'no' );
		
		}
        
    },
	
	add_shipping_prices: function(){

        var shipping_price_setting_row_sample_html = jQuery( '.pactic_connect__shipping_pricing_sample_table' ).html();
		
        shipping_price_setting_row_sample_html = shipping_price_setting_row_sample_html.replace('<tbody>', '' );
        shipping_price_setting_row_sample_html = shipping_price_setting_row_sample_html.replace('</tbody>', '' );
        shipping_price_setting_row_sample_html = shipping_price_setting_row_sample_html.replace(/sample_name/g, 'name' );
		
		var random_shipping_prices_id = Math.floor( Math.random() * Date.now() * 2 );
		
		shipping_price_setting_row_sample_html = shipping_price_setting_row_sample_html.replace(/sample_price_id/g, random_shipping_prices_id );

        jQuery( '.pactic_connect__shipping_pricing_table tbody').append( shipping_price_setting_row_sample_html );
        
        pactic_connect__detailed_pricing.renumbering_shipping_prices_fields();
        
    },
	
	remove_shipping_prices: function(){
        
        var row_id = jQuery( this ).attr( 'row_id' );
        
        jQuery( '.pactic_connect__shipping_pricing_table tr[row_id="' + row_id + '"]' ).remove();
        
        pactic_connect__detailed_pricing.renumbering_shipping_prices_fields();
        
    },
	
	renumbering_shipping_prices_fields: function(){
		
		var counter = 0;

        jQuery( '.pactic_connect__shipping_pricing_table tr.shipping_price_setting_row').each(function( row_id ){
            
            var random = Math.floor( Math.random() * Date.now() );
            
            jQuery(this).attr( 'row_id', random );

    		jQuery( this ).find('select, input').each(function(){
    			
                var name = jQuery(this).attr('name');

    			name = name.replace('shipping_price_setting_id', random );
    			
                jQuery(this).attr( 'name', name );
    		
            });
            
            jQuery( this ).find('button').each(function(){
                jQuery(this).attr( 'row_id', random );
    		
            });
			
			jQuery( this ).find('.pactic_connect__shipping_price_condition').each(function(){
                jQuery(this).attr( 'row_id', random );
    		
            });
			
			jQuery( this ).find('.pactic_connect__shipping_value_suffix').each(function(){
                jQuery(this).attr( 'row_id', random );
    		
            });
			
			counter++;

        });
		
		if( counter ){
			jQuery( '.shipping_price_empty_setting_row' ).removeClass( 'show' );	
		}
		else{
			jQuery( '.shipping_price_empty_setting_row' ).addClass( 'show' );
		}
        
    },
	
	price_condition_change: function(){
		
		var selected_condition = jQuery( this ).val();
		
		var row_id = jQuery( this ).attr( 'row_id' );

		if( selected_condition == 'order_value' ){
			
			new_text = pactic_connect.currency_symbol;
			new_placeholder = __( 'value', 'pactic-connect' );
			
		}
		else if( selected_condition == 'order_weight' ){
			new_text = __( 'kg', 'pactic-connect' );
			new_placeholder = __( 'weight', 'pactic-connect' );
		}

        jQuery( '.pactic_connect__shipping_pricing_table tr[row_id="' + row_id + '"] .pactic_connect__shipping_value_suffix' ).text( new_text );
		jQuery( '.pactic_connect__shipping_pricing_table tr[row_id="' + row_id + '"] .pactic_connect__shipping_value' ).attr( 'placeholder', new_placeholder );

	},
	
	modal_close: function(){
		
		jQuery( '.pactic_connect__detailed_pricing_modal' ).removeClass('opened');
		
		setTimeout(function() {
			jQuery( '.pactic_connect__detailed_pricing_modal_bg' ).removeClass('opened');
		}, 1);
		
		jQuery( ".pactic_connect__detailed_pricing_modal " ).html( '' );
		
		jQuery( 'body' ).removeClass('pactic_connect__detailed_pricing_modal_opened');		
		
	},

	modal_open: function(){
		
		var modal_open_button = jQuery( this );
		
		if( jQuery('.pactic_connect__detailed_pricing_modal').length > 0 ) { 
			
			var instance_id = jQuery( this ).attr( 'instance_id' );
			var method_name = jQuery( this ).attr( 'method_name' );
			
			modal_open_button.next( '.detailed_pricing_modal_open_spinner' ).addClass( 'show' );
		
			jQuery.ajax({
				url: pactic_connect.ajax_url,
				async: true,
				dataType: 'json',
				data: {
					'action':'pactic_connect__get_pricing_modal_content',
					'instance_id' : instance_id,
					'method_name' : method_name,
					'detailed_pricing_modal_nonce': pactic_connect.detailed_pricing_modal_nonce
				},
				success:function( response ) {

					if( response.status == '200' ){
						
						jQuery( ".pactic_connect__detailed_pricing_modal " ).html( response.contents );
						
						pactic_connect__detailed_pricing.renumbering_shipping_prices_fields(); 
						
						pactic_connect__detailed_pricing.renumbering_shipping_cod_prices_fields(); 
						
						jQuery( '.pactic_connect__detailed_pricing_modal_bg' ).addClass('opened');

						setTimeout(function() {
							jQuery( '.pactic_connect__detailed_pricing_modal' ).addClass('opened');
						}, 1);
						
						jQuery( 'body' ).addClass('pactic_connect__detailed_pricing_modal_opened');

					}
					else{
						
						pactic_connect__detailed_pricing.toast_message( 'error', __( 'Failed to load detailed pricing!', 'pactic-connect' ) + ' ' + __( 'Error code:', 'pactic-connect' ) + ' ' + respone.status ); 
						
					}
					
					modal_open_button.next( '.detailed_pricing_modal_open_spinner' ).removeClass( 'show' );

				}

			});
		
		}
		
	},
	
	detailed_pricing_button_text: function() {
				
		if( jQuery( '#wc-backbone-modal-dialog .wc-modal-shipping-method-settings #woocommerce_pactic_connect_parcel_point_detailed_pricing' ).length > 0  ){

			jQuery( '#wc-backbone-modal-dialog .wc-modal-shipping-method-settings #woocommerce_pactic_connect_parcel_point_detailed_pricing').val( __( 'Open detailed pricing settings', 'pactic-connect' ) );

			if( jQuery( '#wc-backbone-modal-dialog .wc-modal-shipping-method-settings .pactic_connect__spinner' ).length == 0  ){
			
				jQuery( '#wc-backbone-modal-dialog .wc-modal-shipping-method-settings #woocommerce_pactic_connect_parcel_point_detailed_pricing').after( '<div class="pactic_connect__spinner detailed_pricing_modal_open_spinner"></div>' );
			
			}

		}
		
		if( jQuery( '#wc-backbone-modal-dialog .wc-modal-shipping-method-settings #woocommerce_pactic_connect_home_delivery_detailed_pricing' ).length > 0  ){

			jQuery( '#wc-backbone-modal-dialog .wc-modal-shipping-method-settings #woocommerce_pactic_connect_home_delivery_detailed_pricing').val( __( 'Open detailed pricing settings', 'pactic-connect' ) );

			if( jQuery( '#wc-backbone-modal-dialog .wc-modal-shipping-method-settings .pactic_connect__spinner' ).length == 0  ){
			
				jQuery( '#wc-backbone-modal-dialog .wc-modal-shipping-method-settings #woocommerce_pactic_connect_home_delivery_detailed_pricing').after( '<div class="pactic_connect__spinner detailed_pricing_modal_open_spinner"></div>' );
			
			}

		}
		
	},
	
	toast_message: function( type, message, time = 4000 ){

		var toast = jQuery.toast({
			icon: type,
			text: message,
			position: 'mid-center',
			hideAfter: time,
			showHideTransition: 'fade',
			allowToastClose: true,
			stack: 20,
			loader: false, 
		});
		
		return toast;
		
	}

}