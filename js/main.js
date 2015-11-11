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

    //Geocode a single address at the location entry to edit screen
    if(jQuery('textarea#maplist_address')){
        var addressContatainer = jQuery('textarea#maplist_address').parent();
        addressContatainer.append('<input type="button" id="btnSingleGeocode" name="btnSingleGeocode" value="Geocode" />');

        jQuery('#btnSingleGeocode').click(function(){
            if(jQuery('textarea#maplist_address').val() == ""){
                alert("The address field can't be empty");
            }else{
                jQuery.ajax({
                    url: ajaxurl,
                    method: "POST",
                    data: {
                        'action': 'mlpg_single_geocode',
                        'address': jQuery('textarea#maplist_address').val()
                    },
                    dataType: 'json',
                    async: true
                }).success(function (response) {
                    switch (response.status) {
                        case "INVALID_REQUEST":
                            alert("The geocoding request is invalid. Please check the address and try again.");
                            break;
                        case "REQUEST_DENIED":
                            alert("The geocoding request was denied. Please check the API key to make sure it is valid.");
                            break;
                        case "OVER_QUERY_LIMIT":
                            alert("The geocoding limit has been reached for today. Please try again tomorrow.");
                            break;
                        case "ZERO_RESULTS":
                            alert("The geocoding request didn't return a result. Please check the address and try again.");
                            break;
                        case "UNKNOWN_ERROR":
                            alert("The geocoding request returned an unknown error. Please check the address and try again.");
                            break;
                        default:
                            jQuery('input#maplist_latitude').val(response.latitude);
                            jQuery('input#maplist_longitude').val(response.longitude);
                            alert('Geocoding successful. The latitude and longitude fields have been updated with the results. You will have to save the location before the changes take effect.');
                    }
                });
            }
        });
    }
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
        setupGeoCodeButton();
    }else{
        jQuery("div#errorArea").hide();
    }
    jQuery("#btnStop").hide();
    processing = false;
}

function stopProcessing(){
    stopLoop = true;
}