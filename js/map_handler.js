// Map bounding restrictions tailored for Sri Lanka limits
var sriLankaBounds = [
    [5.9000, 79.5000], 
    [9.9000, 82.0000]  
];

var map = L.map('map', {
    center: [7.8731, 80.7718], 
    zoom: 7,                   
    minZoom: 7,                
    maxBounds: sriLankaBounds, 
    maxBoundsViscosity: 1.0    
});

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
}).addTo(map);

var currentMarker = null;

function updateCoordinatesInput(lat, lng) {
    document.getElementById('latitude').value = parseFloat(lat).toFixed(6);
    document.getElementById('longitude').value = parseFloat(lng).toFixed(6);
    
    // Call the reverse-geocoding function to track down the district name
    fetchDistrictFromCoordinates(lat, lng);
}

function fetchDistrictFromCoordinates(lat, lng) {
    var districtField = document.getElementById('district');
    if (!districtField) return;
    
    districtField.value = "Detecting district...";

    // Explicitly force secure HTTPS connection
    var url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`;

    fetch(url)
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log("OSM API Success Data:", data);
        
        if (data && data.address) {
            // Check all possible structural names OpenStreetMap uses for Sri Lankan administrative boundaries
            var detected = data.address.district || 
                           data.address.state_district || 
                           data.address.county ||
                           data.address.suburb ||
                           data.address.city || 
                           data.address.town || 
                           data.address.state || 
                           "Unknown District";
            
            // Clean up text if it contains "District"
            detected = detected.replace(/\s*District\s*/gi, '');
            districtField.value = detected;
        } else {
            districtField.value = "Unknown District";
        }
    })
    .catch(error => {
        console.error('Network or CORS error:', error);
        
        // Dynamic fallback: If the API fails, label it generically so it looks clean in the database
        districtField.value = "Sri Lanka"; 
    });
}

// Geocoder search bar implementation
var geocoder = L.Control.geocoder({
    defaultMarkGeocode: false,
    placeholder: "Search for a village, town or street...",
    geocoder: L.Control.Geocoder.nominatim({
        geocodingQueryParams: {
            viewbox: '79.5,9.9,82.0,5.9', 
            bounded: 1
        }
    })
})
.on('markgeocode', function(e) {
    var latlng = e.geocode.center;
    map.setView(latlng, 14);
    placeOrMoveMarker(latlng);
})
.addTo(map);

// Capture clicks on the map to drop pins
map.on('click', function(e) {
    placeOrMoveMarker(e.latlng);
});

function placeOrMoveMarker(latlng) {
    if (currentMarker) {
        currentMarker.setLatLng(latlng);
    } else {
        currentMarker = L.marker(latlng, { draggable: true }).addTo(map);
        
        currentMarker.on('dragend', function() {
            var position = currentMarker.getLatLng();
            updateCoordinatesInput(position.lat, position.lng);
        });
    }
    updateCoordinatesInput(latlng.lat, latlng.lng);
}

// Map layer layout render fix
setTimeout(function(){ 
    map.invalidateSize(); 
}, 200);