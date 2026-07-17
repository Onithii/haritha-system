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

// Sri Lankan Districts list for filter matching
$districts_list = [
    "Colombo", "Gampaha", "Kalutara", "Kandy", "Matale", "Nuwara Eliya", 
    "Galle", "Matara", "Hambantota", "Jaffna", "Kilinochchi", "Mannar", 
    "Vavuniya", "Mullaitivu", "Batticaloa", "Ampara", "Trincomalee", 
    "Kurunegala", "Puttalam", "Anuradhapura", "Polonnaruwa", "Badulla", 
    "Moneragala", "Ratnapura", "Kegalle"
];

// Handle Event Deletion
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    
    // Begin transaction to safely remove references first
    mysqli_begin_transaction($conn);
    try {
        // 1. Delete participant relations first to preserve foreign key integrity
        $del_participants = mysqli_prepare($conn, "DELETE FROM volunteer_participants WHERE event_id = ?");
        mysqli_stmt_bind_param($del_participants, "i", $delete_id);
        mysqli_stmt_execute($del_participants);
        
        // 2. Delete the actual event
        $del_event = mysqli_prepare($conn, "DELETE FROM volunteer_events WHERE event_id = ?");
        mysqli_stmt_bind_param($del_event, "i", $delete_id);
        mysqli_stmt_execute($del_event);
        
        mysqli_commit($conn);
        header("Location: participation_manage.php?msg=deleted");
        exit();
    } catch (Exception $e) {
        mysqli_rollback($conn);
        header("Location: participation_manage.php?error=failed");
        exit();
    }
}

// Get Filters and Sort options
$district_filter = isset($_GET['district']) ? mysqli_real_escape_string($conn, $_GET['district']) : '';
$sort_option = isset($_GET['sort_by']) ? mysqli_real_escape_string($conn, $_GET['sort_by']) : 'newest';

// Build the dynamic query referencing the explicit structural 'district' field
$query = "SELECT e.event_id, e.event_title, e.event_date, e.district, e.status, 
          (SELECT COUNT(*) FROM volunteer_participants vp WHERE vp.event_id = e.event_id) AS total_joined
          FROM volunteer_events e WHERE 1=1";

if (!empty($district_filter)) {
    // Exact structural check against the database district mapping column
    $query .= " AND e.district = '$district_filter'";
}

// Apply Sorting Rules
switch ($sort_option) {
    case 'oldest':
        $query .= " ORDER BY e.created_at ASC";
        break;
    case 'hottest':
        $query .= " ORDER BY total_joined DESC, e.created_at DESC";
        break;
    case 'newest':
    default:
        $query .= " ORDER BY e.created_at DESC";
        break;
}

$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Management Portal</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; }
        .header { background-color: #1b5e20; color: white; padding: 20px; text-align: center; position: relative; }
        .btn-back { position: absolute; left: 20px; top: 30px; background-color: white; color: #1b5e20; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-weight: bold; font-size: 14px; }
        .btn-back:hover { background-color: #e0e0e0; }
        
        .section-title { width: 85%; margin: 30px auto 10px auto; color: #1b5e20; border-bottom: 2px solid #1b5e20; padding-bottom: 5px; }
        
        .filter-section { width: 85%; margin: 0 auto 20px auto; background: white; padding: 15px; border-radius: 8px; box-shadow: 0px 2px 5px rgba(0,0,0,0.1); box-sizing: border-box; }
        .filter-form { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .filter-form select { padding: 8px; border-radius: 5px; border: 1px solid #ccc; min-width: 170px; }
        .filter-form .btn-submit { background-color: #1b5e20; color: white; border: none; padding: 8px 20px; cursor: pointer; border-radius: 5px; font-weight: bold; width: auto; }
        .filter-form .btn-submit:hover { background-color: #003300; }
        .filter-form .btn-reset { background-color: #757575; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; font-size: 14px; }
        .filter-form .btn-reset:hover { background-color: #424242; }

        .alert { width: 85%; margin: 15px auto; padding: 12px; border-radius: 5px; font-weight: bold; text-align: center; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .table-container { width: 85%; margin: 20px auto 50px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0px 2px 8px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #ddd; }
        th { background-color: #1b5e20; color: white; }
        tr:hover { background-color: #f9f9f9; }
        
        .no-data { text-align: center; padding: 30px; color: #757575; font-style: italic; }
        
        .btn-action { font-weight: bold; text-decoration: none; padding: 5px 10px; border-radius: 4px; font-size: 13px; }
        .btn-view { color: #1b5e20; border: 1px solid #1b5e20; background: transparent; }
        .btn-view:hover { background-color: #1b5e20; color: white; }
        .btn-delete { color: #c62828; border: 1px solid #c62828; background: transparent; margin-left: 5px; }
        .btn-delete:hover { background-color: #c62828; color: white; }
        
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-open { background-color: #c8e6c9; color: #25602a; }
        .status-closed { background-color: #ffcdd2; color: #c62828; }
        .district-badge { background-color: #e3f2fd; color: #0d47a1; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .count-badge { background-color: #e8f5e9; color: #1b5e20; padding: 3px 8px; border-radius: 10px; font-size: 12px; font-weight: bold; }
    </style>
</head>
<body>

<div class="header">
    <a href="admin_dash.php" class="btn-back">← Back to Dashboard</a>
    <h1>Event Management System</h1>
    <p>Global Volunteer Campaigns & System Roster Control</p>
</div>

<h2 class="section-title">Active & Scheduled Events</h2>

<!-- Filter Panel Section -->
<div class="filter-section">
    <form method="GET" action="" class="filter-form">
        <label for="district"><strong>Filter by District:</strong></label>
        <select name="district" id="district">
            <option value="">-- All Districts --</option>
            <?php foreach($districts_list as $district): ?>
                <option value="<?php echo htmlspecialchars($district); ?>" <?php if($district_filter == $district) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($district); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="sort_by"><strong>Sort Metrics:</strong></label>
        <select name="sort_by" id="sort_by">
            <option value="newest" <?php if($sort_option == 'newest') echo 'selected'; ?>>Newest Added</option>
            <option value="oldest" <?php if($sort_option == 'oldest') echo 'selected'; ?>>Oldest Added</option>
            <option value="hottest" <?php if($sort_option == 'hottest') echo 'selected'; ?>>Highest Participation</option>
        </select>

        <button type="submit" class="btn-submit">Apply Filters</button>
        <?php if(!empty($district_filter) || $sort_option != 'newest'): ?>
            <a href="participation_manage.php" class="btn-reset">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Display Action Notifications -->
<?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
    <div class="alert alert-success">Event and its tracking participant references were successfully purged.</div>
<?php  endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] == 'failed'): ?>
    <div class="alert alert-danger">Error: Critical operational transaction rolled back. Event extraction failed.</div>
<?php endif; ?>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Event ID</th>
                <th>Event Name / Title</th>
                <th>Scheduled Date</th>
                <th>Target District</th>
                <th>Joined Count</th>
                <th>Status</th>
                <th style="text-align: center;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while($event = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><strong>#<?php echo htmlspecialchars($event['event_id']); ?></strong></td>
                        <td><?php echo htmlspecialchars($event['event_title']); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($event['event_date'])); ?></td>
                        <td>
                            <?php if(!empty($event['district'])): ?>
                                <span class="district-badge"><?php echo htmlspecialchars($event['district']); ?></span>
                            <?php else: ?>
                                <span style="color:#999; font-style:italic; font-size:13px;">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="count-badge">
                                <?php echo $event['total_joined']; ?> Volunteers
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo ($event['status'] == 'OPEN') ? 'status-open' : 'status-closed'; ?>">
                                <?php echo htmlspecialchars($event['status']); ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <a href="view_participants.php?event_id=<?php echo $event['event_id']; ?>" class="btn-action btn-view">
                                View Participants
                            </a>
                            <a href="participation_manage.php?delete_id=<?php echo $event['event_id']; ?>" 
                               class="btn-action btn-delete" 
                               onclick="return confirm('Warning! Are you completely sure you want to delete this event? This will drop all joined participant logs permanently.');">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="no-data">No active volunteer events mapped inside the system records matching your selection.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>