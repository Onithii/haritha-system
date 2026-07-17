<?php
session_start();
include("../config/db.php");

// Secure Access Check: Ensure user is logged in and is an Admin (Role 5)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 5 || empty($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

// Handle Event Deletion
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    
    // Begin transaction to safely remove references first
    mysqli_begin_transaction($conn);
    try {
        // 1. Delete participant relations first to preserve foreign key integrity
        $del_participants = mysqli_prepare($conn, "DELETE FROM volunteer_participants WHERE event_id = ?");
        mysqli_stmt_bind_param($del_participants, "i", $delete_id);
        mysqli_stmt_execute($del_participants);
        
        // 2. Delete the actual event
        $del_event = mysqli_prepare($conn, "DELETE FROM volunteer_events WHERE event_id = ?");
        mysqli_stmt_bind_param($del_event, "i", $delete_id);
        mysqli_stmt_execute($del_event);
        
        mysqli_commit($conn);
        header("Location: participation_manage.php?msg=deleted");
        exit();
    } catch (Exception $e) {
        mysqli_rollback($conn);
        header("Location: participation_manage.php?error=failed");
        exit();
    }
}

// Fetch all events from the database
$query = "SELECT event_id, event_title, event_date, location, status FROM volunteer_events ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Management Portal</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; }
        .header { background-color: #1b5e20; color: white; padding: 20px; text-align: center; position: relative; }
        .btn-back { position: absolute; left: 20px; top: 30px; background-color: white; color: #1b5e20; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-weight: bold; font-size: 14px; }
        .btn-back:hover { background-color: #e0e0e0; }
        
        .section-title { width: 85%; margin: 30px auto 10px auto; color: #1b5e20; border-bottom: 2px solid #1b5e20; padding-bottom: 5px; }
        
        .alert { width: 85%; margin: 15px auto; padding: 12px; border-radius: 5px; font-weight: bold; text-align: center; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .table-container { width: 85%; margin: 20px auto 50px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0px 2px 8px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #ddd; }
        th { background-color: #1b5e20; color: white; }
        tr:hover { background-color: #f9f9f9; }
        
        .no-data { text-align: center; padding: 30px; color: #757575; font-style: italic; }
        
        .btn-action { font-weight: bold; text-decoration: none; padding: 5px 10px; border-radius: 4px; font-size: 13px; }
        .btn-view { color: #1b5e20; border: 1px solid #1b5e20; background: transparent; }
        .btn-view:hover { background-color: #1b5e20; color: white; }
        .btn-delete { color: #c62828; border: 1px solid #c62828; background: transparent; margin-left: 5px; }
        .btn-delete:hover { background-color: #c62828; color: white; }
        
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-open { background-color: #c8e6c9; color: #25602a; }
        .status-closed { background-color: #ffcdd2; color: #c62828; }
    </style>
</head>
<body>

<div class="header">
    <a href="admin_dash.php" class="btn-back">← Back to Dashboard</a>
    <h1>Event Management System</h1>
    <p>Global Volunteer Campaigns & System Roster Control</p>
</div>

<h2 class="section-title">Active & Scheduled Events</h2>

<!-- Display Action Notifications -->
<?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
    <div class="alert alert-success">Event and its tracking participant references were successfully purged.</div>
<?php  endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] == 'failed'): ?>
    <div class="alert alert-danger">Error: Critical operational transaction rolled back. Event extraction failed.</div>
<?php endif; ?>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Event ID</th>
                <th>Event Name / Title</th>
                <th>Scheduled Date</th>
                <th>Location</th>
                <th>Status</th>
                <th style="text-align: center;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while($event = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><strong>#<?php echo htmlspecialchars($event['event_id']); ?></strong></td>
                        <td><?php echo htmlspecialchars($event['event_title']); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($event['event_date'])); ?></td>
                        <td><?php echo htmlspecialchars($event['location']); ?></td>
                        <td>
                            <span class="badge <?php echo ($event['status'] == 'OPEN') ? 'status-open' : 'status-closed'; ?>">
                                <?php echo htmlspecialchars($event['status']); ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <a href="view_participants.php?event_id=<?php echo $event['event_id']; ?>" class="btn-action btn-view">
                                View Participants
                            </a>
                            <a href="participation_manage.php?delete_id=<?php echo $event['event_id']; ?>" 
                               class="btn-action btn-delete" 
                               onclick="return confirm('Warning! Are you completely sure you want to delete this event? This will drop all joined participant logs permanently.');">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="no-data">No active volunteer events mapped inside the system records.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>