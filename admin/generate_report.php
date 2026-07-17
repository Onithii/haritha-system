<?php
session_start();
include("../config/db.php");

// Secure Access Check: Simple validation
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 5) {
    header("Location: ../auth/login.php");
    exit();
}

$report_type = isset($_GET['report_type']) ? mysqli_real_escape_string($conn, $_GET['report_type']) : '';
$report_generated = false;
$report_data = null;

// Stage 1 Extraction: No complex date constraints yet, just fetch raw entries to prove it works
if (!empty($report_type)) {
    $report_generated = true;
    
    if ($report_type == 'complaint_summary') {
        // Simple raw query pulling directly from the main complaints table
        $query = "SELECT complaint_id, title, district, created_at FROM complaints ORDER BY complaint_id DESC";
        $report_data = mysqli_query($conn, $query);
    } elseif ($report_type == 'volunteer_events') {
        // Simple raw query pulling directly from the volunteer events table
        $query = "SELECT event_id, event_title, district, event_date FROM volunteer_events ORDER BY event_id DESC";
        $report_data = mysqli_query($conn, $query);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Haritha Reporting - Stage 1</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; background-color: #f9f9f9; }
        .filter-box { background: white; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin-bottom: 20px; }
        .results-box { background: white; padding: 20px; border: 1px solid #1b5e20; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #1b5e20; color: white; }
        .error-msg { background: #ffebee; color: #c62828; padding: 15px; margin-bottom: 15px; border-radius: 4px; font-weight: bold; }
    </style>
</head>
<body>

    <h1>Haritha Reporting Engine (Stage 1: Base Data Fetch)</h1>
    <a href="admin_dash.php">← Back to Dashboard</a>
    <hr>

    <!-- Debugging Window: If an SQL syntax error exists, it will print here immediately -->
    <?php if ($report_generated && mysqli_error($conn)): ?>
        <div class="error-msg">
            SQL Execution Failure: <?php echo mysqli_error($conn); ?>
        </div>
    <?php endif; ?>

    <!-- Simple Filter Selector Form -->
    <div class="filter-box">
        <form method="GET" action="">
            <label for="report_type"><strong>Select Report Module:</strong></label>
            <select name="report_type" id="report_type" required>
                <option value="">-- Choose Type --</option>
                <option value="complaint_summary" <?php if($report_type == 'complaint_summary') echo 'selected'; ?>>1. Raw Environmental Complaints</option>
                <option value="volunteer_events" <?php if($report_type == 'volunteer_events') echo 'selected'; ?>>2. Raw Volunteer Campaigns</option>
            </select>
            <button type="submit" style="background:#1b5e20; color:white; padding:5px 15px; cursor:pointer;">Generate</button>
            <a href="generate_report.php">Reset</a>
        </form>
    </div>

    <!-- Data Render Output Display -->
    <?php if ($report_generated && !mysqli_error($conn)): ?>
        <div class="results-box">
            <h2>Report Output Preview</h2>
            <p>Showing all active entries found in the table database layout.</p>

            <table>
                <?php if ($report_type == 'complaint_summary'): ?>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Complaint Title</th>
                            <th>District</th>
                            <th>Date Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($report_data) > 0): while($row = mysqli_fetch_assoc($report_data)): ?>
                            <tr>
                                <td>#<?php echo $row['complaint_id']; ?></td>
                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                <td><?php echo htmlspecialchars($row['district']); ?></td>
                                <td><?php echo $row['created_at']; ?></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="4">No entries found inside the 'complaints' table.</td></tr>
                        <?php endif; ?>
                    </tbody>

                <?php elseif ($report_type == 'volunteer_events'): ?>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Event Title</th>
                            <th>District</th>
                            <th>Event Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($report_data) > 0): while($row = mysqli_fetch_assoc($report_data)): ?>
                            <tr>
                                <td>#<?php echo $row['event_id']; ?></td>
                                <td><?php echo htmlspecialchars($row['event_title']); ?></td>
                                <td><?php echo htmlspecialchars($row['district']); ?></td>
                                <td><?php echo $row['event_date']; ?></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="4">No entries found inside the 'volunteer_events' table.</td></tr>
                        <?php endif; ?>
                    </tbody>
                <?php endif; ?>
            </table>
        </div>
    <?php endif; ?>

</body>
</html>