<?php
session_start();
include("config/db.php");

// 1. Secure Access Check: Allow access to Grama Niladhari or the authenticated Citizen themselves
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_GET['event_id']) || !isset($_GET['user_id'])) {
    die("Error: Missing parameters to generate certificate.");
}

$event_id = intval($_GET['event_id']);
$target_user_id = intval($_GET['user_id']);
$viewer_id = $_SESSION['user_id'];
$viewer_role = $_SESSION['role_id'] ?? 3; // Default to citizen role if not set

// Security check: Only the GN (Role 2) or the citizen themselves (Role 3) can view this certificate
if ($viewer_role != 2 && $viewer_id != $target_user_id) {
    die("Access Denied: You do not have permission to view this certificate.");
}

// 2. Fetch Participation and Attendance Proof
$verified = false;
$verify_query = "SELECT attendance_verified, verified_at 
                 FROM volunteer_participants 
                 WHERE event_id = ? AND user_id = ? LIMIT 1";
$v_stmt = mysqli_prepare($conn, $verify_query);
if ($v_stmt) {
    mysqli_stmt_bind_param($v_stmt, "ii", $event_id, $target_user_id);
    mysqli_stmt_execute($v_stmt);
    $v_result = mysqli_stmt_get_result($v_stmt);
    if ($row = mysqli_fetch_assoc($v_result)) {
        if ($row['attendance_verified'] == 1) {
            $verified = true;
            // Fallback to verification date or today's date
            $issue_date = !empty($row['verified_at']) ? date('F d, Y', strtotime($row['verified_at'])) : date('F d, Y');
        }
    }
    mysqli_stmt_close($v_stmt);
}

if (!$verified) {
    die("Error: This citizen has not completed or verified attendance for this event yet.");
}

// 3. Fetch Event and Citizen Details
$citizen_name = "";
$event_title = "";
$gn_name = "Grama Niladhari"; // Placeholder if GN details are not fully queried

$details_query = "SELECT u.f_name, u.l_name, ve.event_title, 
                         (SELECT CONCAT(f_name, ' ', l_name) FROM users WHERE user_id = ve.created_by) as officer_name
                  FROM volunteer_participants vp
                  JOIN users u ON vp.user_id = u.user_id
                  JOIN volunteer_events ve ON vp.event_id = ve.event_id
                  WHERE vp.event_id = ? AND vp.user_id = ? LIMIT 1";

$d_stmt = mysqli_prepare($conn, $details_query);
if ($d_stmt) {
    mysqli_stmt_bind_param($d_stmt, "ii", $event_id, $target_user_id);
    mysqli_stmt_execute($d_stmt);
    $d_result = mysqli_stmt_get_result($d_stmt);
    if ($row = mysqli_fetch_assoc($d_result)) {
        $citizen_name = $row['f_name'] . " " . $row['l_name'];
        $event_title = $row['event_title'];
        if (!empty($row['officer_name'])) {
            $gn_name = $row['officer_name'];
        }
    }
    mysqli_stmt_close($d_stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Appreciation - <?php echo htmlspecialchars($citizen_name); ?></title>
    <style>
        /* Certificate Canvas styling */
        body {
            background-color: #e0e0e0;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            font-family: 'Georgia', serif;
        }
        .certificate-container {
            width: 850px;
            height: 600px;
            padding: 40px;
            background: #fff;
            color: #333;
            border: 20px solid #e65100; /* Theme Accent Color */
            outline: 5px double #ffccbc;
            outline-offset: -15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
            box-sizing: border-box;
            position: relative;
            text-align: center;
        }
        .logo {
            font-size: 24px;
            color: #e65100;
            font-weight: bold;
            margin-bottom: 20px;
            letter-spacing: 2px;
        }
        h1 {
            font-size: 46px;
            margin: 10px 0;
            color: #1a237e;
            font-family: 'Times New Roman', serif;
        }
        h2 {
            font-size: 20px;
            font-weight: normal;
            font-style: italic;
            margin: 15px 0;
            color: #555;
        }
        .recipient {
            font-size: 32px;
            font-weight: bold;
            color: #e65100;
            border-bottom: 2px solid #ddd;
            display: inline-block;
            padding: 5px 30px;
            margin: 15px 0;
            font-family: 'Georgia', serif;
        }
        .reason {
            font-size: 16px;
            line-height: 1.6;
            width: 80%;
            margin: 20px auto;
            color: #444;
        }
        .event-name {
            font-weight: bold;
            color: #1a237e;
        }
        .footer-metrics {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
            padding: 0 50px;
        }
        .signature-block, .date-block {
            width: 200px;
            text-align: center;
        }
        .signature-line, .date-line {
            border-bottom: 1px solid #777;
            margin-bottom: 5px;
            padding-bottom: 5px;
        }
        .signature-img {
            font-family: 'Brush Script MT', cursive, sans-serif;
            font-size: 24px;
            color: #1a237e;
            display: block;
            margin-bottom: -5px;
        }
        .label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #777;
        }
        /* No-Print Control elements */
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #e65100;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .print-btn:hover { background-color: #b33600; }

        /* Print Media queries to isolate document structure */
        @media print {
            body { background: white; }
            .certificate-container {
                box-shadow: none;
                border: 20px solid #e65100;
                outline: 5px double #ffccbc;
                outline-offset: -15px;
                page-break-inside: avoid;
            }
            .print-btn { display: none; }
        }
    </style>
</head>
<body>

    <button class="print-btn" onclick="window.print()">Print / Save PDF</button>

    <div class="certificate-container">
        <div class="logo">HARITHA SYSTEM</div>
        <h1>CERTIFICATE OF APPRECIATION</h1>
        <h2>This certificate is proudly presented to</h2>
        
        <div class="recipient">
            <?php echo htmlspecialchars($citizen_name); ?>
        </div>
        
        <p class="reason">
            For outstanding commitment and valuable service as a volunteer during the <br>
            <span class="event-name">"<?php echo htmlspecialchars($event_title); ?>"</span> program, <br>
            actively contributing toward ecological preservation and community progress.
        </p>

        <div class="footer-metrics">
            <div class="date-block">
                <div class="date-line">
                    <?php echo htmlspecialchars($issue_date); ?>
                </div>
                <div class="label">Date Issued</div>
            </div>
            
            <div class="signature-block">
                <div class="signature-line">
                    <span class="signature-img"><?php echo htmlspecialchars($gn_name); ?></span>
                </div>
                <div class="label">Authorized Signature</div>
            </div>
        </div>
    </div>

</body>
</html>