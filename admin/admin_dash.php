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

// Helper function to safely render badges inside structural loops
function renderStatusBadge($status_id, $status_map) {
    $name = isset($status_map[$status_id]) ? $status_map[$status_id] : "UNKNOWN";
    // Normalize values above 3 to fall back cleanly onto style colors if needed
    $class_id = ($status_id <= 3) ? $status_id : (($status_id == 4 || $status_id == 5) ? 3 : 2);
    return '<span class="badge status-' . $class_id . '">' . htmlspecialchars($name) . '</span>';
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
            $district = !empty($complaint['district']) ? htmlspecialchars($complaint['district']) : 'Not Assigned';
            $badge = renderStatusBadge($complaint['status_id'], $status_map);
            
            echo '<tr>
                    <td>#' . htmlspecialchars($complaint['complaint_id']) . '</td>
                    <td>' . htmlspecialchars($complaint['title']) . '</td>
                    <td><strong>' . $district . '</strong></td>
                    <td>' . $badge . '</td>
                    <td>' . date('Y-m-d', strtotime($complaint['created_at'])) . '</td>
                    <td><a href="view_complaint_details.php?id=' . $complaint['complaint_id'] . '" style="color: #1b5e20; font-weight: bold; text-decoration: none;">View</a></td>
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
    <title>Admin Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; }
        .header { background-color: #1b5e20; color: white; padding: 20px; text-align: center; }
        .container { width: 85%; margin: 30px auto; display: flex; flex-wrap: wrap; gap: 20px; justify-content: center; }
        .card { background: white; padding: 25px; width: 18%; min-width: 220px; border-radius: 10px; text-align: center; box-shadow: 0px 2px 8px gray; display: flex; flex-direction: column; justify-content: space-between; }
        .card h3 { color: #1b5e20; margin-top: 0; }
        .card p { flex-grow: 1; margin-bottom: 20px; color: #555; font-size: 14px; }
        button, .btn-submit { background-color: #1b5e20; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px; width: 100%; font-weight: bold; }
        button:hover, .btn-submit:hover { background-color: #003300; }
        
        .section-title { width: 85%; margin: 20px auto 10px auto; color: #1b5e20; border-bottom: 2px solid #1b5e20; padding-bottom: 5px; }
        .filter-section { width: 85%; margin: 0 auto 20px auto; background: white; padding: 15px; border-radius: 8px; box-shadow: 0px 2px 5px rgba(0,0,0,0.1); }
        .filter-form { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .filter-form select { padding: 8px; border-radius: 5px; border: 1px solid #ccc; min-width: 170px; }
        .filter-form .btn-submit { width: auto; padding: 8px 20px; }
        .filter-form .btn-reset { background-color: #757575; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; font-size: 14px; }
        .filter-form .btn-reset:hover { background-color: #424242; }
        
        .table-container { width: 85%; margin: 0 auto 20px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0px 2px 8px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #ddd; }
        th { background-color: #1b5e20; color: white; }
        tr:hover { background-color: #f9f9f9; }
        .no-data { text-align: center; padding: 30px; color: #757575; font-style: italic; }
        
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .status-1 { background-color: #fff9c4; color: #fbc02d; } 
        .status-2 { background-color: #ffcdd2; color: #c62828; } 
        .status-3 { background-color: #c8e6c9; color: #25602a; } 

        .load-more-container { text-align: center; margin: 20px 0 50px 0; }
        .btn-load-more { background-color: #757575; color: white; border: none; padding: 10px 30px; font-size: 14px; font-weight: bold; cursor: pointer; border-radius: 5px; transition: background 0.2s; width: auto; }
        .btn-load-more:hover { background-color: #424242; }
    </style>
</head>
<body>

<div class="header">
    <h1>System Admin Dashboard</h1>
    <p>Global System Control & Monitoring Portal</p>
</div>

<div class="container">
    <div class="card">
        <h3>User Management</h3>
        <p>Manage citizens, GN officers, and institutional accounts.</p>
        <button onclick="location.href='manage_users.php'">Manage Users</button>
    </div>
    <div class="card">
        <h3>System Reports</h3>
        <p>View cross-district analytics and resolution timelines.</p>
        <button onclick="location.href='view_reports.php'">View Analytics</button>
    </div>
    <div class="card">
        <h3>Complaints Master</h3>
        <p>Monitor, track, or reassign any complaint in the system.</p>
        <button onclick="location.href='all_complaints.php'">All Complaints</button>
    </div>
    <!-- ADDED: Event Management Card Module -->
    <div class="card">
        <h3>Event Management</h3>
        <p>Track active volunteer campaigns, participation rosters, and log data.</p>
        <button onclick="location.href='event_management.php'">Manage Events</button>
    </div>
    <div class="card">
        <h3>Settings</h3>
        <p>Configure regional boundaries, roles, and system parameters.</p>
        <button onclick="location.href='settings.php'">System Settings</button>
    </div>
</div>

<h2 class="section-title">Complaints Overview</h2>

<div class="filter-section">
    <form method="GET" action="" class="filter-form">
        <label for="area"><strong>Filter by District:</strong></label>
        <select name="area" id="area">
            <option value="">-- All Districts --</option>
            <?php foreach($districts_list as $district): ?>
                <option value="<?php echo htmlspecialchars($district); ?>" <?php if($area_filter == $district) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($district); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="status_id"><strong>Filter by Status:</strong></label>
        <select name="status_id" id="status_id">
            <option value="">-- All Statuses --</option>
            <?php foreach($status_map as $id => $name): ?>
                <option value="<?php echo $id; ?>" <?php if($status_filter == $id) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($name); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn-submit">Apply Filters</button>
        <?php if(!empty($area_filter) || !empty($status_filter)): ?>
            <a href="admin_dash.php" class="btn-reset">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="table-container">
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
                        <td>#<?php echo htmlspecialchars($complaint['complaint_id']); ?></td>
                        <td><?php echo htmlspecialchars($complaint['title']); ?></td>
                        <td>
                            <strong>
                                <?php echo htmlspecialchars(!empty($complaint['district']) ? $complaint['district'] : 'Not Assigned'); ?>
                            </strong>
                        </td>
                        <td>
                            <?php 
                            $c_status = $complaint['status_id'];
                            $class_id = ($c_status <= 3) ? $c_status : (($c_status == 4 || $c_status == 5) ? 3 : 2);
                            ?>
                            <span class="badge status-<?php echo $class_id; ?>">
                                <?php echo htmlspecialchars(isset($status_map[$c_status]) ? $status_map[$c_status] : 'UNKNOWN'); ?>
                            </span>
                        </td>
                        <td><?php echo date('Y-m-d', strtotime($complaint['created_at'])); ?></td>
                        <td>
                            <a href="view_complaint_details.php?id=<?php echo $complaint['complaint_id']; ?>" style="color: #1b5e20; font-weight: bold; text-decoration: none;">View</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr id="no-data-row">
                    <td colspan="6" class="no-data">No complaints found matching your criteria.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="load-more-container">
    <button id="load-more-btn" class="btn-load-more" data-offset="5" data-total="<?php echo $total_records; ?>" style="<?php echo ($total_records <= 5) ? 'display:none;' : ''; ?>">
        Load More Complaints
    </button>
</div>

<script>
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
</script>

</body>
</html>