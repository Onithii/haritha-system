<?php
session_start();
include("../config/db.php");

// 1. Secure Access Check: Ensure user is logged in and is a Local Authority official (Role 3 or 5)
if (!isset($_SESSION['role_id']) || ($_SESSION['role_id'] != 5 && $_SESSION['role_id'] != 3) || empty($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

$la_id = intval($_SESSION['user_id']); 

// Handle Session Flash Messages (PRG pattern support)
$message = "";
$message_class = "";
if (isset($_SESSION['flash_msg']) && isset($_SESSION['flash_class'])) {
    $message = $_SESSION['flash_msg'];
    $message_class = $_SESSION['flash_class'];
    unset($_SESSION['flash_msg']);
    unset($_SESSION['flash_class']);
}

// 2. Handle Asynchronous AJAX request to view participants safely
if (isset($_GET['action']) && $_GET['action'] === 'get_participants' && isset($_GET['event_id'])) {
    header('Content-Type: application/json');
    $event_id = (int)$_GET['event_id'];
    
    // Security verification check: ensures this event actually belongs to the active LA session creator
    $verify_query = "SELECT 1 FROM volunteer_events WHERE event_id = ? AND created_by = ?";
    $v_stmt = mysqli_prepare($conn, $verify_query);
    $authorized = false;
    
    if ($v_stmt) {
        mysqli_stmt_bind_param($v_stmt, "ii", $event_id, $la_id);
        mysqli_stmt_execute($v_stmt);
        mysqli_stmt_store_result($v_stmt);
        if (mysqli_stmt_num_rows($v_stmt) > 0) {
            $authorized = true;
        }
        mysqli_stmt_close($v_stmt);
    }
    
    if (!$authorized) {
        echo json_encode([]);
        exit();
    }
    
    // Query to grab volunteer records registered to this event
    $p_query = "SELECT u.user_id, u.name, u.email, vp.attendance_verified 
                FROM volunteer_participants vp 
                JOIN users u ON vp.user_id = u.user_id 
                WHERE vp.event_id = ? 
                ORDER BY vp.attendance_verified DESC, u.name ASC";
                
    $p_stmt = mysqli_prepare($conn, $p_query);
    $participants = [];
    
    if ($p_stmt) {
        mysqli_stmt_bind_param($p_stmt, "i", $event_id);
        mysqli_stmt_execute($p_stmt);
        $result = mysqli_stmt_get_result($p_stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $participants[] = $row;
        }
        mysqli_stmt_close($p_stmt);
    }
    
    echo json_encode($participants);
    exit();
}

// 3. Handle Event Deletion via Safe Structural Transactions
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'delete_event') {
    $event_id_to_delete = (int)$_POST['delete_event_id'];
    
    mysqli_begin_transaction($conn);
    
    try {
        // Step A: Remove child records from volunteer_participants first to avoid foreign key failures
        $del_participants = "DELETE FROM volunteer_participants WHERE event_id = ? AND event_id IN (SELECT event_id FROM volunteer_events WHERE created_by = ?)";
        $stmt1 = mysqli_prepare($conn, $del_participants);
        mysqli_stmt_bind_param($stmt1, "ii", $event_id_to_delete, $la_id);
        mysqli_stmt_execute($stmt1);
        mysqli_stmt_close($stmt1);
        
        // Step B: Delete the event itself while verifying structural ownership scope
        $del_event = "DELETE FROM volunteer_events WHERE event_id = ? AND created_by = ?"; 
        $stmt2 = mysqli_prepare($conn, $del_event);
        mysqli_stmt_bind_param($stmt2, "ii", $event_id_to_delete, $la_id);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);
        
        mysqli_commit($conn);
        $_SESSION['flash_msg'] = "Event #" . $event_id_to_delete . " and all registration logs successfully deleted.";
        $_SESSION['flash_class'] = "msg-success";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['flash_msg'] = "Error: Could not delete the event safely. Please clear dependencies.";
        $_SESSION['flash_class'] = "msg-error";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// 4. Fetch only volunteer programs deployed by this explicit LA officer
$events = [];
$events_query = "SELECT e.event_id, e.event_title, e.location, e.event_date, COUNT(vp.user_id) as total_joined 
                 FROM volunteer_events e
                 LEFT JOIN volunteer_participants vp ON e.event_id = vp.event_id
                 WHERE e.created_by = ? 
                 GROUP BY e.event_id
                 ORDER BY e.event_date DESC";

$evt_stmt = mysqli_prepare($conn, $events_query);
if ($evt_stmt) {
    mysqli_stmt_bind_param($evt_stmt, "i", $la_id);
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
    <title>Participation Management</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; padding-bottom: 50px; }
        .header { background-color: #006064; color: white; padding: 20px; text-align: center; position: relative; }
        .back-btn { 
            position: absolute; top: 20px; left: 20px; 
            background-color: white; color: #006064; 
            padding: 8px 15px; border-radius: 5px; 
            text-decoration: none; font-size: 14px; font-weight: bold; 
        }
        .back-btn:hover { background-color: #b2dfdb; }
        
        .table-section { width: 85%; margin: 30px auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0px 2px 8px rgba(0,0,0,0.1); }
        .table-section h2 { color: #006064; margin-top: 0; border-bottom: 2px solid #b2dfdb; padding-bottom: 10px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; text-align: left; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #ddd; vertical-align: middle; }
        th { background-color: #b2dfdb; color: #006064; font-weight: bold; }
        tr:hover { background-color: #f5f5f5; }
        
        .no-data { text-align: center; color: #757575; padding: 30px; font-style: italic; }
        
        .btn-view { color: #0288d1; font-weight: bold; cursor: pointer; background: none; border: none; padding: 0; font-size: 14px; text-decoration: underline; }
        .btn-view:hover { color: #01579b; }
        
        .btn-delete { 
            background-color: #d32f2f; color: white; 
            border: none; padding: 6px 12px; 
            border-radius: 4px; cursor: pointer; 
            font-weight: bold; font-size: 13px;
        }
        .btn-delete:hover { background-color: #9a0007; }
        
        /* Message styling */
        .msg-box { padding: 12px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; }
        .msg-success { background-color: #c8e6c9; color: #388e3c; border-left: 5px solid #388e3c; }
        .msg-error { background-color: #ffcdd2; color: #c62828; border-left: 5px solid #c62828; }

        /* Dynamic Modal Elements */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-box { background: white; width: 50%; min-width: 450px; max-height: 80vh; border-radius: 8px; overflow-y: auto; padding: 25px; position: relative; box-shadow: 0 5px 20px rgba(0,0,0,0.3); animation: fadeIn 0.2s ease-out; }
        .close-modal { position: absolute; top: 15px; right: 20px; font-size: 24px; color: #777; cursor: pointer; font-weight: bold; }
        .close-modal:hover { color: #000; }
        
        .modal-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .modal-table th, .modal-table td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; font-size: 14px; }
        .modal-table th { background: #eceff1; color: #37474f; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .badge-verified { background: #c8e6c9; color: #1b5e20; }
        .badge-pending { background: #ffe082; color: #e65100; }
        
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>
</head>
<body>

<div class="header">
    <a href="la_dashboard.php" class="back-btn">← Back to Dashboard</a>
    <h1>Volunteer Participation Management</h1>
</div>

<div class="table-section">
    <h2>Active Programs & Roster Links</h2>
    
    <?php if (!empty($message)): ?>
        <div class="msg-box <?php echo $message_class; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($events)): ?>
        <div class="no-data">You haven't created or mobilized any volunteer programs yet.</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Event ID</th>
                    <th>Event Name</th>
                    <th>Date Scheduled</th>
                    <th>Location</th>
                    <th>Roster Management</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td>#<?php echo $event['event_id']; ?></td>
                        <td><b><?php echo htmlspecialchars($event['event_title']); ?></b></td>
                        <td><?php echo date('Y-m-d', strtotime($event['event_date'])); ?></td>
                        <td><?php echo htmlspecialchars($event['location'] ?? 'Not Specified'); ?></td>
                        <td>
                            <button class="btn-view" onclick="openParticipantsModal(<?php echo $event['event_id']; ?>, '<?php echo urlencode($event['event_title']); ?>')">
                                View Participants (<?php echo $event['total_joined']; ?>) →
                            </button>
                        </td>
                        <td>
                            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to completely delete this event? All verification histories will be lost logs.');" style="margin:0;">
                                <input type="hidden" name="action" value="delete_event">
                                <input type="hidden" name="delete_event_id" value="<?php echo $event['event_id']; ?>">
                                <button type="submit" class="btn-delete">Delete Event</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Roster View Overlay Modal -->
<div class="modal-overlay" id="participantModal">
    <div class="modal-box">
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <h3 id="modalTitle" style="color: #006064; margin-top: 0;">Roster Enrolment</h3>
        <div id="modalContent">
            <p style="color:#666;">Loading data feeds securely...</p>
        </div>
    </div>
</div>

<script>
function openParticipantsModal(eventId, eventTitle) {
    const modal = document.getElementById('participantModal');
    const titleContainer = document.getElementById('modalTitle');
    const contentContainer = document.getElementById('modalContent');
    
    titleContainer.textContent = "Volunteers: " + decodeURIComponent(eventTitle);
    contentContainer.innerHTML = "<p style='color:#666;'>Extracting current roster data from server...</p>";
    modal.style.display = 'flex';

    // Async Fetch lookup back to this exact script execution runtime
    fetch(`${window.location.pathname}?action=get_participants&event_id=${eventId}`)
        .then(response => response.json())
        .then(data => {
            if (data.length === 0) {
                contentContainer.innerHTML = "<p style='color:#757575; font-style:italic; text-align:center; margin-top:20px;'>No citizens have registered for this campaign yet.</p>";
                return;
            }

            let tableHtml = `
                <table class='modal-table'>
                    <thead>
                        <tr>
                            <th>Volunteer Name</th>
                            <th>Email Handle</th>
                            <th>Verification Check</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            data.forEach(p => {
                let statusBadge = p.attendance_verified == 1 
                    ? `<span class="badge badge-verified">✓ Attended</span>` 
                    : `<span class="badge badge-pending">Pending Scan</span>`;
                
                tableHtml += `
                    <tr>
                        <td><strong>${escapeHtml(p.name)}</strong></td>
                        <td>${escapeHtml(p.email)}</td>
                        <td>${statusBadge}</td>
                    </tr>
                `;
            });

            tableHtml += "</tbody></table>";
            contentContainer.innerHTML = tableHtml;
        })
        .catch(err => {
            contentContainer.innerHTML = "<p style='color:#d32f2f;'>Error fetching data feed. Please try again.</p>";
        });
}

function closeModal() {
    document.getElementById('participantModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('participantModal');
    if (event.target === modal) {
        modal.style.display = "none";
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>
</body>
</html>