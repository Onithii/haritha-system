<?php
// Include your database connection config
include("../config/db.php");

// Fetch open volunteer events from the database (showing newest events first)
$query = "SELECT event_id, event_title, event_image FROM volunteer_events WHERE status = 'OPEN' ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Citizen Dashboard</title>
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
            padding: 20px;
            text-align: center;
        }

        .container {
            width: 85%;
            margin: 30px auto;
            display: flex;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap; /* Allows wrap on smaller screens */
        }

        /* Base Card Styles for Dashboard Actions */
        .card {
            background: white;
            padding: 25px 15px;
            width: 22%; /* Adjusted to comfortably fit 4 cards in a row */
            min-width: 200px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0px 2px 8px rgba(0,0,0,0.15);
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .card h3 {
            color: #2e7d32;
            margin-top: 0;
            font-size: 18px;
        }

        .card p {
            color: #666;
            font-size: 13px;
            line-height: 1.4;
            margin-bottom: 15px;
            flex-grow: 1;
        }

        button, .btn-link {
            background-color: #2e7d32;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 5px;
            font-weight: bold;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }

        button:hover, .btn-link:hover {
            background-color: #1b5e20;
        }

        /* --- VOLUNTEER SECTION STYLES --- */
        .volunteer-section {
            width: 85%;
            margin: 50px auto 0 auto;
        }

        .volunteer-section h2 {
            color: #2e7d32;
            border-bottom: 2px solid #c8e6c9;
            padding-bottom: 10px;
            margin-bottom: 25px;
        }

        .volunteer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }

        /* Visual Card layout mapping to your wireframe layout */
        .volunteer-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0px 3px 10px rgba(0,0,0,0.15);
            display: flex;
            flex-direction: column;
            transition: transform 0.2s;
        }

        .volunteer-card:hover {
            transform: translateY(-5px);
        }

        /* Top segment of the card holding the image */
        .volunteer-img-wrapper {
            width: 100%;
            height: 180px;
            background-color: #e0e0e0; /* Fallback gray placeholder backdrop */
            position: relative;
        }

        .volunteer-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover; /* Keeps aspect ratios proportional */
        }

        /* Bottom segment of the card holding the event title string */
        .volunteer-info {
            padding: 20px;
            text-align: center;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .volunteer-info h4 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 16px;
            line-height: 1.4;
        }

        .volunteer-info .btn-view {
            width: 100%;
            background-color: #1b5e20;
        }

        .no-events {
            color: #666;
            font-style: italic;
            grid-column: 1 / -1;
            text-align: center;
            padding: 20px;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>Citizen Dashboard</h1>
    <p>Welcome to Haritha Environmental Complaint System</p>
</div>

<div class="container">
    <div class="card">
        <h3>Submit Complaint</h3>
        <p>Report environmental issues in your local area.</p>
        <a href="make_complaint.php">
            <button type="button">Submit Complaint</button>
        </a>
    </div>

    <div class="card">
        <h3>My Complaints</h3>
        <p>View and track the complaints you have submitted.</p>
        <button type="button">View Complaints</button>
    </div>

    <div class="card">
        <h3>My Certificates</h3>
        <p>Access and print certificates you earned from volunteering.</p>
        <a href="certificate_view.php">
            <button type="button" style="background-color: #e65100;">My Certificates</button>
        </a>
    </div>

    <div class="card">
        <h3>Profile</h3>
        <p>View and update your personal information details.</p>
        <button type="button">My Profile</button>
    </div>
</div>

<hr style="width: 85%; border: 0; border-top: 1px dashed #c8e6c9; margin: 40px auto;">

<div class="volunteer-section">
    <h2>Volunteer Opportunities</h2>
    
    <div class="volunteer-grid">
        <?php 
        if ($result && mysqli_num_rows($result) > 0): 
            while ($row = mysqli_fetch_assoc($result)): 
        ?>
                <div class="volunteer-card">
                    <div class="volunteer-img-wrapper">
                        <?php if (!empty($row['event_image'])): ?>
                            <img src="<?php echo htmlspecialchars($row['event_image']); ?>" alt="Event Image" onerror="this.parentNode.style.backgroundColor='#e0e0e0'; this.remove();">
                        <?php endif; ?>
                    </div>
                    <div class="volunteer-info">
                        <h4><?php echo htmlspecialchars($row['event_title']); ?></h4>
                        <a href="view_event.php?id=<?php echo $row['event_id']; ?>">
                            <button class="btn-view" type="button">Join Event</button>
                        </a>
                    </div>
                </div>
        <?php 
            endwhile; 
        else: 
        ?>
            <p class="no-events">No active volunteer operations listed at this moment.</p>
        <?php 
        endif; 
        ?>
    </div>
</div>

</body>
</html>