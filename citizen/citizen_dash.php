<!DOCTYPE html>
<html>
<head>
    <title>Citizen Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f2f5f2;
            margin: 0;
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

        .card {
            background: white;
            padding: 25px;
            width: 30%;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0px 2px 8px gray;
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
        }

        button:hover {
            background-color: #1b5e20;
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
    <p>Report environmental issues in your area.</p>
    <a href="make_complaint.php">
        <button type="button">Submit Complaint</button>
    </a>
</div>


    <div class="card">
        <h3>My Complaints</h3>
        <p>View the complaints you have submitted.</p>

        <button>
            View Complaints
        </button>
    </div>


    <div class="card">
        <h3>Profile</h3>
        <p>View and update your personal information.</p>

        <button>
            My Profile
        </button>
    </div>

</div>


</body>
</html>