<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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

                    $_SESSION['user_id']  = $user['user_id'];
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Haritha System - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Noto+Sans+Sinhala:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', 'Noto Sans Sinhala', sans-serif;
            background: url("../images/background.jpg") no-repeat center center fixed;
            background-size: cover;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Screen Wrapper Split Layout */
        .page-container {
            width: 100%;
            max-width: 1200px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
        }

        /* Left Side: Brand Text */
        .brand-section {
            flex: 1;
            color: #ffffff;
            padding-right: 50px;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.4);
        }

        .brand-logo {
            font-size: 24px;
            font-weight: 300;
            letter-spacing: 2px;
            margin-bottom: 20px;
            text-transform: uppercase;
        }

        .brand-title {
            font-family: 'Noto Sans Sinhala', sans-serif;
            font-size: 5rem;
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 20px;
        }

        .brand-subtitle {
            font-size: 1.2rem;
            font-weight: 300;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .brand-desc {
            font-size: 1.1rem;
            opacity: 0.7;
            font-weight: 300;
        }

        /* Right Side: Glassmorphic Form Card */
        .login-card {
            width: 440px;
            background: rgba(255, 255, 255, 0.15); /* Translucent white background */
            backdrop-filter: blur(15px); /* Frosty blurred effect */
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
            color: #ffffff;
        }

        .login-card h2 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 25px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 0.9rem;
            margin-bottom: 8px;
            opacity: 0.9;
        }

        /* Input Styles */
        .form-group input {
            width: 100%;
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            color: #333333;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 110, 117, 0.5);
        }

        /* Action Buttons */
        .btn-signin {
            width: 100%;
            padding: 14px;
            background: #c8d56e; /* Bright blue button as in reference */
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease;
            margin-top: 10px;
        }

        .btn-signin:hover {
            background: #1976d2;
        }

        .error-box {
            background-color: rgba(239, 83, 80, 0.2);
            border: 1px solid #ef5350;
            color: #ffcdd2;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
        }

        /* Link Options */
        .forgot-link {
            text-align: right;
            margin-top: -10px;
            margin-bottom: 20px;
        }

        .forgot-link a, .register-link a {
            color: #ffffff;
            font-size: 0.85rem;
            text-decoration: underline;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .forgot-link a:hover, .register-link a:hover {
            opacity: 1;
        }

        .register-link {
            text-align: center;
            margin-top: 25px;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        /* Responsive Layout for Tablets/Mobile */
        @media (max-width: 900px) {
            .page-container {
                flex-direction: column;
                justify-content: center;
                gap: 40px;
            }
            .brand-section {
                padding-right: 0;
                text-align: center;
            }
            .brand-title {
                font-size: 3.5rem;
            }
            .login-card {
                width: 100%;
                max-width: 400px;
            }
        }
    </style>
</head>
<body>

<div class="page-container">
    <div class="brand-section">
        <div class="brand-logo">HARITHA SYSTEM</div>
        <h1 class="brand-title">හරිත</h1>
        <p class="brand-subtitle">Where Sustainability Meets Technology.</p>
        <p class="brand-desc">Embark on a journey to manage, maintain, and secure a greener environment with our integrated digital systems.</p>
    </div>

    <div class="login-card">
        <h2>Sign In</h2>

        <?php if (!empty($error)): ?>
            <div class="error-box">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter your username" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••••••" required>
            </div>

            <div class="forgot-link">
                <a href="#">Forgot password?</a>
            </div>

            <button type="submit" name="login" class="btn-signin">SIGN IN</button>
        </form>

        <div class="register-link">
            Are you new? <a href="register.php">Create an Account</a>
        </div>
    </div>
</div>

</body>
</html>