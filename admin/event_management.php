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

// Generate CSRF Token for sensitive actions (e.g., event deletion)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Sri Lankan Districts list for clean filter matching
$districts_list = [
    "Colombo", "Gampaha", "Kalutara", "Kandy", "Matale", "Nuwara Eliya", 
    "Galle", "Matara", "Hambantota", "Jaffna", "Kilinochchi", "Mannar", 
    "Vavuniya", "Mullaitivu", "Batticaloa", "Ampara", "Trincomalee", 
    "Kurunegala", "Puttalam", "Anuradhapura", "Polonnaruwa", "Badulla", 
    "Moneragala", "Ratnapura", "Kegalle"
];

// 2. Handle Event Deletion (Secured via CSRF Token Verification)
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $token = $_GET['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        header("Location: event_management.php?error=csrf");
        exit();
    }

    // Begin transaction to safely remove references first
    mysqli_begin_transaction($conn);
    try {
        // Step A: Delete participant records associated with the event
        $del_participants = mysqli_prepare($conn, "DELETE FROM volunteer_participants WHERE event_id = ?");
        mysqli_stmt_bind_param($del_participants, "i", $delete_id);
        mysqli_stmt_execute($del_participants);
        mysqli_stmt_close($del_participants);

        // Step B: Delete the actual event entry
        $del_event = mysqli_prepare($conn, "DELETE FROM volunteer_events WHERE event_id = ?");
        mysqli_stmt_bind_param($del_event, "i", $delete_id);
        mysqli_stmt_execute($del_event);
        mysqli_stmt_close($del_event);

        mysqli_commit($conn);
        header("Location: event_management.php?msg=deleted");
        exit();
    } catch (Exception $e) {
        mysqli_rollback($conn);
        header("Location: event_management.php?error=failed");
        exit();
    }
}

// 3. Extract and sanitize query filters
$district_filter = isset($_GET['district']) ? trim($_GET['district']) : '';
$sort_option = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'newest';

// 4. Construct Parameterized Query dynamically
$query = "SELECT e.event_id, e.event_title, e.event_date, e.district, e.status, e.created_at,
          (SELECT COUNT(*) FROM volunteer_participants vp WHERE vp.event_id = e.event_id) AS total_joined
          FROM volunteer_events e WHERE 1=1";

$types = "";
$params = [];

if (!empty($district_filter)) {
    $query .= " AND e.district = ?";
    $types .= "s";
    $params[] = $district_filter;
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

$stmt = mysqli_prepare($conn, $query);
if (!empty($types)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management Portal - Haritha</title>
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
            position: relative;
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

        .btn-back {
            position: absolute;
            left: 24px;
            top: 50%;
            transform: translateY(-50%);
            background-color: #ffffff;
            color: var(--primary-color);
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: background 0.2s;
        }

        .btn-back:hover {
            background-color: #f1f5f9;
        }

        .layout-container {
            max-width: 1200px;
            margin: 32px auto;
            padding: 0 24px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .section-title {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--text-main);
        }

        /* --- Filter Panel --- */
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

        /* --- Alert Messages --- */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius);
            font-size: 13px;
            font-weight: 600;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
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

        .no-data {
            text-align: center;
            padding: 36px;
            color: var(--text-muted);
            font-style: italic;
        }

        /* Badges & Actions */
        .badge {
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
            text-transform: uppercase;
        }

        .status-open { background-color: #dcfce7; color: #166534; }
        .status-closed { background-color: #fee2e2; color: #991b1b; }

        .district-badge {
            background-color: #e0f2fe;
            color: #0369a1;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .count-badge {
            background-color: #f1f5f9;
            color: var(--text-main);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .btn-action {
            font-weight: 600;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            display: inline-block;
            transition: background 0.2s, color 0.2s;
        }

        .btn-view {
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            background: transparent;
        }

        .btn-view:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-delete {
            color: #dc2626;
            border: 1px solid #dc2626;
            background: transparent;
            margin-left: 6px;
        }

        .btn-delete:hover {
            background-color: #dc2626;
            color: white;
        }
    </style>
</head>
<body>

<header class="header">
    <a href="admin_dash.php" class="btn-back">&larr; Dashboard</a>
    <h1>Event Management System</h1>
    <p>Global Volunteer Campaigns & System Roster Control</p>
</header>

<main class="layout-container">

    <!-- Notifications -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="alert alert-success">The campaign and its associated volunteer participant records were successfully purged.</div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">
            <?php 
                if ($_GET['error'] === 'csrf') {
                    echo "Security Warning: Invalid submission token. Transaction aborted.";
                } else {
                    echo "Operational Failure: Could not complete database purging sequence.";
                }
            ?>
        </div>
    <?php endif; ?>

    <h2 class="section-title">Active & Scheduled Events</h2>

    <!-- Filter Section -->
    <section class="filter-card">
        <form method="GET" action="" class="filter-form">
            <div class="filter-group">
                <label for="district">District:</label>
                <select name="district" id="district">
                    <option value="">-- All Districts --</option>
                    <?php foreach($districts_list as $district): ?>
                        <option value="<?php echo htmlspecialchars($district); ?>" <?php if($district_filter === $district) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($district); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="sort_by">Sort By:</label>
                <select name="sort_by" id="sort_by">
                    <option value="newest" <?php if($sort_option === 'newest') echo 'selected'; ?>>Newest First</option>
                    <option value="oldest" <?php if($sort_option === 'oldest') echo 'selected'; ?>>Oldest First</option>
                    <option value="hottest" <?php if($sort_option === 'hottest') echo 'selected'; ?>>Highest Participation</option>
                </select>
            </div>

            <button type="submit" class="btn-submit">Apply Filters</button>
            <?php if(!empty($district_filter) || $sort_option !== 'newest'): ?>
                <a href="event_management.php" class="btn-reset">Clear</a>
            <?php endif; ?>
        </form>
    </section>

    <!-- Table Section -->
    <section class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Event ID</th>
                    <th>Event Title</th>
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
                                    <span style="color:#94a3b8; font-style:italic;">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="count-badge">
                                    <?php echo (int)$event['total_joined']; ?> Volunteers
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo ($event['status'] === 'OPEN') ? 'status-open' : 'status-closed'; ?>">
                                    <?php echo htmlspecialchars($event['status']); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <a href="view_participants.php?event_id=<?php echo (int)$event['event_id']; ?>" class="btn-action btn-view">
                                    View Roster
                                </a>
                                <a href="event_management.php?delete_id=<?php echo (int)$event['event_id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" 
                                   class="btn-action btn-delete" 
                                   onclick="return confirm('Warning! Deleting this campaign will purge all associated volunteer signups permanently. Proceed?');">
                                    Delete
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="no-data">No active volunteer events found matching your filter selection.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

</main>

</body>
</html>
<?php
mysqli_stmt_close($stmt);
?>