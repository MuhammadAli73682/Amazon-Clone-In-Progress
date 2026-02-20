<?php
session_start();
require_once 'config/database.php';

// NOTE: PHP's built-in mail() function often does not work on a local
// XAMPP/WAMP installation unless you configure SMTP settings in
// php.ini (e.g. smtp=localhost, port=25) or install a local mail
// server. For production use you should replace this with a library
// like PHPMailer or SwiftMailer and an external SMTP provider. The
// code below will fall back to showing the code on-screen for
// debugging when mail() fails.

$message = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if($user) {
        // Generate 6-digit verification code
        $token = sprintf("%06d", mt_rand(1, 999999));
        
        // --- SECURITY NOTE ---
        // Storing the reset token in plain text is insecure.  We hash it so
        // an attacker with database access cannot see valid codes.  The
        // verification page already supports hashed or plain tokens for
        // backwards compatibility.
        //
        // Make sure the `token` column in `password_resets` is large enough
        // to hold a password hash (VARCHAR(255)).  You can run:
        //
        //    ALTER TABLE password_resets MODIFY token VARCHAR(255) NOT NULL;
        //
        // if you haven't already altered the schema.
        
        $hashedToken = password_hash($token, PASSWORD_DEFAULT);
        
        // Set expiration time (15 minutes from now)
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        // Delete old tokens for this email
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->execute([$email]);
        
        // Insert new hashed token
        $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$email, $hashedToken, $expires_at]);
        
        // Send email (simulated - in production use PHPMailer or similar)
        $to = $email;
        $subject = "Password Reset Code - ShopHub";
        $email_message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .code { font-size: 32px; font-weight: bold; color: #ff9900; letter-spacing: 5px; }
                .box { background: #f5f5f5; padding: 20px; border-radius: 8px; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Password Reset Request</h2>
                <p>Hello {$user['full_name']},</p>
                <p>You requested to reset your password. Use the verification code below:</p>
                <div class='box'>
                    <p>Your verification code is:</p>
                    <p class='code'>{$token}</p>
                </div>
                <p>This code will expire in 15 minutes.</p>
                <p>If you didn't request this, please ignore this email.</p>
                <hr>
                <p style='color: #666; font-size: 12px;'>ShopHub - Your trusted online marketplace</p>
            </div>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: noreply@shophub.com" . "\r\n";
        
        // Try to send email
        if(@mail($to, $subject, $email_message, $headers)) {
            $message = "Verification code sent to your email!";
        } else {
            // For development/testing - show code on screen and keep for debug
            $message = "Email service not configured. Your verification code is: <strong>{$token}</strong>";
            // save the token so the verify page can display the correct value
            $_SESSION['debug_reset_token'] = $token;
        }
        
        // Store email in session for verification page
        $_SESSION['reset_email'] = $email;
        
        // Redirect to verification page after 2 seconds
        header("refresh:2;url=verify-reset-code.php");
        
    } else {
        $error = "No account found with this email address";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - ShopHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-lock fa-3x text-warning"></i>
                            <h2 class="mt-3">Forgot Password?</h2>
                            <p class="text-muted">Enter your email to receive a verification code</p>
                        </div>
                        
                        <?php if($message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?= $message ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if(!$message): ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                            </div>
                            
                            <button type="submit" class="btn btn-warning w-100 mb-3">
                                <i class="fas fa-paper-plane"></i> Send Verification Code
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <div class="text-center">
                            <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
