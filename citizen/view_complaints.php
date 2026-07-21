<?php
session_start();
include("../config/db.php");

// Ensure citizen is logged in (adjust session key according to your login logic)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$citizen_id = $_SESSION['user_id'];

// Query joining complaints with category and status tables
$query = "SELECT c.*, 
                 cat.category_name, 
                 cs.status_name 
          FROM complaints c
          LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
          LEFT JOIN complaint_status cs ON c.status_id = cs.status_id
          WHERE c.citizen_id = '$citizen_id'
          ORDER BY c.created_at DESC";

$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Complaints - Haritha</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f2f5f2;
            margin: 0;
            padding-bottom: 60px;
        }

        .header {
            background-color: #2e7d32;
            color: white;
            padding: 20px 10%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .btn-back {
            background-color: #ffffff;
            color: #2e7d32;
            padding: 9px 18px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            transition: background-color 0.2s;
        }

        .btn-back:hover {
            background-color: #e8f5e9;
        }

        .container {
            width: 85%;
            margin: 30px auto;
        }

        .page-title {
            color: #2e7d32;
            margin-bottom: 20px;
            border-bottom: 2px solid #c8e6c9;
            padding-bottom: 10px;
        }

        .complaint-card {
            background: #ffffff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .complaint-main {
            flex: 1;
            min-width: 300px;
        }

        .complaint-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .complaint-title {
            font-size: 18px;
            color: #1b5e20;
            margin: 0;
        }

        .badge-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        /* Status Colors */
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-in-progress { background-color: #cce5ff; color: #004085; }
        .status-resolved { background-color: #d4edda; color: #155724; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        .status-default { background-color: #e2e3e5; color: #383d41; }

        .meta-info {
            font-size: 13px;
            color: #777;
            margin-bottom: 15px;
        }

        .complaint-desc {
            font-size: 14px;
            color: #444;
            line-height: 1.5;
            margin-bottom: 15px;
        }

        .reply-box {
            background-color: #f8f9fa;
            border-left: 4px solid #2e7d32;
            padding: 12px 15px;
            border-radius: 0 5px 5px 0;
            margin-top: 15px;
        }

        .reply-box h4 {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: #2e7d32;
        }

        .reply-box p {
            margin: 0;
            font-size: 13px;
            color: #555;
        }

        .complaint-img {
            width: 180px;
            height: 140px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .no-records {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            color: #666;
            font-style: italic;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>My Submitted Complaints</h1>
    <a href="citizen_dash.php" class="btn-back">&larr; Back to Dashboard</a>
</div>

<div class="container">
    <h2 class="page-title">Complaint History</h2>

    <?php if ($result && mysqli_num_rows($result) > 0): ?>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <?php 
                // Determine badge color class based on status_id or status_name
                $status_class = 'status-default';
                $status_lower = strtolower($row['status_name'] ?? '');
                
                if (strpos($status_lower, 'pending') !== false) {
                    $status_class = 'status-pending';
                } elseif (strpos($status_lower, 'progress') !== false) {
                    $status_class = 'status-in-progress';
                } elseif (strpos($status_lower, 'resolve') !== false) {
                    $status_class = 'status-resolved';
                } elseif (strpos($status_lower, 'reject') !== false) {
                    $status_class = 'status-rejected';
                }
            ?>
            <div class="complaint-card">
                <div class="complaint-main">
                    <div class="complaint-header">
                        <h3 class="complaint-title"><?php echo htmlspecialchars($row['title']); ?></h3>
                        <span class="badge-status <?php echo $status_class; ?>">
                            <?php echo htmlspecialchars($row['status_name'] ?? 'Pending'); ?>
                        </span>
                    </div>

                    <div class="meta-info">
                        <strong>Category:</strong> <?php echo htmlspecialchars($row['category_name'] ?? 'General'); ?> | 
                        <strong>Submitted:</strong> <?php echo date('M d, Y', strtotime($row['created_at'])); ?> 
                        <?php if (!empty($row['district'])): ?>
                            | <strong>District:</strong> <?php echo htmlspecialchars($row['district']); ?>
                        <?php endif; ?>
                    </div>

                    <div class="complaint-desc">
                        <?php echo nl2br(htmlspecialchars($row['description'])); ?>
                    </div>

                    <?php if (!empty($row['reply'])): ?>
                        <div class="reply-box">
                            <h4>Authority Reply / Response:</h4>
                            <p><?php echo nl2br(htmlspecialchars($row['reply'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($row['image_path'])): ?>
                    <div>
                        <img src="../<?php echo htmlspecialchars($row['image_path']); ?>" alt="Complaint Image" class="complaint-img" onerror="this.style.display='none';">
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="no-records">
            <p>You have not submitted any complaints yet.</p>
            <a href="make_complaint.php" style="color: #2e7d32; font-weight: bold;">Submit a complaint now</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>