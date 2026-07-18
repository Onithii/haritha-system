<?php
session_start();
include("../config/db.php");

// 1. Secure Access Check: Ensure user is logged in as a Divisional Secretariat (Role ID: 4)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 4 || empty($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

$ds_id = $_SESSION['user_id'];

// 2. Fetch complaints escalated/assigned to this specific DS officer
$query = "SELECT c.complaint_id, c.title, c.created_at, cc.category_name, cs.status_name 
          FROM complaints c
          LEFT JOIN complaint_categories cc ON c.category_id = cc.category_id
          LEFT JOIN complaint_status cs ON c.status_id = cs.status_id
          WHERE c.assigned_to_id = ? 
          ORDER BY c.created_at DESC";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $ds_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

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
    <title>Divisional Secretariat Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; padding-bottom: 50px; }
        .header { background-color: #0d47a1; color: white; padding: 20px; text-align: center; position: relative; }
        .logout-btn { position: absolute; right: 20px; top: 30px; background: #b71c1c; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 14px; }
        .logout-btn:hover { background: #7f0000; }
        
        .container { width: 85%; margin: 30px auto; display: flex; flex-wrap: wrap; gap: 20px; justify-content: center; }
        
        /* Adjusted width layout parameters to scale 4 data cards inline efficiently */
        .card { background: white; padding: 25px; width: 22%; min-width: 220px; border-radius: 10px; text-align: center; box-shadow: 0px 2px 8px rgba(0,0,0,0.1); display: flex; flex-direction: column; justify-content: space-between; }
        .card h3 { color: #0d47a1; margin-top: 0; }
        .card p { flex-grow: 1; margin-bottom: 15px; }
        
        button, .action-btn { background-color: #0d47a1; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px; width: 100%; font-weight: bold; text-transform: uppercase; font-size: 13px; text-decoration: none; display: inline-block; box-sizing: border-box; margin-bottom: 8px; }
        button:last-child { margin-bottom: 0; }
        button:hover, .action-btn:hover { background-color: #002171; }
        
        /* Table Layout Configurations */
        .table-container { width: 85%; margin: 10px auto 30px auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0px 2px 8px rgba(0,0,0,0.1); }
        .table-container h2 { color: #0d47a1; margin-top: 0; border-bottom: 2px solid #bbdefb; padding-bottom: 10px; font-size: 20px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { text-align: left; padding: 12px 15px; border-bottom: 1px solid #ddd; }
        th { background-color: #0d47a1; color: white; font-size: 14px; text-transform: uppercase; }
        tr:hover { background-color: #f5f5f5; }
        
        .no-records { text-align: center; padding: 30px; color: #757575; font-style: italic; font-weight: bold; }
        
        /* Badges for status rendering */
        .badge { padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; color: white; display: inline-block; }
        .status-assigned { background-color: #ff9800; }
        .status-progress { background-color: #0288d1; }
        .status-completed { background-color: #388e3c; }
        .status-default { background-color: #757575; }

        /* --- Notification Sticky Trigger Badge Styles --- */
        .notification-trigger {
            position: fixed;
            top: 40%;
            right: 0;
            background-color: #0d47a1;
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
            background-color: #002171;
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
            border-bottom: 2px solid #0d47a1;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }

        .noti-panel-header h2 {
            margin: 0;
            color: #0d47a1;
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
            background: #f4f8fb;
            border-left: 4px solid #0d47a1;
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
    <h1>Divisional Secretariat Dashboard</h1>
    <p>Division Performance & Escalation Overview</p>
    <a href="../auth/logout.php" class="logout-btn">Logout</a>
</div>

<div class="container">
    <div class="card">
        <h3>Division Overview</h3>
        <p>Track live statistics of complaints within your DS Division.</p>
        <button onclick="location.href='ds_stats.php'">View Statistics</button>
    </div>
    <div class="card">
        <h3>Escalated Cases</h3>
        <p>Review complaints that require direct DS intervention.</p>
        <button onclick="location.href='escalated_complaints.php'">Review Escalations</button>
    </div>
    <div class="card">
        <h3>GN Performance</h3>
        <p>Monitor reporting action rates of GN divisions under your scope.</p>
        <button onclick="location.href='gn_status.php'">GN Progress Logs</button>
    </div>
    <div class="card">
        <h3>Volunteer Campaigns</h3>
        <p>Coordinate regional cleanup drives or manage environmental community programs.</p>
        <button onclick="location.href='volunteer_event_submit.php'">Create Event</button>
        <button onclick="location.href='participation_manage.php'">Roster Management</button>
    </div>
</div>

<div class="table-container">
    <h2>Complaints Escalated to Your Jurisdiction</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 10%;">ID</th>
                <th style="width: 40%;">Complaint Title</th>
                <th style="width: 15%;">Category</th>
                <th style="width: 15%;">Status Matrix</th>
                <th style="width: 10%;">Escalated Date</th>
                <th style="width: 10%; text-align: center;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($result)): 
                    // Map statuses to CSS classes dynamically
                    $status_class = 'status-default';
                    if ($row['status_name'] === 'ASSIGNED') $status_class = 'status-assigned';
                    if ($row['status_name'] === 'IN PROGRESS') $status_class = 'status-progress';
                    if ($row['status_name'] === 'COMPLETED') $status_class = 'status-completed';
                ?>
                    <tr>
                        <td><b>#<?php echo $row['complaint_id']; ?></b></td>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td><?php echo htmlspecialchars($row['category_name'] ?? 'General'); ?></td>
                        <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($row['status_name']); ?></span></td>
                        <td><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></td>
                        <td style="text-align: center;">
                            <a href="view_complaint.php?id=<?php echo $row['complaint_id']; ?>" class="action-btn" style="padding: 6px 12px; font-size: 11px;">Investigate</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="no-records">No complaints yet escalated to your division.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php mysqli_stmt_close($stmt); ?>

<script>
// Keep badge hidden on refresh if user has already viewed notifications
document.addEventListener("DOMContentLoaded", function() {
    var badge = document.getElementById('notiBadge');
    if (badge && localStorage.getItem('dsNotificationsViewed') === 'true') {
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
        localStorage.setItem('dsNotificationsViewed', 'true');
    } else {
        panel.classList.remove('open');
        overlay.style.display = 'none';
    }
}
</script>

</body>
</html>