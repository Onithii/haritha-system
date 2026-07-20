<?php
session_start();
include("../config/db.php");

// 1. Secure Access Check: Ensure user is logged in and is a Local Authority official (Role 3)
if (!isset($_SESSION['role_id']) || ($_SESSION['role_id'] != 5 && $_SESSION['role_id'] != 3) || empty($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

// 2. Fetch logged-in user details to scope the escalated issues
$user_id = intval($_SESSION['user_id']);
$user_query = "SELECT ds_division FROM users WHERE user_id = ?";
$user_stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_res = mysqli_stmt_get_result($user_stmt);
$user_data = mysqli_fetch_assoc($user_res);
mysqli_stmt_close($user_stmt);

$la_division = $user_data['ds_division'] ?? '';

// 3. Fetch complaints allocated/escalated to this LA Division
$complaints_list = [];
$escalated_count = 0;

// Dynamic Safe Fallback Strategy: Fallback to global assignment lookup if regional structural columns are missing
$list_query = "SELECT c.complaint_id, c.title, c.created_at, cc.category_name, cs.status_name 
               FROM complaints c
               LEFT JOIN complaint_categories cc ON c.category_id = cc.category_id
               INNER JOIN complaint_status cs ON c.status_id = cs.status_id 
               WHERE cs.status_name = 'ESCALATED' OR c.assigned_to_id = ?
               ORDER BY c.created_at DESC";

$list_stmt = mysqli_prepare($conn, $list_query);
if ($list_stmt) {
    mysqli_stmt_bind_param($list_stmt, "i", $user_id);
    mysqli_stmt_execute($list_stmt);
    $list_res = mysqli_stmt_get_result($list_stmt);
    while ($row = mysqli_fetch_assoc($list_res)) {
        $complaints_list[] = $row;
    }
    $escalated_count = count($complaints_list);
    mysqli_stmt_close($list_stmt);
}

// --- ADDED: Fetch Active System Broadcast Alerts ---
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
    <title>Local Authority Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; padding-bottom: 50px; }
        .header { background-color: #006064; color: white; padding: 20px; text-align: center; position: relative; }
        .logout-btn { position: absolute; right: 20px; top: 25px; background-color: #b71c1c; color: white; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-size: 0.9em; }
        .logout-btn:hover { background-color: #7f0000; }
        
        .container { width: 85%; margin: 30px auto; display: flex; flex-wrap: wrap; gap: 20px; justify-content: center; }
        
        /* Adjusted width parameters to accommodate multi-row responsive flexing smoothly */
        .card { background: white; padding: 25px; width: 22%; min-width: 240px; border-radius: 10px; text-align: center; box-shadow: 0px 2px 8px rgba(0,0,0,0.1); display: flex; flex-direction: column; justify-content: space-between; }
        .card h3 { color: #006064; margin-top: 0; }
        .card p { flex-grow: 1; margin-bottom: 15px; }
        
        button, .action-btn { background-color: #006064; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px; width: 100%; font-weight: bold; text-transform: uppercase; font-size: 13px; text-decoration: none; display: inline-block; box-sizing: border-box; margin-bottom: 8px; }
        button:last-child { margin-bottom: 0; }
        button:hover, .action-btn:hover { background-color: #00363a; }
        
        .badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 0.85em; font-weight: bold; margin-bottom: 15px; }
        .badge-alert { background-color: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
        .badge-clear { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .empty-text { color: #666; font-style: italic; }

        /* Table UI Configurations */
        .table-container { width: 85%; margin: 10px auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0px 2px 8px rgba(0,0,0,0.1); }
        .table-container h3 { color: #006064; margin-top: 0; border-bottom: 2px solid #b2dfdb; padding-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
        th, td { text-align: left; padding: 12px 15px; border-bottom: 1px solid #ddd; }
        th { background-color: #006064; color: white; }
        tr:hover { background-color: #f5f5f5; }
        .status-tag { background-color: #ff9100; color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .sm-btn { padding: 5px 10px; font-size: 12px; border-radius: 3px; margin-bottom: 0; width: auto; text-transform: none; }

        /* --- Notification Sticky Trigger Badge Styles --- */
        .notification-trigger {
            position: fixed;
            top: 40%;
            right: 0;
            background-color: #006064;
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
            background-color: #00363a;
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
            border-bottom: 2px solid #006064;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }

        .noti-panel-header h2 {
            margin: 0;
            color: #006064;
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
            background: #f2f8f8;
            border-left: 4px solid #006064;
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
    <a href="../auth/logout.php" class="logout-btn">Sign Out</a>
    <h1>Local Authority Dashboard</h1>
    <p>Action Center &mdash; Local Division: <strong><?php echo htmlspecialchars($la_division ?: 'Unassigned Division'); ?></strong></p>
</div>

<div class="container">
    <div class="card">
        <div>
            <h3>Assigned Tasks</h3>
            <?php if ($escalated_count > 0): ?>
                <div class="badge badge-alert">⚠️ <?php echo $escalated_count; ?> Escalation(s) Pending</div>
                <p>Environmental hazards have been referred to your council area for immediate cleanup operations.</p>
            <?php else: ?>
                <div class="badge badge-clear">✓ System Clear</div>
                <p class="empty-text">No pending environmental complaints are currently escalated to your division.</p>
            <?php endif; ?>
        </div>
        <button onclick="location.href='assigned_tasks.php'">View Tasks</button>
    </div>

    <div class="card">
        <div>
            <h3>Action Progress</h3>
            <p>Update statuses of ongoing on-site operations, team deployments, or mitigation status updates.</p>
        </div>
        <button onclick="location.href='update_progress.php'">Track Actions</button>
    </div>

    <div class="card">
        <div>
            <h3>Resolution Logs</h3>
            <p>Access historical accounts of resolved field operations and closed environmental clearance entries.</p>
        </div>
        <button onclick="location.href='resolved_logs.php'">Archived Resolutions</button>
    </div>

    <div class="card">
        <div>
            <h3>Volunteer Campaigns</h3>
            <p>Coordinate regional cleanup drives or manage environmental community programs.</p>
        </div>
        <button onclick="location.href='volunteer_event_submit.php'">Create Event</button>
        <button onclick="location.href='participation_manage.php'">Roster Management</button>
    </div>

    <!-- ADDED: Local Authority Report Engine Card -->
    <div class="card">
        <div>
            <h3>Analytics & Reports</h3>
            <p>Compile structural summary matrices and generate printable validation insights for jurisdictional reviews.</p>
        </div>
        <button onclick="location.href='la_generate_report.php'">Compile Reports</button>
    </div>
</div>

<div class="table-container">
    <h3>Escalated Jurisdictional Complaints</h3>
    <?php if (!empty($complaints_list)): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Complaint Title</th>
                    <th>Category</th>
                    <th>Date Received</th>
                    <th>Current Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($complaints_list as $comp): ?>
                    <tr>
                        <td><b>#<?php echo $comp['complaint_id']; ?></b></td>
                        <td><?php echo htmlspecialchars($comp['title']); ?></td>
                        <td><?php echo htmlspecialchars($comp['category_name'] ?? 'General'); ?></td>
                        <td><?php echo $comp['created_at']; ?></td>
                        <td><span class="status-tag"><?php echo htmlspecialchars($comp['status_name']); ?></span></td>
                        <td>
                            <a href="view_complaint.php?id=<?php echo $comp['complaint_id']; ?>" class="action-btn sm-btn">Investigate</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="empty-text" style="padding: 15px 0 0 0;">No matching records found in database tables for <?php echo htmlspecialchars($la_division ?: 'your division'); ?>.</p>
    <?php endif; ?>
</div>

<script>
// Keep badge hidden on refresh if user has already viewed notifications
document.addEventListener("DOMContentLoaded", function() {
    var badge = document.getElementById('notiBadge');
    if (badge && localStorage.getItem('laNotificationsViewed') === 'true') {
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
        // Save state across page reloads locally
        localStorage.setItem('laNotificationsViewed', 'true');
    } else {
        panel.classList.remove('open');
        overlay.style.display = 'none';
    }
}
</script>

</body>
</html>