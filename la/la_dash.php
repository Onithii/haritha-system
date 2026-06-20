<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Local Authority Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; }
        .header { background-color: #006064; color: white; padding: 20px; text-align: center; }
        .container { width: 80%; margin: 30px auto; display: flex; gap: 20px; }
        .card { background: white; padding: 25px; width: 33.33%; border-radius: 10px; text-align: center; box-shadow: 0px 2px 8px gray; }
        .card h3 { color: #006064; }
        button { background-color: #006064; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px; width: 100%; }
        button:hover { background-color: #00363a; }
    </style>
</head>
<body>
<div class="header">
    <h1>Local Authority Dashboard</h1>
    <p>Action Center (Municipal Council / Pradeshiya Sabha)</p>
</div>
<div class="container">
    <div class="card">
        <h3>Assigned Tasks</h3>
        <p>View environmental hazards referred to your council for cleanup action.</p>
        <button onclick="location.href='assigned_tasks.php'">View Tasks</button>
    </div>
    <div class="card">
        <h3>Action Progress</h3>
        <p>Update statuses of ongoing on-site operations or field deployments.</p>
        <button onclick="location.href='update_progress.php'">Track Actions</button>
    </div>
    <div class="card">
        <h3>Resolution Logs</h3>
        <p>Access historical accounts of resolved claims and closed tickets.</p>
        <button onclick="location.href='resolved_logs.php'">Archived Resolutions</button>
    </div>
</div>
</body>
</html>