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
    </style>
</head>
<body>

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
</body>
</html>