<?php
session_start();
include("../config/db.php");

// 1. Secure Access Check: Ensure user is logged in and is a Local Authority official (Role 3)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 5 && $_SESSION['role_id'] != 3 || empty($_SESSION['user_id'])) {
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

// 3. Safe Fallback Count Query: Filters by status to prevent 'Unknown column' exceptions
$escalated_count = 0;

// JOINing complaints with complaint_status using the shared status_id field
$count_query = "SELECT COUNT(*) as pending_count 
                FROM complaints c
                INNER JOIN complaint_status s ON c.status_id = s.status_id 
                WHERE s.status_name = 'ESCALATED'";
/* * NOTE FOR YOU: If your complaints table uses a different name to store the region 
 * (like 'division', 'address', or 'gn_division'), change the query above to:
 * * $count_query = "SELECT COUNT(*) as pending_count FROM complaints WHERE your_column_name = ? AND status = 'ESCALATED'";
 * * And then uncomment the binding lines below:
 */

$count_stmt = mysqli_prepare($conn, $count_query);
if ($count_stmt) {
    // mysqli_stmt_bind_param($count_stmt, "s", $la_division); // Uncomment this line if filtering by region column
    mysqli_stmt_execute($count_stmt);
    $count_res = mysqli_stmt_get_result($count_stmt);
    if ($row = mysqli_fetch_assoc($count_res)) {
        $escalated_count = intval($row['pending_count']);
    }
    mysqli_stmt_close($count_stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Local Authority Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; }
        .header { background-color: #006064; color: white; padding: 20px; text-align: center; position: relative; }
        .logout-btn { position: absolute; right: 20px; top: 25px; background-color: #b71c1c; color: white; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-size: 0.9em; }
        .logout-btn:hover { background-color: #7f0000; }
        .container { width: 80%; margin: 30px auto; display: flex; gap: 20px; }
        .card { background: white; padding: 25px; width: 33.33%; border-radius: 10px; text-align: center; box-shadow: 0px 2px 8px gray; display: flex; flex-direction: column; justify-content: space-between; }
        .card h3 { color: #006064; margin-top: 0; }
        button { background-color: #006064; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px; width: 100%; font-weight: bold; }
        button:hover { background-color: #00363a; }
        
        /* Dynamic Notification Badge UI */
        .badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 0.85em; font-weight: bold; margin-bottom: 15px; }
        .badge-alert { background-color: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
        .badge-clear { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .empty-text { color: #666; font-style: italic; }
    </style>
</head>
<body>

<div class="header">
    <a href="../auth/logout.php" class="logout-btn">Sign Out</a>
    <h1>Local Authority Dashboard</h1>
    <p>Action Center &mdash; Local Division: <strong><?php echo htmlspecialchars($la_division ?: 'Unassigned'); ?></strong></p>
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

</body>
</html>