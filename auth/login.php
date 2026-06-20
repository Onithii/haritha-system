<?php
session_start();
include("../config/db.php");

$error = "";

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        // Fetch user from the database using a Prepared Statement
        $sql = "SELECT user_id, username, password, role_id FROM users WHERE username = ? LIMIT 1";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($user = mysqli_fetch_assoc($result)) {
                // Verify the submitted password against the BCRYPT hash in the database
                if (password_verify($password, $user['password'])) {
                    
                    // Regenerate session ID for security (prevents session fixation)
                    session_regenerate_id(true);

                    // Store essential user info in the session
                    $_SESSION['user_id']  = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role_id']  = $user['role_id'];

                    // Role-Based Redirection Matrix
                    switch ($user['role_id']) {
                        case 1:
                            header("Location: ../citizen/citizen_dash.php");
                            break;
                        case 2:
                            header("Location: ../gn/gn_dash.php");
                            break;
                        case 3:
                            header("Location: ../la/la_dash.php");
                            break;
                        case 4:
                            header("Location: ../ds/ds_dash.php");
                            break;
                        case 5:
                            header("Location: ../admin/admin_dash.php");
                            break;
                        default:
                            $error = "Invalid system role. Contact your administrator.";
                            break;
                    }
                    exit();
                } else {
                    $error = "Invalid username or password.";
                }
            } else {
                $error = "Invalid username or password.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Database error. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Haritha System - Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f2f5f2;
        }

        .login-container {
            width: 400px;
            margin: 100px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0px 2px 8px gray;
        }

        h2 {
            text-align: center;
            color: #2e7d32;
            margin-bottom: 20px;
        }

        .error-box {
            background-color: #ffebee;
            color: #c62828;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 5px solid #e53935;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #2e7d32;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }

        button:hover {
            background: #1b5e20;
        }

        .register-link {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
        }

        .register-link a {
            color: #2e7d32;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="login-container">
    <h2>Haritha System Login</h2>

    <?php if (!empty($error)): ?>
        <div class="error-box">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        
        <button type="submit" name="login">Login</button>
    </form>

    <div class="register-link">
        Don't have an account? <a href="register.php">Register here</a>
    </div>
</div>

</body>
</html>