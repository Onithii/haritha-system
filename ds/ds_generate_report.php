<?php
session_start();
include("../config/db.php");

// 1. Secure Access Check: Ensure user is logged in as a Divisional Secretariat (Role ID: 4)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 4 || empty($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

$ds_user_id = (int)$_SESSION['user_id'];

// Fetch logged-in DS profile info to display context on the report sheet securely using prepared statements
$ds_profile = ['f_name' => 'Officer', 'l_name' => '', 'gn_division' => 'Your Division', 'district' => 'Your District'];
$ds_stmt = mysqli_prepare($conn, "SELECT f_name, l_name, gn_division, district FROM users WHERE user_id = ? LIMIT 1");
if ($ds_stmt) {
    mysqli_stmt_bind_param($ds_stmt, "i", $ds_user_id);
    mysqli_stmt_execute($ds_stmt);
    $ds_result = mysqli_stmt_get_result($ds_stmt);
    if ($row = mysqli_fetch_assoc($ds_result)) {
        $ds_profile = $row;
    }
    mysqli_stmt_close($ds_stmt);
}

// Default date window limits configuration
$start_date = isset($_GET['start_date']) ? mysqli_real_escape_string($conn, $_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? mysqli_real_escape_string($conn, $_GET['end_date']) : date('Y-m-t');
$report_type = isset($_GET['report_type']) ? mysqli_real_escape_string($conn, $_GET['report_type']) : '';

$report_generated = false;
$summary_stats = ['total' => 0, 'status' => [], 'category' => []];
$report_data = null;

if (!empty($report_type) && $report_type == 'complaint_summary') {
    $report_generated = true;

    // Encapsulate queries strictly inside the assigned DS context and the time framework
    $where_str = "c.assigned_to_id = $ds_user_id AND c.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";

    // Fetch total matching cases assigned to this specific DS
    $tot_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM complaints c WHERE $where_str");
    if ($tot_res) {
        $summary_stats['total'] = mysqli_fetch_assoc($tot_res)['total'];
    }

    // Fetch internal metrics breakdown sorted by current operational statuses
    $status_query = "SELECT s.status_name, COUNT(c.complaint_id) as count 
                     FROM complaint_status s 
                     LEFT JOIN complaints c ON c.status_id = s.status_id AND $where_str
                     GROUP BY s.status_id, s.status_name";
    $status_res = mysqli_query($conn, $status_query);
    if ($status_res) {
        while($row = mysqli_fetch_assoc($status_res)) {
            $summary_stats['status'][$row['status_name']] = $row['count'];
        }
    }

    // Fetch categorization metrics mapping volume levels
    $cat_query = "SELECT cat.category_name, COUNT(c.complaint_id) as count 
                  FROM complaint_categories cat 
                  LEFT JOIN complaints c ON c.category_id = cat.category_id AND $where_str
                  GROUP BY cat.category_id, cat.category_name";
    $cat_res = mysqli_query($conn, $cat_query);
    if ($cat_res) {
        while($row = mysqli_fetch_assoc($cat_res)) {
            $summary_stats['category'][$row['category_name']] = $row['count'];
        }
    }

    // Fetch detailed records strictly matching scope limits
    $detail_query = "SELECT c.complaint_id, c.title, c.district, cat.category_name, s.status_name, c.created_at 
                     FROM complaints c
                     JOIN complaint_categories cat ON c.category_id = cat.category_id
                     JOIN complaint_status s ON c.status_id = s.status_id
                     WHERE $where_str ORDER BY c.created_at DESC";
    $report_data = mysqli_query($conn, $detail_query);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DS Environmental Performance Desk</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; padding-bottom: 60px; color: #333; }
        .header { background-color: #0d47a1; color: white; padding: 25px; text-align: center; position: relative; }
        .btn-back { position: absolute; left: 20px; top: 30px; background-color: white; color: #0d47a1; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-weight: bold; font-size: 14px; }
        .btn-back:hover { background-color: #e0e0e0; }
        
        .container { width: 85%; margin: 30px auto; }
        
        .filter-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0px 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-weight: bold; margin-bottom: 5px; font-size: 14px; color: #0d47a1; }
        .filter-group select, .filter-group input { padding: 10px; border-radius: 5px; border: 1px solid #ccc; font-size: 14px; }
        .action-bar { display: flex; gap: 10px; justify-content: flex-end; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 14px; display: inline-block; }
        .btn-primary { background-color: #0d47a1; color: white; }
        .btn-primary:hover { background-color: #002171; }
        .btn-secondary { background-color: #757575; color: white; }
        .btn-secondary:hover { background-color: #424242; }
        .btn-print { background-color: #0288d1; color: white; }
        .btn-print:hover { background-color: #01579b; }

        .report-paper { background: white; padding: 40px; border-radius: 8px; box-shadow: 0px 4px 12px rgba(0,0,0,0.1); border-top: 8px solid #0d47a1; }
        .report-title-block { text-align: center; border-bottom: 2px dashed #0d47a1; padding-bottom: 20px; margin-bottom: 25px; }
        .report-title-block h2 { margin: 0; color: #0d47a1; text-transform: uppercase; letter-spacing: 1px; }
        .report-meta { font-size: 13px; color: #666; margin-top: 8px; line-height: 1.6; }

        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .metric-box { background: #e3f2fd; padding: 15px; border-radius: 6px; text-align: center; border-left: 4px solid #0d47a1; }
        .metric-value { font-size: 24px; font-weight: bold; color: #0d47a1; margin-top: 5px; }
        .metric-label { font-size: 12px; color: #555; text-transform: uppercase; font-weight: bold; }

        .breakdown-section { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
        .breakdown-card { background: #fafafa; border: 1px solid #e0e0e0; padding: 15px; border-radius: 6px; }
        .breakdown-card h4 { margin-top: 0; border-bottom: 1px solid #ccc; padding-bottom: 5px; color: #0d47a1; }
        .breakdown-item { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; border-bottom: 1px dashed #eee; }

        .data-table { width: 100%; border-collapse: collapse; text-align: left; margin-top: 15px; font-size: 14px; }
        .data-table th, .data-table td { padding: 12px; border-bottom: 1px solid #e0e0e0; }
        .data-table th { background-color: #f5f5f5; color: #0d47a1; font-weight: bold; }
        .data-table tr:hover { background-color: #f9f9f9; }
        
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .bg-open { background-color: #ffe0b2; color: #e65100; }
        .bg-progress { background-color: #b3e5fc; color: #0288d1; }
        .bg-resolved { background-color: #c8e6c9; color: #25602a; }
        .bg-fallback { background-color: #e0e0e0; color: #616161; }

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
    <a href="dashboard.php" class="btn-back">← Back to Panel</a>
    <h1>Haritha Division Performance Engine</h1>
    <p>Local Jurisdiction Environmental Tracking & Metrics Panel</p>
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
                        <option value="complaint_summary" <?php if($report_type == 'complaint_summary') echo 'selected'; ?>>Divisional Environmental Complaint Summary Report</option>
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
            </div>

            <div class="action-bar">
                <?php if($report_generated): ?>
                    <button type="button" class="btn btn-print" onclick="window.print();">Print / Save PDF</button>
                <?php endif; ?>
                <a href="ds_generate_report.php" class="btn btn-secondary">Reset Filters</a>
                <button type="submit" class="btn btn-primary">Compile Division Report</button>
            </div>
        </form>
    </div>

    <!-- Output Paper Document Module -->
    <?php if($report_generated): ?>
        <div class="report-paper">
            
            <div class="report-title-block">
                <h2>Haritha Divisional Environmental Complaint Report</h2>
                <div class="report-meta">
                    <strong>Target Period:</strong> <?php echo htmlspecialchars($start_date); ?> to <?php echo htmlspecialchars($end_date); ?><br>
                    <strong>Officer In-Charge:</strong> Officer <?php echo htmlspecialchars($ds_profile['f_name'] . ' ' . $ds_profile['l_name']); ?> (ID: #<?php echo $ds_user_id; ?>)<br>
                    <strong>Assigned Jurisdiction:</strong> GN Division: <?php echo htmlspecialchars($ds_profile['gn_division'] ?? 'Not Specified'); ?> | District: <?php echo htmlspecialchars($ds_profile['district'] ?? 'Not Specified'); ?>
                    <br>
                    <span style="font-size: 11px; color:#888;">System Generated on: <?php echo date('Y-m-d H:i:s'); ?> | Secure Officer Access Mode</span>
                </div>
            </div>

            <!-- COMPLAINT BREAKDOWN OUTPUT -->
            <div class="metrics-grid">
                <div class="metric-box">
                    <div class="metric-label">Your Logged Complaints</div>
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
                    <?php if($report_data && mysqli_num_rows($report_data) > 0): while($row = mysqli_fetch_assoc($report_data)): ?>
                        <?php 
                            $raw_status = strtoupper(trim($row['status_name']));
                            if (in_array($raw_status, ['PENDING', 'NEW', 'SUBMITTED'])) {
                                $badge_class = 'bg-open';
                            } elseif (in_array($raw_status, ['IN PROGRESS', 'INVESTIGATING', 'ASSIGNED'])) {
                                $badge_class = 'bg-progress';
                            } elseif (in_array($raw_status, ['RESOLVED', 'CLOSED', 'COMPLETED'])) {
                                $badge_class = 'bg-resolved';
                            } else {
                                $badge_class = 'bg-fallback';
                            }
                        ?>
                        <tr>
                            <td><strong>#<?php echo $row['complaint_id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo htmlspecialchars($row['district']); ?></td>
                            <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                            <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($row['status_name']); ?></span></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="6" style="text-align:center; color:#999;">No records found assigned to your ID inside the provided dates.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

        </div>
    <?php else: ?>
        <div style="background:#fff; padding:40px; border-radius:8px; border:1px dashed #ccc; text-align:center; color:#757575;">
            Select the environmental summary option above and define filters to compile localized performance insights.
        </div>
    <?php endif; ?>

</div>

</body>
</html>