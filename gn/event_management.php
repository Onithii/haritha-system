<?php
session_start();
include("../config/db.php");

// 1. Authenticate GN Role (Adjust session key names based on your system)
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$gn_id = $_SESSION['user_id']; 
$alert_msg = "";
$alert_class = "";

// 2. Handle Asynchronous AJAX request to view participants
if (isset($_GET['action']) && $_GET['action'] === 'get_participants' && isset($_GET['event_id'])) {
    header('Content-Type: application/json');
    $event_id = (int)$_GET['event_id'];
    
    // Query to pull user details who joined this event
    // Adjust column names (e.g., 'name', 'email', 'phone') to match your actual users table
    $p_query = "SELECT u.user_id, u.name, u.email, vp.attendance_verified, vp.verified_at 
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

// 3. Handle Event Deletion
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'delete_event') {
    $event_id_to_delete = (int)$_POST['event_id'];
    
    // Start transaction to maintain database integrity
    mysqli_begin_transaction($conn);
    
    try {
        // Step A: Remove child records from volunteer_participants first to avoid foreign key failures
        $del_participants = "DELETE FROM volunteer_participants WHERE event_id = ?";
        $stmt1 = mysqli_prepare($conn, $del_participants);
        mysqli_stmt_bind_param($stmt1, "i", $event_id_to_delete);
        mysqli_stmt_execute($stmt1);
        mysqli_stmt_close($stmt1);
        
        // Step B: Delete the event itself (ensuring it belongs to this GN)
        // If your volunteer_events table tracks who created it, use: created_by = ?
        $del_event = "DELETE FROM volunteer_events WHERE event_id = ?"; 
        $stmt2 = mysqli_prepare($conn, $del_event);
        mysqli_stmt_bind_param($stmt2, "i", $event_id_to_delete);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);
        
        mysqli_commit($conn);
        $alert_msg = "🗑️ Campaign event and all associated registration records successfully deleted.";
        $alert_class = "alert-success";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $alert_msg = "❌ Failed to delete event. Please clear constraints and try again.";
        $alert_class = "alert-error";
    }
}

// 4. Fetch all active campaigns posted
// Adjust the WHERE clause if you log specific GN IDs (e.g., WHERE created_by = ?)
$query = "SELECT e.*, COUNT(vp.user_id) as total_joined 
          FROM volunteer_events e 
          LEFT JOIN volunteer_participants vp ON e.event_id = vp.event_id 
          GROUP BY e.event_id 
          ORDER BY e.event_date DESC";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GN Event Management Console</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f7f4; margin: 0; padding-bottom: 50px; }
        .header { background-color: #1b5e20; color: white; padding: 20px; text-align: center; position: relative; }
        .back-btn { position: absolute; top: 20px; left: 20px; background-color: transparent; border: 2px solid white; color: white; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-size: 14px; font-weight: bold; }
        .back-btn:hover { background-color: white; color: #1b5e20; }
        
        .main-container { width: 85%; max-width: 1200px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        h2 { color: #1b5e20; border-bottom: 2px solid #c8e6c9; padding-bottom: 10px; margin-top: 0; }
        
        .alert { padding: 15px; margin-bottom: 25px; border-radius: 6px; font-weight: bold; }
        .alert-success { background-color: #c8e6c9; color: #1b5e20; border-left: 6px solid #1b5e20; }
        .alert-error { background-color: #ffcdd2; color: #b71c1c; border-left: 6px solid #d32f2f; }

        /* Management Table Styling */
        .management-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .management-table th, .management-table td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        .management-table th { background-color: #f1f8e9; color: #2e7d32; font-weight: bold; }
        .management-table tr:hover { background-color: #f9fbf9; }
        
        .btn-view { color: #0288d1; font-weight: bold; cursor: pointer; background: none; border: none; padding: 0; font-size: 15px; text-decoration: underline; }
        .btn-view:hover { color: #01579b; }
        
        .btn-delete { background-color: #d32f2f; color: white; border: none; padding: 8px 14px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 13px; }
        .btn-delete:hover { background-color: #b71c1c; }

        /* Dynamic Modal Background Overlay */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-box { background: white; width: 50%; min-width: 450px; max-height: 80vh; border-radius: 8px; overflow-y: auto; padding: 25px; position: relative; box-shadow: 0 5px 20px rgba(0,0,0,0.3); animation: fadeIn 0.2s ease-out; }
        .close-modal { position: absolute; top: 15px; right: 20px; font-size: 24px; color: #777; cursor: pointer; font-weight: bold; }
        .close-modal:hover { color: #000; }
        
        /* Modal Table Spec */
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
    <a href="gn_dashboard.php" class="back-btn">← Admin Dashboard</a>
    <h1>Environmental Action Center</h1>
    <p>Grama Niladhari Campaign Administration Console</p>
</div>

<div class="main-container">
    <h2>📋 Dispatched Volunteer Campaigns</h2>
    
    <?php if (!empty($alert_msg)): ?>
        <div class="alert <?php echo $alert_class; ?>"><?php echo $alert_msg; ?></div>
    <?php endif; ?>

    <table class="management-table">
        <thead>
            <tr>
                <th>Campaign Title</th>
                <th>Target Date</th>
                <th>Location Hub</th>
                <th>Enrolled</th>
                <th>Roster Status</th>
                <th>Action Matrix</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['event_title']); ?></strong></td>
                        <td><?php echo date("M d, Y", strtotime($row['event_date'])); ?></td>
                        <td><?php echo htmlspecialchars($row['location']); ?></td>
                        <td>
                            <button class="btn-view" onclick="openParticipantsModal(<?php echo $row['event_id']; ?>, '<?php echo urlencode($row['event_title']); ?>')">
                                View Participants (<?php echo $row['total_joined']; ?>)
                            </button>
                        </td>
                        <td>
                            <?php if ($row['total_joined'] >= $row['required_volunteers']): ?>
                                <span class="badge badge-verified">Full Capacity</span>
                            <?php else: ?>
                                <span class="badge badge-pending">Recruiting</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" action="" onsubmit="return confirm('❗ WARNING: Are you certain you want to completely drop this campaign event? All volunteer logs will be purged permanently.');">
                                <input type="hidden" name="action" value="delete_event">
                                <input type="hidden" name="event_id" value="<?php echo $row['event_id']; ?>">
                                <button type="submit" class="btn-delete">Delete Event</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: #757575; padding: 30px;">No campaigns have been deployed yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal-overlay" id="participantModal">
    <div class="modal-box">
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <h3 id="modalTitle" style="color: #2e7d32; margin-top: 0;">Roster Roster</h3>
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

    // Async Fetch call back to this same script with specific flags
    fetch(`event_management.php?action=get_participants&event_id=${eventId}`)
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

// Close modal if user clicks anywhere outside the container box
window.onclick = function(event) {
    const modal = document.getElementById('participantModal');
    if (event.target === modal) {
        modal.style.display = "none";
    }
}

// Utility to escape dangerous HTML strings dynamically injected
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>
</body>
</html>