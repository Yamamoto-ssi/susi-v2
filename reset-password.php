<?php
require_once 'config/db.php'; // Uses your $conn from config/db.php

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';
$isValidRequest = false;
$message = '';

// 1. Verify token and check expiration timestamp using MySQLi
if (!empty($token) && !empty($email)) {
    $stmt = $conn->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW()");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $resetRecord = $result->fetch_assoc();
    $stmt->close();

    if ($resetRecord) {
        $isValidRequest = true;
    } else {
        $message = "This password reset link is invalid or has expired.";
    }
} else {
    $message = "Missing token or email parameters.";
}

// 2. Handle new password submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isValidRequest) {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!empty($newPassword) && strlen($newPassword) >= 6) {
        if ($newPassword === $confirmPassword) {
            // Hash the new password securely
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update password in the users table
            $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $updateStmt->bind_param("ss", $hashedPassword, $email);
            $updateStmt->execute();
            $updateStmt->close();

            // Delete the token so it cannot be reused
            $deleteStmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $deleteStmt->bind_param("s", $email);
            $deleteStmt->execute();
            $deleteStmt->close();

            $message = "Your password has been successfully reset! You can now <a href='login.php'>log in</a>.";
            $isValidRequest = false; // Hide form after successful reset
        } else {
            $message = "Passwords do not match.";
        }
    } else {
        $message = "Password must be at least 6 characters long.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
</head>
<body>
    <h2>Reset Password</h2>
    <?php if (!empty($message)) echo "<p><strong>$message</strong></p>"; ?>

    <?php if ($isValidRequest): ?>
        <form method="POST">
            <label>New Password:</label><br>
            <input type="password" name="password" required><br><br>
            
            <label>Confirm New Password:</label><br>
            <input type="password" name="confirm_password" required><br><br>
            
            <button type="submit">Update Password</button>
        </form>
    <?php else: ?>
        <p><a href="forgot-password.php">Request a new reset link</a> | <a href="login.php">Login</a></p>
    <?php endif; ?>
</body>
</html>