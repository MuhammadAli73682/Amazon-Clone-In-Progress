<?php
session_start();
require_once 'config/database.php';

// Redirect if email not in session
if(!isset($_SESSION['reset_email'])) {
    header('Location: forgot-password.php');
    exit;
}

$email = $_SESSION['reset_email'];
$error = '';
$success = false;

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code = trim($_POST['code']);

    // Remove any non-digit characters
    $code = preg_replace('/\D/', '', $code);

    // Fetch latest unused reset request
    $stmt = $pdo->prepare("
        SELECT * FROM password_resets 
        WHERE email = ? AND used = 0 AND expires_at > NOW()
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$email]);
    $reset = $stmt->fetch();

    if($reset) {
        // The token column may contain either a plain 6-digit code (legacy)
        // or a password_hash() value.  First try verifying as a hash, then
        // fall back to direct comparison for old rows.
        if (password_verify($code, $reset['token']) || $code === $reset['token']) {
            // Code is valid -- store the original value so reset-password.php
            // can later mark it used if desired.
            $_SESSION['verified_reset_token'] = $reset['token'];

            // Optional: mark as used now or after password reset
            // $stmt2 = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
            // $stmt2->execute([$reset['id']]);

            header('Location: reset-password.php');
            exit;
        } else {
            $error = "Invalid verification code";
        }
    } else {
        $error = "Invalid or expired verification code";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code - ShopHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .code-input {
            font-size: 24px;
            text-align: center;
            letter-spacing: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-shield-alt fa-3x text-warning"></i>
                            <h2 class="mt-3">Enter Verification Code</h2>
                            <p class="text-muted">We sent a 6-digit code to<br><strong><?= htmlspecialchars($email) ?></strong></p>
                        </div>

                        <?php if($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                        </div>
                        <?php endif; ?>

                        <?php if(isset($_SESSION['debug_reset_token'])): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Debug code: <strong><?= htmlspecialchars($_SESSION['debug_reset_token']) ?></strong>
                        </div>
                        <?php unset($_SESSION['debug_reset_token']); ?>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-4">
                                <label class="form-label">Verification Code</label>
                                <input type="text" name="code" class="form-control code-input" 
                                       maxlength="6" pattern="[0-9]{6}" 
                                       placeholder="000000" required autofocus
                                       oninput="this.value=this.value.replace(/\D/g,'')">
                                <small class="text-muted">Enter the 6-digit code</small>
                            </div>

                            <button type="submit" class="btn btn-warning w-100 mb-3">
                                <i class="fas fa-check"></i> Verify Code
                            </button>
                        </form>

                        <div class="text-center">
                            <p class="text-muted small">Code expires in 15 minutes</p>
                            <a href="forgot-password.php">Resend Code</a> | 
                            <a href="login.php">Back to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        // Auto-submit when 6 digits entered
        document.querySelector('input[name="code"]').addEventListener('input', function(e) {
            if(this.value.length === 6) {
                this.form.submit();
            }
        });
    </script>
</body>
</html>
