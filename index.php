<?php
include("config/db.php"); // Adjust path if needed

// 1. Total Registered Citizens (users with role_id = 1)
$citizens_count = 0;
$citizens_query = "SELECT COUNT(user_id) AS total FROM users WHERE role_id = 1";
$citizens_result = mysqli_query($conn, $citizens_query);
if ($citizens_result) {
    $row = mysqli_fetch_assoc($citizens_result);
    $citizens_count = (int)$row['total'];
}

// 2. Complaints Resolved (status_id = 2, representing resolved)
$resolved_count = 0;
$resolved_query = "SELECT COUNT(complaint_id) AS total FROM complaints WHERE status_id = 2";
$resolved_result = mysqli_query($conn, $resolved_query);
if ($resolved_result) {
    $row = mysqli_fetch_assoc($resolved_result);
    $resolved_count = (int)$row['total'];
}

// 3. Total Cleanup & Volunteer Events
$events_count = 0;
$events_query = "SELECT COUNT(event_id) AS total FROM volunteer_events";
$events_result = mysqli_query($conn, $events_query);
if ($events_result) {
    $row = mysqli_fetch_assoc($events_result);
    $events_count = (int)$row['total'];
}

// 5. Participating Authorities / Officers (users with role_id IN (2, 3, 4, 5))
$authorities_count = 0;
$authorities_query = "SELECT COUNT(user_id) AS total FROM users WHERE role_id IN (2, 3, 4, 5)";
$authorities_result = mysqli_query($conn, $authorities_query);
if ($authorities_result) {
    $row = mysqli_fetch_assoc($authorities_result);
    $authorities_count = (int)$row['total'];
}
?>
<!DOCTYPE html>
<html lang="si">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>හරිත - Environmental Complaint Portal</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Sinhala:wght@400;700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', 'Noto Sans Sinhala', sans-serif;
            background-color: #f4f9f4;
            color: #2c3e50;
            overflow-x: hidden;
        }

        /* Hero Header */
        header {
            position: relative;
            height: 75vh;
            width: 100%;
            background: 
                linear-gradient(to bottom, rgba(255, 255, 255, 0.1) 60%, #f4f9f4 100%),
                url('image_801f8d.png') no-repeat center center/cover;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            padding: 30px 10%;
        }

        /* Navbar Layout */
        .navbar {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 10;
        }

        .logo {
            font-family: 'Noto Sans Sinhala', sans-serif;
            font-size: 2.2rem;
            font-weight: 700;
            color: #1b4d3e;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
            letter-spacing: 0.5px;
        }

        .logo span {
            color: #27ae60;
        }

        .nav-buttons {
            display: flex;
            gap: 20px;
        }

        .btn {
            font-family: 'Poppins', sans-serif;
            text-decoration: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 30px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .btn-login {
            background-color: #ffffff;
            color: #1b4d3e;
            border: 2px solid transparent;
        }

        .btn-login:hover {
            background-color: transparent;
            color: #ffffff;
            border-color: #ffffff;
            transform: translateY(-2px);
        }

        .btn-register {
            background-color: #27ae60;
            color: #ffffff;
            border: 2px solid transparent;
        }

        .btn-register:hover {
            background-color: #219653;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
        }

        /* Hero Central Content */
        .hero-content {
            text-align: center;
            max-width: 800px;
            margin-bottom: 50px;
            z-index: 5;
            background: rgba(255, 255, 255, 0.85);
            padding: 40px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }

        .hero-content h1 {
            font-size: 3rem;
            color: #1b4d3e;
            margin-bottom: 20px;
            font-weight: 700;
            line-height: 1.2;
        }

        .hero-content p {
            font-size: 1.1rem;
            color: #555;
            line-height: 1.6;
        }

        /* Haritha at a Glance - Key Metrics Section */
        .stats-container {
            padding: 50px 10%;
            max-width: 1400px;
            margin: 0 auto;
        }

        .stats-title {
            text-align: center;
            margin-bottom: 40px;
            color: #1b4d3e;
        }

        .stats-title h2 {
            font-size: 2.2rem;
            font-weight: 700;
        }

        .stats-title p {
            color: #666;
            font-size: 1rem;
            margin-top: 8px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
        }

        .stat-card {
            background: #ffffff;
            padding: 35px 20px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.03);
            border-top: 5px solid #27ae60;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 15px 35px rgba(39, 174, 96, 0.15);
        }

        .stat-number {
            font-size: 2.8rem;
            font-weight: 700;
            color: #1b4d3e;
            line-height: 1;
            margin-bottom: 12px;
        }

        .stat-label {
            font-size: 0.95rem;
            color: #555;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Informational Features Section */
        .features {
            padding: 40px 10%;
            display: flex;
            justify-content: space-around;
            gap: 30px;
            flex-wrap: wrap;
        }

        .card {
            background: #ffffff;
            padding: 30px;
            border-radius: 15px;
            width: 300px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.03);
            border-top: 4px solid #27ae60;
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card h3 {
            color: #1b4d3e;
            margin-bottom: 15px;
        }

        .card p {
            color: #666;
            font-size: 0.95rem;
        }

        footer {
            text-align: center;
            padding: 30px;
            color: #7f8c8d;
            font-size: 0.9rem;
            background-color: #ffffff;
            border-top: 1px solid #e2eee2;
        }

        @media(max-width: 768px) {
            .hero-content h1 {
                font-size: 2rem;
            }
            .stat-number {
                font-size: 2.2rem;
            }
        }
    </style>
</head>
<body>

    <header>
        <div class="navbar">
            <div class="logo">හරිත<span>.</span></div>
            <div class="nav-buttons">
                <a href="auth/login.php" class="btn btn-login">Login</a>
                <a href="auth/register.php" class="btn btn-register">Register</a>
            </div>
        </div>

        <div class="hero-content">
            <h1>Make Our Environment Greener & Cleaner</h1>
            <p>Voice your concerns, report violations, and track solutions. The හරිත Environmental Complaint Portal connects conscious citizens with local authorities to protect our shared home.</p>
        </div>
    </header>

    <!-- Haritha at a Glance - Key Impact Metrics -->
    <section class="stats-container">
        <div class="stats-title">
            <h2>Haritha at a Glance</h2>
            <p>Real-time impact metrics driven directly by our registered community & officers</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($citizens_count); ?>+</div>
                <div class="stat-label">Registered Citizens</div>
            </div>

            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($resolved_count); ?>+</div>
                <div class="stat-label">Complaints Resolved</div>
            </div>

            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($events_count); ?>+</div>
                <div class="stat-label">Cleanup Events</div>
            </div>

            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($authorities_count); ?>+</div>
                <div class="stat-label">Participating Authorities</div>
            </div>
        </div>
    </section>

    <section class="features">
        <div class="card">
            <h3>Report Instantly</h3>
            <p>Easily upload locations and descriptions of environmental issues in your vicinity.</p>
        </div>
        <div class="card">
            <h3>Track Real-time</h3>
            <p>Stay informed with step-by-step updates as environmental officers address your complaint.</p>
        </div>
        <div class="card">
            <h3>Community Power</h3>
            <p>Join thousands of citizens making a measurable difference in urban and rural ecosystem preservation.</p>
        </div>
    </section>

    <footer>
        <p>&copy; 2026 හරිත Environmental Protection Bureau. All rights reserved.</p>
    </footer>

</body>
</html>