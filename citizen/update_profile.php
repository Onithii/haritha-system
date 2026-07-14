<?php
// Start session to grab the logged-in user's ID
session_start();
include("../config/db.php");

// Mocking session user ID check (replace with your actual session configuration key)
if (!isset($_SESSION['user_id'])) {
    // Redirect to login if not authenticated
    // header("Location: login.php");
    // exit();
    $_SESSION['user_id'] = 1; // Temporary fallback for fallback staging
}

$current_user_id = $_SESSION['user_id'];
$errors = [];
$success_message = "";

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
if (isset($_POST['update_profile'])) {
    $f_name      = trim($_POST['f_name']);
    $l_name      = trim($_POST['l_name']);
    $nic         = trim($_POST['nic']);
    $phone       = trim($_POST['phone_number']);
    $email       = trim($_POST['email']);
    $username    = trim($_POST['username']);
    $password    = $_POST['password']; // Can be blank if keeping old one
    $address     = trim($_POST['address']);
    $gn_division = trim($_POST['gn_division']);
    $ds_division = trim($_POST['ds_division']);
    $district    = trim($_POST['district']);
    
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
    if (!empty($password) && strlen($password) < 6) {
        $errors[] = "New password must be at least 6 characters long.";
    }

    if (empty($errors)) {
        // Exclude the CURRENT user from the constraint check when matching credentials
        $check_sql = "SELECT user_id FROM users WHERE (username = ? OR email = ? OR nic = ?) AND user_id != ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($stmt, "sssi", $username, $email, $nic, $current_user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = "Username, Email, or NIC number is already taken by another user.";
            mysqli_stmt_close($stmt);
        } else {
            mysqli_stmt_close($stmt);

            // Dynamically construct SQL string depending on whether password is changing
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $sql = "UPDATE users SET 
                        f_name = ?, l_name = ?, nic = ?, phone_number = ?, email = ?, 
                        username = ?, password = ?, address = ?, gn_division = ?, 
                        ds_division = ?, district = ?, office_latitude = ?, office_longitude = ? 
                        WHERE user_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "sssssssssssssi", 
                    $f_name, $l_name, $nic, $phone, $email, 
                    $username, $hashed_password, $address, $gn_division, 
                    $ds_division, $district, $latitude, $longitude, $current_user_id
                );
            } else {
                $sql = "UPDATE users SET 
                        f_name = ?, l_name = ?, nic = ?, phone_number = ?, email = ?, 
                        username = ?, address = ?, gn_division = ?, ds_division = ?, 
                        district = ?, office_latitude = ?, office_longitude = ? 
                        WHERE user_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ssssssssssssi", 
                    $f_name, $l_name, $nic, $phone, $email, 
                    $username, $address, $gn_division, $ds_division, 
                    $district, $latitude, $longitude, $current_user_id
                );
            }

            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Profile configuration successfully updated.";
            } else {
                $errors[] = "Database Error: Update statement execution failure.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// --- FETCH ORIGINAL CITIZEN VALUES ON INITIAL PAGE LOAD ---
$fetch_sql = "SELECT * FROM users WHERE user_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $fetch_sql);
mysqli_stmt_bind_param($stmt, "i", $current_user_id);
mysqli_stmt_execute($stmt);
$user_res = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($user_res);
mysqli_stmt_close($stmt);

if (!$user) {
    die("User profile target data allocation error.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile</title>
    
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
        .success-box {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 5px solid #4caf50;
            font-weight: bold;
        }
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
        .back-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #2e7d32;
            text-decoration: none;
            font-size: 14px;
        }
        .back-link:hover { text-decoration: underline; }
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
        <h2>Update Profile</h2>

        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="success-box">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="text" name="f_name" placeholder="First Name" value="<?php echo htmlspecialchars($user['f_name']); ?>" required>
            <input type="text" name="l_name" placeholder="Last Name" value="<?php echo htmlspecialchars($user['l_name']); ?>" required>
            <input type="text" name="nic" placeholder="NIC Number" value="<?php echo htmlspecialchars($user['nic']); ?>" required>
            <input type="text" name="phone_number" placeholder="Phone Number" value="<?php echo htmlspecialchars($user['phone_number']); ?>" required>
            <input type="email" name="email" placeholder="Email Address" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            <input type="text" name="username" placeholder="Username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            <input type="password" name="password" placeholder="Leave blank to keep existing password">
            <input type="text" name="address" placeholder="Street Address" value="<?php echo htmlspecialchars($user['address']); ?>" required>
            
            <input type="text" id="gn_division" name="gn_division" placeholder="GN Division" class="map-filled-field" value="<?php echo htmlspecialchars($user['gn_division']); ?>" required>
            <input type="text" id="ds_division" name="ds_division" placeholder="DS Division" class="map-filled-field" value="<?php echo htmlspecialchars($user['ds_division']); ?>" required>
            <input type="text" id="district" name="district" placeholder="District" class="map-filled-field" value="<?php echo htmlspecialchars($user['district']); ?>" required>
            
            <input type="hidden" id="office_latitude" name="office_latitude" value="<?php echo htmlspecialchars($user['office_latitude']); ?>">
            <input type="hidden" id="office_longitude" name="office_longitude" value="<?php echo htmlspecialchars($user['office_longitude']); ?>">
            
            <button type="submit" name="update_profile">Save Changes</button>
            <a href="../citizen/citizen_dash.php" class="back-link">← Return to Dashboard</a>
        </form>
    </div>

    <div class="map-container">
        <div class="map-instruction">📍 Click map or search to alter residential anchor location</div>
        <div id="map"></div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>

<script>
    const sriLankaBounds = L.latLngBounds(
        L.latLng(5.9, 79.5), 
        L.latLng(9.9, 82.0)  
    );

    // Pull database coordinates to position map viewport focus safely
    const existingLat = parseFloat(document.getElementById('office_latitude').value) || 7.8731;
    const existingLng = parseFloat(document.getElementById('office_longitude').value) || 80.7718;
    const initialZoom = (document.getElementById('office_latitude').value) ? 14 : 8;

    const map = L.map('map', {
        center: [existingLat, existingLng],
        zoom: initialZoom,
        minZoom: 7,
        maxBounds: sriLankaBounds,
        maxBoundsViscosity: 1.0 
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Pre-populate with marker pin pointing to saved house location if it exists
    let marker;
    if (document.getElementById('office_latitude').value) {
        marker = L.marker([existingLat, existingLng]).addTo(map);
    }

    const geocoder = L.Control.geocoder({
        defaultMarkGeocode: false,
        placeholder: "Search Town, Village or City..."
    })
    .on('markgeocode', function(e) {
        const latlng = e.geocode.center;
        map.setView(latlng, 14);
        updateSelectedLocation(latlng.lat, latlng.lng);
    })
    .addTo(map);

    map.on('click', function(e) {
        updateSelectedLocation(e.latlng.lat, e.latlng.lng);
    });

    function updateSelectedLocation(lat, lng) {
        document.getElementById('office_latitude').value = lat.toFixed(8);
        document.getElementById('office_longitude').value = lng.toFixed(8);

        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng]).addTo(map);
        }

        // Call current update script structure dynamically
        fetch(`update_profile.php?action=geocode&lat=${lat}&lng=${lng}`)
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    document.getElementById('gn_division').value = data.gn_division || '';
                    document.getElementById('ds_division').value = data.ds_division || '';
                    document.getElementById('district').value = data.district || '';
                }
            })
            .catch(error => {
                console.error('Mapping backend helper exception:', error);
            });
    }
</script>
</body>
</html>