<?php
session_start();
include("../config/db.php");

// 1. Secure Access Check: Ensure user is logged in as a Citizen
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

// Fetch system broadcast alerts for notification panel
$current_time = date('Y-m-d H:i:s');
$broadcast_query = "SELECT title, message, created_at FROM messages 
                    WHERE expires_at IS NULL OR expires_at > '$current_time' 
                    ORDER BY created_at DESC";
$broadcast_result = mysqli_query($conn, $broadcast_query);
$notification_count = $broadcast_result ? mysqli_num_rows($broadcast_result) : 0;

// 2. Fetch all events where this specific citizen's attendance has been verified (attendance_verified = 1)
$certificates = [];
$query = "SELECT e.event_id, e.event_title, e.created_at 
          FROM volunteer_participants vp
          JOIN volunteer_events e ON vp.event_id = e.event_id
          WHERE vp.user_id = ? AND vp.attendance_verified = 1
          ORDER BY e.created_at DESC";

$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $certificates[] = $row;
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Certificates - Haritha</title>
    <style>
        :root {
            --primary-color: #1b5e20;
            --primary-hover: #144718;
            --accent-orange: #e65100;
            --accent-orange-hover: #b33600;
            --bg-color: #f4f6f8;
            --card-bg: #ffffff;
            --text-main: #2c3e50;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0 0 60px 0;
            color: var(--text-main);
        }

        /* --- Header Styling --- */
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

        .btn-back {
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

        .btn-back:hover {
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

        /* --- Main Layout --- */
        .main-container {
            max-width: 900px;
            margin: 35px auto;
            background: var(--card-bg);
            padding: 35px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .page-header-title {
            color: var(--text-main);
            margin-top: 0;
            margin-bottom: 8px;
            font-size: 22px;
            font-weight: 700;
        }

        .sub-description {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 0;
            margin-bottom: 25px;
            line-height: 1.5;
            border-bottom: 2px solid #e8f5e9;
            padding-bottom: 15px;
        }

        /* --- Certificate List Items --- */
        .cert-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .cert-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background-color: #ffffff;
            transition: all 0.2s ease;
        }

        .cert-item:hover {
            border-color: #c8e6c9;
            background-color: #f8fdf9;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.03);
        }

        .cert-details h4 {
            margin: 0 0 6px 0;
            color: var(--primary-color);
            font-size: 16px;
            font-weight: 700;
        }

        .cert-details span {
            font-size: 12px;
            color: var(--text-muted);
            background-color: #f1f3f5;
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        .btn-view-cert {
            background-color: var(--accent-orange);
            color: white;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            transition: background-color 0.2s, box-shadow 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-view-cert:hover {
            background-color: var(--accent-orange-hover);
            box-shadow: 0 2px 6px rgba(230, 81, 0, 0.25);
        }

        .no-certs {
            text-align: center;
            color: var(--text-muted);
            padding: 50px 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px dashed #ced4da;
        }

        .badge-icon {
            font-size: 44px;
            margin-bottom: 12px;
            display: block;
        }

        .no-certs-info {
            font-size: 13px;
            color: #888;
            margin-top: 8px;
            display: block;
        }

        /* --- Notifications Side Drawer --- */
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

        @media (max-width: 600px) {
            .cert-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .btn-view-cert {
                width: 100%;
                justify-content: center;
                box-sizing: border-box;
            }
            .main-container {
                margin: 20px 15px;
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<!-- Header Navigation Bar -->
<div class="header">
    <div class="header-left">
        <a href="citizen_dash.php" class="btn-back">&larr; Back to Dashboard</a>
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

<div class="main-container">
    <h2 class="page-header-title">Earned Volunteer Certificates</h2>
    <p class="sub-description">Thank you for your active contribution to safeguarding our environment. Below are your officially verified participation certificates.</p>

    <div class="cert-list">
        <?php if (empty($certificates)): ?>
            <div class="no-certs">
                <span class="badge-icon">🎖️</span>
                <strong>You haven't received any certificates yet.</strong>
                <span class="no-certs-info">Certificates appear automatically once a Grama Niladhari verifies your event attendance via QR code.</span>
            </div>
        <?php else: ?>
            <?php foreach ($certificates as $cert): ?>
                <div class="cert-item">
                    <div class="cert-details">
                        <h4><?php echo htmlspecialchars($cert['event_title']); ?></h4>
                        <span>Event Ref: #<?php echo $cert['event_id']; ?></span>
                    </div>
                    <div>
                        <a href="../generate_certificate.php?event_id=<?php echo $cert['event_id']; ?>&user_id=<?php echo $user_id; ?>" class="btn-view-cert" target="_blank">
                            View Certificate ↗
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

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