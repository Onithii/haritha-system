<?php
session_start();
include("../config/db.php");

// 1. Secure Access Check: Ensure user is logged in and is an Admin (Role 5)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 5 || empty($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

// Redirect if event ID parameter is missing
if (!isset($_GET['event_id']) || empty($_GET['event_id'])) {
    header("Location: event_management.php");
    exit();
}

$event_id = (int)$_GET['event_id'];

// 2. Fetch metadata for this specific event
$event_stmt = mysqli_prepare($conn, "SELECT event_title, event_date, location FROM volunteer_events WHERE event_id = ?");
mysqli_stmt_bind_param($event_stmt, "i", $event_id);
mysqli_stmt_execute($event_stmt);
$event_result = mysqli_stmt_get_result($event_stmt);
$event_meta = mysqli_fetch_assoc($event_result);
mysqli_stmt_close($event_stmt);

if (!$event_meta) {
    // Event doesn't exist
    header("Location: event_management.php");
    exit();
}

// 3. Fetch all registered participants using parameterized query
$participants_query = "
    SELECT 
        vp.registration_id, 
        vp.user_id, 
        vp.registered_at, 
        vp.attendance_verified, 
        vp.verified_at,
        u.f_name, 
        u.l_name, 
        u.email
    FROM volunteer_participants vp
    INNER JOIN users u ON vp.user_id = u.user_id
    WHERE vp.event_id = ?
    ORDER BY vp.registered_at ASC
";

$part_stmt = mysqli_prepare($conn, $participants_query);
mysqli_stmt_bind_param($part_stmt, "i", $event_id);
mysqli_stmt_execute($part_stmt);
$result = mysqli_stmt_get_result($part_stmt);
$total_participants = mysqli_num_rows($result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Roster - View Participants</title>
    <style>
        :root {
            --primary-color: #1b5e20;
            --primary-hover: #144718;
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --radius: 8px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0;
            color: var(--text-main);
            -webkit-font-smoothing: antialiased;
        }

        .header {
            background-color: var(--primary-color);
            color: white;
            padding: 24px 5%;
            text-align: center;
            position: relative;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }

        .header p {
            margin: 6px 0 0 0;
            font-size: 14px;
            opacity: 0.85;
        }

        .btn-back {
            position: absolute;
            left: 24px;
            top: 50%;
            transform: translateY(-50%);
            background-color: #ffffff;
            color: var(--primary-color);
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: background 0.2s;
        }

        .btn-back:hover {
            background-color: #f1f5f9;
        }

        .layout-container {
            max-width: 1200px;
            margin: 32px auto;
            padding: 0 24px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* --- Event Summary Card --- */
        .summary-card {
            background: var(--card-bg);
            padding: 24px;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .summary-info h2 {
            margin: 0 0 6px 0;
            color: var(--primary-color);
            font-size: 20px;
            font-weight: 600;
        }

        .summary-info p {
            margin: 0;
            color: var(--text-muted);
            font-size: 14px;
        }

        .roster-badge-count {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 15px;
            box-shadow: 0 2px 4px rgba(27, 94, 32, 0.2);
        }

        .section-title {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--text-main);
        }

        /* --- Data Table --- */
        .table-container {
            background: var(--card-bg);
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
        }

        th, td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            background-color: #f8fafc;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.05em;
        }

        tr:hover {
            background-color: #f1f5f9;
        }

        .no-data {
            text-align: center;
            padding: 36px;
            color: var(--text-muted);
            font-style: italic;
        }

        /* Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
            text-transform: uppercase;
        }

        .status-verified {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-pending {
            background-color: #fef9c3;
            color: #854d0e;
        }
    </style>
</head>
<body>

<header class="header">
    <a href="event_management.php" class="btn-back">&larr; Back to Events</a>
    <h1>Event Roster Portal</h1>
    <p>System Participant Log Audits & Check-Ins</p>
</header>

<main class="layout-container">

    <!-- Event Summary Banner -->
    <section class="summary-card">
        <div class="summary-info">
            <h2><?php echo htmlspecialchars($event_meta['event_title']); ?></h2>
            <p>
                <strong>Location:</strong> <?php echo htmlspecialchars($event_meta['location']); ?> &nbsp;|&nbsp; 
                <strong>Date:</strong> <?php echo date('Y-m-d', strtotime($event_meta['event_date'])); ?>
            </p>
        </div>
        <div class="roster-badge-count">
            Total Joined: <?php echo $total_participants; ?>
        </div>
    </section>

    <h2 class="section-title">Registered Roster</h2>

    <!-- Table Section -->
    <section class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Registration ID</th>
                    <th>User ID</th>
                    <th>Volunteer Full Name</th>
                    <th>Email Address</th>
                    <th>Registered At</th>
                    <th>Attendance</th>
                    <th>Verification Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($total_participants > 0): ?>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><strong>#<?php echo htmlspecialchars($row['registration_id']); ?></strong></td>
                            <td>#<?php echo htmlspecialchars($row['user_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['f_name'] . ' ' . $row['l_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($row['registered_at'])); ?></td>
                            <td>
                                <?php if ((int)$row['attendance_verified'] === 1): ?>
                                    <span class="badge status-verified">Verified Present</span>
                                <?php else: ?>
                                    <span class="badge status-pending">Not Checked-In</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                    echo (!empty($row['verified_at'])) 
                                        ? date('Y-m-d H:i', strtotime($row['verified_at'])) 
                                        : '<span style="color:#cbd5e1;">—</span>'; 
                                ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="no-data">No active volunteers have booked or joined this campaign yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

</main>

</body>
</html>
<?php
mysqli_stmt_close($part_stmt);
?>