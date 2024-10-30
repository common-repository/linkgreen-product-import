window.linkgreenMapMarkers = []; // the google map 'marker' list
window.linkgreenGotUserLocation = false;
window.linkgreenTextMarkers = []; // the results list of marker details beside the map


/*
 * NOTE: you may notice some parameters being passed around that aren't being used; this is a todo for you to wire up the paramenters instead
 *  of using globals all over the darn place
 */


function centerMapToUserLocation(map)
{
    var postal = document.getElementById("postal-code").value;

    if (postal !== "")
    {
        window.linkgreenGotUserLocation = true;

        //console.log("using user-entered postal code to center map");

        centerMapToAddress(postal, map, function()
        {
            centerMapToClosestPlace(window.linkgreenMapMarkers, map);
            map.setZoom(8);
        });
    }
    else if (navigator.geolocation && location.protocol === "https:")
    {
        // Try HTML5 geolocation.
        navigator.geolocation.getCurrentPosition(
            function(position)
            {
                var pos = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };

                map.setCenter(pos);
                window.linkgreenGotUserLocation = true;
            },
            function()
            {
                // error
                centerMapToShowAllMarkers(window.linkgreenMapMarkers, map);
            }
        );
    }
    else
    {
        // Browser doesn't support Geolocation
        console.log("location services were not available, possibly because not SSL?");

        centerMapToShowAllMarkers(window.linkgreenMapMarkers, map);
    }
}

function centerMapToShowAllMarkers(markers, map)
{
    var bounds = new google.maps.LatLngBounds();
    for (var i = 0; i < markers.length; i++)
    {
        bounds.extend(markers[i].position); //getPosition());
    }

    //center the map to the geometric center of all markers
    map.setCenter(bounds.getCenter());
    map.fitBounds(bounds);
    drawMarkerResultsList(map, window.linkgreenTextMarkers, window.linkgreenMapMarkers);
}

function centerMapToClosestPlace(placesMarkers, map)
{
    let pos = map.getCenter();
    let closest;

    if (!window.linkgreenGotUserLocation)
    {
        console.log("didn't get user location reliably so shouldn't re-center map");
        return;
    }

    //console.log("got " + placesMarkers.length + " markers");

    for (var i = 0; i < window.linkgreenMapMarkers.length; i++)
    {
        let marker = window.linkgreenMapMarkers[i];
        let markerPos = marker.getPosition();
        var distance = google.maps.geometry.spherical.computeDistanceBetween(markerPos, pos);

        //console.log(distance + " distance to ", marker.title);

        if (!closest || closest.distance > distance)
        {
            closest = {
                marker: marker,
                distance: distance
            };
        }
    }

    if (closest)
    {
        //closest.marker will be the nearest marker, do something with it
        //console.log("closest marker is " + distance + " away, clicking...");
        google.maps.event.trigger(closest.marker, "click");
        map.setCenter(closest.marker.getPosition());
        drawMarkerResultsList(map, window.linkgreenTextMarkers, window.linkgreenMapMarkers);
    }
}

function centerMapToAddress(zipCode, map, callback)
{
    var geocoder = new google.maps.Geocoder();

    geocoder.geocode(
        {
            address: zipCode
        },
        function(results, status)
        {
            if (status == google.maps.GeocoderStatus.OK)
            {
                //Got result, center the map and put it out there
                map.setCenter(results[0].geometry.location);
                drawMarkerResultsList(map, window.linkgreenTextMarkers, window.linkgreenMapMarkers); // redraw the list of markers
                if (callback) callback();
            }
            else
            {
                console.log("Geocode was not successful for the following reason: " + status);
            }
        }
    );
}

function initMap()
{
    //console.log("initializing the map");

    let defaultLocation = new google.maps.LatLng(43.35, -80.55); // head office

    if (!window.lgpi_locations || window.lgpi_locations.length < 1) {

        alert("Sorry, no results found.");
        return;

    }

    if (navigator.geolocation && location.protocol === "https:") {
        navigator.geolocation.getCurrentPosition(
            function(position)
            {
                window.linkgreenGotUserLocation = true;
                finishSetup(new google.maps.LatLng(position.coords.latitude, position.coords.longitude));
            },
            function() {
                Console.log("error getting location");
            });
    }

    //console.log("we have been provided "+ window.lgpi_locations.length + " locations during init");

    finishSetup(defaultLocation);
}

function finishSetup(defaultLocation) {

    var locations = window.lgpi_locations;
    window.linkgreenMapMarkers = []; // since we could be running this twice on one load we need to reset mapmarkers array

    var map = new google.maps.Map(document.getElementById("map"),
    {
        zoom: 10,
        center: defaultLocation,
        mapTypeId: google.maps.MapTypeId.ROADMAP
    });

    postalCodeSubmit = function()
    {
        var postal = document.getElementById("postal-code").value;
        window.linkgreenGotUserLocation = true;
        centerMapToAddress(postal, map, function()
        {
            centerMapToClosestPlace(window.linkgreenMapMarkers, map);
            map.setZoom(8);
        });
    };

    document
        .getElementById("postal-code-submit")
        .addEventListener("click", postalCodeSubmit);
    document
        .getElementById("postal-code")
        .addEventListener("keyup", function(event) {
            if (event.key === "Enter") {
                postalCodeSubmit();
            }
        });

    // clicking the map other than on a marker will close any open infowindow
    google.maps.event.addListener(map, "click", function(event)
    {
        infowindow.close();
    });

    var service = new google.maps.places.PlacesService(map);
    var infowindow = new google.maps.InfoWindow();

    let counter = 0;
    for (var i = 0; i < locations.length; i++)
    {
        let place = locations[i];

        createMarkerForPlace(place, map, infowindow, i, function() {
            if (++counter === locations.length)
            {
                if (window.linkgreenGotUserLocation === true)
                    centerMapToClosestPlace(window.linkgreenMapMarkers, map);
                else
                    centerMapToAddress("N0B2E0", map); // head office
            }
        });
    }
}


// this will create a marker and an infowindow popup for a placeID
function createMarkerForPlace(place, map, infowindow, index, callback)
{
    var $j = jQuery.noConflict(); // we use some jQ in here

    if (!place) return;

    if (isNaN(place.latitude) || isNaN(place.longitude))
    {
        console.log(place.name + ' has no valid lat/long');
        return;
    }

    if (place.website !== undefined && place.website !== null && ! validURL(place.website)) {
        if (place.website && validURL('http://' + place.website))
            place.website = 'http://' + place.website;
        else
            place.website = null;
    }

    var marker = new google.maps.Marker(
    {
        map: map,
        title: place.name,
        position: new google.maps.LatLng(place.latitude, place.longitude)
    });

    let placeUrl = 'https://www.google.com/maps/place/?q=place_id:' + place.place_id;

    let phone = place.formatted_phone_number ?
        place.formatted_phone_number + "<br/>" :
        "";

    let website = !place.website 
        ? ""
        : '<a href="' + place.website + '" target="_blank">Visit Website</a> | ';
        

    let url = '<a class="retail-result-placelink" href="'
        + placeUrl
        + '" target="_blank">Open in Google Maps</a>';

    let markerContent =
        "<div><strong>" +
        place.name +
        "</strong><br>" +
        place.formatted_address +
        "<br/>" +
        phone +
        "<span class='retail-location-result-links'>" +
        website +
        url +
        "</span></div>";

    google.maps.event.addListener(marker, "click", function()
    {
        infowindow.setContent(markerContent);
        infowindow.open(map, this);
    });

    if (marker != undefined)
    {
        //console.log("marker created", marker);

        window.linkgreenMapMarkers.push(marker);
        window.linkgreenTextMarkers.push(
        {
            index: index,
            html: $j("<li />")
                .attr("id", "map-marker-" + index)
                .attr("class", "retail-location-result")
                .html(markerContent)
        });
        window.linkgreenReadyToDraw = true;
    }

    if (callback)
        callback();
}

function getClosestMarkers(map, textMarkers, mapMarkers, numberOfMarkersToGet) {
    let pos = map.getCenter();
    let closest;
    let listOfMarkers = [];

    for (var i = 0; i < mapMarkers.length; i++)
    {
        let marker = mapMarkers[i];
        let markerPos = marker.getPosition();
        var distance = google.maps.geometry.spherical.computeDistanceBetween(markerPos, pos);

        listOfMarkers.push({ marker: marker, distance: distance, textMarker: textMarkers[i] });
    }

    //console.log("built a list of " + listOfMarkers.length + " markers that are close, now sorting");

    if (listOfMarkers && listOfMarkers.length > 0) 
    {
        // sort and return only the first {numberOfMarkersToGet}
        listOfMarkers.sort(function(a, b) {
            return a.distance - b.distance;
        });
        
        //console.log("sorted markers look like this", listOfMarkers);

        // idk i couldn't think of a shorter syntax for this...
        let returnMarkers = [];
        for (let i = 0; i < listOfMarkers.length; i++) {
            returnMarkers.push(listOfMarkers[i].textMarker); // send the textmarker, not the google marker
            if (i+1 === numberOfMarkersToGet) break; // we got what we came for
        }

        return returnMarkers;
    }
}

function drawMarkerResultsList(map, textMarkers, mapMarkers)
{
    if (!textMarkers || !mapMarkers || textMarkers.length < 1 || mapMarkers.length < 1) return;

    var $j = jQuery.noConflict(); // we use some jQ in here
    const maxResultsListEntries = 5;

    // clear the list first
    $j("#results-list").empty();

    let listMax = maxResultsListEntries < textMarkers.length ? maxResultsListEntries : textMarkers.length;
    let closestMarkers = getClosestMarkers(map, textMarkers, mapMarkers, listMax);

    for (let i = 0; i < closestMarkers.length; i++)
    {
        const textMarker = closestMarkers[i].html;
        const index = closestMarkers[i].index;

        //console.log("building marker for " + index, textMarker);

        $j("#results-list").append(textMarker);
        $j("#map-marker-" + index).on("click", function()
        {
            google.maps.event.trigger(mapMarkers[index], "click");
            map.setCenter(mapMarkers[index].getPosition());
            map.setZoom(10);
        });
    }
}

function validURL(str)
{
    if (!str || str === undefined || str === null) return false;
    var regexp = /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/;
    return regexp.test(str);
}
