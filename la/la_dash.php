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
        
        .container { width: 85%; margin: 30px auto; display: flex; gap: 20px; }
        .card { background: white; padding: 25px; width: 33.33%; border-radius: 10px; text-align: center; box-shadow: 0px 2px 8px rgba(0,0,0,0.1); display: flex; flex-direction: column; justify-content: space-between; }
        .card h3 { color: #006064; margin-top: 0; }
        button, .action-btn { background-color: #006064; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px; font-weight: bold; text-decoration: none; display: inline-block; }
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
        .sm-btn { padding: 5px 10px; font-size: 12px; border-radius: 3px; }
    </style>
</head>
<body>

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

</body>
</html>