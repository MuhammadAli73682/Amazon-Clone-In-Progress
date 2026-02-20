<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['reset_email']) || !isset($_SESSION['verified_reset_token'])) {
    header('Location: forgot-password.php');
    exit;
}

$email = $_SESSION['reset_email'];
$token = $_SESSION['verified_reset_token'];
$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if($new_password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif(strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters";
    } else {
        // Verify token is still valid
        $stmt = $pdo->prepare("
            SELECT * FROM password_resets 
            WHERE email = ? AND token = ? AND used = 0 AND expires_at > NOW()
        ");
        $stmt->execute([$email, $token]);
        $reset = $stmt->fetch();
        
        if($reset) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashed_password, $email]);
            
            // Mark token as used
            $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
            $stmt->execute([$reset['id']]);
            
            // Clear session
            unset($_SESSION['reset_email']);
            unset($_SESSION['verified_reset_token']);
            
            $success = "Password reset successful! Redirecting to login...";
            header("refresh:2;url=login.php");
        } else {
            $error = "Invalid or expired reset token";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - ShopHub</title>
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
                            <i class="fas fa-key fa-3x text-warning"></i>
                            <h2 class="mt-3">Reset Password</h2>
                            <p class="text-muted">Enter your new password</p>
                        </div>
                        
                        <?php if($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?= $success ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if(!$success): ?>
                        <form method="POST" id="resetForm">
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <div class="input-group">
                                    <input type="password" name="new_password" id="new_password" 
                                           class="form-control" minlength="6" required>
                                    <button class="btn btn-outline-secondary" type="button" 
                                            onclick="togglePassword('new_password')">
                                        <i class="fas fa-eye" id="new_password_icon"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <input type="password" name="confirm_password" id="confirm_password" 
                                           class="form-control" minlength="6" required>
                                    <button class="btn btn-outline-secondary" type="button" 
                                            onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye" id="confirm_password_icon"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-warning w-100 mb-3">
                                <i class="fas fa-check"></i> Reset Password
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
    
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '_icon');
            
            if(field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Validate passwords match
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            
            if(newPass !== confirmPass) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>
</html>
