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

// Redirect if event target parameter is missing
if (!isset($_GET['event_id'])) {
    header("Location: participation_manage.php");
    exit();
}

$event_id = (int)$_GET['event_id'];

// 1. Fetch metadata about this specific event
$event_stmt = mysqli_prepare($conn, "SELECT event_title, event_date, location FROM volunteer_events WHERE event_id = ?");
mysqli_stmt_bind_param($event_stmt, "i", $event_id);
mysqli_stmt_execute($event_stmt);
$event_meta = mysqli_fetch_assoc(mysqli_stmt_get_result($event_stmt));

if (!$event_meta) {
    // Event doesn't exist
    header("Location: participation_manage.php");
    exit();
}

// 2. Fetch all registered participants for this event using the structural schema fields
$participants_query = "
    SELECT 
        vp.registration_id, 
        vp.user_id, 
        vp.registered_at, 
        vp.attendance_verified, 
        vp.verified_at,
        u.f_name, 
        u.l_name, 
        u.email
    FROM volunteer_participants vp
    INNER JOIN users u ON vp.user_id = u.user_id
    WHERE vp.event_id = $event_id
    ORDER BY vp.registered_at ASC
";
$result = mysqli_query($conn, $participants_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Roster - View Participants</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; }
        .header { background-color: #1b5e20; color: white; padding: 20px; text-align: center; position: relative; }
        .btn-back { position: absolute; left: 20px; top: 30px; background-color: white; color: #1b5e20; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-weight: bold; font-size: 14px; }
        .btn-back:hover { background-color: #e0e0e0; }
        
        .event-summary-card { width: 85%; margin: 25px auto 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0px 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; box-sizing: border-box; }
        .event-info h2 { color: #1b5e20; margin: 0 0 5px 0; }
        .event-info p { margin: 0; color: #555; font-size: 14px; }
        .roster-count { background-color: #1b5e20; color: white; padding: 10px 20px; border-radius: 30px; font-weight: bold; font-size: 18px; }

        .section-title { width: 85%; margin: 30px auto 10px auto; color: #1b5e20; border-bottom: 2px solid #1b5e20; padding-bottom: 5px; }

        .table-container { width: 85%; margin: 20px auto 50px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0px 2px 8px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #ddd; }
        th { background-color: #1b5e20; color: white; }
        tr:hover { background-color: #f9f9f9; }
        
        .no-data { text-align: center; padding: 40px; color: #757575; font-style: italic; }
        
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-verified { background-color: #c8e6c9; color: #25602a; }
        .status-pending { background-color: #fff9c4; color: #fbc02d; }
    </style>
</head>
<body>

<div class="header">
    <a href="participation_manage.php" class="btn-back">← Back to Events</a>
    <h1>Event Roster Portal</h1>
    <p>System Participant Log Audits & Check-Ins</p>
</div>

<div class="event-summary-card">
    <div class="event-info">
        <h2><?php echo htmlspecialchars($event_meta['event_title']); ?></h2>
        <p><strong>Location:</strong> <?php echo htmlspecialchars($event_meta['location']); ?> | <strong>Date:</strong> <?php echo date('Y-m-d', strtotime($event_meta['event_date'])); ?></p>
    </div>
    <div class="roster-count">
        Total Joined: <?php echo mysqli_num_rows($result); ?>
    </div>
</div>

<h2 class="section-title">Registered Roster</h2>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Registration ID</th>
                <th>User ID</th>
                <th>Volunteer Full Name</th>
                <th>Email Address</th>
                <th>Registration Timestamp</th>
                <th>Attendance</th>
                <th>Verification Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><strong>#<?php echo htmlspecialchars($row['registration_id']); ?></strong></td>
                        <td>#<?php echo htmlspecialchars($row['user_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['f_name'] . ' ' . $row['l_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($row['registered_at'])); ?></td>
                        <td>
                            <?php if ($row['attendance_verified'] == 1): ?>
                                <span class="badge status-verified">Verified Present</span>
                            <?php else: ?>
                                <span class="badge status-pending">Not Checked-In</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                                echo (!empty($row['verified_at'])) 
                                    ? date('Y-m-d H:i:s', strtotime($row['verified_at'])) 
                                    : '<span style="color:#aaa;">—</span>'; 
                            ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="no-data">No active volunteers have booked or joined this campaign yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>