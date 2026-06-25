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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; }
        .header { background-color: #1b5e20; color: white; padding: 20px; text-align: center; }
        .container { width: 85%; margin: 30px auto; display: flex; flex-wrap: wrap; gap: 20px; justify-content: center; }
        .card { background: white; padding: 25px; width: 20%; min-width: 220px; border-radius: 10px; text-align: center; box-shadow: 0px 2px 8px gray; }
        .card h3 { color: #1b5e20; }
        button { background-color: #1b5e20; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px; width: 100%; }
        button:hover { background-color: #003300; }
    </style>
</head>
<body>
<div class="header">
    <h1>System Admin Dashboard</h1>
    <p>Global System Control & Monitoring Portal</p>
</div>
<div class="container">
    <div class="card">
        <h3>User Management</h3>
        <p>Manage citizens, GN officers, and institutional accounts.</p>
        <button onclick="location.href='manage_users.php'">Manage Users</button>
    </div>
    <div class="card">
        <h3>System Reports</h3>
        <p>View cross-district analytics and resolution timelines.</p>
        <button onclick="location.href='view_reports.php'">View Analytics</button>
    </div>
    <div class="card">
        <h3>Complaints Master</h3>
        <p>Monitor, track, or reassign any complaint in the system.</p>
        <button onclick="location.href='all_complaints.php'">All Complaints</button>
    </div>
    <div class="card">
        <h3>Settings</h3>
        <p>Configure regional boundaries, roles, and system parameters.</p>
        <button onclick="location.href='settings.php'">System Settings</button>
    </div>
</div>
</body>
</html>