<?php
session_start();
include("../config/db.php");

// Initialize variables early to prevent any "Undefined variable" warnings
$alert_msg = "";
$alert_class = "";

// Handle Session Flash Messages (if redirected via Post-Redirect-Get workflow)
if (isset($_SESSION['flash_msg']) && isset($_SESSION['flash_class'])) {
    $alert_msg = $_SESSION['flash_msg'];
    $alert_class = $_SESSION['flash_class'];
    unset($_SESSION['flash_msg']);
    unset($_SESSION['flash_class']);
}

// 1. Authenticate check: Ensure user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// 2. Validate incoming event ID parameters
if (!isset($_GET['id']) || empty(trim($_GET['id']))) {
    header("Location: citizen_dash.php");
    exit();
}

$event_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Fetch system broadcast alerts for notification panel
$current_time = date('Y-m-d H:i:s');
$broadcast_query = "SELECT title, message, created_at FROM messages 
                    WHERE expires_at IS NULL OR expires_at > '$current_time' 
                    ORDER BY created_at DESC";
$broadcast_result = mysqli_query($conn, $broadcast_query);
$notification_count = $broadcast_result ? mysqli_num_rows($broadcast_result) : 0;

// 3. Fetch the target volunteer event details first
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
    echo "<a href='citizen_dash.php'>Return to Dashboard</a>";
    exit();
}

// 4. ACTION HANDLING: Process forms securely based on individual actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    
    // --- WORKFLOW A: PROCESS VOLUNTEER REGISTRATION ---
    if ($_POST['action'] === 'register') {
        
        $double_check_query = "SELECT 1 FROM volunteer_participants WHERE event_id = ? AND user_id = ?";
        $dc_stmt = mysqli_prepare($conn, $double_check_query);
        $already_in = false;
        
        if ($dc_stmt) {
            mysqli_stmt_bind_param($dc_stmt, "ii", $event_id, $user_id);
            mysqli_stmt_execute($dc_stmt);
            mysqli_stmt_store_result($dc_stmt);
            if (mysqli_stmt_num_rows($dc_stmt) > 0) {
                $already_in = true;
            }
            mysqli_stmt_close($dc_stmt);
        }

        if ($already_in) {
            $_SESSION['flash_msg'] = "You are already registered for this event.";
            $_SESSION['flash_class'] = "alert-info";
        } elseif (!isset($_POST['accept_terms'])) {
            $_SESSION['flash_msg'] = "You must read and agree to the Liability Waiver and Terms before registering.";
            $_SESSION['flash_class'] = "alert-error";
        } else {
            // --- CAPACITY VALIDATION ---
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

            if ($current_volunteers >= (int)$event['required_volunteers']) {
                $_SESSION['flash_msg'] = "This event has already reached its maximum volunteer capacity.";
                $_SESSION['flash_class'] = "alert-error";
            } else {
                // --- PROCEED WITH INSERTION ---
                $insert_query = "INSERT INTO volunteer_participants (event_id, user_id) VALUES (?, ?)";
                $stmt = mysqli_prepare($conn, $insert_query);

                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ii", $event_id, $user_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $_SESSION['flash_msg'] = "You have successfully registered as a volunteer for this event.";
                        $_SESSION['flash_class'] = "alert-success";
                    } else {
                        if (mysqli_errno($conn) == 1062) {
                            $_SESSION['flash_msg'] = "You are already registered for this event.";
                            $_SESSION['flash_class'] = "alert-info";
                        } else {
                            $_SESSION['flash_msg'] = "An error occurred while processing your registration. Please try again.";
                            $_SESSION['flash_class'] = "alert-error";
                        }
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $event_id);
        exit();
    }
    
    // --- WORKFLOW B: PROCESS SCANNER SUBMITTED QR PROOF ---
    if ($_POST['action'] === 'submit_proof') {
        $scanned_code = isset($_POST['qr_code_data']) ? strtolower(trim($_POST['qr_code_data'])) : '';
        
        $token_query = "SELECT verification_token FROM volunteer_events WHERE event_id = ?";
        $t_stmt = mysqli_prepare($conn, $token_query);
        $db_token = "";
        
        if ($t_stmt) {
            mysqli_stmt_bind_param($t_stmt, "i", $event_id);
            mysqli_stmt_execute($t_stmt);
            mysqli_stmt_bind_result($t_stmt, $db_token);
            mysqli_stmt_fetch($t_stmt);
            mysqli_stmt_close($t_stmt);
        }
        
        $db_token = strtolower(trim($db_token));

        if (empty($db_token)) {
            $_SESSION['flash_msg'] = "This event does not have a verification token generated by the administrator.";
            $_SESSION['flash_class'] = "alert-error";
        } elseif ($scanned_code === $db_token) {
            $update_query = "UPDATE volunteer_participants SET attendance_verified = 1, verified_at = NOW() WHERE event_id = ? AND user_id = ?";
            $u_stmt = mysqli_prepare($conn, $update_query);
            if ($u_stmt) {
                mysqli_stmt_bind_param($u_stmt, "ii", $event_id, $user_id);
                if (mysqli_stmt_execute($u_stmt)) {
                    $_SESSION['flash_msg'] = "Attendance successfully verified. You are now eligible to receive your certificate.";
                    $_SESSION['flash_class'] = "alert-success";
                } else {
                    $_SESSION['flash_msg'] = "Database update failed. Please try again.";
                    $_SESSION['flash_class'] = "alert-error";
                }
                mysqli_stmt_close($u_stmt);
            }
        } else {
            // Check if the code scanned belongs to any other event entirely
            $cross_check_query = "SELECT event_title FROM volunteer_events WHERE LOWER(TRIM(verification_token)) = ?";
            $cc_stmt = mysqli_prepare($conn, $cross_check_query);
            $found_other_title = "";
            if ($cc_stmt) {
                mysqli_stmt_bind_param($cc_stmt, "s", $scanned_code);
                mysqli_stmt_execute($cc_stmt);
                mysqli_stmt_bind_result($cc_stmt, $found_other_title);
                mysqli_stmt_fetch($cc_stmt);
                mysqli_stmt_close($cc_stmt);
            }

            if (!empty($found_other_title)) {
                $_SESSION['flash_msg'] = "Mismatched Event: You scanned the verification key for '<strong>" . htmlspecialchars($found_other_title) . "</strong>', but you are on the submission page for '<strong>" . htmlspecialchars($event['event_title']) . "</strong>'. Please select the correct event from your dashboard.";
            } else {
                $_SESSION['flash_msg'] = "Invalid QR Code string. Please scan the official code provided by event coordinators at the venue.";
            }
            $_SESSION['flash_class'] = "alert-error";
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $event_id);
        exit();
    }
}

// 5. Normal view pipeline: fetch real-time volunteer metrics
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

// 6. Check final enrollment status & verification history for interface display
$check_query = "SELECT attendance_verified FROM volunteer_participants WHERE event_id = ? AND user_id = ?";
$check_stmt = mysqli_prepare($conn, $check_query);
$already_enrolled = false;
$attendance_verified = false;

if ($check_stmt) {
    mysqli_stmt_bind_param($check_stmt, "ii", $event_id, $user_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    if ($row = mysqli_fetch_assoc($check_result)) {
        $already_enrolled = true;
        $attendance_verified = (int)$row['attendance_verified'] === 1;
    }
    mysqli_stmt_close($check_stmt);
}

$is_event_full = ($current_volunteers >= (int)$event['required_volunteers']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['event_title']); ?> - Haritha System</title>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        :root {
            --primary-color: #1b5e20;
            --primary-hover: #144718;
            --accent-blue: #0288d1;
            --accent-blue-hover: #01579b;
            --accent-red: #c62828;
            --bg-color: #f4f6f8;
            --card-bg: #ffffff;
            --text-main: #2c3e50;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0 0 60px 0;
            color: var(--text-main);
        }

        /* --- Header Navigation --- */
        .header {
            background-color: var(--primary-color);
            color: white;
            padding: 15px 8%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-back {
            color: white;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 6px;
            transition: background-color 0.2s;
        }

        .btn-back:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }

        .header-title-container h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification-trigger {
            position: relative;
            background: rgba(255, 255, 255, 0.15);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
        }

        .notification-trigger:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        .notification-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background-color: #e74c3c;
            color: white;
            font-size: 11px;
            font-weight: bold;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--primary-color);
        }

        /* --- Main Container --- */
        .details-container {
            max-width: 850px;
            margin: 35px auto;
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .event-hero-image {
            width: 100%;
            height: 320px;
            background-color: #e2e8f0;
            position: relative;
        }

        .event-hero-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .event-body {
            padding: 35px;
        }

        .event-title {
            color: var(--primary-color);
            margin-top: 0;
            font-size: 24px;
            font-weight: 700;
            border-bottom: 2px solid #e8f5e9;
            padding-bottom: 12px;
        }

        /* --- Alert Messages --- */
        .alert {
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            line-height: 1.5;
        }

        .alert-success { background-color: #e8f5e9; color: #1b5e20; border-left: 5px solid var(--primary-color); }
        .alert-info { background-color: #e3f2fd; color: #0d47a1; border-left: 5px solid #1976d2; }
        .alert-error { background-color: #ffebee; color: #b71c1c; border-left: 5px solid var(--accent-red); }

        /* --- Meta Grid Cards --- */
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin: 25px 0;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }

        .meta-item {
            font-size: 14px;
            color: var(--text-main);
        }

        .meta-item strong {
            color: var(--primary-color);
            display: block;
            margin-bottom: 4px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .section-heading {
            font-size: 18px;
            color: var(--text-main);
            margin-top: 25px;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .description-box {
            font-size: 15px;
            line-height: 1.6;
            color: #444;
            white-space: pre-line;
            background: #ffffff;
            padding: 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
        }

        /* --- Waiver & Terms --- */
        .terms-box {
            background-color: #fff8f8;
            border: 1px solid #ffcdd2;
            padding: 16px;
            border-radius: 8px;
            margin-top: 25px;
            font-size: 13px;
            color: #444;
            max-height: 120px;
            overflow-y: auto;
            line-height: 1.5;
        }

        .terms-box h4 {
            margin: 0 0 6px 0;
            color: var(--accent-red);
            font-size: 14px;
        }

        .checkbox-container {
            margin-top: 15px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-main);
            cursor: pointer;
        }

        .checkbox-container input {
            cursor: pointer;
            margin-top: 3px;
            width: 16px;
            height: 16px;
            accent-color: var(--primary-color);
        }

        /* --- Actions & Buttons --- */
        .btn-register, .btn-proof {
            display: block;
            width: 100%;
            border: none;
            padding: 14px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            border-radius: 8px;
            margin-top: 25px;
            text-align: center;
            text-decoration: none;
            box-sizing: border-box;
            transition: background-color 0.2s, transform 0.1s;
        }

        .btn-register {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-register:hover {
            background-color: var(--primary-hover);
        }

        .btn-proof {
            background-color: var(--accent-blue);
            color: white;
        }

        .btn-proof:hover {
            background-color: var(--accent-blue-hover);
        }

        .btn-disabled {
            background-color: #9e9e9e !important;
            cursor: not-allowed;
        }

        /* --- Scanner & Verification Terminal --- */
        .proof-section {
            background: #f0f7ff;
            padding: 24px;
            border-radius: 8px;
            margin-top: 25px;
            text-align: center;
            border: 2px dashed var(--accent-blue);
            display: none;
        }

        .proof-section h3 {
            margin-top: 0;
            color: var(--accent-blue-hover);
        }

        #reader {
            width: 100%;
            max-width: 400px;
            margin: 15px auto;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .manual-input-box {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px dashed #b0bec5;
        }

        .text-input-field {
            width: 70%;
            max-width: 320px;
            padding: 10px;
            font-size: 14px;
            font-family: monospace;
            text-align: center;
            border: 2px solid #b0bec5;
            border-radius: 6px;
            box-sizing: border-box;
        }

        .btn-submit-text {
            background-color: #37474f;
            color: white;
            border: none;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            margin-left: 5px;
            transition: background 0.2s;
        }

        .btn-submit-text:hover {
            background-color: #212121;
        }

        /* --- Notifications Side Drawer --- */
        .noti-panel {
            position: fixed;
            top: 0;
            right: -400px;
            width: 360px;
            height: 100%;
            background-color: #ffffff;
            box-shadow: -4px 0 20px rgba(0,0,0,0.15);
            transition: right 0.3s ease;
            z-index: 1001;
            padding: 24px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }

        .noti-panel.open { right: 0; }

        .noti-panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .noti-panel-header h2 { margin: 0; font-size: 18px; color: var(--text-main); }

        .close-noti-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 24px;
            cursor: pointer;
        }

        .noti-list-wrapper { flex-grow: 1; overflow-y: auto; }

        .noti-item {
            background: #f8f9fa;
            border-left: 3px solid var(--primary-color);
            padding: 14px;
            margin-bottom: 12px;
            border-radius: 4px;
        }

        .noti-item h4 { margin: 0 0 6px 0; font-size: 14px; color: var(--text-main); }
        .noti-item p { margin: 0 0 8px 0; font-size: 12px; color: var(--text-muted); line-height: 1.4; }
        .noti-time { font-size: 10px; color: #aaa; display: block; text-align: right; }

        .noti-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.2);
            display: none;
            z-index: 1000;
        }

        .no-noti-msg { color: var(--text-muted); font-style: italic; text-align: center; padding: 20px; }

        @media (max-width: 600px) {
            .details-container {
                margin: 20px 15px;
            }
            .event-body {
                padding: 20px;
            }
            .event-hero-image {
                height: 200px;
            }
            .text-input-field {
                width: 100%;
                margin-bottom: 10px;
            }
            .btn-submit-text {
                width: 100%;
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

<!-- Header Navigation Bar -->
<div class="header">
    <div class="header-left">
        <a href="citizen_dash.php" class="btn-back">&larr; Back to Dashboard</a>
    </div>

    <div class="header-title-container">
        <h1>Citizen Dashboard</h1>
    </div>

    <div class="header-right">
        <div class="notification-trigger" onclick="toggleNotificationPanel(true)" title="Announcements">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <?php if ($notification_count > 0): ?>
                <div class="notification-badge" id="notiBadge"><?php echo $notification_count; ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Notification Overlay & Drawer Panel -->
<div id="notiOverlay" class="noti-overlay" onclick="toggleNotificationPanel(false)"></div>

<div id="notificationSidePanel" class="noti-panel">
    <div class="noti-panel-header">
        <h2>System Announcements</h2>
        <button class="close-noti-btn" onclick="toggleNotificationPanel(false)">&times;</button>
    </div>
    
    <div class="noti-list-wrapper">
        <?php if ($notification_count > 0): ?>
            <?php while ($msg = mysqli_fetch_assoc($broadcast_result)): ?>
                <div class="noti-item">
                    <h4><?php echo htmlspecialchars($msg['title']); ?></h4>
                    <p><?php echo htmlspecialchars($msg['message']); ?></p>
                    <span class="noti-time"><?php echo date('M d, Y h:i A', strtotime($msg['created_at'])); ?></span>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="no-noti-msg">No active broadcast notices to display.</p>
        <?php endif; ?>
    </div>
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
                <strong>Target Date</strong>
                <?php echo date("F d, Y", strtotime($event['event_date'])); ?>
            </div>
            <div class="meta-item">
                <strong>Needed Volunteers</strong>
                <?php echo $current_volunteers . ' / ' . htmlspecialchars($event['required_volunteers']); ?> Joined
            </div>
            <div class="meta-item">
                <strong>Time Scope</strong>
                <?php echo date("g:i A", strtotime($event['start_time'])) . " - " . date("g:i A", strtotime($event['end_time'])); ?>
            </div>
            <div class="meta-item">
                <strong>Meeting Hub</strong>
                <?php echo htmlspecialchars($event['location']); ?>
            </div>
        </div>

        <h3 class="section-heading">Description & Core Tasks</h3>
        <div class="description-box">
            <?php echo htmlspecialchars($event['description']); ?>
        </div>

        <?php if (!$already_enrolled): ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="register">
                
                <div class="terms-box">
                    <h4>Liability Waiver & Release of Claims</h4>
                    By checking the box below, I acknowledge that volunteering for environmental fieldwork involves physical activity and potential environmental hazards. I explicitly agree that the Haritha Environmental Complaint System, the local Grama Niladhari administration, and property owners are completely exempt from liability for any personal injuries, medical emergencies, or accidental damage to personal property sustained during the course of this volunteer assignment. I confirm that I am participating voluntarily and at my own risk.
                </div>
                
                <label class="checkbox-container">
                    <input type="checkbox" name="accept_terms" value="1" required>
                    I have carefully read and I agree to the terms of the liability waiver above.
                </label>

                <?php if ($is_event_full): ?>
                    <button type="button" class="btn-register btn-disabled" disabled>Event Capacity Reached</button>
                <?php else: ?>
                    <button type="submit" class="btn-register">Confirm Registration as Volunteer</button>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <button type="button" class="btn-register btn-disabled" style="background-color: #6c757d;" disabled>Enrolled in Event</button>
            
            <?php if ($attendance_verified): ?>
                <div class="alert alert-success" style="margin-top:20px;">
                    <strong>Participation Confirmed:</strong> Your attendance has been scanned and verified by authorities. You are eligible for certificate issuance.
                </div>
            <?php else: ?>
                <button type="button" class="btn-proof" id="toggleScannerBtn" onclick="toggleScanner()">Scan Proof of Participation</button>

                <div class="proof-section" id="scannerContainer">
                    <h3>Scan Venue Verification Code</h3>
                    <p style="color:var(--text-muted); font-size:14px; margin-bottom:15px;">Point your camera at the official QR code displayed at the event site desk.</p>
                    
                    <div id="reader"></div>
                    
                    <form id="qrSubmitForm" method="POST" action="">
                        <input type="hidden" name="action" value="submit_proof">
                        <input type="hidden" name="qr_code_data" id="qr_code_data">
                    </form>

                    <div class="manual-input-box">
                        <p style="color:var(--text-main); font-size:14px; font-weight:700; margin-bottom:6px;">QR Image Missing or Distorted?</p>
                        <p style="color:var(--text-muted); font-size:13px; margin:0 0 12px 0;">Enter the verification token key manually below to authorize enrollment:</p>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="submit_proof">
                            <input type="text" name="qr_code_data" class="text-input-field" placeholder="Type key here..." required autocomplete="off">
                            <button type="submit" class="btn-submit-text">Verify Key</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
let html5QrcodeScanner = null;

document.addEventListener("DOMContentLoaded", function() {
    var badge = document.getElementById('notiBadge');
    if (badge && localStorage.getItem('notificationsViewed') === 'true') {
        badge.remove();
    }
});

function toggleNotificationPanel(open) {
    var panel = document.getElementById('notificationSidePanel');
    var overlay = document.getElementById('notiOverlay');
    var badge = document.getElementById('notiBadge');
    
    if (open) {
        panel.classList.add('open');
        overlay.style.display = 'block';
        if (badge) {
            badge.remove();
        }
        localStorage.setItem('notificationsViewed', 'true');
    } else {
        panel.classList.remove('open');
        overlay.style.display = 'none';
    }
}

function toggleScanner() {
    const container = document.getElementById('scannerContainer');
    const btn = document.getElementById('toggleScannerBtn');
    
    if (container.style.display === 'none' || container.style.display === '') {
        container.style.display = 'block';
        btn.textContent = "Close Verification Terminal";
        btn.style.backgroundColor = "var(--accent-red)";
        startScanner();
    } else {
        container.style.display = 'none';
        btn.textContent = "Scan Proof of Participation";
        btn.style.backgroundColor = "var(--accent-blue)";
        if (html5QrcodeScanner) {
            html5QrcodeScanner.clear();
        }
    }
}

function startScanner() {
    function onScanSuccess(decodedText, decodedResult) {
        if (html5QrcodeScanner) {
            html5QrcodeScanner.clear();
        }
        document.getElementById('qr_code_data').value = decodedText;
        document.getElementById('qrSubmitForm').submit();
    }

    function onScanFailure(error) {
        // Suppress failure logs
    }

    html5QrcodeScanner = new Html5QrcodeScanner(
        "reader", 
        { fps: 10, qrbox: { width: 250, height: 250 } },
        false
    );
    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
}
</script>
</body>
</html>