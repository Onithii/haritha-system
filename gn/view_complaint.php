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

$officer_id = $_SESSION['user_id'];
$complaint_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($complaint_id === 0) {
    die("Invalid Complaint ID.");
}

$message = "";

// 2. Handle Action Submissions (Form Submissions)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_type'])) {
        
        // --- ACTION A: UPDATE STATUS & LOG COGNITIVE REPLY ---
        if ($_POST['action_type'] === 'update_complaint') {
            $new_status_id = intval($_POST['status_id']);
            $reply_text = trim($_POST['reply_text']);
            
            // Updates both status_id and the reply column directly in your complaints table
            $update_q = "UPDATE complaints SET status_id = ?, reply = ?, updated_at = NOW() WHERE complaint_id = ? AND assigned_to_id = ?";
            $stmt = mysqli_prepare($conn, $update_q);
            mysqli_stmt_bind_param($stmt, "isii", $new_status_id, $reply_text, $complaint_id, $officer_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "<div class='alert success'>Complaint updated and progress logs committed successfully.</div>";
            } else {
                $message = "<div class='alert error'>Error updating record database configurations.</div>";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// 3. Fetch current complaint information from the provided schema mapping
$query = "SELECT c.*, cc.category_name, cs.status_name 
          FROM complaints c
          LEFT JOIN complaint_categories cc ON c.category_id = cc.category_id
          LEFT JOIN complaint_status cs ON c.status_id = cs.status_id
          WHERE c.complaint_id = ? AND c.assigned_to_id = ? LIMIT 1";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $complaint_id, $officer_id);
mysqli_stmt_execute($stmt);
$complaint = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$complaint) {
    die("Complaint records not found or restricted context authorization.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Investigate Complaint #<?php echo $complaint['complaint_id']; ?></title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; padding-bottom: 60px; color: #333; }
        .header { background-color: #e65100; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .back-btn { background: #b33600; color: white; text-decoration: none; padding: 8px 15px; border-radius: 5px; font-weight: bold; font-size: 14px; }
        .wrapper { width: 85%; margin: 30px auto; display: flex; gap: 30px; }
        .left-panel { width: 60%; }
        .right-panel { width: 40%; display: flex; flex-direction: column; gap: 20px; }
        .content-box { background: white; padding: 25px; border-radius: 10px; box-shadow: 0px 2px 8px gray; margin-bottom: 25px; }
        h2, h3 { color: #e65100; margin-top: 0; border-bottom: 2px solid #ffccbc; padding-bottom: 8px; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .meta-item b { color: #555; }
        .evidence-img { max-width: 100%; border-radius: 8px; border: 1px solid #ddd; margin-top: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .alert { padding: 12px; margin-bottom: 15px; border-radius: 5px; font-weight: bold; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        label { display: block; font-weight: bold; margin-bottom: 5px; color: #555; margin-top: 15px; }
        select, textarea { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 5px; font-size: 14px; }
        button { background-color: #e65100; color: white; border: none; padding: 12px 20px; cursor: pointer; border-radius: 5px; width: 100%; font-weight: bold; font-size: 14px; margin-top: 15px; text-transform: uppercase; }
        button:hover { background-color: #b33600; }
    </style>
</head>
<body>

<div class="header">
    <h1>Haritha Investigation Desk</h1>
    <a href="../gn_dash.php" class="back-btn">← Back to Dashboard</a>
</div>

<div class="wrapper">
    <div class="left-panel">
        <?php echo $message; ?>
        
        <div class="content-box">
            <h2>Complaint #<?php echo $complaint['complaint_id']; ?>: <?php echo htmlspecialchars($complaint['title']); ?></h2>
            <div class="meta-grid">
                <div class="meta-item"><b>Category:</b> <?php echo htmlspecialchars($complaint['category_name'] ?? 'Unassigned'); ?></div>
                <div class="meta-item"><b>Current Status:</b> <?php echo htmlspecialchars($complaint['status_name']); ?></div>
                <div class="meta-item"><b>Reported Date:</b> <?php echo $complaint['created_at']; ?></div>
                <div class="meta-item"><b>Coordinates:</b> <?php echo $complaint['latitude'] . ", " . $complaint['longitude']; ?></div>
            </div>
            
            <label>Incident Details</label>
            <p style="background: #fafafa; padding: 15px; border-radius: 5px; border-left: 4px solid #b0bec5;">
                <?php echo nl2br(htmlspecialchars($complaint['description'])); ?>
            </p>

            <label>Location / Landmark Directions</label>
            <p><?php echo htmlspecialchars($complaint['location_description'] ?: 'Coordinates provided directly.'); ?></p>

            <label>Attached Photographic Evidence</label>
            <?php if (!empty($complaint['image_path']) && file_exists("../uploads/" . $complaint['image_path'])): ?>
                <img src="../uploads/<?php echo htmlspecialchars($complaint['image_path']); ?>" class="evidence-img" alt="Evidence">
            <?php else: ?>
                <p style="font-style: italic; color: #757575;">No media files attached to this complaint entry.</p>
            <?php endif; ?>
        </div>

        <?php if (!empty($complaint['reply'])): ?>
            <div class="content-box">
                <h3>Latest System Action Log / Reply</h3>
                <p style="background: #efebe9; padding: 15px; border-radius: 5px; border-left: 4px solid #8d6e63; font-style: italic;">
                    <?php echo nl2br(htmlspecialchars($complaint['reply'])); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <div class="right-panel">
        <div class="content-box">
            <h3>Manage Progress</h3>
            <form method="POST" action="">
                <input type="hidden" name="action_type" value="update_complaint">
                
                <label for="status_id">Update Status Matrix</label>
                <select name="status_id" id="status_id">
                    <option value="1" <?php if($complaint['status_id'] == 1) echo 'selected'; ?>>SUBMITTED</option>
                    <option value="2" <?php if($complaint['status_id'] == 2) echo 'selected'; ?>>ASSIGNED</option>
                    <option value="3" <?php if($complaint['status_id'] == 3) echo 'selected'; ?>>IN PROGRESS</option>
                    <option value="4" <?php if($complaint['status_id'] == 4) echo 'selected'; ?>>COMPLETED</option>
                    <option value="6" <?php if($complaint['status_id'] == 6) echo 'selected'; ?>>REJECTED</option>
                </select>

                <label for="reply_text">Add Reply / Action Taken</label>
                <textarea name="reply_text" id="reply_text" rows="5" placeholder="Enter instructions, field findings, or resolution updates..."><?php echo htmlspecialchars($complaint['reply'] ?? ''); ?></textarea>
                
                <button type="submit">Save Assessment</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>