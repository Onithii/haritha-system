<?php
session_start();
include("../config/db.php");

// 1. Authenticate check: Ensure user is logged in to access event insights
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// 2. Validate incoming event ID parameters from GET requests
if (!isset($_GET['id']) || empty(trim($_GET['id']))) {
    header("Location: citizen_dashboard.php");
    exit();
}

$event_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];
$alert_msg = "";
$alert_class = "";

// 3. Fetch the target volunteer event details first (needed for details and validation)
$query = "SELECT * FROM volunteer_events WHERE event_id = ?";
$stmt = mysqli_prepare($conn, $query);
$event = null;

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $event_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) === 1) {
        $event = mysqli_fetch_assoc($result);
    }
    mysqli_stmt_close($stmt);
}

if (!$event) {
    echo "<h3>Event not found or has been removed.</h3>";
    echo "<a href='citizen_dashboard.php'>Return to Dashboard</a>";
    exit();
}

// 4. ACTION HANDLING: Process self-submitting POST requests when the user clicks register
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'register') {
    
    // --- START CAPACITY VALIDATION ---
    $cap_query = "SELECT COUNT(*) as current_count FROM volunteer_participants WHERE event_id = ?";
    $cap_stmt = mysqli_prepare($conn, $cap_query);
    $current_volunteers = 0;

    if ($cap_stmt) {
        mysqli_stmt_bind_param($cap_stmt, "i", $event_id);
        mysqli_stmt_execute($cap_stmt);
        $cap_result = mysqli_stmt_get_result($cap_stmt);
        if ($cap_row = mysqli_fetch_assoc($cap_result)) {
            $current_volunteers = (int)$cap_row['current_count'];
        }
        mysqli_stmt_close($cap_stmt);
    }

    // Check if the spots are completely filled up
    if ($current_volunteers >= (int)$event['required_volunteers']) {
        $alert_msg = "🚫 Sorry, this event has already reached its maximum volunteer capacity!";
        $alert_class = "alert-error";
    } else {
        // --- PROCEED WITH REGISTRATION IF SPOTS ARE AVAILABLE ---
        $insert_query = "INSERT INTO volunteer_participants (event_id, user_id) VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $insert_query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $event_id, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $alert_msg = "🎉 Thank you! You have successfully registered as a volunteer for this campaign.";
                $alert_class = "alert-success";
                $current_volunteers++; // Increment local count instantly to update UI stats accurately
            } else {
                if (mysqli_errno($conn) == 1062) { // Duplicate entry error code
                    $alert_msg = "ℹ️ You are already registered for this event. See you there!";
                    $alert_class = "alert-info";
                } else {
                    $alert_msg = "❌ An error occurred while processing your registration. Please try again.";
                    $alert_class = "alert-error";
                }
            }
            mysqli_stmt_close($stmt);
        } else {
            $alert_msg = "❌ System Error: Internal pipeline failure.";
            $alert_class = "alert-error";
        }
    }
} else {
    // If it's a standard GET request, fetch the headcount for the meta info block display
    $cap_query = "SELECT COUNT(*) as current_count FROM volunteer_participants WHERE event_id = ?";
    $cap_stmt = mysqli_prepare($conn, $cap_query);
    $current_volunteers = 0;
    if ($cap_stmt) {
        mysqli_stmt_bind_param($cap_stmt, "i", $event_id);
        mysqli_stmt_execute($cap_stmt);
        $cap_result = mysqli_stmt_get_result($cap_stmt);
        if ($cap_row = mysqli_fetch_assoc($cap_result)) {
            $current_volunteers = (int)$cap_row['current_count'];
        }
        mysqli_stmt_close($cap_stmt);
    }
}

// 5. Check if user is already enrolled beforehand to change button state natively
$check_query = "SELECT 1 FROM volunteer_participants WHERE event_id = ? AND user_id = ?";
$check_stmt = mysqli_prepare($conn, $check_query);
$already_enrolled = false;

if ($check_stmt) {
    mysqli_stmt_bind_param($check_stmt, "ii", $event_id, $user_id);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    if (mysqli_stmt_num_rows($check_stmt) > 0) {
        $already_enrolled = true;
    }
    mysqli_stmt_close($check_stmt);
}

// Check if event is full for formatting the button elements state
$is_event_full = ($current_volunteers >= (int)$event['required_volunteers']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($event['event_title']); ?> - Details</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; padding-bottom: 60px; }
        .header { background-color: #2e7d32; color: white; padding: 20px; text-align: center; position: relative; }
        .back-btn { position: absolute; top: 20px; left: 20px; background-color: transparent; border: 2px solid white; color: white; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-size: 14px; font-weight: bold; }
        .back-btn:hover { background-color: white; color: #2e7d32; }
        .details-container { width: 55%; min-width: 500px; margin: 40px auto; background: white; border-radius: 12px; box-shadow: 0px 4px 12px rgba(0,0,0,0.1); overflow: hidden; }
        .event-hero-image { width: 100%; height: 320px; background-color: #cbd5e1; position: relative; }
        .event-hero-image img { width: 100%; height: 100%; object-fit: cover; }
        .event-body { padding: 35px; }
        .event-title { color: #2e7d32; margin-top: 0; font-size: 26px; border-bottom: 2px solid #c8e6c9; padding-bottom: 12px; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; font-weight: bold; font-size: 15px; }
        .alert-success { background-color: #c8e6c9; color: #1b5e20; border-left: 6px solid #2e7d32; }
        .alert-info { background-color: #e3f2fd; color: #0d47a1; border-left: 6px solid #1976d2; }
        .alert-error { background-color: #ffcdd2; color: #b71c1c; border-left: 6px solid #d32f2f; }

        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 25px 0; background: #f9fbf9; padding: 20px; border-radius: 8px; border-left: 4px solid #2e7d32; }
        .meta-item { font-size: 15px; color: #455a64; }
        .meta-item strong { color: #2e7d32; }
        .description-box { font-size: 16px; line-height: 1.6; color: #333; margin-top: 20px; white-space: pre-line; }
        .btn-register { display: block; width: 100%; background-color: #2e7d32; color: white; border: none; padding: 15px; font-size: 18px; font-weight: bold; cursor: pointer; border-radius: 6px; margin-top: 30px; text-align: center; text-decoration: none; box-sizing: border-box; }
        .btn-register:hover { background-color: #1b5e20; }
    </style>
</head>
<body>

<div class="header">
    <a href="citizen_dashboard.php" class="back-btn">← Back to Dashboard</a>
    <h1>Environmental Action Center</h1>
    <p>Community Mobilization Portal</p>
</div>

<div class="details-container">
    
    <?php if (!empty($event['event_image'])): ?>
        <div class="event-hero-image">
            <img src="<?php echo htmlspecialchars($event['event_image']); ?>" alt="Campaign Banner">
        </div>
    <?php endif; ?>

    <div class="event-body">
        <?php if (!empty($alert_msg)): ?>
            <div class="alert <?php echo $alert_class; ?>"><?php echo $alert_msg; ?></div>
        <?php endif; ?>

        <h2 class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></h2>
        
        <div class="meta-grid">
            <div class="meta-item">
                <strong>📅 Target Date:</strong> <?php echo date("F d, Y", strtotime($event['event_date'])); ?>
            </div>
            <div class="meta-item">
                <strong>👥 Needed Volunteers:</strong> <?php echo $current_volunteers . ' / ' . htmlspecialchars($event['required_volunteers']); ?> joined
            </div>
            <div class="meta-item">
                <strong>⏰ Time Scope:</strong> <?php echo date("g:i A", strtotime($event['start_time'])) . " - " . date("g:i A", strtotime($event['end_time'])); ?>
            </div>
            <div class="meta-item">
                <strong>📍 Meeting Hub:</strong> <?php echo htmlspecialchars($event['location']); ?>
            </div>
        </div>

        <h3>Description & Core Tasks</h3>
        <div class="description-box">
            <?php echo htmlspecialchars($event['description']); ?>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="action" value="register">
            <?php if ($already_enrolled || $alert_class === 'alert-success'): ?>
                <button type="button" class="btn-register" style="background-color: #78909c; cursor: default;" disabled>You are Enrolled</button>
            <?php elseif ($is_event_full): ?>
                <button type="button" class="btn-register" style="background-color: #c62828; cursor: not-allowed;" disabled>Event Full</button>
            <?php else: ?>
                <button type="submit" class="btn-register">Confirm Registration as Volunteer</button>
            <?php endif; ?>
        </form>
    </div>
</div>

</body>
</html>