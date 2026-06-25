<?php
session_start();
include("../config/db.php");

// 1. Secure Access Check: Ensure user is logged in and is a System Admin (Role 5)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 5 || empty($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

$success = "";
$error = "";

// 2. Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $f_name = trim($_POST['f_name']);
    $l_name = trim($_POST['l_name']);
    $nic = trim($_POST['nic']);
    $phone_number = trim($_POST['phone_number']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role_id = intval($_POST['role_id']);
    
    $office_address = trim($_POST['office_address']);
    $gn_division = trim($_POST['gn_division']);
    $ds_division = trim($_POST['ds_division']);
    $district = trim($_POST['district']);
    $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;

    if (empty($username) || empty($password) || empty($f_name) || empty($l_name) || empty($nic) || empty($phone_number) || empty($email) || empty($role_id)) {
        $error = "Please fill in all mandatory account profile fields (*).";
    } else {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $sql = "INSERT INTO users (f_name, l_name, nic, phone_number, email, username, password, role_id, address, gn_division, ds_division, district, office_latitude, office_longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sssssssissssdd", $f_name, $l_name, $nic, $phone_number, $email, $username, $hashed_password, $role_id, $office_address, $gn_division, $ds_division, $district, $latitude, $longitude);
            
            if (mysqli_stmt_execute($stmt)) {
                $success = "User account provisioned successfully!";
            } else {
                $error = "Error adding user: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Database statement syntax validation error.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Manage Users</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<div class="header">
    <a href="admin_dash.php" class="back-btn">⬅ Back to Dashboard</a>
    <h1>System Admin Control</h1>
    <p>Account Provisioning & Geolocation Mapping</p>
</div>

<div class="form-container">
    <h2>Add New System User Account</h2>
    
    <?php if(!empty($error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if(!empty($success)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="row">
            <div class="form-group">
                <label>First Name *</label>
                <input type="text" name="f_name" required placeholder="e.g., Haritha">
            </div>
            <div class="form-group">
                <label>Last Name *</label>
                <input type="text" name="l_name" required placeholder="e.g., Perera">
            </div>
        </div>

        <div class="row">
            <div class="form-group">
                <label>NIC Number *</label>
                <input type="text" name="nic" required placeholder="e.g., 199912345678">
            </div>
            <div class="form-group">
                <label>Phone Number *</label>
                <input type="text" name="phone_number" required placeholder="e.g., 0771234567">
            </div>
        </div>

        <div class="form-group">
            <label>Email Address *</label>
            <input type="email" name="email" required placeholder="e.g., contact@domain.com">
        </div>

        <div class="row">
            <div class="form-group">
                <label>Username (System Login ID) *</label>
                <input type="text" name="username" required placeholder="e.g., gn_cinnamon">
            </div>
            <div class="form-group">
                <label>Default Password *</label>
                <input type="password" name="password" required placeholder="Minimum 6 characters">
            </div>
        </div>

        <div class="row">
            <div class="form-group">
                <label>Assigned System Role *</label>
                <select name="role_id" required id="role_id">
                    <option value="">-- Select Role Assignment --</option>
                    <option value="4">Citizen</option>
                    <option value="2">Grama Niladhari (GN)</option>
                    <option value="3">Local Authority (LA)</option>
                    <option value="5">Divisional Secretariat (DS)</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Select Workspace / Office Location on Map</label>
            <span class="map-instruction">Use the search magnifying glass icon on the map to find a town/address, or drag the marker pin manually.</span>
            <div id="map"></div>
        </div>

        <div class="form-group">
            <label>Auto-Resolved Physical Address (Stored in 'address' column)</label>
            <input type="text" id="office_address" name="office_address" readonly placeholder="Click map or search to resolve address metrics">
        </div>

        <div class="row">
            <div class="form-group">
                <label>GN Division</label>
                <input type="text" id="gn_division" name="gn_division" readonly placeholder="Auto-populated">
            </div>
            <div class="form-group">
                <label>DS Division</label>
                <input type="text" id="ds_division" name="ds_division" readonly placeholder="Auto-populated">
            </div>
            <div class="form-group">
                <label>District</label>
                <input type="text" id="district" name="district" readonly placeholder="Auto-populated">
            </div>
        </div>

        <div class="row">
            <div class="form-group">
                <label>Calculated Office Latitude</label>
                <input type="text" id="latitude" name="latitude" readonly placeholder="Auto-calculated">
            </div>
            <div class="form-group">
                <label>Calculated Office Longitude</label>
                <input type="text" id="longitude" name="longitude" readonly placeholder="Auto-calculated">
            </div>
        </div>

        <button type="submit" class="submit-btn">Provision Account</button>
    </form>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
<script src="../js/admin_map.js"></script>
</body>
</html>