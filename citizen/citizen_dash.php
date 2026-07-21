<?php
// Include your database connection config
include("../config/db.php");

// Fetch open volunteer events from the database (showing newest events first)
$query = "SELECT event_id, event_title, event_image FROM volunteer_events WHERE status = 'OPEN' ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);

// --- ADDED: Fetch Active System Broadcast Alerts ---
$current_time = date('Y-m-d H:i:s');
$broadcast_query = "SELECT title, message, created_at FROM messages 
                    WHERE expires_at IS NULL OR expires_at > '$current_time' 
                    ORDER BY created_at DESC";
$broadcast_result = mysqli_query($conn, $broadcast_query);
$notification_count = mysqli_num_rows($broadcast_result);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Citizen Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f2f5f2;
            margin: 0;
            padding-bottom: 60px;
        }

        .header {
            background-color: #2e7d32;
            color: white;
            padding: 20px 10%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }

        .header-title-container {
            text-align: center;
            flex-grow: 1;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .header p {
            margin: 5px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }

        .btn-home {
            background-color: #ffffff;
            color: #2e7d32;
            padding: 9px 18px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            transition: background-color 0.2s, color 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.15);
        }

        .btn-home:hover {
            background-color: #e8f5e9;
            color: #1b5e20;
        }

        .container {
            width: 85%;
            margin: 30px auto;
            display: flex;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
        }

        .card {
            background: white;
            padding: 25px 15px;
            width: 22%;
            min-width: 200px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0px 2px 8px rgba(0,0,0,0.15);
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .card h3 {
            color: #2e7d32;
            margin-top: 0;
            font-size: 18px;
        }

        .card p {
            color: #666;
            font-size: 13px;
            line-height: 1.4;
            margin-bottom: 15px;
            flex-grow: 1;
        }

        button, .btn-link {
            background-color: #2e7d32;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 5px;
            font-weight: bold;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }

        button:hover, .btn-link:hover {
            background-color: #1b5e20;
        }

        .volunteer-section {
            width: 85%;
            margin: 50px auto 0 auto;
        }

        .volunteer-section h2 {
            color: #2e7d32;
            border-bottom: 2px solid #c8e6c9;
            padding-bottom: 10px;
            margin-bottom: 25px;
        }

        .volunteer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }

        .volunteer-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0px 3px 10px rgba(0,0,0,0.15);
            display: flex;
            flex-direction: column;
            transition: transform 0.2s;
        }

        .volunteer-card:hover {
            transform: translateY(-5px);
        }

        .volunteer-img-wrapper {
            width: 100%;
            height: 180px;
            background-color: #e0e0e0;
            position: relative;
        }

        .volunteer-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .volunteer-info {
            padding: 20px;
            text-align: center;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .volunteer-info h4 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 16px;
            line-height: 1.4;
        }

        .volunteer-info .btn-view {
            width: 100%;
            background-color: #1b5e20;
        }

        .no-events {
            color: #666;
            font-style: italic;
            grid-column: 1 / -1;
            text-align: center;
            padding: 20px;
        }

        /* --- Notification Sticky Trigger Badge Styles --- */
        .notification-trigger {
            position: fixed;
            top: 40%;
            right: 0;
            background-color: #e65100;
            color: white;
            width: 55px;
            height: 50px;
            border-radius: 10px 0 0 10px;
            box-shadow: -2px 2px 10px rgba(0,0,0,0.3);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9997;
            transition: background-color 0.2s, transform 0.2s;
        }
        
        .notification-trigger:hover {
            background-color: #bf360c;
            transform: scale(1.05);
        }

        .notification-badge {
            position: absolute;
            top: 4px;
            left: 6px;
            background-color: #d84315;
            color: white;
            border: 2px solid white;
            font-size: 11px;
            font-weight: bold;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* --- Notification Side Panel Layout Drawer --- */
        .noti-panel {
            position: fixed;
            top: 0;
            right: -420px;
            width: 380px;
            height: 100%;
            background-color: #ffffff;
            box-shadow: -5px 0 15px rgba(0,0,0,0.2);
            transition: right 0.3s ease-in-out;
            z-index: 9999;
            padding: 20px;
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
            border-bottom: 2px solid #2e7d32;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }

        .noti-panel-header h2 {
            margin: 0;
            color: #2e7d32;
            font-size: 20px;
        }

        .close-noti-btn {
            background: none;
            border: none;
            color: #888;
            font-size: 28px;
            cursor: pointer;
        }

        .close-noti-btn:hover {
            color: #d84315;
        }

        .noti-list-wrapper {
            flex-grow: 1;
            overflow-y: auto;
            padding-right: 5px;
        }

        .noti-item {
            background: #f9fbf9;
            border-left: 4px solid #e65100;
            padding: 12px;
            margin-bottom: 12px;
            border-radius: 0 6px 6px 0;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }

        .noti-item h4 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 14px;
        }

        .noti-item p {
            margin: 0 0 8px 0;
            color: #555;
            font-size: 13px;
            line-height: 1.4;
            white-space: pre-wrap;
        }

        .noti-time {
            font-size: 11px;
            color: #999;
            display: block;
            text-align: right;
        }

        .no-noti-msg {
            text-align: center;
            color: #777;
            font-style: italic;
            margin-top: 40px;
        }

        .noti-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.3);
            display: none;
            z-index: 9998;
        }
    </style>
</head>
<body>

<!-- Sticky Floating Notification Bell Trigger -->
<div class="notification-trigger" onclick="toggleNotificationPanel(true)">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
    </svg>
    <?php if ($notification_count > 0): ?>
        <div class="notification-badge" id="notiBadge"><?php echo $notification_count; ?></div>
    <?php endif; ?>
</div>

<!-- Notification Side Panel & Overlay Mask Backdrop -->
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

<div class="header">
    <a href="../index.php" class="btn-home">&larr; Back to Home</a>
    <div class="header-title-container">
        <h1>Citizen Dashboard</h1>
        <p>Welcome to Haritha Environmental Complaint System</p>
    </div>
    <!-- Spacer to keep header title perfectly centered -->
    <div style="width: 130px;"></div>
</div>

<div class="container">
    <div class="card">
        <h3>Submit Complaint</h3>
        <p>Report environmental issues in your local area.</p>
        <a href="make_complaint.php">
            <button type="button">Submit Complaint</button>
        </a>
    </div>

    <div class="card">
        <h3>My Complaints</h3>
        <p>View and track the complaints you have submitted.</p>
        <button type="button">View Complaints</button>
    </div>

    <div class="card">
        <h3>My Certificates</h3>
        <p>Access and print certificates you earned from volunteering.</p>
        <a href="certificate_view.php">
            <button type="button" style="background-color: #e65100;">My Certificates</button>
        </a>
    </div>

    <div class="card">
        <h3>Profile</h3>
        <p>View and update your personal information details.</p>
        <a href="update_profile.php">
            <button type="button">Update Profile</button>
        </a>
    </div>
</div>

<hr style="width: 85%; border: 0; border-top: 1px dashed #c8e6c9; margin: 40px auto;">

<div class="volunteer-section">
    <h2>Volunteer Opportunities</h2>
    
    <div class="volunteer-grid">
        <?php 
        if ($result && mysqli_num_rows($result) > 0): 
            while ($row = mysqli_fetch_assoc($result)): 
        ?>
                <div class="volunteer-card">
                    <div class="volunteer-img-wrapper">
                        <?php if (!empty($row['event_image'])): ?>
                            <img src="<?php echo htmlspecialchars($row['event_image']); ?>" alt="Event Image" onerror="this.parentNode.style.backgroundColor='#e0e0e0'; this.remove();">
                        <?php endif; ?>
                    </div>
                    <div class="volunteer-info">
                        <h4><?php echo htmlspecialchars($row['event_title']); ?></h4>
                        <a href="view_event.php?id=<?php echo $row['event_id']; ?>">
                            <button class="btn-view" type="button">Join Event</button>
                        </a>
                    </div>
                </div>
        <?php 
            endwhile; 
        else: 
        ?>
            <p class="no-events">No active volunteer operations listed at this moment.</p>
        <?php 
        endif; 
        ?>
    </div>
</div>

<script>
// Keep badge hidden on refresh if user has already viewed notifications
document.addEventListener("DOMContentLoaded", function() {
    var badge = document.getElementById('notiBadge');
    if (badge && localStorage.getItem('notificationsViewed') === 'true') {
        badge.remove();
    }
});

// Control functions to handle Notification Sidebar visibility and clear badge state
function toggleNotificationPanel(open) {
    var panel = document.getElementById('notificationSidePanel');
    var overlay = document.getElementById('notiOverlay');
    var badge = document.getElementById('notiBadge');
    
    if (open) {
        panel.classList.add('open');
        overlay.style.display = 'block';
        
        // Instantly hide badge from view upon opening
        if (badge) {
            badge.remove();
        }
        // Save state across page reloads
        localStorage.setItem('notificationsViewed', 'true');
    } else {
        panel.classList.remove('open');
        overlay.style.display = 'none';
    }
}
</script>

</body>
</html>