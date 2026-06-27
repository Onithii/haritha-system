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
$searched_user = null;
$search_query = "";

// 2. Handle User Search via Email
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action_type']) && $_POST['action_type'] == 'search_user') {
    $search_query = trim($_POST['search_email']);
    
    if (empty($search_query)) {
        $error = "Please enter an email address to query.";
    } else {
        $search_sql = "SELECT user_id, f_name, l_name, username, email, status, role_id, ds_division FROM users WHERE email = ?";
        $search_stmt = mysqli_prepare($conn, $search_sql);
        if ($search_stmt) {
            mysqli_stmt_bind_param($search_stmt, "s", $search_query);
            mysqli_stmt_execute($search_stmt);
            $result = mysqli_stmt_get_result($search_stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $searched_user = $row;
            } else {
                $error = "No registered user account found matching email: " . htmlspecialchars($search_query);
            }
            mysqli_stmt_close($search_stmt);
        }
    }
}

// 3. Handle Account Status Toggling (Deactivate / Activate)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action_type']) && $_POST['action_type'] == 'toggle_status') {
    $target_user_id = intval($_POST['target_user_id']);
    $current_status = trim($_POST['current_status']);
    $search_query = trim($_POST['search_email']); // Retain email reference for reloading the view
    
    if ($target_user_id === intval($_SESSION['user_id'])) {
        $error = "Security Guardrail: You cannot alter your own active session status.";
    } else {
        $new_status = ($current_status === 'ACTIVE') ? 'DEACTIVATED' : 'ACTIVE';
        
        $status_sql = "UPDATE users SET status = ? WHERE user_id = ?";
        $status_stmt = mysqli_prepare($conn, $status_sql);
        if ($status_stmt) {
            mysqli_stmt_bind_param($status_stmt, "si", $new_status, $target_user_id);
            if (mysqli_stmt_execute($status_stmt)) {
                $success = "User status altered to " . $new_status . " successfully!";
                
                // Re-fetch updated information to keep screen state clean
                $refetch_sql = "SELECT user_id, f_name, l_name, username, email, status, role_id, ds_division FROM users WHERE user_id = ?";
                $refetch_stmt = mysqli_prepare($conn, $refetch_sql);
                mysqli_stmt_bind_param($refetch_stmt, "i", $target_user_id);
                mysqli_stmt_execute($refetch_stmt);
                $searched_user = mysqli_fetch_assoc(mysqli_stmt_get_result($refetch_stmt));
                mysqli_stmt_close($refetch_stmt);
            } else {
                $error = "Failed to execution update sequence against database.";
            }
            mysqli_stmt_close($status_stmt);
        }
    }
}

// 4. Handle Form Submission (Account Provisioning)
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['action_type'])) {
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

        $sql = "INSERT INTO users (f_name, l_name, nic, phone_number, email, username, password, role_id, address, gn_division, ds_division, district, status, office_latitude, office_longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, ?)";
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
    <style>
       
    </style>
</head>
<body>

<div class="header">
    <a href="admin_dash.php" class="back-btn">⬅ Back to Dashboard</a>
    <h1>System Admin Control</h1>
    <p>Account Provisioning & Geolocation Mapping</p>
</div>

<div class="form-container">
    <h2>Add New System User Account</h2>
    
    <?php if(!empty($error) && !isset($_POST['action_type'])): ?>
        <div class="alert alert-error" style="background:#ffebee; color:#c62828; padding:10px; margin-bottom:15px; border-radius:4px;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if(!empty($success) && !isset($_POST['action_type'])): ?>
        <div class="alert alert-success" style="background:#e8f5e9; color:#2e7d32; padding:10px; margin-bottom:15px; border-radius:4px;"><?php echo htmlspecialchars($success); ?></div>
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

<div class="management-container">
    <h2>Search & Revoke User Sessions</h2>
    <p style="font-size: 0.85em; color: #666; margin-bottom: 10px;">Query an active profile by email to change authentication permissions.</p>

    <?php if(!empty($error) && isset($_POST['action_type'])): ?>
        <div class="alert alert-error" style="background:#ffebee; color:#c62828; padding:10px; margin-bottom:15px; border-radius:4px; font-size:0.9em;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if(!empty($success) && isset($_POST['action_type'])): ?>
        <div class="alert alert-success" style="background:#e8f5e9; color:#2e7d32; padding:10px; margin-bottom:15px; border-radius:4px; font-size:0.9em;"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="action_type" value="search_user">
        <div class="search-row">
            <input type="email" name="search_email" required placeholder="Enter exact email address (e.g., perera@domain.com)" value="<?php echo htmlspecialchars($search_query); ?>">
            <button type="submit" class="search-btn">Search Profile</button>
        </div>
    </form>

    <?php if ($searched_user): 
        $role_label = "Citizen";
        if($searched_user['role_id'] == 2) $role_label = "Grama Niladhari (GN)";
        if($searched_user['role_id'] == 3) $role_label = "Local Authority (LA)";
        if($searched_user['role_id'] == 5) $role_label = "Divisional Secretariat (DS)";
    ?>
        <div class="result-card">
            <h3>Search Result Mapping</h3>
            <hr style="border:0; border-top:1px solid #e0e0e0; margin:10px 0;">
            <div class="result-meta">
                <strong>Full Name:</strong> <?php echo htmlspecialchars($searched_user['f_name'] . ' ' . $searched_user['l_name']); ?><br>
                <strong>System ID:</strong> <code><?php echo htmlspecialchars($searched_user['username']); ?></code><br>
                <strong>Email Address:</strong> <?php echo htmlspecialchars($searched_user['email']); ?><br>
                <strong>Assigned Role:</strong> <?php echo $role_label; ?><br>
                <strong>DS Context Area:</strong> <?php echo htmlspecialchars($searched_user['ds_division'] ?? 'Global Root'); ?><br>
                <strong>System Access State:</strong> 
                <span class="badge <?php echo ($searched_user['status'] === 'ACTIVE') ? 'badge-active' : 'badge-deactivated'; ?>">
                    <?php echo htmlspecialchars($searched_user['status']); ?>
                </span>
            </div>

            <form method="POST" action="" onsubmit="return confirm('Enforce access permission status change for this user account?');">
                <input type="hidden" name="action_type" value="toggle_status">
                <input type="hidden" name="target_user_id" value="<?php echo $searched_user['user_id']; ?>">
                <input type="hidden" name="current_status" value="<?php echo $searched_user['status']; ?>">
                <input type="hidden" name="search_email" value="<?php echo htmlspecialchars($searched_user['email']); ?>">

                <?php if ($searched_user['status'] === 'ACTIVE'): ?>
                    <button type="submit" class="status-action-btn btn-red">Deactivate User Account</button>
                <?php else: ?>
                    <button type="submit" class="status-action-btn btn-green">Reactivate User Account</button>
                <?php endif; ?>
            </form>
        </div>
    <?php endif; ?>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
<script src="../js/admin_map.js"></script>
</body>
</html>