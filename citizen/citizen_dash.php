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
            width: 80%;
            margin: 30px auto;
            display: flex;
            gap: 20px;
        }

        /* Base Card Styles for Dashboard Actions */
        .card {
            background: white;
            padding: 25px;
            width: 30%;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0px 2px 8px rgba(0,0,0,0.15);
        }

        .card h3 {
            color: #2e7d32;
        }

        button {
            background-color: #2e7d32;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 5px;
            font-weight: bold;
        }

        button:hover {
            background-color: #1b5e20;
        }

        /* --- NEW VOLUNTEER SECTION STYLES --- */
        .volunteer-section {
            width: 80%;
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

        /* The Visual Card requested in your wireframe layout */
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
            background-color: #e0e0e0; /* Placeholder color if image is empty */
            position: relative;
        }

        .volunteer-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover; /* Ensures image fits perfectly without distortion */
        }

        /* Bottom segment of the card holding the event topic text */
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
    </style>
 </head>


<body>

<div class="header">
    <h1>Citizen Dashboard</h1>
    <p>Welcome to Haritha Environmental Complaint System</p>
</div>

<!-- Main Quick Actions Row -->
<div class="container">
    <div class="card">
        <h3>Submit Complaint</h3>
        <p>Report environmental issues in your area.</p>
        <a href="make_complaint.php">
            <button type="button">Submit Complaint</button>
        </a>
    </div>

    <div class="card">
        <h3>My Complaints</h3>
        <p>View the complaints you have submitted.</p>
        <button>View Complaints</button>
    </div>

    <div class="card">
        <h3>Profile</h3>
        <p>View and update your personal information.</p>
        <button>My Profile</button>
    </div>
</div>

---

<!-- NEW SECTION: VOLUNTEER OPPORTUNITIES -->
<div class="volunteer-section">
    <h2>Volunteer Opportunities</h2>
    
    <div class="volunteer-grid">
        
        <!-- Example Card 1 (With Active Upload Image) -->
        <div class="volunteer-card">
            <div class="volunteer-img-wrapper">
                <!-- Replace src path dynamically with your DB row value later -->
                <img src="../uploads/event_example1.jpg" alt="Event Image" onerror="this.style.display='none'">
            </div>
            <div class="volunteer-info">
                <h4>Kelani River Basin Cleanup Drive</h4>
                <a href="view_event.php?id=1"><button class="btn-view">Join Event</button></a>
            </div>
        </div>

        <!-- Example Card 2 (With Placeholder fallback if no image was uploaded) -->
        <div class="volunteer-card">
            <div class="volunteer-img-wrapper">
                <!-- If no image exists, default styling handles it gracefully -->
            </div>
            <div class="volunteer-info">
                <h4>Community Tree Planting Awareness Campaign</h4>
                <a href="view_event.php?id=2"><button class="btn-view">Join Event</button></a>
            </div>
        </div>

        <!-- Example Card 3 -->
        <div class="volunteer-card">
            <div class="volunteer-img-wrapper">
                <img src="../uploads/event_example3.jpg" alt="Event Image" onerror="this.style.display='none'">
            </div>
            <div class="volunteer-info">
                <h4>E-Waste Collection Program</h4>
                <a href="view_event.php?id=3"><button class="btn-view">Join Event</button></a>
            </div>
        </div>

    </div>
</div>

</body>
</html>