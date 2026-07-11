<!DOCTYPE html>
<html lang="si">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>හරිත - Environmental Complaint Portal</title>
    <!-- Imported Poppins for English UI elements and Noto Sans Sinhala for perfect Sinhala rendering -->
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

        /* Styled specifically for Sinhala typography */
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

        /* Action Buttons styling */
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

        /* Informational Features Section */
        .features {
            padding: 60px 10%;
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
        }
    </style>
</head>
<body>

    <header>
        <div class="navbar">
            <!-- The brand name is now beautifully set in Sinhala script -->
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