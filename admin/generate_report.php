<?php
session_start();
include("../config/db.php");

// Secure Access Check: Simple validation
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 5) {
    header("Location: ../auth/login.php");
    exit();
}

// Sri Lankan Districts list for filtering layout
$districts_list = [
    "Colombo", "Gampaha", "Kalutara", "Kandy", "Matale", "Nuwara Eliya", 
    "Galle", "Matara", "Hambantota", "Jaffna", "Kilinochchi", "Mannar", 
    "Vavuniya", "Mullaitivu", "Batticaloa", "Ampara", "Trincomalee", 
    "Kurunegala", "Puttalam", "Anuradhapura", "Polonnaruwa", "Badulla", 
    "Moneragala", "Ratnapura", "Kegalle"
];

// Read Form inputs - Defaulting to the current month in 2026
$report_type = isset($_GET['report_type']) ? mysqli_real_escape_string($conn, $_GET['report_type']) : '';
$start_date = isset($_GET['start_date']) ? mysqli_real_escape_string($conn, $_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? mysqli_real_escape_string($conn, $_GET['end_date']) : date('Y-m-t');
$district = isset($_GET['district']) ? mysqli_real_escape_string($conn, $_GET['district']) : '';

$report_generated = false;
$report_data = null;

if (!empty($report_type)) {
    $report_generated = true;
    
    if ($report_type == 'complaint_summary') {
        // Build base condition filtering by dates
        $where = "WHERE created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
        // Dynamically append district if one is chosen
        if (!empty($district)) {
            $where .= " AND district = '$district'";
        }
        
        $query = "SELECT complaint_id, title, district, created_at FROM complaints $where ORDER BY created_at DESC";
        $report_data = mysqli_query($conn, $query);

    } elseif ($report_type == 'volunteer_events') {
        // Build base condition filtering by dates
        $where = "WHERE event_date BETWEEN '$start_date' AND '$end_date'";
        // Dynamically append district if one is chosen
        if (!empty($district)) {
            $where .= " AND district = '$district'";
        }
        
        $query = "SELECT event_id, event_title, district, event_date FROM volunteer_events $where ORDER BY event_date DESC";
        $report_data = mysqli_query($conn, $query);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Haritha Reporting - Stage 2</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; background-color: #f9f9f9; }
        .filter-box { background: white; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin-bottom: 20px; }
        .filter-grid { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 15px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-weight: bold; margin-bottom: 5px; font-size: 13px; }
        .filter-group select, .filter-group input { padding: 6px; border-radius: 4px; border: 1px solid #ccc; }
        .results-box { background: white; padding: 20px; border: 1px solid #1b5e20; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #1b5e20; color: white; }
        .error-msg { background: #ffebee; color: #c62828; padding: 15px; margin-bottom: 15px; border-radius: 4px; font-weight: bold; }
    </style>
</head>
<body>

    <h1>Haritha Reporting Engine (Stage 2: Filtering Logic)</h1>
    <a href="admin_dash.php">← Back to Dashboard</a>
    <hr>

    <?php if ($report_generated && mysqli_error($conn)): ?>
        <div class="error-msg">
            SQL Execution Failure: <?php echo mysqli_error($conn); ?>
        </div>
    <?php endif; ?>

    <!-- Upgraded Stage 2 Filter Form -->
    <div class="filter-box">
        <form method="GET" action="">
            <div class="filter-grid">
                <div class="filter-group">
                    <label for="report_type">Report Module *</label>
                    <select name="report_type" id="report_type" required>
                        <option value="">-- Choose Type --</option>
                        <option value="complaint_summary" <?php if($report_type == 'complaint_summary') echo 'selected'; ?>>1. Environmental Complaints</option>
                        <option value="volunteer_events" <?php if($report_type == 'volunteer_events') echo 'selected'; ?>>2. Volunteer Campaigns</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" name="start_date" id="start_date" value="<?php echo $start_date; ?>">
                </div>

                <div class="filter-group">
                    <label for="end_date">End Date</label>
                    <input type="date" name="end_date" id="end_date" value="<?php echo $end_date; ?>">
                </div>

                <div class="filter-group">
                    <label for="district">District Filter (Optional)</label>
                    <select name="district" id="district">
                        <option value="">-- All Districts --</option>
                        <?php foreach($districts_list as $d): ?>
                            <option value="<?php echo $d; ?>" <?php if($district == $d) echo 'selected'; ?>><?php echo $d; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <button type="submit" style="background:#1b5e20; color:white; padding:8px 20px; cursor:pointer; border:none; border-radius:4px;">Compile Report</button>
                    <a href="generate_report.php" style="padding:7px 15px; border:1px solid #ccc; text-decoration:none; color:#333; margin-left:5px; background:#eee; border-radius:4px; font-size:13px;">Clear</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Data Output View -->
    <?php if ($report_generated && !mysqli_error($conn)): ?>
        <div class="results-box">
            <h2>Active Dataset Metrics</h2>
            <p>Filtering records between <strong><?php echo $start_date; ?></strong> and <strong><?php echo $end_date; ?></strong> 
               <?php echo !empty($district) ? " within <strong>" . htmlspecialchars($district) . "</strong>" : " across all locations"; ?>.</p>

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
                            <tr><td colspan="4" style="color:#777; font-style:italic;">No records match your selected date and location criteria.</td></tr>
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
                            <tr><td colspan="4" style="color:#777; font-style:italic;">No campaign entries match your selected date and location criteria.</td></tr>
                        <?php endif; ?>
                    </tbody>
                <?php endif; ?>
            </table>
        </div>
    <?php endif; ?>

</body>
</html>