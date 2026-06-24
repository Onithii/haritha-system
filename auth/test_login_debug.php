<?php
include("../config/db.php");

echo "<h2>Executing Automated Password Repair Engine</h2>";

$target_user = 'gn_cinnamon';
$plain_password = 'password123';

// 1. Let your local PHP environment natively generate a clean hash
$fresh_hash = password_hash($plain_password, PASSWORD_BCRYPT);

echo "Generated Fresh Hash: <code>" . $fresh_hash . "</code><br>";

// 2. Safely update the database row
$update_sql = "UPDATE users SET password = ? WHERE username = ?";
$stmt = mysqli_prepare($conn, $update_sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ss", $fresh_hash, $target_user);
    if (mysqli_stmt_execute($stmt)) {
        echo "✅ <b>Database Updated Successfully!</b><br><br>";
        
        // 3. Double check verification right now
        if (password_verify($plain_password, $fresh_hash)) {
            echo "🟩 <b>SUCCESS:</b> The new hash matches '$plain_password' perfectly.<br>";
            echo "You can now log into your system using:<br>";
            echo "Username: <b>gn_cinnamon</b><br>";
            echo "Password: <b>password123</b>";
        } else {
            echo "🟥 Core PHP error: Native verification failed.";
        }
    } else {
        echo "🟥 Update execution failed: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
} else {
    echo "🟥 Preparation failed: " . mysqli_error($conn);
}
?>