<?php
session_start();
include("../config/db.php");

// 1. Authenticate check: Ensure user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// 2. Validate incoming event ID parameters
if (!isset($_GET['id']) || empty(trim($_GET['id']))) {
    header("Location: citizen_dashboard.php");
    exit();
}

$event_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];
$alert_msg = "";
$alert_class = "";

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
    echo "<a href='citizen_dashboard.php'>Return to Dashboard</a>";
    exit();
}

// 4. ACTION HANDLING: Process forms securely based on individual actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    
    // --- WORKFLOW A: PROCESS VOLUNTEER REGISTRATION ---
    if ($_POST['action'] === 'register') {
        
        // Anti-Crash Check: Ensure user is not already registered via a dynamic pre-query
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
            $alert_msg = "ℹ️ You are already registered for this event. See you there!";
            $alert_class = "alert-info";
        } elseif (!isset($_POST['accept_terms'])) {
            $alert_msg = "⚠️ You must read and agree to the Liability Waiver and Terms before registering.";
            $alert_class = "alert-error";
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
                $alert_msg = "🚫 Sorry, this event has already reached its maximum volunteer capacity!";
                $alert_class = "alert-error";
            } else {
                // --- PROCEED WITH INSERTION ---
                $insert_query = "INSERT INTO volunteer_participants (event_id, user_id) VALUES (?, ?)";
                $stmt = mysqli_prepare($conn, $insert_query);

                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ii", $event_id, $user_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $alert_msg = "🎉 Thank you! You have successfully registered as a volunteer for this campaign.";
                        $alert_class = "alert-success";
                        $current_volunteers++; 
                    } else {
                        if (mysqli_errno($conn) == 1062) {
                            $alert_msg = "ℹ️ You are already registered for this event. See you there!";
                            $alert_class = "alert-info";
                        } else {
                            $alert_msg = "❌ An error occurred while processing your registration. Please try again.";
                            $alert_class = "alert-error";
                        }
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
    
    // --- WORKFLOW B: PROCESS SCANNER SUBMITTED QR PROOF ---
    if ($_POST['action'] === 'submit_proof') {
        $scanned_code = trim($_POST['qr_code_data']);
        $expected_code = "VALIDATE_EVENT_" . $event_id;

        if ($scanned_code === $expected_code) {
            $update_query = "UPDATE volunteer_participants SET attendance_verified = 1, verified_at = NOW() WHERE event_id = ? AND user_id = ?";
            $u_stmt = mysqli_prepare($conn, $update_query);
            if ($u_stmt) {
                mysqli_stmt_bind_param($u_stmt, "ii", $event_id, $user_id);
                if (mysqli_stmt_execute($u_stmt)) {
                    $alert_msg = "✅ Attendance successfully verified! You are now eligible to receive your certificate.";
                    $alert_class = "alert-success";
                } else {
                    $alert_msg = "❌ Database update failed. Please try again.";
                    $alert_class = "alert-error";
                }
                mysqli_stmt_close($u_stmt);
            }
        } else {
            $alert_msg = "❌ Invalid QR Code. Please scan the official code provided by event coordinators at the venue.";
            $alert_class = "alert-error";
        }
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
    <title><?php echo htmlspecialchars($event['event_title']); ?> - Details</title>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
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
        
        .terms-box { background-color: #fafafa; border: 1px solid #e0e0e0; padding: 15px; border-radius: 6px; margin-top: 30px; font-size: 13px; color: #555; height: 110px; overflow-y: scroll; line-height: 1.5; }
        .terms-box h4 { margin: 0 0 8px 0; color: #c62828; }
        
        .checkbox-container { margin-top: 15px; display: flex; align-items: flex-start; gap: 10px; font-size: 14px; font-weight: bold; color: #333; cursor: pointer; }
        .checkbox-container input { cursor: pointer; margin-top: 3px; }

        .btn-register, .btn-proof { display: block; width: 100%; border: none; padding: 15px; font-size: 18px; font-weight: bold; cursor: pointer; border-radius: 6px; margin-top: 25px; text-align: center; text-decoration: none; box-sizing: border-box; }
        .btn-register { background-color: #2e7d32; color: white; }
        .btn-register:hover { background-color: #1b5e20; }
        
        .btn-proof { background-color: #0288d1; color: white; }
        .btn-proof:hover { background-color: #01579b; }
        .proof-section { background: #f0f4f8; padding: 20px; border-radius: 8px; margin-top: 20px; text-align: center; border: 2px dashed #0288d1; display: none; }
        #reader { width: 100%; max-width: 400px; margin: 15px auto; background: white; border-radius: 8px; overflow: hidden; }
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

        <?php if (!$already_enrolled && $alert_class !== 'alert-success'): ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="register">
                
                <div class="terms-box">
                    <h4>⚠️ Liability Waiver & Release of Claims</h4>
                    By checking the box below, I acknowledge that volunteering for environmental fieldwork involves physical activity and potential environmental hazards. I explicitly agree that the Haritha Environmental Complaint System, the local Grama Niladhari administration, and property owners are completely exempt from liability for any personal injuries, medical emergencies, or accidental damage to personal property sustained during the course of this volunteer assignment. I confirm that I am participating voluntarily and at my own risk.
                </div>
                
                <label class="checkbox-container">
                    <input type="checkbox" name="accept_terms" value="1" required>
                    I have carefully read and I agree to the terms of the liability waiver above.
                </label>

                <?php if ($is_event_full): ?>
                    <button type="button" class="btn-register" style="background-color: #c62828; cursor: not-allowed;" disabled>Event Full</button>
                <?php else: ?>
                    <button type="submit" class="btn-register">Confirm Registration as Volunteer</button>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <button type="button" class="btn-register" style="background-color: #78909c; cursor: default; margin-bottom:10px;" disabled>✓ Enrolled in Event</button>
            
            <?php if ($attendance_verified): ?>
                <div class="alert alert-success" style="margin-top:15px; border-left:6px solid #1b5e20;">
                    🎖️ <strong>Participation Confirmed:</strong> Your attendance has been scanned and verified by authorities. You are qualified for certification.
                </div>
            <?php else: ?>
                <button type="button" class="btn-proof" id="toggleScannerBtn" onclick="toggleScanner()">📷 Scan Proof of Participation</button>

                <div class="proof-section" id="scannerContainer">
                    <h3>Scan Venue Verification Code</h3>
                    <p style="color:#555; font-size:14px;">Point your camera at the official QR code displayed at the event site desk.</p>
                    
                    <div id="reader"></div>
                    
                    <form id="qrSubmitForm" method="POST" action="">
                        <input type="hidden" name="action" value="submit_proof">
                        <input type="hidden" name="qr_code_data" id="qr_code_data">
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
let html5QrcodeScanner = null;

function toggleScanner() {
    const container = document.getElementById('scannerContainer');
    const btn = document.getElementById('toggleScannerBtn');
    
    if (container.style.display === 'none' || container.style.display === '') {
        container.style.display = 'block';
        btn.textContent = "Close Camera Scanner";
        btn.style.backgroundColor = "#c62828";
        startScanner();
    } else {
        container.style.display = 'none';
        btn.textContent = "📷 Scan Proof of Participation";
        btn.style.backgroundColor = "#0288d1";
        if (html5QrcodeScanner) {
            html5QrcodeScanner.clear();
        }
    }
}

function startScanner() {
    function onScanSuccess(decodedText, decodedResult) {
        html5QrcodeScanner.clear();
        document.getElementById('qr_code_data').value = decodedText;
        document.getElementById('qrSubmitForm').submit();
    }

    function onScanFailure(error) {
        // Suppress failure logs to avoid spamming the console frame-by-frame
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