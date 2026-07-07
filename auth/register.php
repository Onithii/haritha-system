<?php
include("../config/db.php");

// Initialize an array to track validation errors
$errors = [];

// --- AJAX ENDPOINT FOR REVERSE GEOCODING ---
if (isset($_GET['action']) && $_GET['action'] === 'geocode' && isset($_GET['lat']) && isset($_GET['lng'])) {
    header('Content-Type: application/json');
    
    $lat = urlencode($_GET['lat']);
    $lng = urlencode($_GET['lng']);
    
    $url = "https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat={$lat}&lon={$lng}&addressdetails=1&zoom=18";
    
    $options = [
        "http" => [
            "header" => "User-Agent: SriLankaCitizenRegistryApp/2.0\r\n"
        ]
    ];
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === FALSE) {
        echo json_encode(['success' => false, 'message' => 'Geocoding service unavailable']);
        exit();
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['address'])) {
        $addr = $data['address'];
        
        $district    = $addr['county'] ?? $addr['state_district'] ?? $addr['state'] ?? '';
        $ds_division = $addr['city_district'] ?? $addr['municipality'] ?? $addr['town'] ?? $addr['city'] ?? '';
        $gn_division = $addr['suburb'] ?? $addr['neighbourhood'] ?? $addr['village'] ?? $addr['hamlet'] ?? '';
        
        $district    = trim(str_replace(' District', '', $district));
        $ds_division = trim(str_replace(' Divisional Secretariat', '', $ds_division));
        
        echo json_encode([
            'success' => true,
            'gn_division' => $gn_division,
            'ds_division' => $ds_division,
            'district' => $district
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No location data found']);
    }
    exit();
}

// --- STANDARD FORM PROCESSING ---
if (isset($_POST['register'])) {
    $f_name      = trim($_POST['f_name']);
    $l_name      = trim($_POST['l_name']);
    $nic         = trim($_POST['nic']);
    $phone       = trim($_POST['phone_number']);
    $email       = trim($_POST['email']);
    $username    = trim($_POST['username']);
    $password    = $_POST['password']; 
    $address     = trim($_POST['address']);
    $gn_division = trim($_POST['gn_division']);
    $ds_division = trim($_POST['ds_division']);
    $district    = trim($_POST['district']);
    
    // Capturing coordinates from map selection
    $latitude    = !empty($_POST['office_latitude']) ? trim($_POST['office_latitude']) : null;
    $longitude   = !empty($_POST['office_longitude']) ? trim($_POST['office_longitude']) : null;

    // Server-side validation
    if (empty($f_name) || empty($l_name) || empty($address) || empty($gn_division) || empty($ds_division) || empty($district)) {
        $errors[] = "All text and location fields are required.";
    }
    if (empty($latitude) || empty($longitude)) {
        $errors[] = "Please select your residential location on the map.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    if (!preg_match("/^[0-9]{10}$/", $phone)) {
        $errors[] = "Phone number must be exactly 10 digits.";
    }
    if (!preg_match("/^([0-9]{9}[vVxX]|[0-9]{12})$/", $nic)) {
        $errors[] = "Invalid NIC format.";
    }
    if (!preg_match("/^[a-zA-Z0-9_]{4,20}$/", $username)) {
        $errors[] = "Username must be 4-20 characters long.";
    }
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }

    if (empty($errors)) {
        $check_sql = "SELECT user_id FROM users WHERE username = ? OR email = ? OR nic = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($stmt, "sss", $username, $email, $nic);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = "Username, Email, or NIC number is already registered.";
            mysqli_stmt_close($stmt);
        } else {
            mysqli_stmt_close($stmt);

            $role_id = 1; // Citizen Role ID
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            // Prepared statement updated to match your exact database columns
            $sql = "INSERT INTO users (
                        f_name, l_name, nic, phone_number, email, 
                        username, password, role_id, address, 
                        gn_division, ds_division, district,
                        office_latitude, office_longitude
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param(
                    $stmt, 
                    "sssssssissssss", 
                    $f_name, $l_name, $nic, $phone, $email, 
                    $username, $hashed_password, $role_id, $address, 
                    $gn_division, $ds_division, $district,
                    $latitude, $longitude
                );

                if (mysqli_stmt_execute($stmt)) {
                    header("Location: ../citizen/citizen_dash.php");
                    exit();
                } else {
                    $errors[] = "Database Error: Registration failed to execute.";
                }
                mysqli_stmt_close($stmt);
            } else {
                $errors[] = "Database Error: Prepared statement initialization failed.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Registration</title>
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f2f5f2;
            margin: 0;
            padding: 20px;
        }
        .container-flex {
            display: flex;
            max-width: 1200px;
            margin: 10px auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0px 2px 12px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        .form-container {
            width: 50%;
            padding: 25px;
            box-sizing: border-box;
            max-height: 90vh;
            overflow-y: auto;
        }
        .map-container {
            width: 50%;
            position: relative;
            background: #e5e5e5;
        }
        #map {
            width: 100%;
            height: 100%;
            min-height: 680px;
        }
        h2 {
            text-align: center;
            color: #2e7d32;
            margin-top: 0;
        }
        .error-box {
            background-color: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 5px solid #e53935;
        }
        .error-box ul { margin: 0; padding-left: 20px; }
        input {
            width: 100%;
            padding: 10px;
            margin: 6px 0;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .map-filled-field {
            background-color: #fafdfa;
            border-left: 4px solid #4caf50;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #2e7d32;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 12px;
            font-size: 16px;
            font-weight: bold;
        }
        button:hover { background: #1b5e20; }
        .map-instruction {
            position: absolute;
            top: 15px;
            left: 55px;
            z-index: 1000;
            background: rgba(46, 125, 50, 0.9);
            color: white;
            padding: 8px 14px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 13px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
            pointer-events: none;
        }
    </style>
</head>
<body>

<div class="container-flex">
    <div class="form-container">
        <h2>Citizen Registration</h2>

        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="text" name="f_name" placeholder="First Name" value="<?php echo isset($_POST['f_name']) ? htmlspecialchars($_POST['f_name']) : ''; ?>" required>
            <input type="text" name="l_name" placeholder="Last Name" value="<?php echo isset($_POST['l_name']) ? htmlspecialchars($_POST['l_name']) : ''; ?>" required>
            <input type="text" name="nic" placeholder="NIC Number" value="<?php echo isset($_POST['nic']) ? htmlspecialchars($_POST['nic']) : ''; ?>" required>
            <input type="text" name="phone_number" placeholder="Phone Number" value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>" required>
            <input type="email" name="email" placeholder="Email Address" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            <input type="text" name="username" placeholder="Username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            <input type="password" name="password" placeholder="Password (Min 6 characters)" required>
            <input type="text" name="address" placeholder="Street Address" value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>" required>
            
            <input type="text" id="gn_division" name="gn_division" placeholder="GN Division" class="map-filled-field" value="<?php echo isset($_POST['gn_division']) ? htmlspecialchars($_POST['gn_division']) : ''; ?>" required>
            <input type="text" id="ds_division" name="ds_division" placeholder="DS Division" class="map-filled-field" value="<?php echo isset($_POST['ds_division']) ? htmlspecialchars($_POST['ds_division']) : ''; ?>" required>
            <input type="text" id="district" name="district" placeholder="District" class="map-filled-field" value="<?php echo isset($_POST['district']) ? htmlspecialchars($_POST['district']) : ''; ?>" required>
            
            <input type="hidden" id="office_latitude" name="office_latitude" value="<?php echo isset($_POST['office_latitude']) ? htmlspecialchars($_POST['office_latitude']) : ''; ?>">
            <input type="hidden" id="office_longitude" name="office_longitude" value="<?php echo isset($_POST['office_longitude']) ? htmlspecialchars($_POST['office_longitude']) : ''; ?>">
            
            <button type="submit" name="register">Register</button>
        </form>
    </div>

    <div class="map-container">
        <div class="map-instruction">📍 Search or Click your home on the map</div>
        <div id="map"></div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>

<script>
    // 1. Defining bounding boxes strict to Sri Lanka bounds map region 
    const sriLankaBounds = L.latLngBounds(
        L.latLng(5.9, 79.5), // South West boundary
        L.latLng(9.9, 82.0)  // North East boundary
    );

    // Initialize Map constrained specifically to Sri Lanka boundaries
    const map = L.map('map', {
        center: [7.8731, 80.7718],
        zoom: 8,
        minZoom: 7,
        maxBounds: sriLankaBounds,
        maxBoundsViscosity: 1.0 // Bounces user back if they try to pan completely outside Sri Lanka
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let marker;

    // 2. Add Search Bar directly inside the Leaflet Control interface
    const geocoder = L.Control.geocoder({
        defaultMarkGeocode: false,
        placeholder: "Search Town, Village or City..."
    })
    .on('markgeocode', function(e) {
        const latlng = e.geocode.center;
        map.setView(latlng, 14); // Zoom closely into searched location
        updateSelectedLocation(latlng.lat, latlng.lng);
    })
    .addTo(map);

    // 3. Handle Direct Manual Clicks on Map
    map.on('click', function(e) {
        updateSelectedLocation(e.latlng.lat, e.latlng.lng);
    });

    // Unified function to handle rendering pins, updating fields & coordinates
    function updateSelectedLocation(lat, lng) {
        // Set values to hidden form coordinate inputs
        document.getElementById('office_latitude').value = lat.toFixed(8);
        document.getElementById('office_longitude').value = lng.toFixed(8);

        // Manage Marker presentation
        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng]).addTo(map);
        }

        // Fetch administrative boundary data details
        fetch(`register.php?action=geocode&lat=${lat}&lng=${lng}`)
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    document.getElementById('gn_division').value = data.gn_division || '';
                    document.getElementById('ds_division').value = data.ds_division || '';
                    document.getElementById('district').value = data.district || '';
                }
            })
            .catch(error => {
                console.error('Mapping helper connection issue:', error);
            });
    }
</script>
</body>
</html>