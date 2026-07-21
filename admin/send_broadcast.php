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

// Sri Lankan Districts list for clean filter matching
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

// 2. Extract and sanitize filters
$area_filter = isset($_GET['area']) ? trim($_GET['area']) : '';
$status_filter = isset($_GET['status_id']) ? (int)$_GET['status_id'] : 0;

// Helper function to safely render badges inside structural loops
function renderStatusBadge($status_id, $status_map) {
    $name = isset($status_map[$status_id]) ? $status_map[$status_id] : "UNKNOWN";
    $class_id = ($status_id <= 3) ? $status_id : (($status_id == 4 || $status_id == 5) ? 3 : 2);
    return '<span class="badge status-' . $class_id . '">' . htmlspecialchars($name) . '</span>';
}

// Helper to construct secure parameterized queries
function buildComplaintQuery($conn, $area_filter, $status_filter, $limit = null, $offset = null) {
    $sql = "SELECT * FROM complaints WHERE 1=1";
    $types = "";
    $params = [];

    if (!empty($area_filter)) {
        $sql .= " AND district = ?";
        $types .= "s";
        $params[] = $area_filter;
    }
    if ($status_filter > 0) {
        $sql .= " AND status_id = ?";
        $types .= "i";
        $params[] = $status_filter;
    }

    $sql .= " ORDER BY created_at DESC";

    if ($limit !== null && $offset !== null) {
        $sql .= " LIMIT ? OFFSET ?";
        $types .= "ii";
        $params[] = (int)$limit;
        $params[] = (int)$offset;
    }

    $stmt = mysqli_prepare($conn, $sql);
    if (!empty($types)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

// 3. Handle AJAX "Load More" Request
if (isset($_GET['ajax_load'])) {
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $result = buildComplaintQuery($conn, $area_filter, $status_filter, 5, $offset);

    if (mysqli_num_rows($result) > 0) {
        while ($complaint = mysqli_fetch_assoc($result)) {
            $district = !empty($complaint['district']) ? htmlspecialchars($complaint['district']) : 'Not Assigned';
            $badge = renderStatusBadge($complaint['status_id'], $status_map);
            
            echo '<tr>
                    <td><strong>#' . htmlspecialchars($complaint['complaint_id']) . '</strong></td>
                    <td>' . htmlspecialchars($complaint['title']) . '</td>
                    <td>' . $district . '</td>
                    <td>' . $badge . '</td>
                    <td>' . date('Y-m-d', strtotime($complaint['created_at'])) . '</td>
                    <td><a href="view_complaint_details.php?id=' . (int)$complaint['complaint_id'] . '" class="action-link">View</a></td>
                  </tr>';
        }
    }
    exit();
}

// 4. Base Initial Query (Gets first 5 rows)
$result = buildComplaintQuery($conn, $area_filter, $status_filter, 5, 0);

// 5. Total count for determining pagination limits
$count_sql = "SELECT COUNT(*) as total FROM complaints WHERE 1=1";
$count_types = "";
$count_params = [];

if (!empty($area_filter)) {
    $count_sql .= " AND district = ?";
    $count_types .= "s";
    $count_params[] = $area_filter;
}
if ($status_filter > 0) {
    $count_sql .= " AND status_id = ?";
    $count_types .= "i";
    $count_params[] = $status_filter;
}

$count_stmt = mysqli_prepare($conn, $count_sql);
if (!empty($count_types)) {
    mysqli_stmt_bind_param($count_stmt, $count_types, ...$count_params);
}
mysqli_stmt_execute($count_stmt);
$count_res = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt));
$total_records = $count_res['total'] ?? 0;
mysqli_stmt_close($count_stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Admin Dashboard - Haritha</title>
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

        .layout-container {
            max-width: 1200px;
            margin: 32px auto;
            padding: 0 24px;
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        /* --- Grid Navigation Cards --- */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .card {
            background: var(--card-bg);
            padding: 24px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
        }

        .card h3 {
            color: var(--primary-color);
            margin: 0 0 8px 0;
            font-size: 16px;
            font-weight: 600;
        }

        .card p {
            margin: 0 0 20px 0;
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.5;
            flex-grow: 1;
        }

        .btn-card {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 16px;
            cursor: pointer;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            width: 100%;
            transition: background 0.2s;
        }

        .btn-card:hover {
            background-color: var(--primary-hover);
        }

        /* --- Overview & Filters --- */
        .section-title {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--text-main);
        }

        .filter-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
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
            font-size: 13px;
            font-weight: 600;
        }

        .filter-form select {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            font-size: 13px;
            outline: none;
            background-color: #fff;
        }

        .filter-form select:focus {
            border-color: var(--primary-color);
        }

        .btn-submit {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 18px;
            cursor: pointer;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
        }

        .btn-submit:hover {
            background-color: var(--primary-hover);
        }

        .btn-reset {
            background-color: #64748b;
            color: white;
            padding: 8px 14px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
        }

        .btn-reset:hover {
            background-color: #475569;
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

        .action-link {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
        }

        .action-link:hover {
            text-decoration: underline;
        }

        /* Status Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
        }

        .status-1 { background-color: #fef9c3; color: #854d0e; } 
        .status-2 { background-color: #fee2e2; color: #991b1b; } 
        .status-3 { background-color: #dcfce7; color: #166534; } 

        .no-data {
            text-align: center;
            padding: 36px;
            color: var(--text-muted);
            font-style: italic;
        }

        .load-more-container {
            text-align: center;
            margin-top: 8px;
        }

        .btn-load-more {
            background-color: #64748b;
            color: white;
            border: none;
            padding: 10px 24px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 6px;
            transition: background 0.2s;
        }

        .btn-load-more:hover {
            background-color: #475569;
        }

        /* --- Side Panel Module --- */
        .side-panel {
            position: fixed;
            top: 0;
            right: -450px; 
            width: 400px;
            height: 100%;
            background-color: #ffffff;
            box-shadow: -5px 0 25px rgba(0,0,0,0.15);
            transition: right 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            z-index: 9999;
            padding: 28px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }

        .side-panel.open {
            right: 0;
        }

        .side-panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 12px;
            margin-bottom: 24px;
        }

        .side-panel-header h2 {
            margin: 0;
            color: var(--primary-color);
            font-size: 20px;
            font-weight: 600;
        }

        .close-panel-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }

        .close-panel-btn:hover {
            color: #dc2626;
        }

        .panel-form-group {
            margin-bottom: 16px;
        }

        .panel-form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 13px;
            color: var(--text-main);
        }

        .panel-form-group input[type="text"],
        .panel-form-group textarea,
        .panel-form-group input[type="datetime-local"] {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            box-sizing: border-box;
            font-family: inherit;
            font-size: 13px;
            outline: none;
        }

        .panel-form-group input:focus,
        .panel-form-group textarea:focus {
            border-color: var(--primary-color);
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
    <h1>System Admin Dashboard</h1>
    <p>Global System Control & Monitoring Portal</p>
</header>

<main class="layout-container">
    
    <!-- Management Navigation Grid -->
    <section class="card-grid">
        <div class="card">
            <h3>User Management</h3>
            <p>Manage citizens, GN officers, and institutional administrative accounts.</p>
            <button class="btn-card" onclick="location.href='manage_users.php'">Manage Users</button>
        </div>
        <div class="card">
            <h3>System Reports</h3>
            <p>View cross-district analytics, metrics, and resolution performance timelines.</p>
            <button class="btn-card" onclick="location.href='generate_report.php'">View Analytics</button>
        </div>
        <div class="card">
            <h3>Complaints Master</h3>
            <p>Monitor, track, or reassign active complaints across all regions.</p>
            <button class="btn-card" onclick="location.href='all_complaints.php'">All Complaints</button>
        </div>
        <div class="card">
            <h3>Event Management</h3>
            <p>Track volunteer campaign rosters, participation data, and log registers.</p>
            <button class="btn-card" onclick="location.href='event_management.php'">Manage Events</button>
        </div>
        <div class="card">
            <h3>Messages</h3>
            <p>Dispatch broadcast alerts and manage internal emergency network transmissions.</p>
            <button class="btn-card" onclick="toggleMessagePanel(true)">View Messages</button>
        </div>
        <div class="card">
            <h3>Settings</h3>
            <p>Configure regional boundaries, role permissions, and system parameters.</p>
            <button class="btn-card" onclick="location.href='settings.php'">System Settings</button>
        </div>
    </section>

    <!-- Controls & Data Overview -->
    <h2 class="section-title">Complaints Overview</h2>

    <section class="filter-card">
        <form method="GET" action="" class="filter-form">
            <div class="filter-group">
                <label for="area">District:</label>
                <select name="area" id="area">
                    <option value="">-- All Districts --</option>
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
                    <option value="">-- All Statuses --</option>
                    <?php foreach($status_map as $id => $name): ?>
                        <option value="<?php echo $id; ?>" <?php if($status_filter == $id) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn-submit">Apply Filters</button>
            <?php if(!empty($area_filter) || $status_filter > 0): ?>
                <a href="admin_dash.php" class="btn-reset">Clear</a>
            <?php endif; ?>
        </form>
    </section>

    <section class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Complaint ID</th>
                    <th>Title / Subject</th>
                    <th>District</th>
                    <th>Status</th>
                    <th>Date Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="complaints-tbody">
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php while($complaint = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><strong>#<?php echo htmlspecialchars($complaint['complaint_id']); ?></strong></td>
                            <td><?php echo htmlspecialchars($complaint['title']); ?></td>
                            <td>
                                <?php echo htmlspecialchars(!empty($complaint['district']) ? $complaint['district'] : 'Not Assigned'); ?>
                            </td>
                            <td>
                                <?php echo renderStatusBadge($complaint['status_id'], $status_map); ?>
                            </td>
                            <td><?php echo date('Y-m-d', strtotime($complaint['created_at'])); ?></td>
                            <td>
                                <a href="view_complaint_details.php?id=<?php echo (int)$complaint['complaint_id']; ?>" class="action-link">View</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr id="no-data-row">
                        <td colspan="6" class="no-data">No complaints found matching your current filters.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <div class="load-more-container">
        <button id="load-more-btn" class="btn-load-more" data-offset="5" data-total="<?php echo $total_records; ?>" style="<?php echo ($total_records <= 5) ? 'display:none;' : ''; ?>">
            Load More Complaints
        </button>
    </div>

</main>

<!-- Overlay element for Drawer Context -->
<div id="panelOverlay" class="panel-overlay" onclick="toggleMessagePanel(false)"></div>

<!-- Broadcast Drawer Panel Module -->
<aside id="messageSidePanel" class="side-panel">
    <div class="side-panel-header">
        <h2>Broadcast Message</h2>
        <button class="close-panel-btn" onclick="toggleMessagePanel(false)">&times;</button>
    </div>
    
    <form id="broadcastForm">
        <div class="panel-form-group">
            <label for="msg_title">Alert Title / Subject *</label>
            <input type="text" id="msg_title" name="title" placeholder="e.g., Scheduled Maintenance" required>
        </div>
        
        <div class="panel-form-group">
            <label for="msg_body">Message Body *</label>
            <textarea id="msg_body" name="message" placeholder="Type transmission copy here..." required></textarea>
        </div>
        
        <div class="panel-form-group">
            <label for="msg_expiry">Expiration Timestamp (Optional)</label>
            <input type="datetime-local" id="msg_expiry" name="expires_at">
        </div>
        
        <button type="submit" id="sendBroadcastBtn" class="btn-card" style="margin-top: 12px;">Send Broadcast Notification</button>
    </form>
</aside>

<script>
// Logic processing dynamic Infinite Scroll
document.getElementById('load-more-btn').addEventListener('click', function() {
    var btn = this;
    var currentOffset = parseInt(btn.getAttribute('data-offset'));
    var totalRecords = parseInt(btn.getAttribute('data-total'));
    
    var urlParams = new URLSearchParams(window.location.search);
    urlParams.set('ajax_load', '1');
    urlParams.set('offset', currentOffset);

    btn.innerText = "Loading...";
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
                    btn.innerText = "Load More Complaints";
                    btn.disabled = false;
                }
            } else {
                btn.style.display = 'none';
            }
        })
        .catch(err => {
            console.error("Failed to load records:", err);
            btn.innerText = "Load More Complaints";
            btn.disabled = false;
        });
});

// Function controlling Slide Drawer State
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

// Dynamic AJAX Submission Interceptor logic for broadcast form
document.getElementById('broadcastForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    var btn = document.getElementById('sendBroadcastBtn');
    var formData = new FormData(this);
    
    btn.innerText = "Processing Broadcast...";
    btn.disabled = true;

    fetch('send_broadcast.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.status === 'success') {
            toggleMessagePanel(false); 
        } else {
            btn.innerText = "Send Broadcast Notification";
            btn.disabled = false;
        }
    })
    .catch(err => {
        console.error("Transmission Failure:", err);
        alert("An error occurred while deploying your broadcast message.");
        btn.innerText = "Send Broadcast Notification";
        btn.disabled = false;
    });
});
</script>

</body>
</html>