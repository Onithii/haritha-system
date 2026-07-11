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

// Geolocation function using Nominatim OpenStreetMap API
function getDistrictFromCoordinates($lat, $lng) {
    if (empty($lat) || empty($lng)) {
        return "Unknown";
    }
    
    $opts = [
        'http' => [
            'method' => "GET",
            'header' => "User-Agent: HarithaSystem/1.0 (admin-portal@haritha.lk)\r\n"
        ]
    ];
    
    $context = stream_context_create($opts);
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=" . $lat . "&lon=" . $lng;
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['address']['district'])) {
            return $data['address']['district']; // e.g. "Galle"
        } elseif (isset($data['address']['state_district'])) {
            return $data['address']['state_district'];
        } elseif (isset($data['address']['city'])) {
            return $data['address']['city'];
        }
    }
    return "Unknown Region";
}

// 1. Target city list for filter matching
$cities_list = ["Colombo", "Galle", "Matara", "Kandy", "Jaffna", "Negombo", "Anuradhapura", "Kurunegala"];

// 2. Handle Filters
$area_filter = isset($_GET['area']) ? mysqli_real_escape_string($conn, $_GET['area']) : '';
$status_filter = isset($_GET['status_id']) ? mysqli_real_escape_string($conn, $_GET['status_id']) : '';

// 3. Build Query dynamically
$query = "SELECT * FROM complaints WHERE 1=1";

if (!empty($area_filter)) {
    // Looks for textual mentions inside location_description or coordinates backup matches
    $query .= " AND (location_description LIKE '%$area_filter%')";
}
if (!empty($status_filter)) {
    $query .= " AND status_id = '$status_filter'";
}

$query .= " ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);

// 4. Fetch distinct status variants for filtering control
$statuses_query = mysqli_query($conn, "SELECT DISTINCT status_id FROM complaints WHERE status_id IS NOT NULL");
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
        .card { background: white; padding: 25px; width: 20%; min-width: 220px; border-radius: 10px; text-align: center; box-shadow: 0px 2px 8px gray; }
        .card h3 { color: #1b5e20; }
        button, .btn-submit { background-color: #1b5e20; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px; width: 100%; }
        button:hover, .btn-submit:hover { background-color: #003300; }
        
        .section-title { width: 85%; margin: 20px auto 10px auto; color: #1b5e20; border-bottom: 2px solid #1b5e20; padding-bottom: 5px; }
        .filter-section { width: 85%; margin: 0 auto 20px auto; background: white; padding: 15px; border-radius: 8px; box-shadow: 0px 2px 5px rgba(0,0,0,0.1); }
        .filter-form { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .filter-form select { padding: 8px; border-radius: 5px; border: 1px solid #ccc; min-width: 150px; }
        .filter-form .btn-submit { width: auto; padding: 8px 20px; }
        .filter-form .btn-reset { background-color: #757575; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; font-size: 14px; }
        .filter-form .btn-reset:hover { background-color: #424242; }
        
        .table-container { width: 85%; margin: 0 auto 50px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0px 2px 8px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #ddd; }
        th { background-color: #1b5e20; color: white; }
        tr:hover { background-color: #f9f9f9; }
        .no-data { text-align: center; padding: 30px; color: #757575; font-style: italic; }
        
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .status-1 { background-color: #fff9c4; color: #fbc02d; } 
        .status-2 { background-color: #ffcdd2; color: #c62828; } 
        .status-3 { background-color: #c8e6c9; color: #25602a; } 
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
    <div class="card">
        <h3>Settings</h3>
        <p>Configure regional boundaries, roles, and system parameters.</p>
        <button onclick="location.href='settings.php'">System Settings</button>
    </div>
</div>

<h2 class="section-title">Complaints Overview</h2>

<div class="filter-section">
    <form method="GET" action="" class="filter-form">
        <label for="area"><strong>Filter by Region:</strong></label>
        <select name="area" id="area">
            <option value="">-- All Regions --</option>
            <?php foreach($cities_list as $city): ?>
                <option value="<?php echo htmlspecialchars($city); ?>" <?php if($area_filter == $city) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($city); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="status_id"><strong>Status ID:</strong></label>
        <select name="status_id" id="status_id">
            <option value="">-- All Statuses --</option>
            <?php while($row = mysqli_fetch_assoc($statuses_query)): ?>
                <option value="<?php echo htmlspecialchars($row['status_id']); ?>" <?php if($status_filter == $row['status_id']) echo 'selected'; ?>>
                    Status Code: <?php echo htmlspecialchars($row['status_id']); ?>
                </option>
            <?php endwhile; ?>
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
                <th>Resolved District</th>
                <th>Location Description</th>
                <th>Status Code</th>
                <th>Date Submitted</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while($complaint = mysqli_fetch_assoc($result)): 
                    // Calculate the live district output based on DB coordinates
                    $detected_district = getDistrictFromCoordinates($complaint['latitude'], $complaint['longitude']);
                    
                    // If a filter is set, skip displaying rows that don't match the API output
                    if (!empty($area_filter) && strtolower($detected_district) !== strtolower($area_filter)) {
                        continue;
                    }
                ?>
                    <tr>
                        <td>#<?php echo htmlspecialchars($complaint['complaint_id']); ?></td>
                        <td><?php echo htmlspecialchars($complaint['title']); ?></td>
                        <td><strong><?php echo htmlspecialchars($detected_district); ?></strong></td>
                        <td><?php echo htmlspecialchars($complaint['location_description']); ?></td>
                        <td>
                            <span class="badge status-<?php echo $complaint['status_id']; ?>">
                                ID: <?php echo htmlspecialchars($complaint['status_id']); ?>
                            </span>
                        </td>
                        <td><?php echo date('Y-m-d', strtotime($complaint['created_at'])); ?></td>
                        <td>
                            <a href="view_complaint_details.php?id=<?php echo $complaint['complaint_id']; ?>" style="color: #1b5e20; font-weight: bold; text-decoration: none;">View</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="no-data">No complaints found matching your criteria.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>