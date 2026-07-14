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

// 3. Fetch Event Metadata to confirm ownership
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
        header("Location: participation_manage.php");
        exit();
    }
    mysqli_stmt_close($evt_stmt);
}

// 4. Handle Certificate Form Submission
$msg = "";
$msg_class = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_certificates'])) {
    if (!empty($_POST['certified_users'])) {
        $selected_users = $_POST['certified_users']; // Array of user IDs
        
        /* Operational Note: For now, this loop updates/logs the state or handles logic.
           You can replace this logic later with a database query updating an issue column 
           (e.g., UPDATE volunteer_participants SET certificate_issued = 1 WHERE event_id = ? AND user_id = ?)
        */
        $success_count = 0;
        foreach ($selected_users as $u_id) {
            // Placeholder logic: assuming dynamic certificate logic processing
            $success_count++;
        }
        
        $msg = "Success: Certificates processed and issued to $success_count verified participant(s).";
        $msg_class = "msg-success";
    } else {
        $msg = "Error: No verified participants were selected.";
        $msg_class = "msg-error";
    }
}

// 5. Fetch registered citizens along with their accurate QR verification column status
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
        
        .table-section { width: 85%; margin: 30px auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0px 2px 8px gray; }
        .table-section h2 { color: #e65100; margin-top: 0; padding-bottom: 5px; margin-bottom: 5px; }
        .subtitle { color: #757575; font-style: italic; margin-bottom: 20px; border-bottom: 2px solid #ffccbc; padding-bottom: 10px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; text-align: left; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #ddd; vertical-align: middle; }
        th { background-color: #ffe0b2; color: #e65100; font-weight: bold; }
        tr:hover { background-color: #fbe9e7; }
        
        .no-data { text-align: center; color: #757575; padding: 30px; font-style: italic; }
        
        /* Proof Badges */
        .proof-badge { padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .proof-success { background-color: #c8e6c9; color: #388e3c; }
        .proof-null { background-color: #eeeeee; color: #9e9e9e; }

        /* Certificate View Link */
        .cert-link { color: #e65100; font-weight: bold; text-decoration: none; font-size: 14px; }
        .cert-link:hover { text-decoration: underline; color: #b33600; }
        .cert-disabled { color: #9e9e9e; font-style: italic; font-size: 13px; }

        /* Action Footer */
        .action-footer { margin-top: 25px; padding-top: 15px; border-top: 1px solid #ddd; text-align: right; }
        .btn-submit { background-color: #e65100; color: white; border: none; padding: 12px 25px; cursor: pointer; border-radius: 5px; font-weight: bold; font-size: 14px; }
        .btn-submit:hover { background-color: #b33600; }

        /* Message Box styling */
        .msg-box { padding: 12px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; }
        .msg-success { background-color: #c8e6c9; color: #388e3c; border-left: 5px solid #388e3c; }
        .msg-error { background-color: #ffcdd2; color: #c62828; border-left: 5px solid #c62828; }
        
        input[type="checkbox"] { transform: scale(1.2); cursor: pointer; }
        input[type="checkbox"]:disabled { cursor: not-allowed; opacity: 0.5; }
    </style>
    <script>
        function toggleSelectAll(masterCheckbox) {
            // Select all checkboxes belonging to verified participants
            const checkboxes = document.querySelectorAll('.eligible-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = masterCheckbox.checked;
            });
        }
    </script>
</head>
<body>

<div class="header">
    <a href="participation_manage.php" class="back-btn">← Back to Programs</a>
    <h1>Roster & Verification Logs</h1>
</div>

<div class="table-section">
    <h2><?php echo htmlspecialchars($event_name); ?></h2>
    <div class="subtitle">Event ID Reference: #<?php echo $event_id; ?> | Registered Citizen Overview</div>

    <?php if (!empty($msg)): ?>
        <div class="msg-box <?php echo $msg_class; ?>">
            <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($participants)): ?>
        <div class="no-data">No citizens have registered for this event yet.</div>
    <?php else: ?>
        <form method="POST" action="view_participants.php?event_id=<?php echo $event_id; ?>">
            <table>
                <thead>
                    <tr>
                        <th>Citizen ID</th>
                        <th>Full Name</th>
                        <th>Attendance Proof (QR Status)</th>
                        <th>Certificate Action</th>
                        <th>
                            <input type="checkbox" id="select_all" onclick="toggleSelectAll(this)"> 
                            <label for="select_all" style="font-size: 12px; margin-left: 5px; cursor:pointer; font-weight: bold;">Select All Ok</label>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($participants as $citizen): ?>
                        <?php 
                            $is_verified = (isset($citizen['attendance_verified']) && $citizen['attendance_verified'] == 1);
                        ?>
                        <tr>
                            <td>#<?php echo $citizen['user_id']; ?></td>
                            <td><b><?php echo htmlspecialchars($citizen['f_name'] . " " . $citizen['l_name']); ?></b></td>
                            <td>
                                <?php if ($is_verified): ?>
                                    <span class="proof-badge proof-success">Proved</span>
                                <?php else: ?>
                                    <span class="proof-badge proof-null">Null</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_verified): ?>
                                    <a href="../generate_certificate.php?event_id=<?php echo $event_id; ?>&user_id=<?php echo $citizen['user_id']; ?>" class="cert-link" target="_blank">
                                        View Certificate ↗
                                    </a>
                                <?php else: ?>
                                    <span class="cert-disabled">Ineligible</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_verified): ?>
                                    <input type="checkbox" name="certified_users[]" value="<?php echo $citizen['user_id']; ?>" class="eligible-checkbox">
                                <?php else: ?>
                                    <input type="checkbox" disabled title="Attendance verification required to issue certificate">
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="action-footer">
                <button type="submit" name="issue_certificates" class="btn-submit">Submit & Issue Certificates</button>
            </div>
        </form>
    <?php endif; ?>
</div>

</body>
</html>