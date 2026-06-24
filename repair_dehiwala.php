<?php
include("config/db.php");

// 1. Generate a clean, verifiable Bcrypt hash for the password
$password_plain = "password123"; // You can change this to your preferred test password
$new_hash = password_hash($password_plain, PASSWORD_BCRYPT);

// 2. Target the specific username causing the issue
$username = "gn_dehiwala"; 

$query = "UPDATE users SET password = ? WHERE username = ?";
$stmt = mysqli_prepare($conn, $query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ss", $new_hash, $username);
    if (mysqli_stmt_execute($stmt)) {
        echo "✅ Success! The password hash for <b>" . htmlspecialchars($username) . "</b> has been completely repaired.<br>";
        echo "🔑 Your temporary plaintext password is: <code>" . htmlspecialchars($password_plain) . "</code>";
    } else {
        echo "❌ SQL Execution Error: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
} else {
    echo "❌ Statement Preparation Error: " . mysqli_error($conn);
}
?>