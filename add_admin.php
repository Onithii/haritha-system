<?php
// 1. Include your database connection path
include("config/db.php"); // Adjust this path if your db.php is somewhere else

// 2. Define the plain details
$username = 'admin';
$plain_password = '123456';

// 3. Generate the hash using your server's native PHP configuration
$hashed_password = password_hash($plain_password, PASSWORD_BCRYPT);

// 4. Delete the old faulty admin if it exists
mysqli_query($conn, "DELETE FROM users WHERE username = '$username'");

// 5. Insert clean record
$sql = "INSERT INTO users (
            f_name, l_name, nic, phone_number, email, 
            username, password, role_id, address, 
            gn_division, ds_division, district, status,
            office_latitude, office_longitude
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sql);

$f_name = 'Admin';
$l_name = 'User';
$nic = '199500000000';
$phone = '0771234567';
$email = 'admin@system.lk';
$role_id = 2; // Admin Role
$address = 'Head Office, Colombo';
$gn = 'Fort';
$ds = 'Colombo';
$district = 'Colombo';
$status = 'ACTIVE';
$lat = 6.93190000;
$lng = 79.84780000;

mysqli_stmt_bind_param(
    $stmt, 
    "sssssssisssssdd", 
    $f_name, $l_name, $nic, $phone, $email, 
    $username, $hashed_password, $role_id, $address, 
    $gn, $ds, $district, $status, $lat, $lng
);

if (mysqli_stmt_execute($stmt)) {
    echo "<h2>Admin account successfully inserted!</h2>";
    echo "<strong>Username:</strong> " . $username . "<br>";
    echo "<strong>Password:</strong> " . $plain_password . "<br><br>";
    echo "Go back to your application login page and try logging in now.";
} else {
    echo "Database insertion error: " . mysqli_error($conn);
}

mysqli_stmt_close($stmt);
?>