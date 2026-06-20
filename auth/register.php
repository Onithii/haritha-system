<?php
include("../config/db.php");

if (isset($_POST['register'])) {
    $f_name      = $_POST['f_name'];
    $l_name      = $_POST['l_name'];
    $nic         = $_POST['nic'];
    $phone       = $_POST['phone_number'];
    $email       = $_POST['email'];
    $username    = $_POST['username'];
    $password    = $_POST['password']; // Note: Consider hashing this!
    $address     = $_POST['address'];
    $gn_division = $_POST['gn_division'];
    $ds_division = $_POST['ds_division'];
    $district    = $_POST['district'];

    // Citizen role
    $role_id = 1;

    $sql = "INSERT INTO users (
                f_name, l_name, nic, phone_number, email, 
                username, password, role_id, address, 
                gn_division, ds_division, district
            ) VALUES (
                '$f_name', '$l_name', '$nic', '$phone', '$email', 
                '$username', '$password', '$role_id', '$address', 
                '$gn_division', '$ds_division', '$district'
            )";

    if (mysqli_query($conn, $sql)) {
        header("Location: ..citizen/citizen_dash.php");
        exit();
    } else {
        echo "Registration Failed: " . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Citizen Registration</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f2f5f2;
        }

        .form-container {
            width: 500px;
            margin: 30px auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0px 2px 8px gray;
        }

        h2 {
            text-align: center;
            color: #2e7d32;
        }

        input {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #2e7d32;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        button:hover {
            background: #1b5e20;
        }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Citizen Registration</h2>
    <form method="POST">
        <input type="text" name="f_name" placeholder="First Name" required>
        <input type="text" name="l_name" placeholder="Last Name" required>
        <input type="text" name="nic" placeholder="NIC Number" required>
        <input type="text" name="phone_number" placeholder="Phone Number" required>
        <input type="email" name="email" placeholder="Email Address" required>
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="text" name="address" placeholder="Address" required>
        <input type="text" name="gn_division" placeholder="GN Division" required>
        <input type="text" name="ds_division" placeholder="DS Division" required>
        <input type="text" name="district" placeholder="District" required>
        
        <button type="submit" name="register">Register</button>
    </form>
</div>

</body>
</html>