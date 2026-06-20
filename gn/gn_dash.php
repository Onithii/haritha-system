<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Grama Niladhari Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; }
        .header { background-color: #e65100; color: white; padding: 20px; text-align: center; }
        .container { width: 80%; margin: 30px auto; display: flex; gap: 20px; }
        .card { background: white; padding: 25px; width: 33.33%; border-radius: 10px; text-align: center; box-shadow: 0px 2px 8px gray; }
        .card h3 { color: #e65100; }
        button { background-color: #e65100; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px; width: 100%; }
        button:hover { background-color: #b33600; }
    </style>
</head>
<body>
<div class="header">
    <h1>Grama Niladhari Dashboard</h1>
    <p>GN Division Area Management & Citizen Verification</p>
</div>
<div class="container">
    <div class="card">
        <h3>Pending Verifications</h3>
        <p>Verify citizen profiles, addresses, and local residency details.</p>
        <button onclick="location.href='verify_citizens.php'">Verify Records</button>
    </div>
    <div class="card">
        <h3>Local Complaints</h3>
        <p>Investigate environmental issues flagged directly inside your GN boundaries.</p>
        <button onclick="location.href='gn_complaints.php'">View Field Inquiries</button>
    </div>
    <div class="card">
        <h3>Submit Field Report</h3>
        <p>Log physical environment assessments directly to the DS office.</p>
        <button onclick="location.href='submit_report.php'">Log Field Action</button>
    </div>
</div>
</body>
</html>