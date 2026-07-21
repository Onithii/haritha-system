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

// --- Self-contained Broadcast Processing Layer ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_broadcast') {
    header('Content-Type: application/json');
    
    $title = isset($_POST['title']) ? trim(mysqli_real_escape_string($conn, $_POST['title'])) : '';
    $message = isset($_POST['message']) ? trim(mysqli_real_escape_string($conn, $_POST['message'])) : '';
    $expires_at = !empty($_POST['expires_at']) ? mysqli_real_escape_string($conn, $_POST['expires_at']) : null;
    $created_by = (int)$_SESSION['user_id'];

    if (empty($title) || empty($message)) {
        echo json_encode(['status' => 'error', 'message' => 'Title and Message fields are mandatory.']);
        exit();
    }

    if ($expires_at) {
        $query = "INSERT INTO messages (title, message, created_by, expires_at) VALUES ('$title', '$message', $created_by, '$expires_at')";
    } else {
        $query = "INSERT INTO messages (title, message, created_by) VALUES ('$title', '$message', $created_by)";
    }

    if (mysqli_query($conn, $query)) {
        echo json_encode(['status' => 'success', 'message' => 'Broadcast notification successfully dispatched.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database operation error: ' . mysqli_error($conn)]);
    }
    exit();
}

// 1. Sri Lankan Districts list for clean filter matching
$districts_list = [
    "Colombo", "Gampaha", "Kalutara", "Kandy", "Matale", "Nuwara Eliya", 
    "Galle", "Matara", "Hambantota", "Jaffna", "Kilinochchi", "Mannar", 
    "Vavuniya", "Mullaitivu", "Batticaloa", "Ampara", "Trincomalee", 
    "Kurunegala", "Puttalam", "Anuradhapura", "Polonnaruwa", "Badulla", 
    "Moneragala", "Ratnapura", "Kegalle"
];

// Master Status List Mapping for display names and badges
$status_map = [
    1 => "SUBMITTED",
    2 => "ASSIGNED",
    3 => "IN_PROGRESS",
    4 => "COMPLETED",
    5 => "ESCALATED",
    6 => "REJECTED"
];

// 2. Handle Filters
$area_filter = isset($_GET['area']) ? mysqli_real_escape_string($conn, $_GET['area']) : '';
$status_filter = isset($_GET['status_id']) ? mysqli_real_escape_string($conn, $_GET['status_id']) : '';

// Helper function to render status badges
function renderStatusBadge($status_id, $status_map) {
    $name = isset($status_map[$status_id]) ? $status_map[$status_id] : "UNKNOWN";
    
    // Status color class assignment mapping
    $class_map = [
        1 => 'badge-submitted',
        2 => 'badge-assigned',
        3 => 'badge-progress',
        4 => 'badge-completed',
        5 => 'badge-escalated',
        6 => 'badge-rejected'
    ];
    
    $badge_class = isset($class_map[$status_id]) ? $class_map[$status_id] : 'badge-default';
    return '<span class="badge ' . $badge_class . '">' . htmlspecialchars($name) . '</span>';
}

// 3. Handle AJAX "Load More" Request
if (isset($_GET['ajax_load'])) {
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    $query = "SELECT * FROM complaints WHERE 1=1";
    if (!empty($area_filter)) { $query .= " AND district = '$area_filter'"; }
    if (!empty($status_filter)) { $query .= " AND status_id = '$status_filter'"; }
    $query .= " ORDER BY created_at DESC LIMIT 5 OFFSET $offset";
    
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        while($complaint = mysqli_fetch_assoc($result)) {
            $district = !empty($complaint['district']) ? htmlspecialchars($complaint['district']) : 'Unassigned';
            $badge = renderStatusBadge($complaint['status_id'], $status_map);
            
            echo '<tr>
                    <td class="font-mono">#' . htmlspecialchars($complaint['complaint_id']) . '</td>
                    <td><strong>' . htmlspecialchars($complaint['title']) . '</strong></td>
                    <td>' . $district . '</td>
                    <td>' . $badge . '</td>
                    <td>' . date('M d, Y', strtotime($complaint['created_at'])) . '</td>
                    <td>
                        <a href="view_complaints.php?id=' . $complaint['complaint_id'] . '" class="table-action-link">
                            View Details
                        </a>
                    </td>
                  </tr>';
        }
    }
    exit();
}

// 4. Base Initial Query (Gets first 5 rows)
$query = "SELECT * FROM complaints WHERE 1=1";
if (!empty($area_filter)) { $query .= " AND district = '$area_filter'"; }
if (!empty($status_filter)) { $query .= " AND status_id = '$status_filter'"; }
$query .= " ORDER BY created_at DESC LIMIT 5 OFFSET 0";
$result = mysqli_query($conn, $query);

// 5. Total count for determining pagination limits
$count_query = "SELECT COUNT(*) as total FROM complaints WHERE 1=1";
if (!empty($area_filter)) { $count_query .= " AND district = '$area_filter'"; }
if (!empty($status_filter)) { $count_query .= " AND status_id = '$status_filter'"; }
$count_res = mysqli_fetch_assoc(mysqli_query($conn, $count_query));
$total_records = $count_res['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Administration Control Panel</title>
    
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
            letter-spacing: -0.01em;
        }

        .header-title p {
            margin: 4px 0 0 0;
            font-size: 13px;
            opacity: 0.85;
        }

        .btn-top-logout {
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

        .btn-top-logout:hover {
            background-color: #dc2626;
            border-color: #dc2626;
        }

        .layout-wrapper {
            max-width: 1280px;
            margin: 32px auto;
            padding: 0 24px;
        }

        /* --- Action Cards Grid --- */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 36px;
        }

        .card {
            background: var(--card-bg);
            padding: 24px;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
        }

        .card-header-icon {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .card-header-icon svg {
            color: var(--primary-color);
        }

        .card h3 {
            color: var(--text-main);
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }

        .card p {
            margin: 8px 0 20px 0;
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.5;
            flex-grow: 1;
        }

        .btn-card-action {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 16px;
            cursor: pointer;
            border-radius: 6px;
            width: 100%;
            font-weight: 600;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.2s ease;
        }

        .btn-card-action:hover {
            background-color: var(--primary-hover);
        }

        /* --- Section Title & Filters --- */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .section-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--text-main);
        }

        .filter-section {
            background: var(--card-bg);
            padding: 16px 20px;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            margin-bottom: 24px;
        }

        .filter-form {
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
        }

        .filter-form select {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            font-size: 13px;
            color: var(--text-main);
            background-color: #fff;
            min-width: 180px;
            outline: none;
        }

        .filter-form select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.1);
        }

        .btn-filter-apply {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-filter-apply:hover {
            background-color: var(--primary-hover);
        }

        .btn-reset {
            background-color: #f1f5f9;
            color: var(--text-muted);
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid var(--border-color);
            transition: all 0.2s;
        }

        .btn-reset:hover {
            background-color: #e2e8f0;
            color: var(--text-main);
        }

        /* --- Data Table Section --- */
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
            font-size: 14px;
        }

        th {
            background-color: #f8fafc;
            color: var(--text-muted);
            padding: 14px 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover {
            background-color: #f8fafc;
        }

        .font-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 13px;
            color: var(--text-muted);
        }

        .table-action-link {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
            font-size: 13px;
        }

        .table-action-link:hover {
            text-decoration: underline;
        }

        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
            font-style: italic;
        }

        /* --- Status Badges --- */
        .badge {
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.03em;
            display: inline-block;
        }

        .badge-submitted { background-color: #fef3c7; color: #92400e; }
        .badge-assigned  { background-color: #e0f2fe; color: #075985; }
        .badge-progress  { background-color: #fef9c3; color: #854d0e; }
        .badge-completed { background-color: #dcfce7; color: #166534; }
        .badge-escalated { background-color: #fee2e2; color: #991b1b; }
        .badge-rejected  { background-color: #f1f5f9; color: #475569; }
        .badge-default   { background-color: #f1f5f9; color: #475569; }

        .load-more-container {
            text-align: center;
            margin: 28px 0 60px 0;
        }

        .btn-load-more {
            background-color: #ffffff;
            color: var(--text-main);
            border: 1px solid var(--border-color);
            padding: 10px 24px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 6px;
            box-shadow: var(--shadow);
            transition: all 0.2s ease;
        }

        .btn-load-more:hover {
            background-color: #f8fafc;
            border-color: #cbd5e1;
        }

        /* --- Side Panel Drawer --- */
        .side-panel {
            position: fixed;
            top: 0;
            right: -450px;
            width: 420px;
            height: 100%;
            background-color: #ffffff;
            box-shadow: -10px 0 25px rgba(0, 0, 0, 0.1);
            transition: right 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 9999;
            padding: 32px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }

        .side-panel.open { right: 0; }

        .side-panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 16px;
            margin-bottom: 24px;
        }

        .side-panel-header h2 {
            margin: 0;
            color: var(--text-main);
            font-size: 18px;
            font-weight: 600;
        }

        .close-panel-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .close-panel-btn:hover {
            background-color: #f1f5f9;
            color: var(--text-main);
        }

        .panel-form-group {
            margin-bottom: 20px;
        }

        .panel-form-group label {
            display: block;
            font-weight: 500;
            font-size: 13px;
            margin-bottom: 6px;
            color: var(--text-main);
        }

        .panel-form-group input[type="text"],
        .panel-form-group textarea,
        .panel-form-group input[type="datetime-local"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            box-sizing: border-box;
            font-family: inherit;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .panel-form-group input:focus,
        .panel-form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.1);
        }

        .panel-form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .panel-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(2px);
            display: none;
            z-index: 9998;
        }
    </style>
</head>
<body>

<header class="header">
    <div class="header-title">
        <h1>Administrative Control Console</h1>
        <p>System Overview & Global Management</p>
    </div>
    <a href="../auth/logout.php" class="btn-top-logout">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Sign Out
    </a>
</header>

<main class="layout-wrapper">

    <!-- Action Management Grid -->
    <div class="cards-grid">
        <div class="card">
            <div>
                <div class="card-header-icon">
                    <h3>User Accounts</h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <p>Manage citizen accounts, Grama Niladhari officer profiles, and administrative credentials.</p>
            </div>
            <button class="btn-card-action" onclick="location.href='manage_users.php'">Manage Accounts</button>
        </div>

        <div class="card">
            <div>
                <div class="card-header-icon">
                    <h3>Analytics & Reports</h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                </div>
                <p>Generate cross-district metric analyses and review complaint resolution timelines.</p>
            </div>
            <button class="btn-card-action" onclick="location.href='generate_report.php'">View Analytics</button>
        </div>

        <div class="card">
            <div>
                <div class="card-header-icon">
                    <h3>Volunteer Campaigns</h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                <p>Coordinate active volunteer initiatives, manage registration rosters, and monitor progress.</p>
            </div>
            <button class="btn-card-action" onclick="location.href='event_management.php'">Manage Events</button>
        </div>

        <div class="card">
            <div>
                <div class="card-header-icon">
                    <h3>System Broadcast</h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3zm-8.27 4a2 2 0 0 1-3.46 0"/></svg>
                </div>
                <p>Dispatch platform-wide announcements and targeted administrative alerts to registered users.</p>
            </div>
            <button class="btn-card-action" onclick="toggleMessagePanel(true)">New Broadcast</button>
        </div>
    </div>

    <!-- Section Header & Filters -->
    <div class="section-header">
        <h2>Complaints Oversight</h2>
    </div>

    <div class="filter-section">
        <form method="GET" action="" class="filter-form">
            <div class="filter-group">
                <label for="area">District:</label>
                <select name="area" id="area">
                    <option value="">All Districts</option>
                    <?php foreach($districts_list as $district): ?>
                        <option value="<?php echo htmlspecialchars($district); ?>" <?php if($area_filter == $district) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($district); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="status_id">Status:</label>
                <select name="status_id" id="status_id">
                    <option value="">All Statuses</option>
                    <?php foreach($status_map as $id => $name): ?>
                        <option value="<?php echo $id; ?>" <?php if($status_filter == $id) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn-filter-apply">Apply Filters</button>
            <?php if(!empty($area_filter) || !empty($status_filter)): ?>
                <a href="admin_dash.php" class="btn-reset">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Data Table -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Reference ID</th>
                    <th>Title / Subject</th>
                    <th>District Location</th>
                    <th>Current Status</th>
                    <th>Date Lodged</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="complaints-tbody">
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php while($complaint = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td class="font-mono">#<?php echo htmlspecialchars($complaint['complaint_id']); ?></td>
                            <td><strong><?php echo htmlspecialchars($complaint['title']); ?></strong></td>
                            <td><?php echo htmlspecialchars(!empty($complaint['district']) ? $complaint['district'] : 'Unassigned'); ?></td>
                            <td><?php echo renderStatusBadge($complaint['status_id'], $status_map); ?></td>
                            <td><?php echo date('M d, Y', strtotime($complaint['created_at'])); ?></td>
                            <td>
                                <a href="view_complaints.php?id=<?php echo $complaint['complaint_id']; ?>" class="table-action-link">View Details</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr id="no-data-row">
                        <td colspan="6" class="no-data">No records found matching the designated filter criteria.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="load-more-container">
        <button id="load-more-btn" class="btn-load-more" data-offset="5" data-total="<?php echo $total_records; ?>" style="<?php echo ($total_records <= 5) ? 'display:none;' : ''; ?>">
            Load More Records
        </button>
    </div>

</main>

<!-- Broadcast Overlay & Slide Drawer Panel -->
<div id="panelOverlay" class="panel-overlay" onclick="toggleMessagePanel(false)"></div>

<aside id="messageSidePanel" class="side-panel">
    <div class="side-panel-header">
        <h2>System Broadcast</h2>
        <button class="close-panel-btn" onclick="toggleMessagePanel(false)" aria-label="Close">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="12"/></svg>
        </button>
    </div>
    
    <form id="broadcastForm">
        <input type="hidden" name="action" value="send_broadcast">
        
        <div class="panel-form-group">
            <label for="msg_title">Alert Subject</label>
            <input type="text" id="msg_title" name="title" placeholder="e.g., Maintenance Schedule Notice" required>
        </div>
        
        <div class="panel-form-group">
            <label for="msg_body">Message Details</label>
            <textarea id="msg_body" name="message" placeholder="Provide broadcast text..." required></textarea>
        </div>
        
        <div class="panel-form-group">
            <label for="msg_expiry">Expiration Timestamp (Optional)</label>
            <input type="datetime-local" id="msg_expiry" name="expires_at">
        </div>
        
        <button type="submit" id="sendBroadcastBtn" class="btn-card-action" style="margin-top: 10px;">
            Dispatch Announcement
        </button>
    </form>
</aside>

<script>
// Paginated dynamic loading
document.getElementById('load-more-btn').addEventListener('click', function() {
    var btn = this;
    var currentOffset = parseInt(btn.getAttribute('data-offset'));
    var totalRecords = parseInt(btn.getAttribute('data-total'));
    
    var urlParams = new URLSearchParams(window.location.search);
    urlParams.set('ajax_load', '1');
    urlParams.set('offset', currentOffset);

    btn.innerText = "Loading Records...";
    btn.disabled = true;

    fetch('admin_dash.php?' + urlParams.toString())
        .then(response => response.text())
        .then(htmlRows => {
            if (htmlRows.trim().length > 0) {
                document.getElementById('complaints-tbody').insertAdjacentHTML('beforeend', htmlRows);
                
                var newOffset = currentOffset + 5;
                btn.setAttribute('data-offset', newOffset);
                
                if (newOffset >= totalRecords) {
                    btn.style.display = 'none';
                } else {
                    btn.innerText = "Load More Records";
                    btn.disabled = false;
                }
            } else {
                btn.style.display = 'none';
            }
        })
        .catch(err => {
            console.error("Data load failure:", err);
            btn.innerText = "Load More Records";
            btn.disabled = false;
        });
});

// Panel visibility control
function toggleMessagePanel(open) {
    var panel = document.getElementById('messageSidePanel');
    var overlay = document.getElementById('panelOverlay');
    if (open) {
        panel.classList.add('open');
        overlay.style.display = 'block';
    } else {
        panel.classList.remove('open');
        overlay.style.display = 'none';
        document.getElementById('broadcastForm').reset();
    }
}

// AJAX Broadcast Submission
document.getElementById('broadcastForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    var btn = document.getElementById('sendBroadcastBtn');
    var formData = new FormData(this);
    
    btn.innerText = "Processing...";
    btn.disabled = true;

    fetch('admin_dash.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error("HTTP Status " + response.status);
        }
        return response.text();
    })
    .then(text => {
        try {
            var data = JSON.parse(text);
            alert(data.message);
            if (data.status === 'success') {
                toggleMessagePanel(false); 
            }
        } catch (jsonError) {
            console.error("Server output error:", text);
            alert("Unexpected server error encountered.");
        }
    })
    .catch(err => {
        console.error("Communication error:", err);
        alert("Transmission Error: " + err.message);
    })
    .finally(() => {
        btn.innerText = "Dispatch Announcement";
        btn.disabled = false;
    });
});
</script>

</body>
</html>