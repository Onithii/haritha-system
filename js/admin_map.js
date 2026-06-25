/**
 * Sri Lanka Boundary Spatial Geolocation Handler
 */
document.addEventListener("DOMContentLoaded", function () {
    // 1. Define Strict Geographic Boundaries for Sri Lanka
    var southWest = L.latLng(5.9000, 79.5000);
    var northEast = L.latLng(9.9500, 82.0000);
    var sriLankaBounds = L.latLngBounds(southWest, northEast);

    var defaultLat = 6.9271;
    var defaultLng = 79.8612; // Fixed from 'defaulting' to match defaultLng usage below
    
    // 2. Initialize Map with Constraint Enforcements
    var map = L.map('map', {
        maxBounds: sriLankaBounds,       // Lock camera movement within boundaries
        maxBoundsViscosity: 1.0,         // Strong snap-back effect if pulled outside
        minZoom: 7,                      // Limit zooming out to global map view
        maxZoom: 19
    }).setView([defaultLat, defaultLng], 12);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    var marker = L.marker([defaultLat, defaultLng], {draggable: true}).addTo(map);

    // 3. Initialize Search Control Restricted to Sri Lanka (Country Code: lk)
    var geocoder = L.Control.geocoder({
        defaultMarkGeocode: false,
        geocoder: L.Control.geocoder.nominatim({
            geocodingQueryParams: {
                countrycodes: 'lk' // Limit autocomplete results to Sri Lankan regions
            }
        })
    })
    .on('markgeocode', function(e) {
        var center = e.geocode.center;
        
        if (sriLankaBounds.contains(center)) {
            marker.setLatLng(center);
            map.setView(center, 15);
            updateGeocodedMetrics(center.lat, center.lng);
        } else {
            alert("The selected location falls outside Sri Lanka regional system boundaries.");
        }
    })
    .addTo(map);

    // Pin Drag Release Events
    marker.on('dragend', function (e) {
        var coord = marker.getLatLng();
        
        if (sriLankaBounds.contains(coord)) {
            updateGeocodedMetrics(coord.lat, coord.lng);
        } else {
            marker.setLatLng([defaultLat, defaultLng]);
            updateGeocodedMetrics(defaultLat, defaultLng);
            alert("Cannot position administrative profiles outside Sri Lanka.");
        }
    });

    // Map Interface Area Clicks
    map.on('click', function(e) {
        if (sriLankaBounds.contains(e.latlng)) {
            marker.setLatLng(e.latlng);
            updateGeocodedMetrics(e.latlng.lat, e.latlng.lng);
        }
    });

    // Core Reverse Geocoding API Engine
    function updateGeocodedMetrics(lat, lng) {
        document.getElementById('latitude').value = lat.toFixed(6);
        document.getElementById('longitude').value = lng.toFixed(6);

        var reverseUrl = "https://nominatim.openstreetmap.org/reverse?format=json&lat=" + lat + "&lon=" + lng + "&zoom=18&addressdetails=1&countrycodes=lk";

        fetch(reverseUrl)
            .then(response => response.json())
            .then(data => {
                if (data && data.display_name) {
                    document.getElementById('office_address').value = data.display_name;

                    var addr = data.address;
                    
                    var derivedGN = addr.suburb || addr.neighbourhood || addr.village || "N/A";
                    var derivedDS = addr.suburb || addr.city_district || addr.town || addr.city || "N/A";
                    var derivedDistrict = addr.state_district || addr.county || "N/A";

                    document.getElementById('gn_division').value = derivedGN;
                    document.getElementById('ds_division').value = derivedDS;
                    document.getElementById('district').value = derivedDistrict;
                }
            })
            .catch(error => {
                console.error("Reverse Geocoding Failure: ", error);
            });
    }
});