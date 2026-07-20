<?php
include("config/db.php"); // Adjust this path to point to your database configuration file

// 1. Fetch data for the District Complaints Chart
$district_labels = [];
$district_counts = [];
$district_query = "SELECT district, COUNT(complaint_id) as total FROM complaints WHERE district IS NOT NULL AND district != '' GROUP BY district LIMIT 8";
$district_result = mysqli_query($conn, $district_query);
if ($district_result) {
    while ($row = mysqli_fetch_assoc($district_result)) {
        $district_labels[] = $row['district'];
        $district_counts[] = (int)$row['total'];
    }
}

// 2. Fetch data for the Volunteer Events Tracking Metrics
$event_titles = [];
$event_volunteers = [];
$event_query = "SELECT event_title, required_volunteers FROM volunteer_events ORDER BY created_at DESC LIMIT 5";
$event_result = mysqli_query($conn, $event_query);
if ($event_result) {
    while ($row = mysqli_fetch_assoc($event_result)) {
        $event_titles[] = strlen($row['event_title']) > 20 ? substr($row['event_title'], 0, 17) . '...' : $row['event_title'];
        $event_volunteers[] = (int)$row['required_volunteers'];
    }
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
    <!-- Chart.js Engine CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* Hero Header with the fading background image */
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

        /* Live Metrics Visual Data Component Dashboard Layout */
        .analytics-container {
            padding: 40px 10%;
            max-width: 1400px;
            margin: 0 auto;
        }

        .analytics-title {
            text-align: center;
            margin-bottom: 30px;
            color: #1b4d3e;
        }

        .analytics-title h2 {
            font-size: 2rem;
            font-weight: 700;
        }

        .analytics-title p {
            color: #666;
            font-size: 1rem;
            margin-top: 5px;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .chart-card {
            background: #ffffff;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.03);
            border-top: 4px solid #1b4d3e;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .chart-card h3 {
            font-size: 1.1rem;
            color: #1b4d3e;
            margin-bottom: 20px;
            text-align: left;
            width: 100%;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .chart-wrapper {
            position: relative;
            width: 100%;
            height: 300px;
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
            .charts-grid {
                grid-template-columns: 1fr;
            }
            .hero-content h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>

    <header>
        <div class="navbar">
            <div class="logo">හරිත<span>.</span></div>
            <div class="nav-buttons">
                <a href="login.html" class="btn btn-login">Login</a>
                <a href="register.html" class="btn btn-register">Register</a>
            </div>
        </div>

        <div class="hero-content">
            <h1>Make Our Environment Greener & Cleaner</h1>
            <p>Voice your concerns, report violations, and track solutions. The හරිත Environmental Complaint Portal connects conscious citizens with local authorities to protect our shared home.</p>
        </div>
    </header>

    <!-- Interactive Metrics Dashboard Visualization Panel -->
    <section class="analytics-container">
        <div class="analytics-title">
            <h2>Our Environmental Footprint Context</h2>
            <p>Live regional parameters managed directly through the database system metrics</p>
        </div>

        <div class="charts-grid">
            <!-- Card Chart 1: Regional Incidents -->
            <div class="chart-card">
                <h3>Complaints Tracked Across Districts</h3>
                <div class="chart-wrapper">
                    <canvas id="complaintsChart"></canvas>
                </div>
            </div>

            <!-- Card Chart 2: Volunteers Performance -->
            <div class="chart-card">
                <h3>Required Support Volume by Active Volunteer Events</h3>
                <div class="chart-wrapper">
                    <canvas id="eventsChart"></canvas>
                </div>
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

    <!-- Chart Configuration Script Section Injector -->
    <script>
        // Data injected smoothly from PHP arrays into JavaScript arrays
        const districtLabels = <?php echo json_encode($district_labels); ?>;
        const districtCounts = <?php echo json_encode($district_counts); ?>;

        const eventTitles = <?php echo json_encode($event_titles); ?>;
        const eventVolunteers = <?php echo json_encode($event_volunteers); ?>;

        // Render Chart 1: District Complaints Overview (Bar Chart)
        const ctxComplaints = document.getElementById('complaintsChart').getContext('2d');
        new Chart(ctxComplaints, {
            type: 'bar',
            data: {
                labels: districtLabels.length ? districtLabels : ['No Data Received'],
                datasets: []
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#eef2ee' },
                        ticks: { color: '#7f8c8d' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#2c3e50', font: { weight: '600' } }
                    }
                }
            }
        });

        // Add dataset dynamically to allow clear conditional checks
        if (districtLabels.length) {
            ctxComplaints.chart.data.datasets.push({
                label: 'Logged Complaints Count',
                data: districtCounts,
                backgroundColor: 'rgba(39, 174, 96, 0.85)',
                borderColor: '#27ae60',
                borderWidth: 1.5,
                borderRadius: 6,
                barThickness: 28
            });
            ctxComplaints.chart.update();
        }

        // Render Chart 2: Volunteer Action Scope Metrics (Horizontal Bar / Progress Track Chart)
        const ctxEvents = document.getElementById('eventsChart').getContext('2d');
        new Chart(ctxEvents, {
            type: 'y', // Horizontal Layout Direction Configuration
            data: {
                labels: eventTitles.length ? eventTitles : ['No Active Events Available'],
                datasets: []
            },
            options: {
                indexAxis: 'y', 
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: '#eef2ee' },
                        ticks: { color: '#7f8c8d' }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { color: '#2c3e50', font: { size: 12 } }
                    }
                }
            }
        });

        if (eventTitles.length) {
            ctxEvents.chart.data.datasets.push({
                label: 'Required Volunteers Capacity Target',
                data: eventVolunteers,
                backgroundColor: 'rgba(27, 77, 62, 0.85)',
                borderColor: '#1b4d3e',
                borderWidth: 1.5,
                borderRadius: 4,
                barThickness: 18
            });
            ctxEvents.chart.update();
        }
    </script>
</body>
</html>