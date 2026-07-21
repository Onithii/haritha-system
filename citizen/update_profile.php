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

// Fetch Active System Broadcast Alerts for Header
$current_time = date('Y-m-d H:i:s');
$broadcast_query = "SELECT title, message, created_at FROM messages 
                    WHERE expires_at IS NULL OR expires_at > '$current_time' 
                    ORDER BY created_at DESC";
$broadcast_result = mysqli_query($conn, $broadcast_query);
$notification_count = $broadcast_result ? mysqli_num_rows($broadcast_result) : 0;

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
    <title>Update Profile - Haritha</title>
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    
    <style>
        :root {
            --primary-color: #1b5e20;
            --primary-hover: #144718;
            --bg-color: #f4f6f8;
            --card-bg: #ffffff;
            --text-main: #2c3e50;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            --error-bg: #f8d7da;
            --error-text: #721c24;
            --success-bg: #d4edda;
            --success-text: #155724;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0;
            color: var(--text-main);
        }

        /* --- Modern Top Navbar Header --- */
        .header {
            background-color: var(--primary-color);
            color: white;
            padding: 15px 8%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-home {
            color: white;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 6px;
            transition: background-color 0.2s;
        }

        .btn-home:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }

        .header-title-container h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification-trigger {
            position: relative;
            background: rgba(255, 255, 255, 0.15);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
        }

        .notification-trigger:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        .notification-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background-color: #e74c3c;
            color: white;
            font-size: 11px;
            font-weight: bold;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--primary-color);
        }

        /* --- Split View Container Layout --- */
        .container-flex {
            display: flex;
            max-width: 1200px;
            margin: 30px auto;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .form-container {
            width: 50%;
            padding: 35px;
            box-sizing: border-box;
            max-height: 82vh;
            overflow-y: auto;
        }

        .form-container h2 {
            margin-top: 0;
            margin-bottom: 25px;
            font-size: 22px;
            color: var(--text-main);
            border-bottom: 2px solid #e8f5e9;
            padding-bottom: 12px;
        }

        .map-container {
            width: 50%;
            position: relative;
            background: #e5e5e5;
        }

        #map {
            width: 100%;
            height: 100%;
            min-height: 600px;
        }

        .error-box {
            background-color: var(--error-bg);
            color: var(--error-text);
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            font-size: 14px;
        }

        .error-box ul { margin: 0; padding-left: 20px; }

        .success-box {
            background-color: var(--success-bg);
            color: var(--success-text);
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            font-size: 14px;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 6px;
            color: var(--text-main);
        }

        .form-group input {
            width: 100%;
            padding: 10px 14px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            font-family: inherit;
            font-size: 14px;
            box-sizing: border-box;
            background-color: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.1);
        }

        .map-filled-field {
            background-color: #f8f9fa;
            border-left: 4px solid var(--primary-color) !important;
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 15px;
            font-size: 15px;
            font-weight: 600;
            transition: background 0.2s;
        }

        .btn-submit:hover {
            background: var(--primary-hover);
        }

        .map-instruction {
            position: absolute;
            top: 15px;
            left: 55px;
            z-index: 1000;
            background: rgba(27, 94, 32, 0.9);
            color: white;
            padding: 8px 14px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            pointer-events: none;
        }

        /* --- Slide-Out Notifications Side Drawer --- */
        .noti-panel {
            position: fixed;
            top: 0;
            right: -400px;
            width: 360px;
            height: 100%;
            background-color: #ffffff;
            box-shadow: -4px 0 20px rgba(0,0,0,0.15);
            transition: right 0.3s ease;
            z-index: 1001;
            padding: 24px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }

        .noti-panel.open { right: 0; }

        .noti-panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .noti-panel-header h2 { margin: 0; font-size: 18px; color: var(--text-main); }

        .close-noti-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 24px;
            cursor: pointer;
        }

        .noti-list-wrapper { flex-grow: 1; overflow-y: auto; }

        .noti-item {
            background: #f8f9fa;
            border-left: 3px solid var(--primary-color);
            padding: 14px;
            margin-bottom: 12px;
            border-radius: 4px;
        }

        .noti-item h4 { margin: 0 0 6px 0; font-size: 14px; color: var(--text-main); }
        .noti-item p { margin: 0 0 8px 0; font-size: 12px; color: var(--text-muted); line-height: 1.4; }
        .noti-time { font-size: 10px; color: #aaa; display: block; text-align: right; }

        .noti-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.2);
            display: none;
            z-index: 1000;
        }

        .no-noti-msg { color: var(--text-muted); font-style: italic; text-align: center; padding: 20px; }

        @media (max-width: 900px) {
            .container-flex {
                flex-direction: column;
            }
            .form-container, .map-container {
                width: 100%;
            }
            .form-container {
                max-height: none;
            }
            #map {
                min-height: 400px;
            }
        }
    </style>
</head>
<body>

<!-- Header Navigation Bar -->
<div class="header">
    <div class="header-left">
        <a href="../citizen/citizen_dash.php" class="btn-home">&larr; Back to Dashboard</a>
    </div>

    <div class="header-title-container">
        <h1>Citizen Dashboard</h1>
    </div>

    <div class="header-right">
        <div class="notification-trigger" onclick="toggleNotificationPanel(true)" title="Announcements">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <?php if ($notification_count > 0): ?>
                <div class="notification-badge" id="notiBadge"><?php echo $notification_count; ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Notification Overlay & Drawer Panel -->
<div id="notiOverlay" class="noti-overlay" onclick="toggleNotificationPanel(false)"></div>

<div id="notificationSidePanel" class="noti-panel">
    <div class="noti-panel-header">
        <h2>System Announcements</h2>
        <button class="close-noti-btn" onclick="toggleNotificationPanel(false)">&times;</button>
    </div>
    
    <div class="noti-list-wrapper">
        <?php if ($notification_count > 0): ?>
            <?php while ($msg = mysqli_fetch_assoc($broadcast_result)): ?>
                <div class="noti-item">
                    <h4><?php echo htmlspecialchars($msg['title']); ?></h4>
                    <p><?php echo htmlspecialchars($msg['message']); ?></p>
                    <span class="noti-time"><?php echo date('M d, Y h:i A', strtotime($msg['created_at'])); ?></span>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="no-noti-msg">No active broadcast notices to display.</p>
        <?php endif; ?>
    </div>
</div>

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
            <div class="form-group">
                <label for="f_name">First Name *</label>
                <input type="text" id="f_name" name="f_name" placeholder="First Name" value="<?php echo htmlspecialchars($user['f_name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="l_name">Last Name *</label>
                <input type="text" id="l_name" name="l_name" placeholder="Last Name" value="<?php echo htmlspecialchars($user['l_name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="nic">NIC Number *</label>
                <input type="text" id="nic" name="nic" placeholder="NIC Number" value="<?php echo htmlspecialchars($user['nic']); ?>" required>
            </div>

            <div class="form-group">
                <label for="phone_number">Phone Number *</label>
                <input type="text" id="phone_number" name="phone_number" placeholder="Phone Number" value="<?php echo htmlspecialchars($user['phone_number']); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" placeholder="Email Address" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" id="username" name="username" placeholder="Username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>

            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" placeholder="Leave blank to keep existing password">
            </div>

            <div class="form-group">
                <label for="address">Street Address *</label>
                <input type="text" id="address" name="address" placeholder="Street Address" value="<?php echo htmlspecialchars($user['address']); ?>" required>
            </div>

            <div class="form-group">
                <label for="gn_division">GN Division *</label>
                <input type="text" id="gn_division" name="gn_division" placeholder="GN Division" class="map-filled-field" value="<?php echo htmlspecialchars($user['gn_division']); ?>" required>
            </div>

            <div class="form-group">
                <label for="ds_division">DS Division *</label>
                <input type="text" id="ds_division" name="ds_division" placeholder="DS Division" class="map-filled-field" value="<?php echo htmlspecialchars($user['ds_division']); ?>" required>
            </div>

            <div class="form-group">
                <label for="district">District *</label>
                <input type="text" id="district" name="district" placeholder="District" class="map-filled-field" value="<?php echo htmlspecialchars($user['district']); ?>" required>
            </div>
            
            <input type="hidden" id="office_latitude" name="office_latitude" value="<?php echo htmlspecialchars($user['office_latitude']); ?>">
            <input type="hidden" id="office_longitude" name="office_longitude" value="<?php echo htmlspecialchars($user['office_longitude']); ?>">
            
            <button type="submit" name="update_profile" class="btn-submit">Save Changes</button>
        </form>
    </div>

    <div class="map-container">
        <div class="map-instruction">📍 Click map or search to alter residential location</div>
        <div id="map"></div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
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

    // Pre-populate with marker pin pointing to saved location if it exists
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

    // Drawer Toggle Logic
    document.addEventListener("DOMContentLoaded", function() {
        var badge = document.getElementById('notiBadge');
        if (badge && localStorage.getItem('notificationsViewed') === 'true') {
            badge.remove();
        }
    });

    function toggleNotificationPanel(open) {
        var panel = document.getElementById('notificationSidePanel');
        var overlay = document.getElementById('notiOverlay');
        var badge = document.getElementById('notiBadge');
        
        if (open) {
            panel.classList.add('open');
            overlay.style.display = 'block';
            if (badge) {
                badge.remove();
            }
            localStorage.setItem('notificationsViewed', 'true');
        } else {
            panel.classList.remove('open');
            overlay.style.display = 'none';
        }
    }
</script>
</body>
</html>