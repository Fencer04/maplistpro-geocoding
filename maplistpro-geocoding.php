<?php

/*
Plugin Name: Map List Pro Geocoding
Plugin URI: https://github.com/Fencer04/maplistpro-geocoding
Description: This plugin will geocode the items that have been imported or entered using the MapListPro Plugin.
Version: 1.0
Author: Justin Hansen
Author URI: http://justin-hansen.com
License: GPL2
*/

//Add Admin Menu Items and Settings Page
add_action( 'admin_menu', 'mlpg_setup_menu' );
function mlpg_setup_menu(){
	add_submenu_page( 'edit.php?post_type=maplist', 'Map List Pro Geocoding', 'Geocoding', 'manage_options', 'geocoding', 'mlpg_geocoding_page' );
    add_submenu_page( 'edit.php?post_type=maplist', 'Map List Pro Geocoding Settings', 'Geocoding Settings', 'manage_options', 'geocoding-options', 'mlpg_geocoding_options_page' );
    add_action( 'admin_init', 'mlpg_register_mysettings' );
}

//Geocodeing settings page
function mlpg_register_mysettings() { // whitelist options
    register_setting( 'mlpg-option-group', 'mlpg-api-key' );
}
//
function mlpg_geocoding_options_page(){?>
    <div class="wrap">
    <h2>Map List Pro Geocoding Settings</h2>
    <form method="post" action="options.php">
        <?php settings_fields( 'mlpg-option-group' );
        do_settings_sections( 'mlpg-option-group' );?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Google Geocoding API Key</th>
                <td><input type="text" name="mlpg-api-key" style="width: 350px;" value="<?php echo esc_attr( get_option('mlpg-api-key') ); ?>" /><br />See <a href="https://developers.google.com/maps/documentation/geocoding/intro" target="_blank">Google Geocoding API</a> to get your own key.</td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div><?php
}

//Geocoding User Interface
function mlpg_geocoding_page(){?>
	<h1>Map List Pro Geocoding</h1>
	<p>Select your options and click the button to see how many items there are to geocode. In order for this to work the locations must have correct street addresses listed in their address field. The free Google Maps API only allows 2500 location requests per day.</p>
    <div id="countCheckArea">
        <input type="radio" name="locationRadio" value="all" /><label for="locationRadio">Geocode all map locations</label><br />
        <input type="radio" name="locationRadio" value="blank" checked="checked" /><label for="locationRadio">Geocode map locations without coordinates</label><br /><br />
        <input type="button" name="checkGeo" id="checkGeo" value="Check Amount to Geocode" />
    </div>
	<div id="countArea"></div>
	<div id="msgArea"></div>
	<div id="errorArea" style="display: none;"></div>
	<?php
}

//Add Javascript for geocoding to footer
add_action( 'admin_footer', 'mlpg_add_javascript' );
function mlpg_add_javascript(){
    //blockUI
    wp_enqueue_script( 'blockUI', plugins_url( '/js/jquery.blockUI.js', __FILE__ ), array(), '2.70.0', true );
    ?>
	<script type="text/javascript">
        var stopLoop = false;
		var locationList = "";
		var totalItems = 0;
        var processing = false;
        var errorList = new Array();
		jQuery(document).ready(function(){
			jQuery("input#checkGeo").click(function(){
                processing = true;
				jQuery("div#countArea").text('');
                jQuery("#errorArea").text();
                jQuery("#errorArea").hide();
                jQuery("#countCheckArea").block({
                    message: '<h1>Processing</h1>',
                    css: { border: '3px solid #a00' }
                });
				jQuery.ajax({ url: ajaxurl, method: "POST", data: {'action': 'mlpg_geocode_count', 'type': jQuery("input[name='locationRadio']:checked").val()}, dataType: 'json' })
					.success(function(response) {
						jQuery("div#countArea").html("Locations available for geocoding: " + response.count + " <input type='button' name='btnGeocode' id='btnGeocode' class='btnGeocode' value='Geocode' />");
						totalItems = response.count;
						locationList = response.locations;
						setupGeoCodeButton();
                        jQuery("#countCheckArea").unblock();
                        processing = false;
					});
			});

            jQuery(window).bind('beforeunload', function(){
                if(processing){
                    return 'Are you sure you want to leave? Leaving will stop the geocoding process.';
                }
            });
		});

		function setupGeoCodeButton(){
			jQuery("input.btnGeocode").click(function(){
                processing = true;
                jQuery("#errorArea").html('<strong>Errors:</strong>');
                jQuery("#errorArea").show();
                jQuery("#countCheckArea").block({
                    message: '<h1>Processing</h1>',
                    css: { border: '3px solid #a00' }
                });

                var i = 0;
                var currentItem = i + 1;
                stopLoop = false;

                //Local method to handle synchronous calls without locking up UI
                function next(){
                    jQuery("div#countArea").html("<h3>Location # " + currentItem + " out of " + totalItems + "</h3> <input type='button' name='btnStop' id='btnStop' value='Stop Processing' onClick='stopProcessing()' />");

                    //If the address is blank then don't bother wasting an API call
                    if(locationList[i].address == "" || locationList[i].address == null){
                        jQuery('#errorArea').append("<div>" + locationList[i].name + " has no address. <a target='_blank' href='<?php echo site_url(); ?>/wp-admin/post.php?post=" + locationList[i].id + "&action=edit'>Click here to edit address</a>.</div>");
                        errorList.push(locationList[i]);
                        ++i;
                        currentItem = i + 1;
                        if(i >= locationList.length){
                            checkErrors();
                        }else{
                            next();
                        }
                    }else {
                        jQuery.ajax({
                            url: ajaxurl,
                            method: "POST",
                            data: {
                                'action': 'mlpg_geocode',
                                'address': locationList[i].address,
                                'id': locationList[i].id
                            },
                            dataType: 'text',
                            async: true
                        }).success(function (response) {
                            switch (response) {
                                case "INVALID_REQUEST":
                                    jQuery('#errorArea').append("<div>" + locationList[i].name + " made an invalid request. <a target='_blank' href='<?php echo site_url(); ?>/wp-admin/post.php?post=" + locationList[i].id + "&action=edit'>Click here to check location information</a>.</div>");
                                    errorList.push(locationList[i]);
                                    break;
                                case "REQUEST_DENIED":
                                    jQuery('#errorArea').append("<div>Request for " + locationList[i].name + " was denied. <a target='_blank' href='<?php echo site_url(); ?>/wp-admin/post.php?post=" + locationList[i].id + "&action=edit'>Click here to check location information</a>.</div>");
                                    errorList.push(locationList[i]);
                                    break;
                                case "OVER_QUERY_LIMIT":
                                    jQuery('#errorArea').append("<div>You are over your daily quota for geocoding requests. The geocoding process has been stopped and you will have to wait until tomorrow to make more requests.</div>");
                                    stopLoop = true;
                                    break;
                                case "ZERO_RESULTS":
                                    jQuery('#errorArea').append("<div>Request for " + locationList[i].name + " did not return results. <a target='_blank' href='<?php echo site_url(); ?>/wp-admin/post.php?post=" + locationList[i].id + "&action=edit'>Click here to check location information</a>.</div>");
                                    errorList.push(locationList[i]);
                                    break;
                                case "UNKNOWN_ERROR":
                                    jQuery('#errorArea').append("<div>Request for " + locationList[i].name + " returned an unknown error. <a target='_blank' href='<?php echo site_url(); ?>/wp-admin/post.php?post=" + locationList[i].id + "&action=edit'>Click here to check location information</a>.</div>");
                                    errorList.push(locationList[i]);
                                    break;
                            }

                            //check to see if the local function should be called again
                            if(!stopLoop){
                                ++i;
                                currentItem = i + 1;
                                if(i >= locationList.length){
                                    checkErrors();
                                }else{
                                    next();
                                }
                            }else{
                                checkErrors();
                            }
                        });
                    }
				}
                //make first call
                next();
			});
        }

        //Check to see if any items have errors
        function checkErrors(){
            jQuery("#countCheckArea").unblock();
            if(errorList.length > 0){
                locationList = errorList;
                totalItems = locationList.length;
                jQuery("div#errorArea").append(locationList.length + " errors returned. After using the links above to fix any location data issues use this button to check those items again: <input type='button' name='btnGeocode' id='btnGeocode' class='btnGeocode' value='Geocode Locations With Errors' />");
                errorList = new Array();
                processing = false;
                setupGeoCodeButton();
            }
        }

        function stopProcessing(){
            stopLoop = true;
        }
	</script><?php
}

//AJAX Functions

//Get count for geocode
add_action( 'wp_ajax_mlpg_geocode_count', 'mlpg_geocode_count' );
function mlpg_geocode_count(){
	$type = $_POST['type'];
	$args = array('post_type' => 'maplist', 'posts_per_page' => -1);
	//Check to see which locations will be geocoded
	if($type == "blank"){
		$meta_query = array(
			'relation' => 'OR',
			array( 'key' => 'maplist_latitude', 'value' => '' ),
			array( 'key' => 'maplist_longitude', 'value' => '' )
		);
		$args['meta_query'] = $meta_query;
	}
	$locationList = new WP_Query( $args );
	$locationInfo = array();
	$locationResponse = array();
	$locationResponse['count'] = $locationList->found_posts;
	if($locationList->have_posts()) :
		while( $locationList->have_posts() ) : $locationList->the_post();
			$currentLocation = array('id' => get_the_ID(), 'name' => get_the_title(), 'address' => get_post_meta( get_the_ID(), 'maplist_address' )[0]);
			$locationInfo[] = $currentLocation;
		endwhile;
		$locationResponse['locations'] = $locationInfo;
	endif;

	wp_send_json( $locationResponse );

	wp_die();
}

//Geocode addresses
add_action( 'wp_ajax_mlpg_geocode', 'mlpg_geocode' );
function mlpg_geocode(){
    //Grab API key from plugin settings page
    $mapKey = get_option('mlpg-api-key');
	$apiUrl = "https://maps.googleapis.com/maps/api/geocode/json";

	$address = urlencode($_POST['address']);
	$curlUrl = $apiUrl . "?address=" . $address . "&key=" . $mapKey;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $curlUrl);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	$location = json_decode(curl_exec($ch), true);
	$curlError = curl_error();
	curl_close();
	$status = $location['status'];
	if($status == "OK"){
		$latitude = $location['results'][0]['geometry']['location']['lat'];
		$longitude = $location['results'][0]['geometry']['location']['lng'];

		//Update MapListPro item with results of geocoding
		update_post_meta( get_the_ID(), 'maplist_latitude', $latitude );
		update_post_meta( get_the_ID(), 'maplist_longitude', $longitude );
	}
	echo $status;

	wp_die();
}