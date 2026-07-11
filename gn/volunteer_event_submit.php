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
$success_msg = "";
$error_msg = "";

// 2. Form Processing
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize form inputs
    $event_title = isset($_POST['event_title']) ? trim($_POST['event_title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $event_date = isset($_POST['event_date']) ? trim($_POST['event_date']) : '';
    $start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
    $end_time = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';
    $location = isset($_POST['location']) ? trim($_POST['location']) : '';
    $required_volunteers = isset($_POST['required_volunteers']) ? (int)$_POST['required_volunteers'] : 0;

    // Server-side validation
    if (empty($event_title) || empty($event_date) || empty($start_time) || empty($end_time) || empty($location) || $required_volunteers <= 0) {
        $error_msg = "Please fill out all fields with valid information.";
    } elseif (strtotime($event_date) < strtotime(date('Y-m-d'))) {
        $error_msg = "The event date cannot be in the past.";
    } elseif (strtotime($end_time) <= strtotime($start_time)) {
        $error_msg = "End time must be after the start time.";
    } else {
        // Prepared statement to insert the data cleanly. 
        // status defaults to 'OPEN' and created_at defaults to CURRENT_TIMESTAMP natively in DB.
        $insert_query = "INSERT INTO volunteer_events (created_by, event_title, description, event_date, start_time, end_time, location, required_volunteers, status) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'OPEN')";
        
        $stmt = mysqli_prepare($conn, $insert_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "issssssi", $officer_id, $event_title, $description, $event_date, $start_time, $end_time, $location, $required_volunteers);
            
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "Volunteer event successfully published and listed for community action!";
                // Clear out values on clean success submission
                $event_title = $description = $event_date = $start_time = $end_time = $location = "";
                $required_volunteers = "";
            } else {
                $error_msg = "Database Error: Could not save the event structure.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_msg = "Internal Error: System failed to prepare data stream.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Post Volunteer Opportunity</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; padding-bottom: 50px; }
        .header { background-color: #e65100; color: white; padding: 20px; text-align: center; position: relative; }
        .back-btn { 
            position: absolute; top: 20px; left: 20px; 
            background-color: transparent; border: 2px solid white; color: white; 
            padding: 8px 15px; border-radius: 5px; 
            text-decoration: none; font-size: 14px; font-weight: bold; 
        }
        .back-btn:hover { background-color: white; color: #e65100; }
        
        .form-section { width: 50%; min-width: 450px; margin: 40px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0px 2px 8px gray; }
        .form-section h2 { color: #e65100; margin-top: 0; border-bottom: 2px solid #ffccbc; padding-bottom: 10px; }
        
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 5px; font-weight: bold; font-size: 14px; }
        .alert-success { background-color: #c8e6c9; color: #25602a; border-left: 5px solid #388e3c; }
        .alert-error { background-color: #ffcdd2; color: #c62828; border-left: 5px solid #d32f2f; }
        
        .form-group { margin-bottom: 18px; display: flex; flex-direction: column; }
        .form-row { display: flex; gap: 15px; }
        .form-row .form-group { flex: 1; }
        
        label { font-weight: bold; margin-bottom: 6px; color: #455a64; font-size: 14px; }
        input[type="text"], input[type="date"], input[type="time"], input[type="number"], textarea {
            padding: 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px; font-family: inherit; box-sizing: border-box; width: 100%;
        }
        input:focus, textarea:focus { border-color: #e65100; outline: none; box-shadow: 0 0 5px rgba(230, 81, 0, 0.2); }
        textarea { resize: vertical; min-height: 100px; }
        
        .btn-submit { background-color: #e65100; color: white; border: none; padding: 12px 20px; cursor: pointer; border-radius: 5px; font-weight: bold; font-size: 16px; width: 100%; margin-top: 10px; }
        .btn-submit:hover { background-color: #b33600; }
    </style>
</head>
<body>

<div class="header">
    <a href="gn_dash.php" class="back-btn">← Return to Dashboard</a>
    <h1>Environmental Action Center</h1>
    <p>Mobilize Community Support & Manage Local Volunteer Resources</p>
</div>

<div class="form-section">
    <h2>Post New Volunteer Opportunity</h2>
    
    <!-- Render Response Alerts Dynamic Messages -->
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success"><?php echo $success_msg; ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-error"><?php echo $error_msg; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="event_title">Event Title / Campaign Objective:</label>
            <input type="text" id="event_title" name="event_title" placeholder="e.g., Kelani River Basin Cleanup Drive" value="<?php echo isset($event_title) ? htmlspecialchars($event_title) : ''; ?>" required>
        </div>

        <div class="form-group">
            <label for="description">Detailed Description & Instructions:</label>
            <textarea id="description" name="description" placeholder="Provide event instructions, safety tips, required gear, or general tasks expected..." required><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="event_date">Target Date:</label>
                <input type="date" id="event_date" name="event_date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo isset($event_date) ? htmlspecialchars($event_date) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="required_volunteers">Volunteers Requested:</label>
                <input type="number" id="required_volunteers" name="required_volunteers" min="1" placeholder="Ex: 25" value="<?php echo isset($required_volunteers) ? htmlspecialchars($required_volunteers) : ''; ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="start_time">Start Time:</label>
                <input type="time" id="start_time" name="start_time" value="<?php echo isset($start_time) ? htmlspecialchars($start_time) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="end_time">Estimated End Time:</label>
                <input type="time" id="end_time" name="end_time" value="<?php echo isset($end_time) ? htmlspecialchars($end_time) : ''; ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label for="location">Meeting Location / Physical Address:</label>
            <input type="text" id="location" name="location" placeholder="e.g., Junction Bus Stand, GN Office Premises" value="<?php echo isset($location) ? htmlspecialchars($location) : ''; ?>" required>
        </div>

        <button type="submit" class="btn-submit">Publish Opportunity</button>
    </form>
</div>

</body>
</html>