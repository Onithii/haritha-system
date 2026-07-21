<?php
session_start();
include("../config/db.php");

// Ensure citizen is logged in (fallback used for staging/testing if not set)
if (!isset($_SESSION['user_id'])) {
    // header("Location: ../auth/login.php");
    // exit();
    $_SESSION['user_id'] = 1; 
}

$citizen_id = $_SESSION['user_id'];

// Fetch system broadcast alerts for notification panel
$current_time = date('Y-m-d H:i:s');
$broadcast_query = "SELECT title, message, created_at FROM messages 
                    WHERE expires_at IS NULL OR expires_at > '$current_time' 
                    ORDER BY created_at DESC";
$broadcast_result = mysqli_query($conn, $broadcast_query);
$notification_count = $broadcast_result ? mysqli_num_rows($broadcast_result) : 0;

// Query complaints securely using prepared statements
$query = "SELECT c.*, 
                 cat.category_name, 
                 cs.status_name 
          FROM complaints c
          LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
          LEFT JOIN complaint_status cs ON c.status_id = cs.status_id
          WHERE c.citizen_id = ?
          ORDER BY c.created_at DESC";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $citizen_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Complaints - Haritha</title>
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
        .container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #e8f5e9;
            padding-bottom: 12px;
        }

        .page-title {
            color: var(--text-main);
            margin: 0;
            font-size: 22px;
            font-weight: 700;
        }

        .btn-new-complaint {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 18px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.2s;
        }

        .btn-new-complaint:hover {
            background-color: var(--primary-hover);
        }

        /* --- Complaint Card Components --- */
        .complaint-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            display: flex;
            gap: 24px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .complaint-card:hover {
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
        }

        .complaint-main {
            flex: 1;
        }

        .complaint-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            gap: 15px;
        }

        .complaint-title {
            font-size: 18px;
            color: var(--primary-color);
            margin: 0;
            font-weight: 700;
        }

        .badge-status {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        /* Dynamic Status Colors */
        .status-pending { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .status-in-progress { background-color: #cce5ff; color: #004085; border: 1px solid #b8daff; }
        .status-resolved { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-rejected { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status-default { background-color: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }

        .meta-info {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 6px;
        }

        .meta-item strong {
            color: var(--text-main);
        }

        .complaint-desc {
            font-size: 14px;
            color: #444;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .reply-box {
            background-color: #f0fdf4;
            border-left: 4px solid var(--primary-color);
            padding: 14px 18px;
            border-radius: 0 8px 8px 0;
            margin-top: 15px;
        }

        .reply-box h4 {
            margin: 0 0 6px 0;
            font-size: 14px;
            color: var(--primary-color);
            font-weight: 700;
        }

        .reply-box p {
            margin: 0;
            font-size: 13px;
            color: #333;
            line-height: 1.5;
        }

        .complaint-media {
            width: 200px;
            flex-shrink: 0;
        }

        .complaint-img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .complaint-img:hover {
            opacity: 0.9;
        }

        .no-records {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            color: var(--text-muted);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .no-records p {
            font-size: 16px;
            margin-bottom: 20px;
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

        @media (max-width: 768px) {
            .complaint-card {
                flex-direction: column-reverse;
            }
            .complaint-media {
                width: 100%;
            }
            .complaint-img {
                height: 200px;
            }
            .page-header-flex {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
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

<div class="container">
    <div class="page-header-flex">
        <h2 class="page-title">Complaint History</h2>
        <a href="make_complaint.php" class="btn-new-complaint">+ Submit New Complaint</a>
    </div>

    <?php if ($result && mysqli_num_rows($result) > 0): ?>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <?php 
                // Determine badge color class based on status name
                $status_class = 'status-default';
                $status_lower = strtolower($row['status_name'] ?? '');
                
                if (strpos($status_lower, 'pending') !== false) {
                    $status_class = 'status-pending';
                } elseif (strpos($status_lower, 'progress') !== false) {
                    $status_class = 'status-in-progress';
                } elseif (strpos($status_lower, 'resolve') !== false) {
                    $status_class = 'status-resolved';
                } elseif (strpos($status_lower, 'reject') !== false) {
                    $status_class = 'status-rejected';
                }
            ?>
            <div class="complaint-card">
                <div class="complaint-main">
                    <div class="complaint-header">
                        <h3 class="complaint-title"><?php echo htmlspecialchars($row['title']); ?></h3>
                        <span class="badge-status <?php echo $status_class; ?>">
                            <?php echo htmlspecialchars($row['status_name'] ?? 'Pending'); ?>
                        </span>
                    </div>

                    <div class="meta-info">
                        <span class="meta-item"><strong>Category:</strong> <?php echo htmlspecialchars($row['category_name'] ?? 'General'); ?></span>
                        <span class="meta-item"><strong>Submitted:</strong> <?php echo date('M d, Y', strtotime($row['created_at'])); ?></span>
                        <?php if (!empty($row['district'])): ?>
                            <span class="meta-item"><strong>District:</strong> <?php echo htmlspecialchars($row['district']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($row['ds_division'])): ?>
                            <span class="meta-item"><strong>DS Division:</strong> <?php echo htmlspecialchars($row['ds_division']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="complaint-desc">
                        <?php echo nl2br(htmlspecialchars($row['description'])); ?>
                    </div>

                    <?php if (!empty($row['reply'])): ?>
                        <div class="reply-box">
                            <h4>Authority Response:</h4>
                            <p><?php echo nl2br(htmlspecialchars($row['reply'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($row['image_path'])): ?>
                    <div class="complaint-media">
                        <a href="../<?php echo htmlspecialchars($row['image_path']); ?>" target="_blank" title="View Full Image">
                            <img src="../<?php echo htmlspecialchars($row['image_path']); ?>" alt="Complaint Evidence" class="complaint-img" onerror="this.parentElement.style.display='none';">
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="no-records">
            <p>You have not submitted any complaints yet.</p>
            <a href="make_complaint.php" class="btn-new-complaint">Submit Your First Complaint</a>
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