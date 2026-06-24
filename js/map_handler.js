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

// Map layer rendering fix
setTimeout(function(){ 
    map.invalidateSize(); 
}, 200);