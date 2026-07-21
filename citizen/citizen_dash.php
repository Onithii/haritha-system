<?php
// Include your database connection config
include("../config/db.php");

// Fetch open volunteer events from the database
$query = "SELECT event_id, event_title, event_image FROM volunteer_events WHERE status = 'OPEN' ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);

// Fetch Active System Broadcast Alerts
$current_time = date('Y-m-d H:i:s');
$broadcast_query = "SELECT title, message, created_at FROM messages 
                    WHERE expires_at IS NULL OR expires_at > '$current_time' 
                    ORDER BY created_at DESC";
$broadcast_result = mysqli_query($conn, $broadcast_query);
$notification_count = mysqli_num_rows($broadcast_result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Dashboard - Haritha</title>
    <style>
        :root {
            --primary-color: #1b5e20;
            --primary-hover: #144718;
            --bg-color: #f4f6f8;
            --card-bg: #ffffff;
            --text-main: #2c3e50;
            --text-muted: #6c757d;
            --accent-orange: #f39c12;
            --border-color: #e9ecef;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
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

        /* Notification Trigger Button inside Header */
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

        /* --- Main Content Layout --- */
        .main-wrapper {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-main);
        }

        /* Quick Action Grid Cards */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 45px;
        }

        .action-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }

        .card-icon-box {
            width: 48px;
            height: 48px;
            background-color: #e8f5e9;
            color: var(--primary-color);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }

        .action-card h3 {
            margin: 0 0 8px 0;
            font-size: 16px;
            color: var(--text-main);
        }

        .action-card p {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.4;
            margin-bottom: 20px;
            flex-grow: 1;
        }

        .btn-card {
            display: inline-block;
            text-align: center;
            padding: 10px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            transition: background 0.2s;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }

        .btn-card.primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-card.primary:hover {
            background-color: var(--primary-hover);
        }

        .btn-card.outline:hover {
            background-color: #e8f5e9;
        }

        /* --- Volunteer Operations Grid --- */
        .volunteer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
            gap: 25px;
        }

        .volunteer-card {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            transition: transform 0.2s;
        }

        .volunteer-card:hover {
            transform: translateY(-4px);
        }

        .volunteer-img-wrapper {
            width: 100%;
            height: 160px;
            background-color: #e2e8f0;
            position: relative;
        }

        .volunteer-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .volunteer-info {
            padding: 18px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .volunteer-info h4 {
            margin: 0 0 10px 0;
            font-size: 15px;
            color: var(--text-main);
        }

        .event-meta {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .btn-view {
            margin-top: auto;
            width: 100%;
            padding: 9px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: white;
            color: var(--text-main);
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: background 0.2s;
        }

        .btn-view:hover {
            background-color: #f8f9fa;
            border-color: #ccc;
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

        .noti-panel.open {
            right: 0;
        }

        .noti-panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .noti-panel-header h2 {
            margin: 0;
            font-size: 18px;
            color: var(--text-main);
        }

        .close-noti-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 24px;
            cursor: pointer;
        }

        .noti-list-wrapper {
            flex-grow: 1;
            overflow-y: auto;
        }

        .noti-item {
            background: #f8f9fa;
            border-left: 3px solid var(--primary-color);
            padding: 14px;
            margin-bottom: 12px;
            border-radius: 4px;
        }

        .noti-item h4 {
            margin: 0 0 6px 0;
            font-size: 14px;
            color: var(--text-main);
        }

        .noti-item p {
            margin: 0 0 8px 0;
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .noti-time {
            font-size: 10px;
            color: #aaa;
            display: block;
            text-align: right;
        }

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

        .no-events, .no-noti-msg {
            color: var(--text-muted);
            font-style: italic;
            text-align: center;
            padding: 20px;
        }
    </style>
</head>
<body>

<!-- Header Navigation Bar -->
<div class="header">
    <div class="header-left">
        <a href="../index.php" class="btn-home">&larr; Back to Home</a>
    </div>

    <div class="header-title-container">
        <h1>Citizen Dashboard</h1>
    </div>

    <div class="header-right">
        <!-- Notification Bell Drawer Trigger -->
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
    <!-- Top Action Grid -->
    <div class="actions-grid">
        <div class="action-card">
            <div>
                <div class="card-icon-box">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                </div>
                <h3>Submit Complaint</h3>
                <p>Report environmental issues in your local area easily.</p>
            </div>
            <a href="make_complaint.php" class="btn-card primary">Submit Complaint</a>
        </div>

        <div class="action-card">
            <div>
                <div class="card-icon-box">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                </div>
                <h3>My Complaints</h3>
                <p>Track the progress and status of your filed reports.</p>
            </div>
            <a href="view_complaints.php" class="btn-card outline">My Complaints</a>
        </div>

        <div class="action-card">
            <div>
                <div class="card-icon-box" style="background-color: #fff3e0; color: #e65100;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
                </div>
                <h3>My Certificates</h3>
                <p>Access and print volunteering contribution certificates.</p>
            </div>
            <a href="certificate_view.php" class="btn-card outline">My Certificates</a>
        </div>

        <div class="action-card">
            <div>
                <div class="card-icon-box">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </div>
                <h3>Profile</h3>
                <p>Manage and update your account information details.</p>
            </div>
            <a href="update_profile.php" class="btn-card outline">Update Profile</a>
        </div>
    </div>

    <!-- Volunteer Section Grid -->
    <div class="section-title">Volunteer Opportunities</div>
    
    <div class="volunteer-grid">
        <?php if ($result && mysqli_num_rows($result) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <div class="volunteer-card">
                    <div class="volunteer-img-wrapper">
                        <?php if (!empty($row['event_image'])): ?>
                            <img src="<?php echo htmlspecialchars($row['event_image']); ?>" alt="Event Image" onerror="this.parentNode.style.backgroundColor='#e2e8f0'; this.remove();">
                        <?php endif; ?>
                    </div>
                    <div class="volunteer-info">
                        <h4><?php echo htmlspecialchars($row['event_title']); ?></h4>
                        <div class="event-meta">
                            <span>📅 Upcoming Event</span>
                        </div>
                        <a href="view_event.php?id=<?php echo $row['event_id']; ?>" class="btn-view">View Details</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="no-events">No active volunteer operations listed at this moment.</p>
        <?php endif; ?>
    </div>
</div>

<script>
// Keep badge state synced across page dynamic reloads
document.addEventListener("DOMContentLoaded", function() {
    var badge = document.getElementById('notiBadge');
    if (badge && localStorage.getItem('notificationsViewed') === 'true') {
        badge.remove();
    }
});

// Control function for sidebar sliding panel
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