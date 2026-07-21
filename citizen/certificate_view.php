<?php
session_start();
include("../config/db.php");

// 1. Secure Access Check: Ensure user is logged in as a Citizen (Assuming Role 1 or simply logged in)
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

// 2. Fetch all events where this specific citizen's attendance has been verified (attendance_verified = 1)
$certificates = [];
$query = "SELECT e.event_id, e.event_title, e.created_at 
          FROM volunteer_participants vp
          JOIN volunteer_events e ON vp.event_id = e.event_id
          WHERE vp.user_id = ? AND vp.attendance_verified = 1
          ORDER BY e.created_at DESC";

$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $certificates[] = $row;
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Certificates</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; padding-bottom: 50px; }
        .header { background-color: #2e7d32; color: white; padding: 20px; text-align: center; position: relative; }
        .back-btn { 
            position: absolute; top: 20px; left: 20px; 
            background-color: white; color: #2e7d32; 
            padding: 8px 15px; border-radius: 5px; 
            text-decoration: none; font-size: 14px; font-weight: bold; 
        }
        .back-btn:hover { background-color: #c8e6c9; }
        
        .main-container { width: 70%; margin: 40px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0px 2px 8px rgba(0,0,0,0.1); }
        .main-container h2 { color: #2e7d32; margin-top: 0; border-bottom: 2px solid #c8e6c9; padding-bottom: 10px; }
        
        .cert-list { margin-top: 20px; }
        .cert-item { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 18px 20px; 
            border-bottom: 1px solid #e0e0e0;
            transition: background-color 0.2s;
        }
        .cert-item:hover { background-color: #f1f8e9; }
        .cert-item:last-child { border-bottom: none; }
        
        .cert-details h4 { margin: 0 0 5px 0; color: #333; font-size: 16px; }
        .cert-details span { font-size: 12px; color: #757575; }
        
        .btn-view-cert { 
            background-color: #e65100; color: white; 
            text-decoration: none; padding: 10px 18px; 
            border-radius: 5px; font-weight: bold; font-size: 13px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .btn-view-cert:hover { background-color: #b33600; }
        
        .no-certs { text-align: center; color: #666; font-style: italic; padding: 40px 20px; }
        .badge-icon { font-size: 40px; color: #c8e6c9; margin-bottom: 10px; display: block; }
    </style>
</head>
<body>

<div class="header">
    <!-- Links safely back to the dashboard you just updated -->
    <a href="citizen_dash.php" class="back-btn">← Back to Dashboard</a>
    <h1>Achievements & Recognitions</h1>
</div>

<div class="main-container">
    <h2>Earned Volunteer Certificates</h2>
    <p style="color: #666; font-size: 14px;">Thank you for your active contribution to safeguarding our environment. Below are your officially verified participation certificates.</p>

    <div class="cert-list">
        <?php if (empty($certificates)): ?>
            <div class="no-certs">
                <span class="badge-icon">🎖️</span>
                You haven't received any certificates yet.<br>
                <span style="font-size: 12px; color:#999; font-style:normal;">Certificates appear automatically once a Grama Niladhari verifies your event attendance via QR code.</span>
            </div>
        <?php else: ?>
            <?php foreach ($certificates as $cert): ?>
                <div class="cert-item">
                    <div class="cert-details">
                        <h4><?php echo htmlspecialchars($cert['event_title']); ?></h4>
                        <span>Event ID Reference: #<?php echo $cert['event_id']; ?></span>
                    </div>
                    <div>
                        <!-- Direct root-level reference to your certificate generator page -->
                        <a href="../generate_certificate.php?event_id=<?php echo $cert['event_id']; ?>&user_id=<?php echo $user_id; ?>" class="btn-view-cert" target="_blank">
                            View Certificate ↗
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>