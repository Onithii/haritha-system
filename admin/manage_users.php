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
                $error = "Failed execution update sequence against database.";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage User Accounts - Admin Control</title>
    
    <!-- External GIS Mapping Styles -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    
    <style>
        :root {
            --primary-color: #1b5e20;
            --primary-hover: #144718;
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --radius: 8px;
            --danger-color: #dc2626;
            --danger-hover: #b91c1c;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0;
            color: var(--text-main);
            -webkit-font-smoothing: antialiased;
        }

        /* --- Navigation Bar --- */
        .header {
            background-color: var(--primary-color);
            color: white;
            padding: 16px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header-title h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }

        .header-title p {
            margin: 4px 0 0 0;
            font-size: 13px;
            opacity: 0.85;
        }

        .btn-top-back {
            background-color: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-top-back:hover {
            background-color: rgba(255, 255, 255, 0.22);
        }

        .layout-wrapper {
            max-width: 1000px;
            margin: 32px auto;
            padding: 0 24px;
            display: flex;
            flex-direction: column;
            gap: 32px;
        }

        /* --- Content Panels --- */
        .panel-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            padding: 28px;
        }

        .panel-card h2 {
            margin-top: 0;
            margin-bottom: 6px;
            font-size: 18px;
            font-weight: 600;
            color: var(--text-main);
        }

        .panel-subtitle {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 13px;
            color: var(--text-muted);
        }

        /* --- Alerts --- */
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 20px;
        }

        .alert-error {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background-color: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        /* --- Forms & Field Sets --- */
        .row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text-main);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            font-size: 13px;
            color: var(--text-main);
            background-color: #fff;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.1);
        }

        .form-group input[readonly] {
            background-color: #f8fafc;
            color: var(--text-muted);
            cursor: not-allowed;
        }

        .map-instruction {
            display: block;
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        #map {
            height: 320px;
            width: 100%;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            margin-bottom: 18px;
            z-index: 1;
        }

        .btn-submit {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background-color: var(--primary-hover);
        }

        /* --- Search & Action Card Styles --- */
        .search-row {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .search-row input {
            flex-grow: 1;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 13px;
            outline: none;
        }

        .search-row input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.1);
        }

        .result-card {
            margin-top: 20px;
            padding: 20px;
            border-radius: 6px;
            background-color: #f8fafc;
            border: 1px solid var(--border-color);
        }

        .result-card h3 {
            margin-top: 0;
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .result-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px 20px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .result-meta-item {
            color: var(--text-muted);
        }

        .result-meta-item strong {
            color: var(--text-main);
            display: block;
            margin-bottom: 2px;
        }

        /* --- Badges --- */
        .badge {
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.03em;
            display: inline-block;
        }

        .badge-active { background-color: #dcfce7; color: #166534; }
        .badge-deactivated { background-color: #fee2e2; color: #991b1b; }

        .btn-red {
            background-color: var(--danger-color);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .btn-red:hover {
            background-color: var(--danger-hover);
        }

        .btn-green {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .btn-green:hover {
            background-color: var(--primary-hover);
        }
    </style>
</head>
<body>

<header class="header">
    <div class="header-title">
        <h1>User Account Administration</h1>
        <p>Account Provisioning & Geolocation Mapping</p>
    </div>
    <a href="admin_dash.php" class="btn-top-back">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Dashboard
    </a>
</header>

<main class="layout-wrapper">

    <!-- Provisioning Section -->
    <section class="panel-card">
        <h2>Add System User Account</h2>
        <p class="panel-subtitle">Create new personnel records and associate GIS workspace metrics.</p>
        
        <?php if(!empty($error) && !isset($_POST['action_type'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if(!empty($success) && !isset($_POST['action_type'])): ?>
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

            <div class="form-group">
                <label>Assigned System Role *</label>
                <select name="role_id" required id="role_id">
                    <option value="">-- Select Role Assignment --</option>
                    <option value="1">Citizen</option>
                    <option value="2">Grama Niladhari (GN)</option>
                    <option value="3">Local Authority (LA)</option>
                    <option value="4">Divisional Secretariat (DS)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Workspace / Office Location Mapping</label>
                <span class="map-instruction">Use the search icon on the map to query locations or drag the marker directly.</span>
                <div id="map"></div>
            </div>

            <div class="form-group">
                <label>Auto-Resolved Physical Address</label>
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

            <button type="submit" class="btn-submit">Provision Account</button>
        </form>
    </section>

    <!-- Account Management / Status Toggling Section -->
    <section class="panel-card">
        <h2>Search & Manage User Access</h2>
        <p class="panel-subtitle">Query registered profiles by email address to adjust authentication and access permissions.</p>

        <?php if(!empty($error) && isset($_POST['action_type'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if(!empty($success) && isset($_POST['action_type'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="action_type" value="search_user">
            <div class="search-row">
                <input type="email" name="search_email" required placeholder="Enter exact email address (e.g., perera@domain.com)" value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit" class="btn-submit">Search Profile</button>
            </div>
        </form>

        <?php if ($searched_user): 
            $role_label = "Citizen";
            if($searched_user['role_id'] == 2) $role_label = "Grama Niladhari (GN)";
            if($searched_user['role_id'] == 3) $role_label = "Local Authority (LA)";
            if($searched_user['role_id'] == 4) $role_label = "Divisional Secretariat (DS)";
            if($searched_user['role_id'] == 5) $role_label = "System Administrator";
        ?>
            <div class="result-card">
                <h3>Matched Profile</h3>
                
                <div class="result-meta-grid">
                    <div class="result-meta-item">
                        <strong>Full Name</strong>
                        <?php echo htmlspecialchars($searched_user['f_name'] . ' ' . $searched_user['l_name']); ?>
                    </div>
                    <div class="result-meta-item">
                        <strong>System ID</strong>
                        <code><?php echo htmlspecialchars($searched_user['username']); ?></code>
                    </div>
                    <div class="result-meta-item">
                        <strong>Email Address</strong>
                        <?php echo htmlspecialchars($searched_user['email']); ?>
                    </div>
                    <div class="result-meta-item">
                        <strong>Assigned Role</strong>
                        <?php echo htmlspecialchars($role_label); ?>
                    </div>
                    <div class="result-meta-item">
                        <strong>DS Division Context</strong>
                        <?php echo htmlspecialchars($searched_user['ds_division'] ?? 'Global Root'); ?>
                    </div>
                    <div class="result-meta-item">
                        <strong>Access Status</strong>
                        <span class="badge <?php echo ($searched_user['status'] === 'ACTIVE') ? 'badge-active' : 'badge-deactivated'; ?>">
                            <?php echo htmlspecialchars($searched_user['status']); ?>
                        </span>
                    </div>
                </div>

                <form method="POST" action="" onsubmit="return confirm('Enforce access permission status change for this user account?');">
                    <input type="hidden" name="action_type" value="toggle_status">
                    <input type="hidden" name="target_user_id" value="<?php echo $searched_user['user_id']; ?>">
                    <input type="hidden" name="current_status" value="<?php echo $searched_user['status']; ?>">
                    <input type="hidden" name="search_email" value="<?php echo htmlspecialchars($searched_user['email']); ?>">

                    <?php if ($searched_user['status'] === 'ACTIVE'): ?>
                        <button type="submit" class="btn-red">Deactivate User Account</button>
                    <?php else: ?>
                        <button type="submit" class="btn-green">Reactivate User Account</button>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>
    </section>

</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
<script src="../js/admin_map.js"></script>

</body>
</html>