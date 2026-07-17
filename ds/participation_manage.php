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

// 2. Handle Event Deletion Request
$message = "";
$message_class = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event_id'])) {
    $delete_id = intval($_POST['delete_event_id']);
    
    // Security check: Match against 'created_by' from your database structure
    $delete_query = "DELETE FROM volunteer_events WHERE event_id = ? AND created_by = ?";
    $del_stmt = mysqli_prepare($conn, $delete_query);
    if ($del_stmt) {
        mysqli_stmt_bind_param($del_stmt, "ii", $delete_id, $ds_id);
        if (mysqli_stmt_execute($del_stmt)) {
            $message = "Event #" . $delete_id . " has been successfully deleted.";
            $message_class = "msg-success";
        } else {
            $message = "Error: Could not delete the event. Please try again.";
            $message_class = "msg-error";
        }
        mysqli_stmt_close($del_stmt);
    }
}

// 3. Fetch Volunteer Events using exact database columns: event_title, created_by
$events = [];
$events_query = "SELECT event_id, event_title, location, event_date 
                 FROM volunteer_events 
                 WHERE created_by = ? 
                 ORDER BY event_date DESC";

$evt_stmt = mysqli_prepare($conn, $events_query);
if ($evt_stmt) {
    mysqli_stmt_bind_param($evt_stmt, "i", $ds_id);
    mysqli_stmt_execute($evt_stmt);
    $evt_result = mysqli_stmt_get_result($evt_stmt);
    while ($row = mysqli_fetch_assoc($evt_result)) {
        $events[] = $row;
    }
    mysqli_stmt_close($evt_stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DS Roster Management</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; padding-bottom: 50px; }
        
        /* Matching the blue branding from ds_dash */
        .header { background-color: #0d47a1; color: white; padding: 20px; text-align: center; position: relative; }
        .back-btn { 
            position: absolute; top: 20px; left: 20px; 
            background-color: white; color: #0d47a1; 
            padding: 8px 15px; border-radius: 5px; 
            text-decoration: none; font-size: 14px; font-weight: bold; 
        }
        .back-btn:hover { background-color: #bbdefb; }
        
        .table-section { width: 85%; margin: 30px auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0px 2px 8px rgba(0,0,0,0.1); }
        .table-section h2 { color: #0d47a1; margin-top: 0; border-bottom: 2px solid #bbdefb; padding-bottom: 10px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; text-align: left; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #ddd; vertical-align: middle; }
        th { background-color: #bbdefb; color: #0d47a1; font-weight: bold; text-transform: uppercase; font-size: 13px; }
        tr:hover { background-color: #e3f2fd; }
        
        .no-data { text-align: center; color: #757575; padding: 30px; font-style: italic; font-weight: bold; }
        
        .action-link { color: #0288d1; text-decoration: none; font-weight: bold; }
        .action-link:hover { text-decoration: underline; }
        
        .btn-delete { 
            background-color: #b71c1c; color: white; 
            border: none; padding: 6px 12px; 
            border-radius: 4px; cursor: pointer; 
            font-weight: bold; font-size: 13px;
        }
        .btn-delete:hover { background-color: #7f0000; }
        
        /* Notification Messages */
        .msg-box { padding: 12px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; }
        .msg-success { background-color: #c8e6c9; color: #388e3c; border-left: 5px solid #388e3c; }
        .msg-error { background-color: #ffcdd2; color: #c62828; border-left: 5px solid #c62828; }
    </style>
</head>
<body>

<div class="header">
    <a href="ds_dash.php" class="back-btn">← Back to Dashboard</a>
    <h1>Volunteer Program & Roster Management</h1>
</div>

<div class="table-section">
    <h2>Regional Campaigns Managed By Your Office</h2>
    
    <?php if (!empty($message)): ?>
        <div class="msg-box <?php echo $message_class; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($events)): ?>
        <div class="no-data">Your office hasn't created or mobilized any regional volunteer campaigns yet.</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">Event ID</th>
                    <th style="width: 35%;">Campaign Title</th>
                    <th style="width: 15%;">Scheduled Date</th>
                    <th style="width: 20%;">Location Target</th>
                    <th style="width: 10%;">Roster</th>
                    <th style="width: 10%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td><b>#<?php echo $event['event_id']; ?></b></td>
                        <td><b><?php echo htmlspecialchars($event['event_title']); ?></b></td>
                        <td><?php echo date('Y-m-d', strtotime($event['event_date'])); ?></td>
                        <td><?php echo htmlspecialchars($event['location'] ?? 'Not Specified'); ?></td>
                        <td>
                            <a href="view_participants.php?event_id=<?php echo $event['event_id']; ?>" class="action-link">
                                View Roster →
                            </a>
                        </td>
                        <td>
                            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to completely cancel and delete this campaign? This action cannot be undone.');" style="margin:0;">
                                <input type="hidden" name="delete_event_id" value="<?php echo $event['event_id']; ?>">
                                <button type="submit" class="btn-delete">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>