<?php

include_once 'config.php';
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on" ? "https://" : "http://";
$domain = (isset($_SERVER) && is_array($_SERVER) && isset($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : '';

$address = isset($_POST['address']) ? $_POST['address'] : '';
$address = isset($_GET['address']) ? urldecode($_GET['address']) : $address;
$addressEncode = urlencode($address);

set_time_limit(0);

if (isset($_POST['get']) || isset($_GET['get'])) {

    $latLngResults = getData('https://maps.googleapis.com/maps/api/geocode/json?address=' . $addressEncode);
    $latLngResults = json_decode($latLngResults, true);
    if (isset($latLngResults['status']) && $latLngResults['status'] == 'OK' && (isset($latLngResults['results'][0]['geometry']['location']) || isset($latLngResults['results']['geometry']['location']))) {

        $lat = '';
        $lng = '';
        if (isset($latLngResults['results']['geometry']['location'])) {
            $lat = $latLngResults['results']['geometry']['location']['lat'];
            $lng = $latLngResults['results']['geometry']['location']['lng'];
        } elseif ($latLngResults['results'][0]['geometry']['location']) {
            $lat = $latLngResults['results'][0]['geometry']['location']['lat'];
            $lng = $latLngResults['results'][0]['geometry']['location']['lng'];
        }

        if (!empty($lat) && !empty($lng)) {
            $ok = getData($protocol . $domain . ':9999/next_loc?lat=' . $lat . '&lon=' . $lng);

            if ($ok == 'ok') {
                sleep(10);
            }
        }
    }

    header('Location: ?address=' . $addressEncode);
    exit;
}

if (isset($_GET['data'])) {
    echo getData($protocol . $domain . ':9999/data');
    exit;
}

function getData($url)
{
    return file_get_contents($url);
}

function debug($data)
{
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}

?>

<!DOCTYPE html>
<html>
<head>
    <script src="http://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>"></script>
    <script>
        var oldMarker;
        var map;
        var infowindow;
        var myLocation = <?php echo !empty($address) ? "'" . $address . "'" : "''";?>;
        var markersArray = [];

        var centerMarkerSet = false;

        function initialize() {
            if (myLocation) {
                setAddressMarker(myLocation);
            }
            var pos = new google.maps.LatLng(51.508742, -0.120850);
            var mapProp = {
                center: pos,
                zoom: 16,
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                zoomControl: true,
                mapTypeControl: true,
                scaleControl: true,
                streetViewControl: true,
                rotateControl: true,
                fullscreenControl: true,
                styles: [{
                    "featureType": "landscape",
                    "stylers": [{"hue": "#FFBB00"}, {"saturation": 43.400000000000006}, {"lightness": 37.599999999999994}, {"gamma": 1}]
                }, {
                    "featureType": "road.highway",
                    "stylers": [{"hue": "#FFC200"}, {"saturation": -61.8}, {"lightness": 45.599999999999994}, {"gamma": 1}]
                }, {
                    "featureType": "road.arterial",
                    "stylers": [{"hue": "#FF0300"}, {"saturation": -100}, {"lightness": 51.19999999999999}, {"gamma": 1}]
                }, {
                    "featureType": "road.local",
                    "stylers": [{"hue": "#FF0300"}, {"saturation": -100}, {"lightness": 52}, {"gamma": 1}]
                }, {
                    "featureType": "water",
                    "stylers": [{"hue": "#0078FF"}, {"saturation": -13.200000000000003}, {"lightness": 2.4000000000000057}, {"gamma": 1}]
                }, {
                    "featureType": "poi",
                    "stylers": [{"hue": "#00FF6A"}, {"saturation": -1.0989010989011234}, {"lightness": 11.200000000000017}, {"gamma": 1}]
                }]
            };
            map = new google.maps.Map(document.getElementById("googleMap"), mapProp);

            infowindow = new google.maps.InfoWindow({
                content: ''
            });
            if (!myLocation) {
                placeMarker(pos, 'You are here!');
                currentLocation();
            }

            getPokemons();
            window.setInterval(function () {
                map.clearOverlays();
                getPokemons();
            }, 10000);
        }

        google.maps.event.addDomListener(window, 'load', initialize);

        google.maps.Map.prototype.clearOverlays = function() {
            for (var i = 0; i < markersArray.length; i++ ) {
                markersArray[i].setMap(null);
            }
            markersArray.length = 0;
        }

        function getPokemons() {
            var infowindow = new google.maps.InfoWindow({
                content: ""
            });

            var result = httpGet('<?php echo $protocol . $domain . '/?data=true'; ?>');
            var pokemons = JSON.parse(result);

            if (pokemons) {

                for (var i = 0; i < pokemons.length; i++) {
                    var pokemon = pokemons[i];

                    if(pokemon.key == 'start-position' && pokemon.type == 'custom' && centerMarkerSet == false){
                        var pos = new google.maps.LatLng(pokemon.lat, pokemon.lng);
                        placeMarker(pos, 'You are here!');
                        centerMarkerSet = true;
                    }

                    if (pokemon.type != 'pokemon') {
                        continue;
                    }

                    var cleanNumber = pokemon.icon.replace('static/icons/', '');
                    cleanNumber = cleanNumber.replace('.png', '');

                    var latLng = new google.maps.LatLng(pokemon.lat, pokemon.lng);
                    var marker = new google.maps.Marker({
                        position: latLng,
                        map: map,
                        title: pokemon.name,
                        icon: 'icons/' + cleanNumber + '.png'
                    });

                    markersArray.push(marker);

                    bindInfoWindow(marker, map, infowindow, pokemon.infobox);
                }
            }
        }

        function currentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function (position) {
                    var pos = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);
                    map.setCenter(pos);
                    placeMarker(pos, 'You are here!');
                    getAddressLatLng(position.coords.latitude, position.coords.longitude);
                });
            }
        }

        function placeMarker(location, title) {
            marker = new google.maps.Marker({
                position: location,
                map: map,
                draggable: true,
                animation: google.maps.Animation.DROP,
                title: title
            });
            if (oldMarker !== undefined) {
                oldMarker.setMap(null);
            }
            oldMarker = marker;
            google.maps.event.addListener(oldMarker, 'click', function () {
                infowindow.setContent(title);
                infowindow.open(map, oldMarker);
            });

            google.maps.event.addListener(oldMarker, 'dragend', function (event) {
                getAddressLatLng(event.latLng.lat(), event.latLng.lng());
            });

            map.setCenter(location);
        }

        function setAddressMarker(address) {
            var geocoder = new google.maps.Geocoder();
            geocoder.geocode({'address': address}, function (results, status) {
                if (status == google.maps.GeocoderStatus.OK) {
                    placeMarker(results[0].geometry.location, 'You are here!');
                }
            });
        }

        function bindInfoWindow(marker, map, infowindow, description) {
            marker.addListener('click', function () {
                infowindow.setContent(description);
                infowindow.open(map, this);
            });
        }

        function getAddressLatLng(lat, lng) {
            var xmlhttp = new XMLHttpRequest();

            xmlhttp.onreadystatechange = function () {
                if (xmlhttp.readyState == XMLHttpRequest.DONE) {
                    if (xmlhttp.status == 200) {
                        var result = xmlhttp.responseText;
                        var parsed = JSON.parse(result);

                        if (parsed.results[0].formatted_address) {
                            document.getElementById("address").value = parsed.results[0].formatted_address;
                        } else if (parsed.results.formatted_address) {
                            document.getElementById("address").value = parsed.results.formatted_address;
                        }
                    }
                    else if (xmlhttp.status == 400) {
                        alert('There was an error 400');
                    }
                    else {
                        alert('something else other than 200 was returned');
                    }
                }
            };

            xmlhttp.open("GET", "http://maps.googleapis.com/maps/api/geocode/json?latlng=" + lat + "," + lng + "&sensor=true", true);
            xmlhttp.send();
        }

        function httpGet(theUrl) {
            var xmlHttp = new XMLHttpRequest();
            xmlHttp.open("GET", theUrl, false);
            xmlHttp.send(null);
            return xmlHttp.responseText;
        }

        function arrayContains(needle, arrhaystack)
        {
            return (arrhaystack.indexOf(needle) > -1);
        }

        var setLabelTime = function(){

            var elements = document.getElementsByClassName('label-countdown');

            for (var i = 0; i < elements.length; i++) {
                var element = elements[i];

                var disappearsAt = new Date(parseInt(element.getAttribute("disappears-at"))*1000);
                var now = new Date();

                var difference = Math.abs(disappearsAt - now);
                var hours = Math.floor(difference / 36e5);
                var minutes = Math.floor((difference - (hours * 36e5)) / 6e4);
                var seconds = Math.floor((difference - (hours * 36e5) - (minutes * 6e4)) / 1e3);

                if(disappearsAt < now){
                    timestring = "(expired)";
                }
                else {
                    timestring = "(";
                    if(hours > 0)
                        timestring = hours + "h";

                    timestring += ("0" + minutes).slice(-2) + "m";
                    timestring += ("0" + seconds).slice(-2) + "s";
                    timestring += ")";
                }

                element.innerHTML = timestring;
            }
        };

        window.setInterval(setLabelTime, 1000);

    </script>

</head>

<body>

<form action="<?php echo $protocol . $domain; ?>" method="POST" style="margin-bottom: 20px">
    <textarea id="address" name="address" rows="2"
              cols="50"><?php echo !empty($address) ? $address : ''; ?></textarea></br></br>
    <input type="submit" name="get" value="Get pokemon"> | <input type="button" value="Get Current Location"
                                                                  onclick="currentLocation(); return false;">
</form>

<div id="googleMap" style="width:100%;height:600px;margin-bottom: 20px;"></div>

</body>

</html>
