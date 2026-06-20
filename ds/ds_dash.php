<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Divisional Secretariat Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; }
        .header { background-color: #0d47a1; color: white; padding: 20px; text-align: center; }
        .container { width: 80%; margin: 30px auto; display: flex; gap: 20px; }
        .card { background: white; padding: 25px; width: 33.33%; border-radius: 10px; text-align: center; box-shadow: 0px 2px 8px gray; }
        .card h3 { color: #0d47a1; }
        button { background-color: #0d47a1; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px; width: 100%; }
        button:hover { background-color: #002171; }
    </style>
</head>
<body>
<div class="header">
    <h1>Divisional Secretariat Dashboard</h1>
    <p>Division Performance & Escalation Overview</p>
</div>
<div class="container">
    <div class="card">
        <h3>Division Overview</h3>
        <p>Track live statistics of complaints within your DS Division.</p>
        <button onclick="location.href='ds_stats.php'">View Statistics</button>
    </div>
    <div class="card">
        <h3>Escalated Cases</h3>
        <p>Review complaints that require direct DS intervention.</p>
        <button onclick="location.href='escalated_complaints.php'">Review Escalations</button>
    </div>
    <div class="card">
        <h3>GN Performance</h3>
        <p>Monitor reporting action rates of GN divisions under your scope.</p>
        <button onclick="location.href='gn_status.php'">GN Progress Logs</button>
    </div>
</div>
</body>
</html>