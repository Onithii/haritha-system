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

// 2. Validate Event ID from Request parameter
if (!isset($_GET['event_id']) || empty($_GET['event_id'])) {
    header("Location: participation_manage.php");
    exit();
}

$event_id = intval($_GET['event_id']);
$officer_id = $_SESSION['user_id'];

// 3. Fetch Event Metadata to confirm ownership and display header context
$event_name = "Volunteer Program";
$event_check_query = "SELECT event_title FROM volunteer_events WHERE event_id = ? AND created_by = ? LIMIT 1";
$evt_stmt = mysqli_prepare($conn, $event_check_query);
if ($evt_stmt) {
    mysqli_stmt_bind_param($evt_stmt, "ii", $event_id, $officer_id);
    mysqli_stmt_execute($evt_stmt);
    $evt_result = mysqli_stmt_get_result($evt_stmt);
    if ($row = mysqli_fetch_assoc($evt_result)) {
        $event_name = $row['event_title'];
    } else {
        // Fallback security safety metric: Event not found or doesn't belong to this GN
        header("Location: participation_manage.php");
        exit();
    }
    mysqli_stmt_close($evt_stmt);
}

// 4. Fetch registered citizens along with their accurate QR verification column status
$participants = [];
$participants_query = "SELECT u.user_id, u.f_name, u.l_name, vp.attendance_verified 
                       FROM volunteer_participants vp
                       JOIN users u ON vp.user_id = u.user_id
                       WHERE vp.event_id = ?
                       ORDER BY u.f_name ASC";

$part_stmt = mysqli_prepare($conn, $participants_query);
if ($part_stmt) {
    mysqli_stmt_bind_param($part_stmt, "i", $event_id);
    mysqli_stmt_execute($part_stmt);
    $part_result = mysqli_stmt_get_result($part_stmt);
    while ($row = mysqli_fetch_assoc($part_result)) {
        $participants[] = $row;
    }
    mysqli_stmt_close($part_stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Participants</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; padding-bottom: 50px; }
        .header { background-color: #e65100; color: white; padding: 20px; text-align: center; position: relative; }
        .back-btn { 
            position: absolute; top: 20px; left: 20px; 
            background-color: white; color: #e65100; 
            padding: 8px 15px; border-radius: 5px; 
            text-decoration: none; font-size: 14px; font-weight: bold; 
        }
        .back-btn:hover { background-color: #ffe0b2; }
        
        .table-section { width: 80%; margin: 30px auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0px 2px 8px gray; }
        .table-section h2 { color: #e65100; margin-top: 0; padding-bottom: 5px; margin-bottom: 5px; }
        .subtitle { color: #757575; font-style: italic; margin-bottom: 20px; border-bottom: 2px solid #ffccbc; padding-bottom: 10px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; text-align: left; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #ddd; }
        th { background-color: #ffe0b2; color: #e65100; font-weight: bold; }
        tr:hover { background-color: #fbe9e7; }
        
        .no-data { text-align: center; color: #757575; padding: 30px; font-style: italic; }
        
        /* Proof Badges */
        .proof-badge { padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .proof-success { background-color: #c8e6c9; color: #388e3c; }
        .proof-null { background-color: #eeeeee; color: #9e9e9e; }
    </style>
</head>
<body>

<div class="header">
    <a href="participation_manage.php" class="back-btn">← Back to Programs</a>
    <h1>Roster & Verification Logs</h1>
</div>

<div class="table-section">
    <h2><?php echo htmlspecialchars($event_name); ?></h2>
    <div class="subtitle">Event ID Reference: #<?php echo $event_id; ?> | Registered Citizen Overview</div>

    <?php if (empty($participants)): ?>
        <div class="no-data">No citizens have registered for this event yet.</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Citizen ID</th>
                    <th>Full Name</th>
                    <th>Attendance Proof (QR Status)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($participants as $citizen): ?>
                    <tr>
                        <td>#<?php echo $citizen['user_id']; ?></td>
                        <td><b><?php echo htmlspecialchars($citizen['f_name'] . " " . $citizen['l_name']); ?></b></td>
                        <td>
                            <?php if (isset($citizen['attendance_verified']) && $citizen['attendance_verified'] == 1): ?>
                                <span class="proof-badge proof-success">Proved</span>
                            <?php else: ?>
                                <span class="proof-badge proof-null">Null</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>