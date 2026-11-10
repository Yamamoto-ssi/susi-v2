<?php
include 'config/db.php'; // Uses your $conn from config/db.php

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);

    if ($email) {
        // 1. Check if email exists in users table using MySQLi prepared statement
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user) {
            // 2. Generate secure token and expiration (1 hour)
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // 3. Check if a reset request already exists for this email
            $checkStmt = $conn->prepare("SELECT email FROM password_resets WHERE email = ?");
            $checkStmt->bind_param("s", $email);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $exists = $checkResult->fetch_assoc();
            $checkStmt->close();

            if ($exists) {
                // Update existing record
                $tokenStmt = $conn->prepare("UPDATE password_resets SET token = ?, expires_at = ?, created_at = NOW() WHERE email = ?");
                $tokenStmt->bind_param("sss", $token, $expires_at, $email);
                $tokenStmt->execute();
                $tokenStmt->close();
            } else {
                // Insert new record
                $tokenStmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (?, ?, ?, NOW())");
                $tokenStmt->bind_param("sss", $email, $token, $expires_at);
                $tokenStmt->execute();
                $tokenStmt->close();
            }

            // 4. Send email via Brevo cURL API
            $apiKey = 'xsmtpsib-d1bc0fbc90af8fab96ee6c48bd1fb702f6c97a1b8164a580c55f9c00e0149172-Ix5exQ855s68FnHw'; // Replace with your actual Brevo API Key
            $url = 'https://api.brevo.com/v3/smtp/email';

            $resetLink = "http://localhost/HouseAndLotApp/reset-password.php?token=" . $token . "&email=" . urlencode($email);

            $emailData = [
                'sender' => [
                    'name' => 'Mendoza Real Estate',
                    'email' => 'brixsedillo@yourdomain.com' // Ensure this sender is verified in Brevo
                ],
                'to' => [
                    [
                        'email' => $email
                    ]
                ],
                'subject' => 'Password Reset Request',
                'htmlContent' => '<html><body>' .
                    '<h2>Password Reset Request</h2>' .
                    '<p>You requested to reset your password. Click the link below to proceed:</p>' .
                    '<p><a href="' . $resetLink . '" style="padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">Reset Password</a></p>' .
                    '<p>This link will expire in 1 hour.</p>' .
                    '<p>If you did not request this, please ignore this email.</p>' .
                    '</body></html>'
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($emailData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: application/json',
                'api-key: ' . $apiKey,
                'content-type: application/json'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                $message = "Success! Check your Gmail inbox for the password reset link.";
            } else {
                $message = "Failed to send email. Please try again later.";
            }
        } else {
            $message = "If that email address exists in our system, a reset link has been sent.";
        }
    } else {
        $message = "Please enter a valid email address.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
</head>
<body>
    <h2>Forgot Password</h2>
    <?php if (!empty($message)) echo "<p><strong>$message</strong></p>"; ?>
    <form method="POST">
        <label>Email Address:</label><br>
        <input type="email" name="email" required><br><br>
        <button type="submit">Send Reset Link</button>
    </form>
    <p><a href="login.php">Back to Login</a></p>
</body>
</html>