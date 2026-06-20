<?php
include("../config/db.php");

// Initialize an array to track validation errors
$errors = [];

if (isset($_POST['register'])) {
    // 1. Sanitize and trim inputs to clear unwanted trailing/leading spaces
    $f_name      = trim($_POST['f_name']);
    $l_name      = trim($_POST['l_name']);
    $nic         = trim($_POST['nic']);
    $phone       = trim($_POST['phone_number']);
    $email       = trim($_POST['email']);
    $username    = trim($_POST['username']);
    $password    = $_POST['password']; // Don't trim passwords; spaces can be part of a password
    $address     = trim($_POST['address']);
    $gn_division = trim($_POST['gn_division']);
    $ds_division = trim($_POST['ds_division']);
    $district    = trim($_POST['district']);

    // --- SERVER-SIDE VALIDATION ---

    // Empty field checks
    if (empty($f_name) || empty($l_name) || empty($address) || empty($gn_division) || empty($ds_division) || empty($district)) {
        $errors[] = "All text and location fields are required.";
    }

    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    // Phone number validation (Ensures 10-digit formats like 0771234567)
    if (!preg_match("/^[0-9]{10}$/", $phone)) {
        $errors[] = "Phone number must be exactly 10 digits.";
    }

    // Sri Lankan NIC validation (Supports Old 9-digit format + V/X and New 12-digit format)
    if (!preg_match("/^([0-9]{9}[vVxX]|[0-9]{12})$/", $nic)) {
        $errors[] = "Invalid NIC format. Use old format (e.g., 123456789V) or new format (e.g., 200012345678).";
    }

    // Username complexity (Alphanumeric only, 4-20 chars long)
    if (!preg_match("/^[a-zA-Z0-9_]{4,20}$/", $username)) {
        $errors[] = "Username must be 4-20 characters long and contain only letters, numbers, or underscores.";
    }

    // Password length enforcement
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }

    // --- DUPLICATE CHECK & SECURE INSERTION ---
    if (empty($errors)) {
        // Check if username, email, or NIC already exists using Prepared Statements
        $check_sql = "SELECT user_id FROM users WHERE username = ? OR email = ? OR nic = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($stmt, "sss", $username, $email, $nic);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = "Username, Email, or NIC number is already registered.";
            mysqli_stmt_close($stmt);
        } else {
            mysqli_stmt_close($stmt);

            // Citizen role assignment
            $role_id = 1;

            // Securely Hash Password
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            // Secure Insertion using Prepared Statements to block SQL Injection completely
            $sql = "INSERT INTO users (
                        f_name, l_name, nic, phone_number, email, 
                        username, password, role_id, address, 
                        gn_division, ds_division, district
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param(
                    $stmt, 
                    "sssssssissss", 
                    $f_name, $l_name, $nic, $phone, $email, 
                    $username, $hashed_password, $role_id, $address, 
                    $gn_division, $ds_division, $district
                );

                if (mysqli_stmt_execute($stmt)) {
                    // Corrected potential typo path from original snippet
                    header("Location: ../citizen/citizen_dash.php");
                    exit();
                } else {
                    $errors[] = "Database Error: Registration failed to execute.";
                }
                mysqli_stmt_close($stmt);
            } else {
                $errors[] = "Database Error: Prepared statement initialization failed.";
            }
        }
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

        .error-box {
            background-color: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 5px solid #e53935;
        }

        .error-box ul {
            margin: 0;
            padding-left: 20px;
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
            margin-top: 10px;
        }

        button:hover {
            background: #1b5e20;
        }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Citizen Registration</h2>

    <?php if (!empty($errors)): ?>
        <div class="error-box">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="text" name="f_name" placeholder="First Name" value="<?php echo isset($_POST['f_name']) ? htmlspecialchars($_POST['f_name']) : ''; ?>" required>
        <input type="text" name="l_name" placeholder="Last Name" value="<?php echo isset($_POST['l_name']) ? htmlspecialchars($_POST['l_name']) : ''; ?>" required>
        <input type="text" name="nic" placeholder="NIC Number (e.g., 123456789V or 12-digit)" value="<?php echo isset($_POST['nic']) ? htmlspecialchars($_POST['nic']) : ''; ?>" required>
        <input type="text" name="phone_number" placeholder="Phone Number (e.g., 0771234567)" value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>" required>
        <input type="email" name="email" placeholder="Email Address" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
        <input type="text" name="username" placeholder="Username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
        <input type="password" name="password" placeholder="Password (Min 6 characters)" required>
        <input type="text" name="address" placeholder="Address" value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>" required>
        <input type="text" name="gn_division" placeholder="GN Division" value="<?php echo isset($_POST['gn_division']) ? htmlspecialchars($_POST['gn_division']) : ''; ?>" required>
        <input type="text" name="ds_division" placeholder="DS Division" value="<?php echo isset($_POST['ds_division']) ? htmlspecialchars($_POST['ds_division']) : ''; ?>" required>
        <input type="text" name="district" placeholder="District" value="<?php echo isset($_POST['district']) ? htmlspecialchars($_POST['district']) : ''; ?>" required>
        
        <button type="submit" name="register">Register</button>
    </form>
</div>

</body>
</html>