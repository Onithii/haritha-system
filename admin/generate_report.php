<?php
session_start();
include("../config/db.php");

// Secure Access Check: Ensure user is logged in and is an Admin (Role 5)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 5 || empty($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
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

// Default form configurations
$report_type = isset($_GET['report_type']) ? mysqli_real_escape_string($conn, $_GET['report_type']) : '';
$start_date = isset($_GET['start_date']) ? mysqli_real_escape_string($conn, $_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? mysqli_real_escape_string($conn, $_GET['end_date']) : date('Y-m-t');
$district = isset($_GET['district']) ? mysqli_real_escape_string($conn, $_GET['district']) : '';

$report_generated = false;
$summary_stats = [];
$report_data = [];

// Process calculations if a specific type is requested
if (!empty($report_type)) {
    $report_generated = true;

    if ($report_type == 'complaint_summary') {
        // --- 1. COMPLAINT SUMMARY REPORT LOGIC ---
        $where_clauses = ["c.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'"];
        if (!empty($district)) {
            $where_clauses[] = "c.district = '$district'";
        }
        $where_str = implode(" AND ", $where_clauses);

        // Fetch Total Metrics
        $tot_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM complaints c WHERE $where_str");
        $summary_stats['total'] = mysqli_fetch_assoc($tot_res)['total'];

        // Fetch breakdown by Status
        $status_query = "SELECT s.status_name, COUNT(c.complaint_id) as count 
                         FROM complaint_status s 
                         LEFT JOIN complaints c ON c.status_id = s.status_id AND $where_str
                         GROUP BY s.status_id";
        $status_res = mysqli_query($conn, $status_query);
        while($row = mysqli_fetch_assoc($status_res)) {
            $summary_stats['status'][$row['status_name']] = $row['count'];
        }

        // Fetch breakdown by Category
        $cat_query = "SELECT cat.category_name, COUNT(c.complaint_id) as count 
                      FROM complaint_categories cat 
                      LEFT JOIN complaints c ON c.category_id = cat.category_id AND $where_str
                      GROUP BY cat.category_id";
        $cat_res = mysqli_query($conn, $cat_query);
        while($row = mysqli_fetch_assoc($cat_res)) {
            $summary_stats['category'][$row['category_name']] = $row['count'];
        }

        // Detailed table row tracking
        $detail_query = "SELECT c.complaint_id, c.title, c.district, cat.category_name, s.status_name, c.created_at 
                         FROM complaints c
                         JOIN complaint_categories cat ON c.category_id = cat.category_id
                         JOIN complaint_status s ON c.status_id = s.status_id
                         WHERE $where_str ORDER BY c.created_at DESC";
        $report_data = mysqli_query($conn, $detail_query);

    } elseif ($report_type == 'volunteer_events') {
        // --- 2. VOLUNTEER CAMPAIGN PERFORMANCE REPORT ---
        $where_clauses = ["e.event_date BETWEEN '$start_date' AND '$end_date'"];
        if (!empty($district)) {
            $where_clauses[] = "e.district = '$district'";
        }
        $where_str = implode(" AND ", $where_clauses);

        $event_query = "SELECT e.event_id, e.event_title, e.event_date, e.district, e.required_volunteers, e.status,
                        (SELECT COUNT(*) FROM volunteer_participants vp WHERE vp.event_id = e.event_id) as registered_count
                        FROM volunteer_events e
                        WHERE $where_str ORDER BY e.event_date DESC";
        $report_data = mysqli_query($conn, $event_query);
        
        // Cumulative Metrics
        $totals_query = "SELECT COUNT(e.event_id) as total_events, SUM(e.required_volunteers) as total_req 
                         FROM volunteer_events e WHERE $where_str";
        $tot_res = mysqli_query($conn, $totals_query);
        $summary_stats = mysqli_fetch_assoc($tot_res);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Haritha Automated Reporting System</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; padding-bottom: 60px; color: #333; }
        .header { background-color: #1b5e20; color: white; padding: 25px; text-align: center; position: relative; }
        .btn-back { position: absolute; left: 20px; top: 30px; background-color: white; color: #1b5e20; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-weight: bold; font-size: 14px; }
        .btn-back:hover { background-color: #e0e0e0; }
        
        .container { width: 85%; margin: 30px auto; }
        
        /* Interactive Form Filter Layout */
        .filter-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0px 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-weight: bold; margin-bottom: 5px; font-size: 14px; color: #2e7d32; }
        .filter-group select, .filter-group input { padding: 10px; border-radius: 5px; border: 1px solid #ccc; font-size: 14px; }
        .action-bar { display: flex; gap: 10px; justify-content: flex-end; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 14px; display: inline-block; }
        .btn-primary { background-color: #1b5e20; color: white; }
        .btn-primary:hover { background-color: #003300; }
        .btn-secondary { background-color: #757575; color: white; }
        .btn-secondary:hover { background-color: #424242; }
        .btn-print { background-color: #0288d1; color: white; }
        .btn-print:hover { background-color: #01579b; }

        /* Report Output Display */
        .report-paper { background: white; padding: 40px; border-radius: 8px; box-shadow: 0px 4px 12px rgba(0,0,0,0.1); border-top: 8px solid #1b5e20; }
        .report-title-block { text-align: center; border-bottom: 2px dashed #1b5e20; padding-bottom: 20px; margin-bottom: 25px; }
        .report-title-block h2 { margin: 0; color: #1b5e20; text-transform: uppercase; letter-spacing: 1px; }
        .report-meta { font-size: 13px; color: #666; margin-top: 8px; }

        /* Summary Stats Cards Grid */
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .metric-box { background: #e8f5e9; padding: 15px; border-radius: 6px; text-align: center; border-left: 4px solid #2e7d32; }
        .metric-value { font-size: 24px; font-weight: bold; color: #1b5e20; margin-top: 5px; }
        .metric-label { font-size: 12px; color: #555; text-transform: uppercase; font-weight: bold; }

        .breakdown-section { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
        .breakdown-card { background: #fafafa; border: 1px solid #e0e0e0; padding: 15px; border-radius: 6px; }
        .breakdown-card h4 { margin-top: 0; border-bottom: 1px solid #ccc; padding-bottom: 5px; color: #1b5e20; }
        .breakdown-item { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; border-bottom: 1px dashed #eee; }

        /* Data Tables styling */
        .data-table { width: 100%; border-collapse: collapse; text-align: left; margin-top: 15px; font-size: 14px; }
        .data-table th, .data-table td { padding: 12px; border-bottom: 1px solid #e0e0e0; }
        .data-table th { background-color: #f5f5f5; color: #1b5e20; font-weight: bold; }
        .data-table tr:hover { background-color: #f9f9f9; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .bg-open { background-color: #c8e6c9; color: #25602a; }
        .bg-closed { background-color: #ffcdd2; color: #c62828; }

        /* Print Override System styles */
        @media print {
            body { background: white; color: black; }
            .header, .filter-card, .btn-back, .action-bar { display: none !important; }
            .container { width: 100%; margin: 0; }
            .report-paper { box-shadow: none; border: none; padding: 0; }
        }
    </style>
</head>
<body>

<div class="header">
    <a href="admin_dash.php" class="btn-back">← Back to Dashboard</a>
    <h1>Haritha Governance Reporting Engine</h1>
    <p>Dynamic Environmental Performance Metrics & System Aggregates</p>
</div>

<div class="container">
    
    <!-- Input Parameter Controls Section -->
    <div class="filter-card">
        <form method="GET" action="">
            <div class="filter-grid">
                <div class="filter-group">
                    <label for="report_type">Select Report Type *</label>
                    <select name="report_type" id="report_type" required>
                        <option value="">-- Select Report Matrix --</option>
                        <option value="complaint_summary" <?php if($report_type == 'complaint_summary') echo 'selected'; ?>>1. Environmental Complaint Summary Report</option>
                        <option value="volunteer_events" <?php if($report_type == 'volunteer_events') echo 'selected'; ?>>2. Volunteer Campaign Performance Report</option>
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
                    <label for="district">Target District (Optional)</label>
                    <select name="district" id="district">
                        <option value="">-- All Districts --</option>
                        <?php foreach($districts_list as $d): ?>
                            <option value="<?php echo $d; ?>" <?php if($district == $d) echo 'selected'; ?>><?php echo $d; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="action-bar">
                <?php if($report_generated): ?>
                    <button type="button" class="btn btn-print" onclick="window.print();">Print / Save PDF</button>
                <?php endif; ?>
                <a href="generate_report.php" class="btn btn-secondary">Reset Filters</a>
                <button type="submit" class="btn btn-primary">Compile System Report</button>
            </div>
        </form>
    </div>

    <!-- Output Paper Document Module -->
    <?php if($report_generated): ?>
        <div class="report-paper">
            
            <div class="report-title-block">
                <h2>
                    <?php 
                        if ($report_type == 'complaint_summary') echo "Haritha Environmental Complaint Summary Report";
                        if ($report_type == 'volunteer_events') echo "Volunteer Campaign Performance Report";
                    ?>
                </h2>
                <div class="report-meta">
                    <strong>Target Period:</strong> <?php echo $start_date; ?> to <?php echo $end_date; ?> 
                    <?php if(!empty($district)) echo " | <strong>Jurisdiction Filter:</strong> " . htmlspecialchars($district) . " District"; ?>
                    <br>
                    <span style="font-size: 11px; color:#888;">System Generated on: <?php echo date('Y-m-d H:i:s'); ?> | Operator: Administration Portal</span>
                </div>
            </div>

            <?php if($report_type == 'complaint_summary'): ?>
                <!-- COMPLAINT BREAKDOWN OUTPUT -->
                <div class="metrics-grid">
                    <div class="metric-box">
                        <div class="metric-label">Total Logged Complaints</div>
                        <div class="metric-value"><?php echo $summary_stats['total']; ?></div>
                    </div>
                </div>

                <div class="breakdown-section">
                    <div class="breakdown-card">
                        <h4>Volume Status Distribution</h4>
                        <?php if(!empty($summary_stats['status'])): foreach($summary_stats['status'] as $status_name => $count): ?>
                            <div class="breakdown-item">
                                <span><?php echo htmlspecialchars($status_name); ?></span>
                                <strong><?php echo $count; ?></strong>
                            </div>
                        <?php endforeach; else: echo "<p style='color:#999;font-size:13px;'>No status tracks found</p>"; endif; ?>
                    </div>

                    <div class="breakdown-card">
                        <h4>Environmental Category Distribution</h4>
                        <?php if(!empty($summary_stats['category'])): foreach($summary_stats['category'] as $cat_name => $count): ?>
                            <div class="breakdown-item">
                                <span><?php echo htmlspecialchars($cat_name); ?></span>
                                <strong><?php echo $count; ?></strong>
                            </div>
                        <?php endforeach; else: echo "<p style='color:#999;font-size:13px;'>No classifications mapped</p>"; endif; ?>
                    </div>
                </div>

                <h3>Granular Record Log</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Complaint ID</th>
                            <th>Title / Issue Summary</th>
                            <th>District</th>
                            <th>Classification</th>
                            <th>Status Flag</th>
                            <th>Logged At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($report_data) > 0): while($row = mysqli_fetch_assoc($report_data)): ?>
                            <tr>
                                <td><strong>#<?php echo $row['complaint_id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                <td><?php echo htmlspecialchars($row['district']); ?></td>
                                <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                <td><span class="badge <?php echo ($row['status_name'] == 'Resolved') ? 'bg-open' : 'bg-closed'; ?>"><?php echo htmlspecialchars($row['status_name']); ?></span></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="6" style="text-align:center; color:#999;">No records match structural query criteria within specified date windows.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif($report_type == 'volunteer_events'): ?>
                <!-- VOLUNTEER EVENT OUTPUT -->
                <div class="metrics-grid">
                    <div class="metric-box">
                        <div class="metric-label">Campaigns Evaluated</div>
                        <div class="metric-value"><?php echo (int)$summary_stats['total_events']; ?></div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-label">Target Workforce Target</div>
                        <div class="metric-value"><?php echo (int)$summary_stats['total_req']; ?></div>
                    </div>
                </div>

                <h3>Campaign Deployment Register</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Event ID</th>
                            <th>Event Title</th>
                            <th>Scheduled Date</th>
                            <th>Target District</th>
                            <th>Required Capacity</th>
                            <th>Registered Roster</th>
                            <th>Operation State</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($report_data) > 0): while($row = mysqli_fetch_assoc($report_data)): ?>
                            <tr>
                                <td><strong>#<?php echo $row['event_id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($row['event_title']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($row['event_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['district']); ?></td>
                                <td><?php echo $row['required_volunteers']; ?> Headcount</td>
                                <td><strong><?php echo $row['registered_count']; ?> Registered</strong></td>
                                <td><span class="badge <?php echo ($row['status'] == 'OPEN') ? 'bg-open' : 'bg-closed'; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="7" style="text-align:center; color:#999;">No volunteer tracking records found for target parameters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </div>
    <?php else: ?>
        <div style="background:#fff; padding:40px; border-radius:8px; border:1px dashed #ccc; text-align:center; color:#757575;">
            Select a specific report metric above and configure filters to extract automated structural datasets from the system.
        </div>
    <?php endif; ?>

</div>

</body>
</html>