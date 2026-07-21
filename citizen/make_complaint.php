<?php
session_start();
include("../config/db.php");

// 1. Secure Access Check
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1 || empty($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

$citizen_id = $_SESSION['user_id'];
$error = "";
$success = "";

// 2. Fetch complaint categories dynamically
$categories = [];
$cat_query = "SELECT category_id, category_name FROM complaint_categories ORDER BY category_id ASC";
$cat_result = mysqli_query($conn, $cat_query);
if ($cat_result) {
    while ($row = mysqli_fetch_assoc($cat_result)) {
        $categories[] = $row;
    }
}

// 3. Fetch Active System Broadcast Alerts for Header
$current_time = date('Y-m-d H:i:s');
$broadcast_query = "SELECT title, message, created_at FROM messages 
                    WHERE expires_at IS NULL OR expires_at > '$current_time' 
                    ORDER BY created_at DESC";
$broadcast_result = mysqli_query($conn, $broadcast_query);
$notification_count = mysqli_num_rows($broadcast_result);

// 4. Process Form Submission
if (isset($_POST['submit_complaint'])) {
    $title                = trim($_POST['title']);
    $category_id          = intval($_POST['category_id']);
    $description          = trim($_POST['description']);
    $location_description = trim($_POST['location_description']);
    $district             = isset($_POST['district']) ? trim($_POST['district']) : 'Unknown District';
    
    $latitude  = (isset($_POST['latitude']) && $_POST['latitude'] !== '') ? floatval($_POST['latitude']) : null;
    $longitude = (isset($_POST['longitude']) && $_POST['longitude'] !== '') ? floatval($_POST['longitude']) : null;
    
    $status_id = 1; // Pending
    $image_path = null;

    if (empty($title) || empty($description) || empty($category_id)) {
        $error = "Title, Category, and Description are required fields.";
    } else {
        
        // Handle Optional Image Upload
        if (isset($_FILES['complaint_image']) && $_FILES['complaint_image']['error'] == 0) {
            $allowed_extensions = ['jpg', 'jpeg', 'png'];
            $file_name = $_FILES['complaint_image']['name'];
            $file_tmp  = $_FILES['complaint_image']['tmp_name'];
            $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (in_array($file_ext, $allowed_extensions)) {
                $new_file_name = "IMG_" . uniqid() . "." . $file_ext;
                $upload_dir = "../uploads/";
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
                    $image_path = "uploads/" . $new_file_name;
                } else {
                    $error = "Failed to upload the image.";
                }
            } else {
                $error = "Invalid file extension. Only JPG, JPEG, and PNG are allowed.";
            }
        }

        // 5. Save Record and Auto-Escalate to Nearest GN via Haversine Formula
        if (empty($error)) {
            $sql = "INSERT INTO complaints (
                        citizen_id, category_id, status_id, title, 
                        description, image_path, latitude, longitude, 
                        location_description, district
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param(
                    $stmt, 
                    "iiisssssss", 
                    $citizen_id, $category_id, $status_id, $title, 
                    $description, $image_path, $latitude, $longitude, 
                    $location_description, $district
                );

                if (mysqli_stmt_execute($stmt)) {
                    $complaint_id = mysqli_insert_id($conn);
                    $success = "Your complaint has been submitted successfully!";
                    
                    // Proximity auto-routing calculations
                    if ($latitude !== null && $longitude !== null) {
                        $gn_sql = "SELECT user_id, 
                                          (6371 * ACOS(
                                              COS(RADIANS(?)) * COS(RADIANS(office_latitude)) * COS(RADIANS(office_longitude) - RADIANS(?)) + 
                                              SIN(RADIANS(?)) * SIN(RADIANS(office_latitude))
                                          )) AS distance 
                                   FROM users 
                                   WHERE role_id = 2 AND office_latitude IS NOT NULL 
                                   ORDER BY distance ASC 
                                   LIMIT 1";
                        
                        $gn_stmt = mysqli_prepare($conn, $gn_sql);
                        if ($gn_stmt) {
                            mysqli_stmt_bind_param($gn_stmt, "ddd", $latitude, $longitude, $latitude);
                            mysqli_stmt_execute($gn_stmt);
                            $gn_result = mysqli_stmt_get_result($gn_stmt);
                            
                            if ($closest_gn = mysqli_fetch_assoc($gn_result)) {
                                $assigned_gn_id = $closest_gn['user_id'];
                                
                                $update_sql = "UPDATE complaints SET assigned_to_id = ? WHERE complaint_id = ?";
                                $update_stmt = mysqli_prepare($conn, $update_sql);
                                if ($update_stmt) {
                                    mysqli_stmt_bind_param($update_stmt, "ii", $assigned_gn_id, $complaint_id);
                                    mysqli_stmt_execute($update_stmt);
                                    mysqli_stmt_close($update_stmt);
                                    $success = "Your complaint has been submitted and auto-escalated to the nearest Grama Niladhari officer!";
                                }
                            }
                            mysqli_stmt_close($gn_stmt);
                        }
                    }
                } else {
                    $error = "Database Error: Could not save your complaint. " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = "Database Error: Statement compilation failed.";
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
    <title>Report Environmental Issue - Haritha</title>
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

        /* --- Form Container Card --- */
        .main-wrapper {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px 60px 20px;
        }

        .form-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 35px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .form-card h2 {
            margin-top: 0;
            margin-bottom: 25px;
            font-size: 22px;
            color: var(--text-main);
            border-bottom: 2px solid #e8f5e9;
            padding-bottom: 12px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .alert-error {
            background-color: var(--error-bg);
            color: var(--error-text);
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background-color: var(--success-bg);
            color: var(--success-text);
            border: 1px solid #c3e6cb;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 8px;
            color: var(--text-main);
        }

        .form-group input[type="text"],
        .form-group select,
        .form-group textarea,
        .form-group input[type="file"] {
            width: 100%;
            padding: 11px 14px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            font-family: inherit;
            font-size: 14px;
            box-sizing: border-box;
            background-color: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input[type="text"]:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 110px;
        }

        .form-group input[readonly] {
            background-color: #f8f9fa;
            color: var(--text-muted);
            cursor: not-allowed;
        }

        .geo-group {
            display: flex;
            gap: 15px;
        }

        .geo-group .form-group {
            flex: 1;
        }

        #map {
            height: 320px;
            margin-bottom: 25px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            width: 100%;
            z-index: 1;
        }

        .btn-submit {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.2s;
        }

        .btn-submit:hover {
            background-color: var(--primary-hover);
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
    </style>
</head>
<body>

<!-- Header Navigation Bar -->
<div class="header">
    <div class="header-left">
        <a href="citizen_dash.php" class="btn-home">&larr; Back to Dashboard</a>
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

<div class="main-wrapper">
    <div class="form-card">
        <h2>Report Environmental Issue</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Complaint Title *</label>
                <input type="text" id="title" name="title" placeholder="Brief title of the hazard" required>
            </div>

            <div class="form-group">
                <label for="category_id">Issue Category *</label>
                <select id="category_id" name="category_id" required>
                    <option value="">-- Select a Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>">
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Detailed Description *</label>
                <textarea id="description" name="description" placeholder="Describe the environmental issue and its impact..." required></textarea>
            </div>

            <div class="form-group">
                <label for="complaint_image">Upload Photo Evidence (Optional)</label>
                <input type="file" id="complaint_image" name="complaint_image" accept="image/*">
            </div>

            <div class="form-group">
                <label for="location_description">Location Landmarks / Address</label>
                <input type="text" id="location_description" name="location_description" placeholder="e.g., Near the 4th milestone, opposite the school">
            </div>

            <div class="form-group">
                <label>Pin Hazard Location on Map</label>
                <div id="map"></div>
            </div>

            <div class="geo-group">
                <div class="form-group">
                    <label for="latitude">Latitude</label>
                    <input type="text" id="latitude" name="latitude" placeholder="e.g., 6.9271" readonly>
                </div>
                <div class="form-group">
                    <label for="longitude">Longitude</label>
                    <input type="text" id="longitude" name="longitude" placeholder="e.g., 79.8612" readonly>
                </div>
            </div>

            <div class="form-group">
                <label for="district">Detected District</label>
                <input type="text" id="district" name="district" placeholder="Click on the map to auto-fill district..." readonly style="background-color: #e8f5e9; font-weight: 600; color: var(--primary-color);">
            </div>

            <button type="submit" name="submit_complaint" class="btn-submit">Submit Report</button>
        </form>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
<script src="../js/map_handler.js"></script>

<script>
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