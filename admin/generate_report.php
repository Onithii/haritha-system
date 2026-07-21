<?php
session_start();
include("../config/db.php");

// 1. Secure Access Check: Ensure user is logged in and is a System Admin (Role 5)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 5 || empty($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

// Sri Lankan Districts list for dynamic filtering
$districts_list = [
    "Colombo", "Gampaha", "Kalutara", "Kandy", "Matale", "Nuwara Eliya", 
    "Galle", "Matara", "Hambantota", "Jaffna", "Kilinochchi", "Mannar", 
    "Vavuniya", "Mullaitivu", "Batticaloa", "Ampara", "Trincomalee", 
    "Kurunegala", "Puttalam", "Anuradhapura", "Polonnaruwa", "Badulla", 
    "Moneragala", "Ratnapura", "Kegalle"
];

// Default form configurations
$report_type = isset($_GET['report_type']) ? trim($_GET['report_type']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-t');
$district = isset($_GET['district']) ? trim($_GET['district']) : '';

$report_generated = false;
$summary_stats = [];
$report_data = null;

// 2. Process Data Processing Engine
if (!empty($report_type)) {
    $report_generated = true;

    if ($report_type == 'complaint_summary') {
        // --- 1. COMPLAINT SUMMARY REPORT LOGIC ---
        $start_timestamp = $start_date . " 00:00:00";
        $end_timestamp = $end_date . " 23:59:59";

        // Total Count Query
        if (!empty($district)) {
            $tot_sql = "SELECT COUNT(*) as total FROM complaints WHERE created_at BETWEEN ? AND ? AND district = ?";
            $tot_stmt = mysqli_prepare($conn, $tot_sql);
            mysqli_stmt_bind_param($tot_stmt, "sss", $start_timestamp, $end_timestamp, $district);
        } else {
            $tot_sql = "SELECT COUNT(*) as total FROM complaints WHERE created_at BETWEEN ? AND ?";
            $tot_stmt = mysqli_prepare($conn, $tot_sql);
            mysqli_stmt_bind_param($tot_stmt, "ss", $start_timestamp, $end_timestamp);
        }
        mysqli_stmt_execute($tot_stmt);
        $summary_stats['total'] = mysqli_fetch_assoc(mysqli_stmt_get_result($tot_stmt))['total'] ?? 0;
        mysqli_stmt_close($tot_stmt);

        // Status Distribution Breakdown
        if (!empty($district)) {
            $status_sql = "SELECT s.status_name, COUNT(c.complaint_id) as count 
                           FROM complaint_status s 
                           LEFT JOIN complaints c ON c.status_id = s.status_id 
                           AND c.created_at BETWEEN ? AND ? AND c.district = ?
                           GROUP BY s.status_id, s.status_name";
            $status_stmt = mysqli_prepare($conn, $status_sql);
            mysqli_stmt_bind_param($status_stmt, "sss", $start_timestamp, $end_timestamp, $district);
        } else {
            $status_sql = "SELECT s.status_name, COUNT(c.complaint_id) as count 
                           FROM complaint_status s 
                           LEFT JOIN complaints c ON c.status_id = s.status_id 
                           AND c.created_at BETWEEN ? AND ?
                           GROUP BY s.status_id, s.status_name";
            $status_stmt = mysqli_prepare($conn, $status_sql);
            mysqli_stmt_bind_param($status_stmt, "ss", $start_timestamp, $end_timestamp);
        }
        mysqli_stmt_execute($status_stmt);
        $status_res = mysqli_stmt_get_result($status_stmt);
        while ($row = mysqli_fetch_assoc($status_res)) {
            $summary_stats['status'][$row['status_name']] = $row['count'];
        }
        mysqli_stmt_close($status_stmt);

        // Category Breakdown
        if (!empty($district)) {
            $cat_sql = "SELECT cat.category_name, COUNT(c.complaint_id) as count 
                        FROM complaint_categories cat 
                        LEFT JOIN complaints c ON c.category_id = cat.category_id 
                        AND c.created_at BETWEEN ? AND ? AND c.district = ?
                        GROUP BY cat.category_id, cat.category_name";
            $cat_stmt = mysqli_prepare($conn, $cat_sql);
            mysqli_stmt_bind_param($cat_stmt, "sss", $start_timestamp, $end_timestamp, $district);
        } else {
            $cat_sql = "SELECT cat.category_name, COUNT(c.complaint_id) as count 
                        FROM complaint_categories cat 
                        LEFT JOIN complaints c ON c.category_id = cat.category_id 
                        AND c.created_at BETWEEN ? AND ?
                        GROUP BY cat.category_id, cat.category_name";
            $cat_stmt = mysqli_prepare($conn, $cat_sql);
            mysqli_stmt_bind_param($cat_stmt, "ss", $start_timestamp, $end_timestamp);
        }
        mysqli_stmt_execute($cat_stmt);
        $cat_res = mysqli_stmt_get_result($cat_stmt);
        while ($row = mysqli_fetch_assoc($cat_res)) {
            $summary_stats['category'][$row['category_name']] = $row['count'];
        }
        mysqli_stmt_close($cat_stmt);

        // Detailed Complaint Table Records
        if (!empty($district)) {
            $detail_sql = "SELECT c.complaint_id, c.title, c.district, cat.category_name, s.status_name, c.created_at 
                           FROM complaints c
                           JOIN complaint_categories cat ON c.category_id = cat.category_id
                           JOIN complaint_status s ON c.status_id = s.status_id
                           WHERE c.created_at BETWEEN ? AND ? AND c.district = ?
                           ORDER BY c.created_at DESC";
            $detail_stmt = mysqli_prepare($conn, $detail_sql);
            mysqli_stmt_bind_param($detail_stmt, "sss", $start_timestamp, $end_timestamp, $district);
        } else {
            $detail_sql = "SELECT c.complaint_id, c.title, c.district, cat.category_name, s.status_name, c.created_at 
                           FROM complaints c
                           JOIN complaint_categories cat ON c.category_id = cat.category_id
                           JOIN complaint_status s ON c.status_id = s.status_id
                           WHERE c.created_at BETWEEN ? AND ?
                           ORDER BY c.created_at DESC";
            $detail_stmt = mysqli_prepare($conn, $detail_sql);
            mysqli_stmt_bind_param($detail_stmt, "ss", $start_timestamp, $end_timestamp);
        }
        mysqli_stmt_execute($detail_stmt);
        $report_data = mysqli_stmt_get_result($detail_stmt);

    } elseif ($report_type == 'volunteer_events') {
        // --- 2. VOLUNTEER CAMPAIGN PERFORMANCE LOGIC ---
        if (!empty($district)) {
            $event_sql = "SELECT e.event_id, e.event_title, e.event_date, e.district, e.required_volunteers, e.status,
                          (SELECT COUNT(*) FROM volunteer_participants vp WHERE vp.event_id = e.event_id) as registered_count
                          FROM volunteer_events e
                          WHERE e.event_date BETWEEN ? AND ? AND e.district = ?
                          ORDER BY e.event_date DESC";
            $event_stmt = mysqli_prepare($conn, $event_sql);
            mysqli_stmt_bind_param($event_stmt, "sss", $start_date, $end_date, $district);
        } else {
            $event_sql = "SELECT e.event_id, e.event_title, e.event_date, e.district, e.required_volunteers, e.status,
                          (SELECT COUNT(*) FROM volunteer_participants vp WHERE vp.event_id = e.event_id) as registered_count
                          FROM volunteer_events e
                          WHERE e.event_date BETWEEN ? AND ?
                          ORDER BY e.event_date DESC";
            $event_stmt = mysqli_prepare($conn, $event_sql);
            mysqli_stmt_bind_param($event_stmt, "ss", $start_date, $end_date);
        }
        mysqli_stmt_execute($event_stmt);
        $report_data = mysqli_stmt_get_result($event_stmt);

        // Cumulative Totals
        if (!empty($district)) {
            $totals_sql = "SELECT COUNT(e.event_id) as total_events, COALESCE(SUM(e.required_volunteers),0) as total_req 
                           FROM volunteer_events e WHERE e.event_date BETWEEN ? AND ? AND e.district = ?";
            $totals_stmt = mysqli_prepare($conn, $totals_sql);
            mysqli_stmt_bind_param($totals_stmt, "sss", $start_date, $end_date, $district);
        } else {
            $totals_sql = "SELECT COUNT(e.event_id) as total_events, COALESCE(SUM(e.required_volunteers),0) as total_req 
                           FROM volunteer_events e WHERE e.event_date BETWEEN ? AND ?";
            $totals_stmt = mysqli_prepare($conn, $totals_sql);
            mysqli_stmt_bind_param($totals_stmt, "ss", $start_date, $end_date);
        }
        mysqli_stmt_execute($totals_stmt);
        $summary_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($totals_stmt));
        mysqli_stmt_close($totals_stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Governance Reporting Engine - Haritha Admin</title>
    
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
            --secondary-color: #475569;
            --secondary-hover: #334155;
            --accent-blue: #0288d1;
            --accent-blue-hover: #01579b;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0;
            color: var(--text-main);
            -webkit-font-smoothing: antialiased;
        }

        /* --- Header Styling --- */
        .header {
            background-color: var(--primary-color);
            color: white;
            padding: 16px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header-title h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }

        .header-title p {
            margin: 4px 0 0 0;
            font-size: 13px;
            opacity: 0.85;
        }

        .btn-top-back {
            background-color: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-top-back:hover {
            background-color: rgba(255, 255, 255, 0.22);
        }

        /* --- Container Wrapper --- */
        .layout-wrapper {
            max-width: 1100px;
            margin: 32px auto;
            padding: 0 24px;
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        /* --- Filter Card --- */
        .filter-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            padding: 24px;
        }

        .filter-card h2 {
            margin-top: 0;
            margin-bottom: 6px;
            font-size: 18px;
            font-weight: 600;
        }

        .filter-subtitle {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 13px;
            color: var(--text-muted);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text-main);
        }

        .filter-group select, 
        .filter-group input {
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            font-size: 13px;
            color: var(--text-main);
            background-color: #fff;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .filter-group select:focus, 
        .filter-group input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.1);
        }

        .action-bar {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            align-items: center;
            border-top: 1px solid var(--border-color);
            padding-top: 18px;
        }

        /* --- Buttons --- */
        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.2s ease, opacity 0.2s ease;
        }

        .btn-primary { background-color: var(--primary-color); color: white; }
        .btn-primary:hover { background-color: var(--primary-hover); }

        .btn-secondary { background-color: var(--secondary-color); color: white; }
        .btn-secondary:hover { background-color: var(--secondary-hover); }

        .btn-print { background-color: var(--accent-blue); color: white; }
        .btn-print:hover { background-color: var(--accent-blue-hover); }

        /* --- Report Document Styling --- */
        .report-paper {
            background: var(--card-bg);
            padding: 40px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            border-top: 6px solid var(--primary-color);
        }

        .report-header-block {
            text-align: center;
            border-bottom: 2px dashed var(--border-color);
            padding-bottom: 20px;
            margin-bottom: 28px;
        }

        .report-header-block h2 {
            margin: 0;
            color: var(--primary-color);
            font-size: 22px;
            letter-spacing: -0.01em;
            text-transform: uppercase;
        }

        .report-meta {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 8px;
            line-height: 1.5;
        }

        /* --- Stats Metric Cards --- */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .metric-box {
            background: #f0fdf4;
            padding: 18px;
            border-radius: var(--radius);
            text-align: center;
            border: 1px solid #bbf7d0;
            border-left: 4px solid var(--primary-color);
        }

        .metric-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
            margin-top: 4px;
        }

        .metric-label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.05em;
        }

        /* --- Breakdown Section --- */
        .breakdown-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .breakdown-card {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            padding: 20px;
            border-radius: var(--radius);
        }

        .breakdown-card h3 {
            margin-top: 0;
            margin-bottom: 12px;
            font-size: 14px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 8px;
            color: var(--primary-color);
            font-weight: 600;
        }

        .breakdown-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 13px;
            border-bottom: 1px dashed var(--border-color);
        }

        .breakdown-item:last-child {
            border-bottom: none;
        }

        /* --- Data Tables --- */
        .section-heading {
            font-size: 16px;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 14px;
            color: var(--text-main);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
        }

        .data-table th, 
        .data-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table th {
            background-color: #f8fafc;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.04em;
        }

        .data-table tr:hover {
            background-color: #f1f5f9;
        }

        .badge {
            padding: 3px 8px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
        }

        .bg-open { background-color: #dcfce7; color: #166534; }
        .bg-closed { background-color: #fee2e2; color: #991b1b; }

        .empty-placeholder {
            background: var(--card-bg);
            padding: 48px 24px;
            border-radius: var(--radius);
            border: 1px dashed var(--border-color);
            text-align: center;
            color: var(--text-muted);
            font-size: 14px;
        }

        /* --- Print Mode Optimizations --- */
        @media print {
            body { background: white; color: black; }
            .header, .filter-card, .btn-top-back, .action-bar { display: none !important; }
            .layout-wrapper { max-width: 100%; margin: 0; padding: 0; }
            .report-paper { box-shadow: none; border: none; padding: 0; border-top: none; }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="header-title">
        <h1>Haritha Governance Reporting Engine</h1>
        <p>Automated Environmental Performance Metrics & System Aggregates</p>
    </div>
    <a href="admin_dash.php" class="btn-top-back">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Dashboard
    </a>
</header>

<main class="layout-wrapper">
    
    <!-- Input Parameter Controls Section -->
    <section class="filter-card">
        <h2>Report Query Parameters</h2>
        <p class="filter-subtitle">Select dataset parameters to isolate specific time spans or regional jurisdictions.</p>
        
        <form method="GET" action="">
            <div class="filter-grid">
                <div class="filter-group">
                    <label for="report_type">Target Matrix *</label>
                    <select name="report_type" id="report_type" required>
                        <option value="">-- Select Report Matrix --</option>
                        <option value="complaint_summary" <?php if($report_type == 'complaint_summary') echo 'selected'; ?>>1. Environmental Complaint Summary Report</option>
                        <option value="volunteer_events" <?php if($report_type == 'volunteer_events') echo 'selected'; ?>>2. Volunteer Campaign Performance Report</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>

                <div class="filter-group">
                    <label for="end_date">End Date</label>
                    <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
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
                    <button type="button" class="btn btn-print" onclick="window.print();">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                        Print / Export PDF
                    </button>
                <?php endif; ?>
                <a href="generate_report.php" class="btn btn-secondary">Reset Parameters</a>
                <button type="submit" class="btn btn-primary">Compile System Report</button>
            </div>
        </form>
    </section>

    <!-- Output Paper Document Module -->
    <?php if($report_generated): ?>
        <article class="report-paper">
            
            <header class="report-header-block">
                <h2>
                    <?php 
                        if ($report_type == 'complaint_summary') echo "Environmental Complaint Summary Report";
                        if ($report_type == 'volunteer_events') echo "Volunteer Campaign Performance Report";
                    ?>
                </h2>
                <div class="report-meta">
                    <strong>Evaluation Window:</strong> <?php echo htmlspecialchars($start_date); ?> to <?php echo htmlspecialchars($end_date); ?> 
                    <?php if(!empty($district)) echo " | <strong>Jurisdiction:</strong> " . htmlspecialchars($district) . " District"; ?>
                    <br>
                    <span style="font-size: 11px;">Generated on: <?php echo date('Y-m-d H:i:s'); ?> | System Operator: Administrator Control Panel</span>
                </div>
            </header>

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
                        <h3>Volume Status Distribution</h3>
                        <?php if(!empty($summary_stats['status'])): foreach($summary_stats['status'] as $status_name => $count): ?>
                            <div class="breakdown-item">
                                <span><?php echo htmlspecialchars($status_name); ?></span>
                                <strong><?php echo $count; ?></strong>
                            </div>
                        <?php endforeach; else: echo "<p style='color:var(--text-muted);font-size:13px;'>No status tracks found.</p>"; endif; ?>
                    </div>

                    <div class="breakdown-card">
                        <h3>Environmental Category Breakdown</h3>
                        <?php if(!empty($summary_stats['category'])): foreach($summary_stats['category'] as $cat_name => $count): ?>
                            <div class="breakdown-item">
                                <span><?php echo htmlspecialchars($cat_name); ?></span>
                                <strong><?php echo $count; ?></strong>
                            </div>
                        <?php endforeach; else: echo "<p style='color:var(--text-muted);font-size:13px;'>No classifications mapped.</p>"; endif; ?>
                    </div>
                </div>

                <h3 class="section-heading">Detailed Record Register</h3>
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
                            <tr>
                                <td><strong>#<?php echo $row['complaint_id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                <td><?php echo htmlspecialchars($row['district']); ?></td>
                                <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                <td>
                                    <span class="badge <?php echo ($row['status_name'] == 'Resolved') ? 'bg-open' : 'bg-closed'; ?>">
                                        <?php echo htmlspecialchars($row['status_name']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No records match structural query criteria within specified date windows.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif($report_type == 'volunteer_events'): ?>
                <!-- VOLUNTEER EVENT OUTPUT -->
                <div class="metrics-grid">
                    <div class="metric-box">
                        <div class="metric-label">Campaigns Evaluated</div>
                        <div class="metric-value"><?php echo (int)($summary_stats['total_events'] ?? 0); ?></div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-label">Target Workforce Target</div>
                        <div class="metric-value"><?php echo (int)($summary_stats['total_req'] ?? 0); ?></div>
                    </div>
                </div>

                <h3 class="section-heading">Campaign Deployment Register</h3>
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
                        <?php if($report_data && mysqli_num_rows($report_data) > 0): while($row = mysqli_fetch_assoc($report_data)): ?>
                            <tr>
                                <td><strong>#<?php echo $row['event_id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($row['event_title']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($row['event_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['district']); ?></td>
                                <td><?php echo $row['required_volunteers']; ?> Headcount</td>
                                <td><strong><?php echo $row['registered_count']; ?> Registered</strong></td>
                                <td>
                                    <span class="badge <?php echo ($row['status'] == 'OPEN') ? 'bg-open' : 'bg-closed'; ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No volunteer tracking records found for target parameters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </article>
    <?php else: ?>
        <div class="empty-placeholder">
            Select a report matrix type above and configure filters to aggregate dynamic environmental governance data.
        </div>
    <?php endif; ?>

</main>

</body>
</html>