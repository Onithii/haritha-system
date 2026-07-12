<?php
session_start();
include("../config/db.php");

// 1. Check if an ID was passed in the URL parameter
if (!isset($_GET['id']) || empty(trim($_GET['id']))) {
    header("Location: citizen_dashboard.php");
    exit();
}

$event_id = (int)$_GET['id'];

// 2. Fetch full event metrics based on unique ID structure
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

// Re-route out if the event data isn't found in system registers
if (!$event) {
    echo "<h3>Event not found or has been removed.</h3>";
    echo "<a href='citizen_dashboard.php'>Return to Dashboard</a>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($event['event_title']); ?> - Details</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f2f5f2;
            margin: 0;
            padding-bottom: 60px;
        }

        .header {
            background-color: #2e7d32;
            color: white;
            padding: 20px;
            text-align: center;
            position: relative;
        }

        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            background-color: transparent;
            border: 2px solid white;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
        }

        .back-btn:hover {
            background-color: white;
            color: #2e7d32;
        }

        .details-container {
            width: 55%;
            min-width: 500px;
            margin: 40px auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0px 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        /* Large hero picture showcase block */
        .event-hero-image {
            width: 100%;
            height: 320px;
            background-color: #cbd5e1;
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
            color: #2e7d32;
            margin-top: 0;
            font-size: 26px;
            border-bottom: 2px solid #c8e6c9;
            padding-bottom: 12px;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 25px 0;
            background: #f9fbf9;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #2e7d32;
        }

        .meta-item {
            font-size: 15px;
            color: #455a64;
        }

        .meta-item strong {
            color: #2e7d32;
        }

        .description-box {
            font-size: 16px;
            line-height: 1.6;
            color: #333;
            margin-top: 20px;
            white-space: pre-line; /* Preserves breaks made by the GN */
        }

        .btn-register {
            display: block;
            width: 100%;
            background-color: #2e7d32;
            color: white;
            border: none;
            padding: 15px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            border-radius: 6px;
            margin-top: 30px;
            text-align: center;
            text-decoration: none;
            box-sizing: border-box;
        }

        .btn-register:hover {
            background-color: #1b5e20;
        }
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
        <h2 class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></h2>
        
        <div class="meta-grid">
            <div class="meta-item">
                <strong>📅 Target Date:</strong> <?php echo date("F d, Y", strtotime($event['event_date'])); ?>
            </div>
            <div class="meta-item">
                <strong>👥 Needed Volunteers:</strong> <?php echo htmlspecialchars($event['required_volunteers']); ?> spots available
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

        <form method="POST" action="join_event_action.php">
            <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
            <button type="submit" class="btn-register">Confirm Registration as Volunteer</button>
        </form>
    </div>
</div>

</body>
</html>