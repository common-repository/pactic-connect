const { __, _x, _n, _nx } = wp.i18n;

var bounds;
var markers;
var infowindows;
var map;
var currentInfoWindow;
var parcel_points_data;
var selected_parcel_point_code;
var selected_parcel_point_method_id;
var markerCluster;
var selected_shipping_method;
var block_checkout = false;
var shipping_postcode;

jQuery(document).ready(function($) {

	if( jQuery('.pactic_connect__modal').length > 0 ) {
		
		pactic_connect_frontend.init();

	}

});

var pactic_connect_frontend = {
	
	init: function() {
		
		jQuery(document).on( 'click', '#pactic_connect__modal_open', this.open_modal );
		jQuery(document).on( 'click', '.pactic_connect__modal_close', this.close_modal );
		jQuery(document).on( 'updated_checkout', this.checkout_updated );
		jQuery(document).on( 'click', '.pactic_connect__additional_data_opening_hours_title', this.toggle_opening );
		jQuery(document).on( 'click', '.pactic_connect__modal.dropdown .pactic_connect__modal_search_field_button', this.search_list );
		jQuery(document).on( 'click', '.pactic_connect__modal.map .pactic_connect__modal_search_field_button', this.search_map );
		jQuery(document).on( 'click', '.pactic_connect__modal.map .pactic_connect__parcel_point_list_item', this.jump_to_marker );
		jQuery(document).on( 'click', '.pactic_connect__select_parcel_point_button', this.select_parcel_point );
		jQuery('form.checkout').on( 'change', 'input[name^="shipping_method"]', this.shipping_method_changed );
		jQuery('form.checkout').on('change', 'input[name="payment_method"]', this.payment_method_changed );
		jQuery(document).on( 'click', '.pactic_connect__modal_search_remove', this.search_remove );
		jQuery( 'body.woocommerce-checkout .wc-block-checkout' ).arrive( '*', this.block_checkout );

		pactic_connect_frontend.block_checkout();
		
		if( block_checkout == false ){
			
			selected_parcel_point_code = pactic_connect.selected_parcel_point_code;
			selected_shipping_method = pactic_connect_frontend.get_selected_shipping_method();
			
			if( !selected_parcel_point_method_id){
				selected_parcel_point_method_id = selected_shipping_method;
			}
			
			pactic_connect_frontend.get_parcel_points_data( selected_shipping_method );
			pactic_connect_frontend.toggle_ship_to_different_address( selected_shipping_method );
			
		}

	},
	
	block_checkout: function() { 
	
		var new_block_checkout = false;
		
		if( jQuery('.wp-block-woocommerce-checkout').length > 0 ) {
				
			if( jQuery('.wp-block-woocommerce-checkout .wc-block-components-shipping-rates-control' ).length > 0 ) {

				jQuery( '.wp-block-woocommerce-checkout .wc-block-components-shipping-rates-control input' ).each( function( i ) {

					if ( jQuery( this ).attr( 'id' ).includes('pactic_connect_parcel_point') ) {

						new_block_checkout = true;
					  
					}

				});
				
			}

		} 

		if( new_block_checkout != block_checkout ){
			
			block_checkout = new_block_checkout;

			pactic_connect_frontend.block__init();
						
		}
		
	},

	get_selected_shipping_method: function() { 
	
		if( block_checkout == false ){
			
			var shipping_method = jQuery("#shipping_method input:checked").val();
		
			if( !shipping_method ) {
				
				shipping_method = jQuery('#shipping_method .shipping_method').val();
				
			} 
		
		}
		else if( block_checkout == true ){
			
			var shipping_method = jQuery(".wc-block-components-shipping-rates-control input:checked").val();
		
			if( !shipping_method ) {
				
				shipping_method = jQuery('.wc-block-components-shipping-rates-control input').val();
				
			}
		
		}
		
		return shipping_method;
			
	},
	
	search_map: function() {
		
		jQuery( this ).prop( "disabled", true );

		var keyword = jQuery( '.pactic_connect__modal_search_field' ).val();
		
		if( keyword.length > 2 ) {
			
			jQuery( '.pactic_connect__modal_search_remove' ).addClass( 'show' );
			
			keyword = keyword.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
			
			jQuery( '.pactic_connect__parcel_point_list_item' ).each( function( i ) {
				
				data = jQuery( this ).attr( 'search_data' ).toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
				
				if ( data.indexOf( keyword ) === -1 ) {

					jQuery( this ).removeClass('show');
				  
				}
				else{
					
					jQuery( this ).addClass('show');
					
				}
				
			});

			pactic_connect_frontend.init_map( parcel_points_data, keyword );

		}
		else if( keyword.length == 0 ) {
			
			jQuery( '.pactic_connect__parcel_point_list_item' ).addClass('show');

			jQuery( '.pactic_connect__modal_search_remove' ).removeClass( 'show' );
			
			pactic_connect_frontend.init_map( parcel_points_data, '' );
		}
		
		jQuery( this ).unblock();
				
		jQuery( this ).prop( "disabled", false );
		
	},
	
	search_list: function() {

		var keyword = jQuery( '.pactic_connect__modal_search_field' ).val();

		if( keyword.length > 2 ) {
			
			jQuery( '.pactic_connect__modal_search_remove' ).addClass( 'show' );
			
			keyword = keyword.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");

			jQuery( '.pactic_connect__parcel_point_list_item' ).each( function( i ) {
				
				data = jQuery( this ).attr( 'search_data' ).toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
				
				if ( data.indexOf( keyword ) === -1 ) {

					jQuery( this ).removeClass('show');
				  
				}
				else{
					
					jQuery( this ).addClass('show');
					
				}
				
			});
			
		}
		else if( keyword.length == 0 ) {
			
			jQuery( '.pactic_connect__parcel_point_list_item' ).addClass('show');
			
			jQuery( '.pactic_connect__modal_search_remove' ).removeClass( 'show' );
			
		}
		
	},
	
	open_modal: function() {

		jQuery( '.pactic_connect__modal_bg' ).addClass('opened');

		setTimeout(function() {
			jQuery( '.pactic_connect__modal' ).addClass('opened');
		}, 1);
		
		jQuery( 'body' ).addClass('pactic_connect_frontend_opened');
		

	},
	
	close_modal: function() {
		
		jQuery( '.pactic_connect__modal' ).removeClass('opened');
		
		setTimeout(function() {
			jQuery( '.pactic_connect__modal_bg' ).removeClass('opened');
		}, 1);
		
		jQuery( 'body' ).removeClass('pactic_connect_frontend_opened');

	}, 
	
	toggle_opening: function() {
		
		jQuery( this ).next( '.pactic_connect__additional_data_opening_hours_content' ).toggleClass( 'open' );

	},
	
	toggle_ship_to_different_address: function( selected_shipping_method ) {

		if( selected_shipping_method && selected_shipping_method.includes('pactic_connect_parcel_point') ){
			
			jQuery('#ship-to-different-address-checkbox').prop('checked', false);
			jQuery('.woocommerce-shipping-fields').hide();
			
		}
		else{
			
			jQuery('.woocommerce-shipping-fields').show();
			
		} 
		
	},
	 
	shipping_method_changed: function() {
		
		selected_shipping_method = jQuery(this).val();
		
		pactic_connect_frontend.get_parcel_points_data( selected_shipping_method );
		
		pactic_connect_frontend.toggle_ship_to_different_address( selected_shipping_method );
				
	},
	
	get_parcel_points_data: function( selected_shipping_method ) {
		
		parcel_points_data = '';
				
		if( selected_shipping_method && selected_shipping_method.includes('pactic_connect_parcel_point') ){
			
			var current_shipping_method = pactic_connect_frontend.get_selected_shipping_method();
		
			if( selected_shipping_method != current_shipping_method || !parcel_points_data ){
				
				jQuery.ajax({
					url: pactic_connect.upload_dir_baseurl + '/pactic_connect/' + pactic_connect.parcel_point_settings[selected_shipping_method].cdCountry + '_' + pactic_connect.parcel_point_settings[selected_shipping_method].idCarrier + '.json',
					async: false,
					dataType: 'json',
					data: {}
					
				}).done(function(data){
					
					if( data[0]['ParcelPoints'] ){ 

						parcel_points_dropdown_html = '<ul class="pactic_connect__parcel_point_list">';
							
						jQuery.each( data[0]['ParcelPoints'], function( parcel_point_id, parcel_point ) {
							
							parcel_point_location = parcel_point['Location']['PostCode'] + ' ' + parcel_point['Location']['City'] + ', ' + parcel_point['Location']['Address'];
		 
							parcel_points_dropdown_html += '<li class="pactic_connect__parcel_point_list_item show" parcel_point_id="' + parcel_point_id + '" parcel_point_code="' + parcel_point['Code'] + '" search_data="' +  parcel_point['Name'] + ' ' + parcel_point_location + '">';
							
								parcel_points_dropdown_html += '<div class="pactic_connect__parcel_point_list_item_left">';
									
									parcel_points_dropdown_html += '<div class="pactic_connect__parcel_point_list_item_basic_data">';
										parcel_points_dropdown_html += '<div class="pactic_connect__parcel_point_list_item_name">';
											parcel_points_dropdown_html += parcel_point['Name'];
										parcel_points_dropdown_html += '</div>';
										parcel_points_dropdown_html += '<div class="pactic_connect__parcel_point_list_item_address">';
											parcel_points_dropdown_html += parcel_point_location;
										parcel_points_dropdown_html += '</div>';
									parcel_points_dropdown_html += '</div>';
									
									parcel_points_dropdown_html += '<div class="pactic_connect__parcel_point_list_item_additional_data">';						

										opening_hours = '';
										
										jQuery.each( parcel_point['Details']['OpeningHours'], function( oh_id, oh_data ) {
											
											opening_hours += '<li class="">';
												opening_hours += '<span>';
													opening_hours += __( pactic_connect.days[ oh_data['DayOfWeek'] ], 'pactic-connect');
													opening_hours += ':&nbsp;';
												opening_hours += '</span>';
												opening_hours += '<span>';
													opening_hours += oh_data['From'];
													opening_hours += ' - ';
													opening_hours += oh_data['To'];
												opening_hours += '</span>';
											opening_hours += '</li>';
											
										});		

										if( opening_hours ){
											
											parcel_points_dropdown_html += '<div class="pactic_connect__additional_data_opening_hours">';
												parcel_points_dropdown_html += '<div class="pactic_connect__additional_data_opening_hours_title">';
													parcel_points_dropdown_html += __('Opening hours', 'pactic-connect');
												parcel_points_dropdown_html += '</div>';
												parcel_points_dropdown_html += '<div class="pactic_connect__additional_data_opening_hours_content">';
													parcel_points_dropdown_html += '<ul>';
														parcel_points_dropdown_html += opening_hours;
													parcel_points_dropdown_html += '</ul>';
												parcel_points_dropdown_html += '</div>';
											parcel_points_dropdown_html += '</div>';
											
										}
									 
									parcel_points_dropdown_html += '</div>';
								
								parcel_points_dropdown_html += '</div>';
								
								parcel_points_dropdown_html += '<div class="pactic_connect__parcel_point_list_item_right">';
									 
									parcel_points_dropdown_html += '<div class="pactic_connect__select_parcel_point_button" method_id="' + pactic_connect.parcel_point_settings[selected_shipping_method].method_id + '" parcel_point_code="' + parcel_point['Code'] + '" cdcountry="' + pactic_connect.parcel_point_settings[selected_shipping_method].cdCountry + '" idcarrier="' + pactic_connect.parcel_point_settings[selected_shipping_method].idCarrier + '" idservice="' + pactic_connect.parcel_point_settings[selected_shipping_method].idService + '" parcel_point_name="' + parcel_point['Name'] + '" parcel_point_countrycode="' + parcel_point['Location']['CountryCode'] + '" parcel_point_postcode="' + parcel_point['Location']['PostCode'] + '" parcel_point_city="' + parcel_point['Location']['City'] + '" parcel_point_address="' + parcel_point['Location']['Address'] + '" >';
										parcel_points_dropdown_html += __('Pick up here', 'pactic-connect');
									parcel_points_dropdown_html += '</div>';
									
								parcel_points_dropdown_html += '</div>';

							parcel_points_dropdown_html += '</li>';
							
						});
					
					}

					parcel_points_data = {
						'status' : 200,
						'name' : pactic_connect.parcel_point_settings[selected_shipping_method].name,
						'id' : pactic_connect.parcel_point_settings[selected_shipping_method].id,
						'service_id' : pactic_connect.parcel_point_settings[selected_shipping_method].service_id,
						'parcel_points_dropdown_html' : parcel_points_dropdown_html,
						'parcel_points' : data[0]['ParcelPoints'],
						'method_id' : pactic_connect.parcel_point_settings[selected_shipping_method].method_id,
						'cdcountry' : pactic_connect.parcel_point_settings[selected_shipping_method].cdCountry,
						'idcarrier' : pactic_connect.parcel_point_settings[selected_shipping_method].idCarrier,
						'idservice' : pactic_connect.parcel_point_settings[selected_shipping_method].idService,
					}; 

				}).fail(function(){

					alert( __( 'Failed to load package points!', 'pactic-connect' ) + ' ' + __( 'Error code:', 'pactic-connect' ) + ' 456'  );
					
				}); 
				
			}
		
		}
		
	},
	
	checkout_updated: function() {
		
		var current_shipping_method = pactic_connect_frontend.get_selected_shipping_method();
		
		if( selected_shipping_method != current_shipping_method ){
			
			selected_shipping_method = current_shipping_method;
			
			pactic_connect_frontend.get_parcel_points_data( selected_shipping_method );
		
			pactic_connect_frontend.toggle_ship_to_different_address( selected_shipping_method );
			
		}
		
		if( jQuery('.pactic_connect__parcel_point_info_row').length > 0 ) {
			
			jQuery( '.pactic_connect__modal' ).removeClass('opened');
			jQuery( '.pactic_connect__modal_bg' ).removeClass('opened');
			jQuery( 'body' ).removeClass('pactic_connect_frontend_opened');
			jQuery( ".pactic_connect__modal_middle " ).html( '' );

			if( parcel_points_data ){
				
				if( pactic_connect.parcel_point_display_type == 'map' ){
					
					jQuery( ".pactic_connect__modal" ).addClass( 'map' );
					jQuery( ".pactic_connect__modal" ).removeClass( 'dropdown' );
					
					jQuery( ".pactic_connect__modal_middle" ).html( '<div class="pactic_connect__modal_middle_left">'+parcel_points_data.parcel_points_dropdown_html+'</div><div class="pactic_connect__modal_middle_right"><div id="pactic_connect__parcel_point_map"></div></div>' );
	
					pactic_connect_frontend.init_map( parcel_points_data, '' );
		
				}
				else if( pactic_connect.parcel_point_display_type == 'dropdown' ){
					
					jQuery( ".pactic_connect__modal" ).removeClass( 'map' );
					jQuery( ".pactic_connect__modal" ).addClass( 'dropdown' );
					
					jQuery( ".pactic_connect__modal_middle" ).html( parcel_points_data.parcel_points_dropdown_html );
	
				}
				
				jQuery( '.pactic_connect__parcel_point_list_item' ).removeClass( 'selected' );
				jQuery( '.pactic_connect__parcel_point_list_item' ).removeClass( 'sticky' );
				jQuery( '.pactic_connect__parcel_point_list_item' ).css("order", '' );
				jQuery( '.pactic_connect__parcel_point_list_item[parcel_point_code="' + selected_parcel_point_code + '"]' ).addClass( 'selected' );
				jQuery( '.pactic_connect__parcel_point_list_item[parcel_point_code="' + selected_parcel_point_code + '"]' ).css("order", '-1' );
				jQuery( '.pactic_connect__parcel_point_list_item[parcel_point_code="' + selected_parcel_point_code + '"]' ).addClass( 'sticky' );
			
			} 

		}

	}, 
	
	select_parcel_point: function() {

		selected_parcel_point_code = jQuery(this).attr( 'parcel_point_code' );
		selected_parcel_point_method_id = jQuery(this).attr( 'method_id' );
		
		var method_id = jQuery(this).attr( 'method_id' );
		var cdcountry = jQuery(this).attr( 'cdcountry' );
		var idcarrier = jQuery(this).attr( 'idcarrier' );
		var idservice = jQuery(this).attr( 'idservice' );
		
		var parcel_point_name = jQuery(this).attr( 'parcel_point_name' );
		var parcel_point_countrycode = jQuery(this).attr( 'parcel_point_countrycode' );
		var parcel_point_postcode = jQuery(this).attr( 'parcel_point_postcode' );
		var parcel_point_city = jQuery(this).attr( 'parcel_point_city' );
		var parcel_point_address = jQuery(this).attr( 'parcel_point_address' );
				
		jQuery( '.pactic_connect__modal ' ).addClass( 'processing' );
		
		jQuery( '.pactic_connect__modal ' ).block({
			message: null,
			overlayCSS: {
				background: '#ccc',
				opacity: 0.5
			}
		});
		
		if( block_checkout == true ){

			wp.data.dispatch('wc/store/cart').setShippingAddress( { 
				company: parcel_point_name,
				 city: parcel_point_city,
				 state: '',
				 postcode: parcel_point_postcode,
				 country: parcel_point_countrycode,
				 address_1: parcel_point_address
			});
			
			pactic_connect_frontend.block__toggle_ship_to_different_address( selected_shipping_method ); 
	
		}
				
		jQuery( '.pactic_connect__parcel_point_list_item' ).removeClass( 'selected' );
		jQuery( '.pactic_connect__parcel_point_list_item' ).removeClass( 'sticky' );
		jQuery( '.pactic_connect__parcel_point_list_item' ).css("order", '' );
		jQuery( '.pactic_connect__parcel_point_list_item[parcel_point_code="' + selected_parcel_point_code + '"]' ).addClass( 'selected' );
		jQuery( '.pactic_connect__parcel_point_list_item[parcel_point_code="' + selected_parcel_point_code + '"]' ).css("order", '-1' );
		jQuery( '.pactic_connect__parcel_point_list_item[parcel_point_code="' + selected_parcel_point_code + '"]' ).addClass( 'sticky' );
	 
		var parcel_point_data = { 
			'method_id': method_id,
			'cdcountry': cdcountry,
			'idcarrier': idcarrier,
			'idservice': idservice,
			'parcel_point_code': selected_parcel_point_code 
		};
		
		jQuery.ajax({
			url: pactic_connect.ajax_url,
			async: true,
			dataType: 'json',
			data: {
				'action':'pactic_connect__select_parcel_point',
				'parcel_point_data' : parcel_point_data,
				'select_parcel_point_nonce': pactic_connect.select_parcel_point_nonce,
			},
			success:function( response ) {
				
				if( block_checkout == true ){
					
					pactic_connect_frontend.block__get_checkout_point_data();

				}
				
				jQuery( '.pactic_connect__modal ' ).unblock();
				jQuery( '.pactic_connect__modal ' ).removeClass('processing');

				jQuery('.pactic_connect__modal_close').trigger('click');
		
				jQuery('body').trigger('update_checkout');

			}
			
		});
		
	},	
	
	init_map: async function( parcel_points_data, keyword = '' ) {
		
		if( keyword == '' && shipping_postcode ){
			 keyword = shipping_postcode;
		}
		
		const { Map } = await google.maps.importLibrary("maps");

		currentInfoWindow = null;
	   
		bounds = new google.maps.LatLngBounds();
		
		markers = [];
		
		infowindows = [];
		
		map = new google.maps.Map(document.getElementById('pactic_connect__parcel_point_map'), {
		  center: {lat: 47.180086, lng: 19.503736},
		  zoom: 8,
		  mapTypeId: google.maps.MapTypeId.ROADMAP,
		  mapId: "pactic_connect__parcel_point_map",

		});
		
		pactic_connect_frontend.set_markers( parcel_points_data, keyword ); 

	},
	
	set_markers: async function( parcel_points_data, keyword = '' ) {	
	
		const { AdvancedMarkerElement, PinElement } = await google.maps.importLibrary("marker");
		
		var marker_counter = 0;
		
		jQuery.each( parcel_points_data.parcel_points, function( parcel_point_id, parcel_point ) {
			
			var parcel_point_location = parcel_point.Location.PostCode + ' ' + parcel_point.Location.City + ', ' + parcel_point.Location.Address;
			
			var marker_title = parcel_point.Name + ' ' + parcel_point_location;

			marker_title_search = marker_title.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
			
			if( keyword == '' || (  keyword != '' && marker_title_search.indexOf( keyword ) !== -1 ) ){ 
			
				if( parcel_point.Location.Coordinates.Lat != 0 && parcel_point.Location.Coordinates.Lat != '' && parcel_point.Location.Coordinates.Long != 0 && parcel_point.Location.Coordinates.Long != '' ){
				
					var marker_position = new google.maps.LatLng( parcel_point.Location.Coordinates.Lat, parcel_point.Location.Coordinates.Long );
					
					bounds.extend( marker_position );

					if( parcel_point.Type == 'ParcelPoint' ){
						marker_image_src =  pactic_connect.plugin_dir_url+'assets/images/shops.png';
					}
					else if( parcel_point.Type == 'Locker'){
						marker_image_src =  pactic_connect.plugin_dir_url+'assets/images/locker.png';
					}
					else{
						marker_image_src =  pactic_connect.plugin_dir_url+'assets/images/pin.png';
					}
				
					const marker_content = document.createElement("div");

					marker_content.classList.add("pactic_connect__parcel_point_map_item");
					marker_content.setAttribute("search_data", parcel_point.Name + ' ' + parcel_point_location );
					marker_content.innerHTML = '<img src="' + marker_image_src + '">';

					const marker = new AdvancedMarkerElement({
						map,
						position: marker_position, 
						title: marker_title,
						content: marker_content, 
					}); 
					  
					markers.push( marker );
					
					var marker_infowindow_content =
						'<div class="pactic_connect__infowindow">'+
							'<div class="pactic_connect__infowindow_title">'+parcel_point.Name+'</div>'+
							'<div class="pactic_connect__infowindow_address" >'+
								parcel_point_location +
							'</div>'+
								
							'<div class="pactic_connect__select_parcel_point_button" method_id="' + parcel_points_data.method_id + '" parcel_point_code="' + parcel_point.Code + '" cdcountry="' + parcel_points_data.cdcountry + '" idcarrier="' + parcel_points_data.idcarrier + '" idservice="' + parcel_points_data.idservice + '" parcel_point_name="' + parcel_point.Name + '" parcel_point_countrycode="' + parcel_point.Location.CountryCode + '" parcel_point_postcode="' + parcel_point.Location.PostCode + '" parcel_point_city="' + parcel_point.Location.City + '" parcel_point_address="' + parcel_point.Location.Address + '">' +
							 __('Pick up here', 'pactic-connect') +
							 '</div>' +
						'</div>';

					
					var infowindow = new google.maps.InfoWindow({
					  content: marker_infowindow_content
					});
					
					infowindows.push( infowindow );
					
					marker.addListener('click', ({ domEvent, latLng }) => {

						if (currentInfoWindow != null) {
							currentInfoWindow.close();
						} 

						infowindow.open( map, marker );

						currentInfoWindow = infowindow; 

						map.setCenter( marker_position );

					});
					
					jQuery( '.pactic_connect__parcel_point_list_item[parcel_point_id="'+parcel_point_id+'"]' ).attr( 'parcel_point_marker_id', marker_counter );
					
					marker_counter++;
					
				}
			
			}
	
		});
		 
		markerCluster = new markerClusterer.MarkerClusterer({ map, markers });

		if(!bounds.isEmpty()) {
			
			map.fitBounds(bounds);
			
		}
		
	},
	
	search_remove: function() {
		
		jQuery( '.pactic_connect__modal' ).addClass( 'processing' );
		
		jQuery( '.pactic_connect__modal' ).block({
			message: null, 
			overlayCSS: {
				background: '#ccc',
				opacity: 0.5
			}
		}); 
		
		jQuery( '.pactic_connect__modal_search_remove' ).removeClass( 'show' );
		
		jQuery( '.pactic_connect__modal_search_field' ).val( '' );
		
		shipping_postcode = '';
		
		if( pactic_connect.parcel_point_display_type == 'map' ){
			
			jQuery( '.pactic_connect__parcel_point_list_item' ).addClass('show');

			pactic_connect_frontend.init_map( parcel_points_data, '' );
			
			jQuery( '.pactic_connect__parcel_point_list_item' ).addClass('show');

			jQuery( '.pactic_connect__modal_search_remove' ).removeClass( 'show' );
			
			jQuery( '.pactic_connect__modal' ).unblock();
			jQuery( '.pactic_connect__modal' ).removeClass('processing');

		}
		else if( pactic_connect.parcel_point_display_type == 'dropdown' ){
			
			jQuery( '.pactic_connect__parcel_point_list_item' ).addClass('show');
			
			pactic_connect_frontend.search_list();
			
			jQuery( '.pactic_connect__modal' ).unblock();
			jQuery( '.pactic_connect__modal' ).removeClass('processing');

		}

	},
	
	jump_to_marker: function() {

		var parcel_point_marker_id = jQuery(this).attr( 'parcel_point_marker_id' );
		
		var marker = markers[parcel_point_marker_id];
		
		var infowindow = infowindows[parcel_point_marker_id];
		
		map.setZoom(17);
		
		map.panTo(marker.position);

		if (currentInfoWindow != null) {
			currentInfoWindow.close();
		} 

		infowindow.open( map, marker );

		currentInfoWindow = infowindow; 

	},
	
	payment_method_changed: function() {
		
		jQuery('body').trigger('update_checkout');
		
	},
	
	block__init: function() { 

		jQuery( 'form.wc-block-checkout__form').on('change', 'input[id="shipping-postcode"]', this.block__shipping_postcode_changed );
		jQuery( '.wc-block-components-shipping-rates-control' ).on( 'change', 'input', this.block__shipping_method_changed );

		jQuery( '.wp-block-woocommerce-checkout-order-summary-block' ).arrive( '*', this.block__shipping_address_text_changed );
		jQuery( '.wp-block-woocommerce-checkout-shipping-methods-block' ).arrive( '*', this.block__shipping_method_updated );
		
		wp.hooks.addAction( 'experimental__woocommerce_blocks-checkout-set-active-payment-method', 'pactic-connect-checkout-block', this.block__experimental );
		wp.hooks.addAction( 'experimental__woocommerce_blocks-checkout-set-selected-shipping-rate', 'pactic-connect-checkout-block', this.block__experimental );

		shipping_postcode = jQuery('form.wc-block-checkout__form input[id="shipping-postcode"]' ).val();
		jQuery('.pactic_connect__modal_search_field' ).val( shipping_postcode );
		
		pactic_connect_frontend.search_list();
		
		selected_parcel_point_code = pactic_connect.selected_parcel_point_code;
		selected_shipping_method = pactic_connect_frontend.get_selected_shipping_method();
		
		if( !selected_parcel_point_method_id){
			selected_parcel_point_method_id = selected_shipping_method;
		}
		
		pactic_connect_frontend.get_parcel_points_data( selected_shipping_method );
		pactic_connect_frontend.toggle_ship_to_different_address( selected_shipping_method );
		
		pactic_connect_frontend.block__toggle_ship_to_different_address( selected_shipping_method );
		
		pactic_connect_frontend.block__get_checkout_point_data();
		
		pactic_connect_frontend.block__checkout_updated(); 
		
		pactic_connect_frontend.block__shipping_address_text_changed();
		
		//pactic_connect_frontend.block__experimental();
			
	},
	
	block__experimental: function() {
		
		wc.blocksCheckout.extensionCartUpdate( {
			namespace: 'pactic-connect-checkout-block',
			data: {
				block_checkout:block_checkout,
				shipping_method: document.querySelector('input[class="wc-block-components-radio-control__input"]:checked').value,
				payment_method: document.querySelector('input[name="radio-control-wc-payment-method-options"]:checked').value
			},
		});

		pactic_connect_frontend.block__shipping_address_text_changed();
		 
	},
	
	block__shipping_method_updated: function() {

		var current_shipping_method = pactic_connect_frontend.get_selected_shipping_method();
		
		if( selected_shipping_method != current_shipping_method ){
			
			pactic_connect_frontend.block__checkout_updated(); 
		
		}
		 
	},
	
	block__shipping_postcode_changed: function() {
		
		shipping_postcode = jQuery('form.wc-block-checkout__form input[id="shipping-postcode"]' ).val();

		pactic_connect_frontend.block__checkout_updated(); 
		
		jQuery('.pactic_connect__modal_search_field' ).val( shipping_postcode );

		pactic_connect_frontend.search_list();
		
	},
	
	get_parcel_point_info: function( selected_parcel_point_code ) {
		
		var result;
		
		jQuery.each( parcel_points_data.parcel_points, function( parcel_point_id, parcel_point ) {
			
			if( selected_parcel_point_code == parcel_point.Code ){
				
				result = parcel_point;
				
			}
				
		});
		
		return result;
			
	},
	
	block__get_checkout_point_data: function() {
		
		var point_data = '';
			
		if( selected_shipping_method && selected_shipping_method.includes('pactic_connect_parcel_point') ){
			
			point_data += '<div class="pactic_connect__parcel_point_info_row">';
		
				point_data += '<div class="pactic_connect__parcel_point_info_row_content">';
				
					point_data += '<div class="pactic_connect__parcel_point_info_row_title">';
						point_data += __('Pickup location', 'pactic-connect');
					point_data += '</div>';
					
					if( selected_parcel_point_code  ){
						
						var parcel_point_info = pactic_connect_frontend.get_parcel_point_info( selected_parcel_point_code );
						
						if( parcel_point_info ){

							point_data += '<div class="pactic_connect__selected_parcel_point_info">';

								point_data += '<div class="pactic_connect__selected_parcel_point_name">';
									point_data += parcel_point_info['Name'];
								point_data += '</div>';
								
								point_data += '<div class="pactic_connect__selected_parcel_point_address">';
									point_data += parcel_point_info['Location']['PostCode'] + ' ' + parcel_point_info['Location']['City'] + ', ' + parcel_point_info['Location']['Address'];
								point_data += '</div>';
								
							point_data += '</div>';
												
						}

					}
					
				point_data += '</div>';

				if( !selected_parcel_point_code  ){
					
					point_data += '<a href="#" id="pactic_connect__modal_open">';
						point_data += __('Choosing a parcel point', 'pactic-connect');
					point_data += '</a>';
				
				}
				else{ 
					
					if( selected_parcel_point_method_id == selected_shipping_method ){
												
						point_data += '<a href="#" id="pactic_connect__modal_open">';
							point_data += __('Change parcel point', 'pactic-connect');
						point_data += '</a>';
					
					}
					else{
						
						point_data += '<a href="#" id="pactic_connect__modal_open">';
							point_data += __('Choosing a parcel point', 'pactic-connect');
						point_data += '</a>';
						
					}

				}
				
			point_data += '</div>';

			jQuery('.pactic_connect__parcel_point_info_row').remove();
							
			jQuery( "#shipping-option .wc-block-components-checkout-step__container" ).append( point_data );

		}
		else{
			
			jQuery('.pactic_connect__parcel_point_info_row').remove();
			
		}
		
	},
	
	block__shipping_method_changed: function() {
		
		var current_shipping_method = pactic_connect_frontend.get_selected_shipping_method();
		
		if( selected_shipping_method != current_shipping_method ){
		
			selected_shipping_method = pactic_connect_frontend.get_selected_shipping_method();

			pactic_connect_frontend.get_parcel_points_data( selected_shipping_method );
			
			pactic_connect_frontend.block__checkout_updated();
			
		}
		
		pactic_connect_frontend.block__get_checkout_point_data();
		
		pactic_connect_frontend.block__toggle_ship_to_different_address( selected_shipping_method );
		
	},
	
	block__checkout_updated: function() {
		
		var current_shipping_method = pactic_connect_frontend.get_selected_shipping_method();
		
		if( selected_shipping_method != current_shipping_method ){

			selected_shipping_method = current_shipping_method;
			
			pactic_connect_frontend.get_parcel_points_data( selected_shipping_method );

		}
		
		pactic_connect_frontend.block__toggle_ship_to_different_address( selected_shipping_method );

		jQuery( '.pactic_connect__modal' ).removeClass('opened');
		jQuery( '.pactic_connect__modal_bg' ).removeClass('opened');
		jQuery( 'body' ).removeClass('pactic_connect_frontend_opened');
		jQuery( ".pactic_connect__modal_middle " ).html( '' );

		if( parcel_points_data ){
			
			if( pactic_connect.parcel_point_display_type == 'map' ){
				
				jQuery( ".pactic_connect__modal" ).addClass( 'map' );
				jQuery( ".pactic_connect__modal" ).removeClass( 'dropdown' );
				
				jQuery( ".pactic_connect__modal_middle" ).html( '<div class="pactic_connect__modal_middle_left">'+parcel_points_data.parcel_points_dropdown_html+'</div><div class="pactic_connect__modal_middle_right"><div id="pactic_connect__parcel_point_map"></div></div>' );

				pactic_connect_frontend.init_map( parcel_points_data, '' );
				
				pactic_connect_frontend.search_list();
	
			}
			else if( pactic_connect.parcel_point_display_type == 'dropdown' ){
				
				jQuery( ".pactic_connect__modal" ).removeClass( 'map' );
				jQuery( ".pactic_connect__modal" ).addClass( 'dropdown' );
				
				jQuery( ".pactic_connect__modal_middle" ).html( parcel_points_data.parcel_points_dropdown_html );
				
				pactic_connect_frontend.search_list();

			}
			
			jQuery( '.pactic_connect__parcel_point_list_item' ).removeClass( 'selected' );
			jQuery( '.pactic_connect__parcel_point_list_item' ).removeClass( 'sticky' );
			jQuery( '.pactic_connect__parcel_point_list_item' ).css("order", '' );
			jQuery( '.pactic_connect__parcel_point_list_item[parcel_point_code="' + selected_parcel_point_code + '"]' ).addClass( 'selected' );
			jQuery( '.pactic_connect__parcel_point_list_item[parcel_point_code="' + selected_parcel_point_code + '"]' ).css("order", '-1' );
			jQuery( '.pactic_connect__parcel_point_list_item[parcel_point_code="' + selected_parcel_point_code + '"]' ).addClass( 'sticky' );
		
		}

	}, 
	
	block__toggle_ship_to_different_address: function( selected_shipping_method ) {
		
		if( selected_shipping_method && selected_shipping_method.includes('pactic_connect_parcel_point') ){
			
			jQuery('#wc-block-checkout__use-address-for-billing input').prop('checked', false);

			jQuery('#shipping-fields .wc-block-components-address-form__last_name').addClass( 'hide_shipping_fields' );
			jQuery('#shipping-fields .wc-block-components-address-form__first_name').addClass( 'hide_shipping_fields' );
			jQuery('#shipping-fields .wc-block-components-address-form__company').addClass( 'hide_shipping_fields' );
			jQuery('#shipping-fields .wc-block-components-address-form__city').addClass( 'hide_shipping_fields' );
			jQuery('#shipping-fields .wc-block-components-address-form__address_1').addClass( 'hide_shipping_fields' );
			jQuery('#shipping-fields .wc-block-components-address-form__address_2').addClass( 'hide_shipping_fields' );
			jQuery('#shipping-fields .wc-block-components-address-form__state').addClass( 'hide_shipping_fields' );
			jQuery('#shipping-fields .wc-block-components-address-form__phone').addClass( 'hide_shipping_fields' );
			jQuery('#wc-block-checkout__use-address-for-billing input').addClass( 'hide_shipping_fields' );
			
			jQuery('#shipping-fields .wc-block-components-address-form-wrapper').addClass( 'show_shipping' );
			jQuery('#shipping-fields .wc-block-components-address-card-wrapper').addClass( 'hide_shipping' );
			jQuery('#shipping-fields .wc-block-checkout__use-address-for-billing').addClass( 'hide_shipping' );

		}
		else{

			jQuery('#shipping-fields .wc-block-components-address-form__last_name').removeClass( 'hide_shipping_fields' );
			jQuery('#shipping-fields .wc-block-components-address-form__first_name').removeClass( 'hide_shipping_fields' );
			jQuery('#shipping-fields .wc-block-components-address-form__company').removeClass( 'hide_shipping_fields' );
			jQuery('#shipping-fields .wc-block-components-address-form__city').removeClass( 'hide_shipping_fields' );
			jQuery('#shipping-fields .wc-block-components-address-form__address_1').removeClass( 'hide_shipping_fields' );
			jQuery('#shipping-fields .wc-block-components-address-form__address_2').removeClass( 'hide_shipping_fields' );
			jQuery('#shipping-fields .wc-block-components-address-form__state').removeClass( 'hide_shipping_fields' );
			jQuery('#shipping-fields .wc-block-components-address-form__phone').removeClass( 'hide_shipping_fields' );
			jQuery('#wc-block-checkout__use-address-for-billing input').removeClass( 'hide_shipping_fields' );
			
			jQuery('#shipping-fields .wc-block-components-address-form-wrapper').removeClass( 'show_shipping' );
			jQuery('#shipping-fields .wc-block-components-address-card-wrapper').removeClass( 'hide_shipping' );
			jQuery('#shipping-fields .wc-block-checkout__use-address-for-billing').removeClass( 'hide_shipping' );

		}
		
		pactic_connect_frontend.block__shipping_address_text_changed();
		
	},
	
	block__shipping_address_text_changed: function() {
		
		if( selected_shipping_method && selected_shipping_method.includes('pactic_connect_parcel_point') ){

			jQuery('.wp-block-woocommerce-checkout-order-summary-block .wp-block-woocommerce-checkout-order-summary-shipping-block').addClass( 'hide_shipping_fields' );
			
		}
		else{

			jQuery('wp-block-woocommerce-checkout-order-summary-block .wp-block-woocommerce-checkout-order-summary-shipping-block').removeClass( 'hide_shipping_fields' );
			
		}
		
	},
	
}
