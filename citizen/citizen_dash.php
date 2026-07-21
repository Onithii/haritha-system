<?php
// Include database connection config
include("../config/db.php");

// Fetch open volunteer events from the database
$query = "SELECT event_id, event_title, event_image, event_date, location, required_volunteers FROM volunteer_events WHERE status = 'OPEN' ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);

// Fetch Active System Broadcast Alerts
$current_time = date('Y-m-d H:i:s');
$broadcast_query = "SELECT title, message, created_at FROM messages 
                    WHERE expires_at IS NULL OR expires_at > '$current_time' 
                    ORDER BY created_at DESC";
$broadcast_result = mysqli_query($conn, $broadcast_query);
$notification_count = $broadcast_result ? mysqli_num_rows($broadcast_result) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Dashboard - Haritha System</title>
    <style>
        :root {
            --primary-color: #1b5e20;
            --primary-hover: #144718;
            --bg-color: #f8f9fa;
            --surface-bg: #ffffff;
            --text-main: #212529;
            --text-muted: #6c757d;
            --border-color: #e0e0e0;
            --border-light: #f1f3f5;
            --shadow-sm: 0 2px 6px rgba(0, 0, 0, 0.04);
            --shadow-hover: 0 8px 18px rgba(0, 0, 0, 0.08);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0 0 50px 0;
            color: var(--text-main);
        }

        /* --- Header Navigation --- */
        .header {
            background-color: var(--primary-color);
            color: white;
            padding: 14px 8%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-home {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 4px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: background-color 0.2s, color 0.2s;
        }

        .btn-home:hover {
            background-color: rgba(255, 255, 255, 0.15);
            color: white;
        }

        .header-title-container h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .header-right {
            display: flex;
            align-items: center;
        }

        .notification-trigger {
            position: relative;
            background: rgba(255, 255, 255, 0.12);
            width: 36px;
            height: 36px;
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
            background-color: #d32f2f;
            color: white;
            font-size: 10px;
            font-weight: bold;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--primary-color);
        }

        /* --- Main Dashboard Container --- */
        .dashboard-container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .dashboard-header {
            margin-bottom: 25px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 12px;
        }

        .dashboard-header h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            color: var(--text-main);
        }

        .dashboard-header p {
            margin: 4px 0 0 0;
            font-size: 14px;
            color: var(--text-muted);
        }

        /* --- Quick Action Navigation Bar --- */
        .quick-nav {
            background: var(--surface-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 35px;
            box-shadow: var(--shadow-sm);
        }

        .quick-nav-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }

        .quick-nav-links {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-nav {
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 4px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-nav-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-nav-primary:hover {
            background-color: var(--primary-hover);
        }

        .btn-nav-secondary {
            background-color: #fff;
            color: var(--text-main);
            border: 1px solid var(--border-color);
        }

        .btn-nav-secondary:hover {
            background-color: #f1f3f5;
            border-color: #ced4da;
        }

        /* --- Volunteer Event Cards Grid --- */
        .section-header {
            margin-bottom: 18px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-main);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
            gap: 22px;
        }

        .event-card {
            background: var(--surface-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .event-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .card-image-wrapper {
            width: 100%;
            height: 160px;
            background-color: #e9ecef;
            border-bottom: 1px solid var(--border-light);
            position: relative;
        }

        .card-image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .card-body {
            padding: 18px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .card-title {
            margin: 0 0 10px 0;
            font-size: 16px;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.3;
        }

        .card-meta {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 18px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .meta-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .meta-label {
            font-weight: 600;
            color: #495057;
        }

        .badge-status {
            display: inline-block;
            padding: 3px 8px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 12px;
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
            width: fit-content;
            margin-bottom: 12px;
        }

        .btn-card-action {
            margin-top: auto;
            display: block;
            text-align: center;
            padding: 9px;
            font-size: 13px;
            font-weight: 600;
            color: var(--primary-color);
            background-color: transparent;
            border: 1px solid var(--primary-color);
            border-radius: 4px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-card-action:hover {
            background-color: var(--primary-color);
            color: white;
        }

        /* --- Side Drawer Notifications --- */
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

        .noti-panel-header h2 { margin: 0; font-size: 16px; font-weight: 700; color: var(--text-main); }

        .close-noti-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 22px;
            cursor: pointer;
        }

        .noti-list-wrapper { flex-grow: 1; overflow-y: auto; }

        .noti-item {
            background: #f8f9fa;
            border-left: 3px solid var(--primary-color);
            padding: 12px 14px;
            margin-bottom: 12px;
            border-radius: 4px;
        }

        .noti-item h4 { margin: 0 0 4px 0; font-size: 13px; color: var(--text-main); }
        .noti-item p { margin: 0 0 6px 0; font-size: 12px; color: var(--text-muted); line-height: 1.4; }
        .noti-time { font-size: 10px; color: #adb5bd; display: block; text-align: right; }

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

        .no-records {
            color: var(--text-muted);
            font-size: 14px;
            text-align: center;
            padding: 30px 15px;
            background: var(--surface-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
        }

        @media (max-width: 768px) {
            .quick-nav {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            .quick-nav-links {
                width: 100%;
                flex-direction: column;
            }
            .btn-nav {
                text-align: center;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<!-- Header Navigation Bar -->
<div class="header">
    <div class="header-left">
        <a href="../index.php" class="btn-home">&larr; Return to Home</a>
    </div>

    <div class="header-title-container">
        <h1>Haritha Portal</h1>
    </div>

    <div class="header-right">
        <div class="notification-trigger" onclick="toggleNotificationPanel(true)" title="System Announcements">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
        <h2>Broadcast Notices</h2>
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
            <p class="no-records">No active broadcast notices at this time.</p>
        <?php endif; ?>
    </div>
</div>

<div class="dashboard-container">
    
    <div class="dashboard-header">
        <h2>Citizen Management Dashboard</h2>
        <p>Access municipal environmental services, track filings, and manage volunteer activities.</p>
    </div>

    <!-- Streamlined Navigation Bar -->
    <div class="quick-nav">
        <span class="quick-nav-title">Quick Actions</span>
        <div class="quick-nav-links">
            <a href="make_complaint.php" class="btn-nav btn-nav-primary">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                File New Complaint
            </a>
            <a href="view_complaints.php" class="btn-nav btn-nav-secondary">My Complaints History</a>
            <a href="certificate_view.php" class="btn-nav btn-nav-secondary">My Certificates</a>
            <a href="update_profile.php" class="btn-nav btn-nav-secondary">Account Settings</a>
        </div>
    </div>

    <!-- Active Volunteer Cards Section -->
    <div class="section-header">
        <span class="section-title">Open Volunteer Opportunities</span>
    </div>

    <?php if ($result && mysqli_num_rows($result) > 0): ?>
        <div class="cards-grid">
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <div class="event-card">
                    <div class="card-image-wrapper">
                        <?php if (!empty($row['event_image'])): ?>
                            <img src="<?php echo htmlspecialchars($row['event_image']); ?>" alt="Event Thumbnail" onerror="this.style.display='none';">
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <span class="badge-status">Open for Enrollment</span>
                        <h3 class="card-title"><?php echo htmlspecialchars($row['event_title']); ?></h3>
                        
                        <div class="card-meta">
                            <div class="meta-line">
                                <span class="meta-label">Target Date:</span>
                                <span><?php echo !empty($row['event_date']) ? date("M d, Y", strtotime($row['event_date'])) : 'Scheduled'; ?></span>
                            </div>
                            <div class="meta-line">
                                <span class="meta-label">Location:</span>
                                <span><?php echo !empty($row['location']) ? htmlspecialchars($row['location']) : 'Specified upon registration'; ?></span>
                            </div>
                        </div>

                        <a href="view_event.php?id=<?php echo $row['event_id']; ?>" class="btn-card-action">View & Register</a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="no-records">
            There are currently no active volunteer operations open for enrollment.
        </div>
    <?php endif; ?>

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