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

// 1. Get Complaint ID from Query String
$complaint_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($complaint_id <= 0) {
    header("Location: admin_dash.php");
    exit();
}

$alert_message = "";
$alert_type = "";

// 2. Handle Form Updates (Status, Assignment, Reply)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_complaint') {
    $status_id = (int)$_POST['status_id'];
    $assigned_to_id = !empty($_POST['assigned_to_id']) ? (int)$_POST['assigned_to_id'] : "NULL";
    $reply = !empty($_POST['reply']) ? "'" . mysqli_real_escape_string($conn, trim($_POST['reply'])) . "'" : "NULL";

    $update_query = "UPDATE complaints SET 
                        status_id = $status_id, 
                        assigned_to_id = $assigned_to_id, 
                        reply = $reply,
                        updated_at = NOW()
                     WHERE complaint_id = $complaint_id";

    if (mysqli_query($conn, $update_query)) {
        $alert_message = "Complaint details updated successfully.";
        $alert_type = "success";
    } else {
        $alert_message = "Failed to update complaint: " . mysqli_error($conn);
        $alert_type = "error";
    }
}

// 3. Fetch Master Data for Forms
// Fetch Statuses
$status_res = mysqli_query($conn, "SELECT * FROM complaint_status ORDER BY status_id ASC");
$statuses = [];
if ($status_res) {
    while ($row = mysqli_fetch_assoc($status_res)) {
        $statuses[] = $row;
    }
}

// Fetch Staff / Officers for Assignment (Combining f_name and l_name)
$officers_res = mysqli_query($conn, "SELECT user_id, CONCAT(f_name, ' ', l_name) AS name, email FROM users WHERE role_id IN (2, 3, 4) ORDER BY f_name ASC");
$officers = [];
if ($officers_res) {
    while ($row = mysqli_fetch_assoc($officers_res)) {
        $officers[] = $row;
    }
}

// 4. Fetch Main Complaint Details with Joins
$query = "SELECT c.*, 
                 COALESCE(CONCAT(u_cit.f_name, ' ', u_cit.l_name), 'Anonymous') AS citizen_name, 
                 u_cit.email AS citizen_email, 
                 u_cit.phone_number AS citizen_phone,
                 CONCAT(u_off.f_name, ' ', u_off.l_name) AS officer_name,
                 cat.category_name,
                 st.status_name
          FROM complaints c
          LEFT JOIN users u_cit ON c.citizen_id = u_cit.user_id
          LEFT JOIN users u_off ON c.assigned_to_id = u_off.user_id
          LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
          LEFT JOIN complaint_status st ON c.status_id = st.status_id
          WHERE c.complaint_id = $complaint_id 
          LIMIT 1";

$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    echo "<h3>Complaint not found. <a href='admin_dash.php'>Return to Dashboard</a></h3>";
    exit();
}

$complaint = mysqli_fetch_assoc($result);

// Badge Helper
function getBadgeClass($status_id) {
    $class_map = [
        1 => 'badge-submitted',
        2 => 'badge-assigned',
        3 => 'badge-progress',
        4 => 'badge-completed',
        5 => 'badge-escalated',
        6 => 'badge-rejected'
    ];
    return isset($class_map[$status_id]) ? $class_map[$status_id] : 'badge-default';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Details #<?php echo htmlspecialchars($complaint['complaint_id']); ?> - Admin Console</title>
    
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
            background-color: rgba(255, 255, 255, 0.25);
        }

        .layout-wrapper {
            max-width: 1200px;
            margin: 32px auto;
            padding: 0 24px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        @media (max-width: 900px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: var(--card-bg);
            padding: 24px;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            margin-bottom: 24px;
        }

        .card h2 {
            margin-top: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--text-main);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 12px;
            margin-bottom: 20px;
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

        /* --- Info Grid --- */
        .info-group {
            margin-bottom: 16px;
        }

        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 14px;
            color: var(--text-main);
            line-height: 1.5;
        }

        .info-two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .complaint-description {
            background-color: #f8fafc;
            padding: 16px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            font-size: 14px;
            white-space: pre-wrap;
            color: #334155;
        }

        .complaint-image {
            max-width: 100%;
            height: auto;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            margin-top: 8px;
        }

        /* --- Form Elements --- */
        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-main);
            margin-bottom: 6px;
        }

        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 13px;
            color: var(--text-main);
            background-color: #fff;
            box-sizing: border-box;
            outline: none;
        }

        .form-group select:focus, 
        .form-group textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }

        .btn-submit {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: background 0.2s;
        }

        .btn-submit:hover {
            background-color: var(--primary-hover);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .map-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .map-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<header class="header">
    <div class="header-title">
        <h1>Complaint Reference #<?php echo htmlspecialchars($complaint['complaint_id']); ?></h1>
        <p>Submitted on <?php echo date('F d, Y \a\t h:i A', strtotime($complaint['created_at'])); ?></p>
    </div>
    <a href="admin_dash.php" class="btn-top-back">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Back to Dashboard
    </a>
</header>

<main class="layout-wrapper">

    <?php if (!empty($alert_message)): ?>
        <div class="alert alert-<?php echo $alert_type; ?>">
            <?php echo htmlspecialchars($alert_message); ?>
        </div>
    <?php endif; ?>

    <div class="details-grid">
        
        <!-- Left Column: Primary Details -->
        <div>
            <div class="card">
                <h2><?php echo htmlspecialchars($complaint['title']); ?></h2>
                
                <div class="info-two-col">
                    <div class="info-group">
                        <div class="info-label">Category</div>
                        <div class="info-value"><strong><?php echo htmlspecialchars($complaint['category_name'] ?? 'General'); ?></strong></div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Current Status</div>
                        <div class="info-value">
                            <span class="badge <?php echo getBadgeClass($complaint['status_id']); ?>">
                                <?php echo htmlspecialchars($complaint['status_name'] ?? 'UNKNOWN'); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="info-group">
                    <div class="info-label">Description</div>
                    <div class="complaint-description"><?php echo htmlspecialchars($complaint['description']); ?></div>
                </div>

                <?php if (!empty($complaint['image_path'])): ?>
                    <div class="info-group">
                        <div class="info-label">Attached Photo</div>
                        <a href="../uploads/<?php echo htmlspecialchars($complaint['image_path']); ?>" target="_blank">
                            <img src="../uploads/<?php echo htmlspecialchars($complaint['image_path']); ?>" alt="Complaint Evidence" class="complaint-image">
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Location Information Card -->
            <div class="card">
                <h2>Location & Geography</h2>
                <div class="info-two-col">
                    <div class="info-group">
                        <div class="info-label">District</div>
                        <div class="info-value"><?php echo htmlspecialchars(!empty($complaint['district']) ? $complaint['district'] : 'Not Specified'); ?></div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Location Details</div>
                        <div class="info-value"><?php echo htmlspecialchars(!empty($complaint['location_description']) ? $complaint['location_description'] : 'None provided'); ?></div>
                    </div>
                </div>

                <?php if (!empty($complaint['latitude']) && !empty($complaint['longitude'])): ?>
                    <div class="info-group" style="margin-top: 8px;">
                        <div class="info-label">Coordinates</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($complaint['latitude']); ?>, <?php echo htmlspecialchars($complaint['longitude']); ?>
                            &nbsp;&bull;&nbsp;
                            <a href="https://maps.google.com/?q=<?php echo $complaint['latitude']; ?>,<?php echo $complaint['longitude']; ?>" target="_blank" class="map-link">
                                Open in Google Maps
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Official Reply / Resolution -->
            <div class="card">
                <h2>Official Administrative Response</h2>
                <?php if (!empty($complaint['reply'])): ?>
                    <div class="complaint-description" style="background-color: #f0fdf4; border-color: #bbf7d0;">
                        <?php echo htmlspecialchars($complaint['reply']); ?>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-muted); font-size: 13px; font-style: italic; margin: 0;">No official reply has been attached to this complaint yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Metadata & Management Action Panel -->
        <div>
            <!-- Complainant Profile -->
            <div class="card">
                <h2>Complainant Info</h2>
                <div class="info-group">
                    <div class="info-label">Full Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($complaint['citizen_name'] ?? 'Anonymous / Unknown'); ?></div>
                </div>
                <div class="info-group">
                    <div class="info-label">Email Address</div>
                    <div class="info-value"><?php echo htmlspecialchars($complaint['citizen_email'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-group">
                    <div class="info-label">Contact Phone</div>
                    <div class="info-value"><?php echo htmlspecialchars($complaint['citizen_phone'] ?? 'N/A'); ?></div>
                </div>
            </div>

            <!-- Management Form Card -->
            <div class="card">
                <h2>Take Action</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_complaint">
                    
                    <div class="form-group">
                        <label for="status_id">Update Status</label>
                        <select name="status_id" id="status_id" required>
                            <?php foreach ($statuses as $st): ?>
                                <option value="<?php echo $st['status_id']; ?>" <?php if ($st['status_id'] == $complaint['status_id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($st['status_name'] ?? ('Status #' . $st['status_id'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="assigned_to_id">Assign to Officer</label>
                        <select name="assigned_to_id" id="assigned_to_id">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($officers as $off): ?>
                                <option value="<?php echo $off['user_id']; ?>" <?php if ($off['user_id'] == $complaint['assigned_to_id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($off['name']); ?> (ID: <?php echo $off['user_id']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="reply">Official Reply / Note</label>
                        <textarea name="reply" id="reply" placeholder="Type official response or resolution notes..."><?php echo htmlspecialchars($complaint['reply'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn-submit">Save Changes</button>
                </form>
            </div>
        </div>

    </div>

</main>

</body>
</html>