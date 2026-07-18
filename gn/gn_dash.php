<?php
session_start();
include("../config/db.php");

// 1. Secure Access Check: Ensure user is logged in and is a Grama Niladhari (Role 2)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 2 || empty($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

// Fetch officer metadata natively for personalization
$officer_id = $_SESSION['user_id'];
$officer_name = "Officer";
$gn_division = "Your Division";

$query = "SELECT f_name, l_name, gn_division FROM users WHERE user_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $officer_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $officer_name = $row['f_name'] . " " . $row['l_name'];
        $gn_division  = $row['gn_division'];
    }
    mysqli_stmt_close($stmt);
}

// 2. Fetch Assigned Complaints (Show currently assigned OR historically escalated complaints)
$complaints = [];
$complaint_query = "SELECT c.complaint_id, c.title, c.description, c.location_description, 
                            c.latitude, c.longitude, c.created_at,
                            cc.category_name,
                            cs.status_name
                    FROM complaints c
                    LEFT JOIN complaint_categories cc ON c.category_id = cc.category_id
                    LEFT JOIN complaint_status cs ON c.status_id = cs.status_id
                    WHERE c.assigned_to_id = ? 
                       OR c.reply LIKE '%escalated%' 
                    ORDER BY c.created_at DESC";

$comp_stmt = mysqli_prepare($conn, $complaint_query);
if ($comp_stmt) {
    mysqli_stmt_bind_param($comp_stmt, "i", $officer_id);
    mysqli_stmt_execute($comp_stmt);
    $comp_result = mysqli_stmt_get_result($comp_stmt);
    while ($row = mysqli_fetch_assoc($comp_result)) {
        $complaints[] = $row;
    }
    mysqli_stmt_close($comp_stmt);
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
    <title>Grama Niladhari Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; padding-bottom: 50px; }
        .header { background-color: #e65100; color: white; padding: 20px; text-align: center; position: relative; }
        .container { width: 85%; margin: 30px auto; display: flex; flex-wrap: wrap; gap: 20px; justify-content: center; }
        
        /* Adjusted width parameters to support 4 columns inline cleanly */
        .card { background: white; padding: 25px; width: 22%; min-width: 220px; border-radius: 10px; text-align: center; box-shadow: 0px 2px 8px gray; display: flex; flex-direction: column; justify-content: space-between; }
        .card h3 { color: #e65100; margin-top: 0; }
        .card p { flex-grow: 1; margin-bottom: 15px; }
        
        /* Button styles with a small margin bottom for spacing between stacked buttons */
        button { background-color: #e65100; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px; width: 100%; font-weight: bold; margin-bottom: 8px; }
        button:last-child { margin-bottom: 0; }
        button:hover { background-color: #b33600; }
        
        .logout-btn { 
            position: absolute; top: 20px; right: 20px; 
            background-color: #d32f2f; color: white; 
            padding: 8px 15px; border-radius: 5px; 
            text-decoration: none; font-size: 14px; font-weight: bold; 
        }
        .logout-btn:hover { background-color: #9a0007; }
        .welcome-text { margin-top: 5px; font-style: italic; color: #ffccbc; }

        /* Complaints Section Styling */
        .table-section { width: 85%; margin: 20px auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0px 2px 8px gray; }
        .table-section h2 { color: #e65100; margin-top: 0; border-bottom: 2px solid #ffccbc; padding-bottom: 10px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; text-align: left; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #ddd; }
        th { background-color: #ffe0b2; color: #e65100; font-weight: bold; }
        tr:hover { background-color: #fbe9e7; }
        
        .no-data { text-align: center; color: #757575; padding: 30px; font-style: italic; }
        
        /* Category Badges */
        .cat-badge { background-color: #eceff1; color: #455a64; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }

        /* Dynamic Status Badges */
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .status-pending { background-color: #ffe0b2; color: #e65100; }
        .status-progress { background-color: #b3e5fc; color: #0288d1; }
        .status-resolved { background-color: #c8e6c9; color: #388e3c; }
        .status-fallback { background-color: #e0e0e0; color: #616161; }

        .action-link { color: #e65100; text-decoration: none; font-weight: bold; }
        .action-link:hover { text-decoration: underline; }

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
            border-bottom: 2px solid #e65100;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }

        .noti-panel-header h2 {
            margin: 0;
            color: #e65100;
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
            background: #fffaf8;
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
    <a href="../auth/logout.php" class="logout-btn">Logout</a>
    <h1>Grama Niladhari Dashboard</h1>
    <p class="welcome-text">Welcome, <?php echo htmlspecialchars($officer_name); ?> | Division: <?php echo htmlspecialchars($gn_division); ?></p>
</div>

<div class="container">
    <div class="card">
        <h3>Pending Verifications</h3>
        <p>Verify citizen profiles, addresses, and local residency details.</p>
        <button onclick="location.href='verify_citizens.php'">Verify Records</button>
    </div>
    
    <div class="card">
        <h3>Active Territory Map</h3>
        <p>View complete geolocated environmental maps inside your boundary constraints.</p>
        <button onclick="location.href='gn_map.php'">Open Map View</button>
    </div>
    
    <div class="card">
        <h3>Submit Field Report</h3>
        <p>Log physical environment assessments directly to the DS office.</p>
        <button onclick="location.href='submit_report.php'">Log Field Action</button>
    </div>

    <div class="card">
        <h3>Volunteer Programs</h3>
        <p>Create and mobilize cleanup campaigns or local environmental events.</p>
        <button onclick="location.href='volunteer_event_submit.php'">Post Opportunity</button>
        <button onclick="location.href='participation_manage.php'">Participation Management</button>
    </div>
</div>

<div class="table-section">
    <h2>Assigned Environmental Complaints</h2>
    <?php if (empty($complaints)): ?>
        <div class="no-data">No environmental complaints currently routed to your division.</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Category</th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Location Details</th>
                    <th>Status</th>
                    <th>Date Reported</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($complaints as $comp): ?>
                    <?php 
                        $raw_status = isset($comp['status_name']) ? strtoupper(trim($comp['status_name'])) : 'PENDING';
                        
                        if ($raw_status === 'PENDING' || $raw_status === 'NEW' || $raw_status === 'SUBMITTED') {
                            $status_class = 'status-pending';
                        } elseif ($raw_status === 'IN PROGRESS' || $raw_status === 'INVESTIGATING' || $raw_status === 'ASSIGNED') {
                            $status_class = 'status-progress';
                        } elseif ($raw_status === 'RESOLVED' || $raw_status === 'CLOSED' || $raw_status === 'COMPLETED') {
                            $status_class = 'status-resolved';
                        } else {
                            $status_class = 'status-fallback';
                        }
                    ?>
                    <tr>
                        <td>#<?php echo $comp['complaint_id']; ?></td>
                        <td>
                            <span class="cat-badge">
                                <?php echo htmlspecialchars($comp['category_name'] ?? 'General'); ?>
                            </span>
                        </td>
                        <td><b><?php echo htmlspecialchars($comp['title']); ?></b></td>
                        <td><?php echo htmlspecialchars(substr($comp['description'], 0, 60)) . (strlen($comp['description']) > 60 ? '...' : ''); ?></td>
                        <td><?php echo htmlspecialchars($comp['location_description'] ?: 'Coordinates provided'); ?></td>
                        <td>
                            <span class="status-badge <?php echo $status_class; ?>">
                                <?php echo htmlspecialchars($raw_status); ?>
                            </span>
                        </td>
                        <td><?php echo date('Y-m-d h:i A', strtotime($comp['created_at'])); ?></td>
                        <td>
                            <a href="view_complaint.php?id=<?php echo $comp['complaint_id']; ?>" class="action-link">Investigate</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
// Keep badge hidden on refresh if user has already viewed notifications
document.addEventListener("DOMContentLoaded", function() {
    var badge = document.getElementById('notiBadge');
    if (badge && localStorage.getItem('gnNotificationsViewed') === 'true') {
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
        
        // Instantly remove the indicator badge element upon opening
        if (badge) {
            badge.remove();
        }
        // Save state across page reloads locally
        localStorage.setItem('gnNotificationsViewed', 'true');
    } else {
        panel.classList.remove('open');
        overlay.style.display = 'none';
    }
}
</script>

</body>
</html>