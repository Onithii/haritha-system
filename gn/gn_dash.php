<?php
session_start();
include("../config/db.php");

// 1. Secure Access Check: Ensure user is logged in and is a Grama Niladhari (Role 2)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 2 || empty($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

// Fetch officer metadata natively for personalization
$officer_id = $_SESSION['user_id'];
$officer_name = "Officer";
$gn_division = "Your Division";

$query = "SELECT f_name, l_name, gn_division FROM users WHERE user_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $officer_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $officer_name = $row['f_name'] . " " . $row['l_name'];
        $gn_division  = $row['gn_division'];
    }
    mysqli_stmt_close($stmt);
}

// 2. Fetch Assigned Complaints matching your exact schema column: assigned_to_id
$complaints = [];
$complaint_query = "SELECT c.complaint_id, c.title, c.description, c.location_description, 
                           c.latitude, c.longitude, c.created_at,
                           cc.category_name,
                           cs.status_name
                    FROM complaints c
                    LEFT JOIN complaint_categories cc ON c.category_id = cc.category_id
                    LEFT JOIN complaint_status cs ON c.status_id = cs.status_id
                    WHERE c.assigned_to_id = ? 
                    ORDER BY c.created_at DESC";

$comp_stmt = mysqli_prepare($conn, $complaint_query);
if ($comp_stmt) {
    mysqli_stmt_bind_param($comp_stmt, "i", $officer_id);
    mysqli_stmt_execute($comp_stmt);
    $comp_result = mysqli_stmt_get_result($comp_stmt);
    while ($row = mysqli_fetch_assoc($comp_result)) {
        $complaints[] = $row;
    }
    mysqli_stmt_close($comp_stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Grama Niladhari Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; padding-bottom: 50px; }
        .header { background-color: #e65100; color: white; padding: 20px; text-align: center; position: relative; }
        .container { width: 85%; margin: 30px auto; display: flex; gap: 20px; }
        .card { background: white; padding: 25px; width: 33.33%; border-radius: 10px; text-align: center; box-shadow: 0px 2px 8px gray; }
        .card h3 { color: #e65100; margin-top: 0; }
        button { background-color: #e65100; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px; width: 100%; font-weight: bold; }
        button:hover { background-color: #b33600; }
        
        .logout-btn { 
            position: absolute; top: 20px; right: 20px; 
            background-color: #d32f2f; color: white; 
            padding: 8px 15px; border-radius: 5px; 
            text-decoration: none; font-size: 14px; font-weight: bold; 
        }
        .logout-btn:hover { background-color: #9a0007; }
        .welcome-text { margin-top: 5px; font-style: italic; color: #ffccbc; }

        /* Complaints Section Styling */
        .table-section { width: 85%; margin: 20px auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0px 2px 8px gray; }
        .table-section h2 { color: #e65100; margin-top: 0; border-bottom: 2px solid #ffccbc; padding-bottom: 10px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; text-align: left; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #ddd; }
        th { background-color: #ffe0b2; color: #e65100; font-weight: bold; }
        tr:hover { background-color: #fbe9e7; }
        
        .no-data { text-align: center; color: #757575; padding: 30px; font-style: italic; }
        
        /* Category Badges */
        .cat-badge { background-color: #eceff1; color: #455a64; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }

        /* Dynamic Status Badges */
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .status-pending { background-color: #ffe0b2; color: #e65100; }
        .status-progress { background-color: #b3e5fc; color: #0288d1; }
        .status-resolved { background-color: #c8e6c9; color: #388e3c; }
        .status-fallback { background-color: #e0e0e0; color: #616161; }

        .action-link { color: #e65100; text-decoration: none; font-weight: bold; }
        .action-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="header">
    <a href="../auth/logout.php" class="logout-btn">Logout</a>
    <h1>Grama Niladhari Dashboard</h1>
    <p class="welcome-text">Welcome, <?php echo htmlspecialchars($officer_name); ?> | Division: <?php echo htmlspecialchars($gn_division); ?></p>
</div>

<div class="container">
    <div class="card">
        <h3>Pending Verifications</h3>
        <p>Verify citizen profiles, addresses, and local residency details.</p>
        <button onclick="location.href='verify_citizens.php'">Verify Records</button>
    </div>
    
    <div class="card">
        <h3>Active Territory Map</h3>
        <p>View complete geolocated environmental maps inside your boundary constraints.</p>
        <button onclick="location.href='gn_map.php'">Open Map View</button>
    </div>
    
    <div class="card">
        <h3>Submit Field Report</h3>
        <p>Log physical environment assessments directly to the DS office.</p>
        <button onclick="location.href='submit_report.php'">Log Field Action</button>
    </div>
</div>

<!-- Dynamic Inlined Complaints Table Section -->
<div class="table-section">
    <h2>Assigned Environmental Complaints</h2>
    <?php if (empty($complaints)): ?>
        <div class="no-data">No environmental complaints currently routed to your division.</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Category</th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Location Details</th>
                    <th>Status</th>
                    <th>Date Reported</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($complaints as $comp): ?>
                    <?php 
                        $raw_status = isset($comp['status_name']) ? strtoupper(trim($comp['status_name'])) : 'PENDING';
                        
                        if ($raw_status === 'PENDING' || $raw_status === 'NEW') {
                            $status_class = 'status-pending';
                        } elseif ($raw_status === 'IN PROGRESS' || $raw_status === 'INVESTIGATING') {
                            $status_class = 'status-progress';
                        } elseif ($raw_status === 'RESOLVED' || $raw_status === 'CLOSED') {
                            $status_class = 'status-resolved';
                        } else {
                            $status_class = 'status-fallback';
                        }
                    ?>
                    <tr>
                        <td>#<?php echo $comp['complaint_id']; ?></td>
                        <td>
                            <span class="cat-badge">
                                <?php echo htmlspecialchars($comp['category_name'] ?? 'General'); ?>
                            </span>
                        </td>
                        <td><b><?php echo htmlspecialchars($comp['title']); ?></b></td>
                        <td><?php echo htmlspecialchars(substr($comp['description'], 0, 60)) . (strlen($comp['description']) > 60 ? '...' : ''); ?></td>
                        <td><?php echo htmlspecialchars($comp['location_description'] ?: 'Coordinates provided'); ?></td>
                        <td>
                            <span class="status-badge <?php echo $status_class; ?>">
                                <?php echo htmlspecialchars($raw_status); ?>
                            </span>
                        </td>
                        <td><?php echo date('Y-m-d h:i A', strtotime($comp['created_at'])); ?></td>
                        <td>
                            <a href="view_complaint.php?id=<?php echo $comp['complaint_id']; ?>" class="action-link">Investigate</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>