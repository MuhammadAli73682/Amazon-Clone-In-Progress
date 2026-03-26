<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/auth.php';
require_once 'includes/mailer.php';

// Redirect if already logged in, send to role-specific dashboard
if(isset($_SESSION['user_id'])) {
    if($_SESSION['user_type'] === 'seller') {
        header('Location: seller/dashboard.php');
    } elseif($_SESSION['user_type'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';
$info = '';

if(($_GET['review'] ?? '') === '1') {
    $info = 'Your seller account request is on review. We will update you by email.';
}
if(!empty($_GET['google_error'])) {
    $error = (string)$_GET['google_error'];
}
if(!empty($_GET['google_info'])) {
    $info = (string)$_GET['google_info'];
}

// initialize attempt counter
if(!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_csrf_or_fail();

    // simple rate limit: max 5 attempts per 15 minutes
    if($_SESSION['login_attempts'] >= 5 && time() - ($_SESSION['last_attempt_time'] ?? 0) < 900) {
        $error = 'Too many login attempts. Please try again later.';
    } else {
        $_SESSION['last_attempt_time'] = time();
        $_SESSION['login_attempts']++;

        // trim whitespace and force lowercase to avoid simple typos
        $email = strtolower(trim($_POST['email']));
        $password = $_POST['password'];

        // Fetch user by email (emails stored lowercase in this app)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

    // Verify password using hash; also allow a plaintext match for legacy/admin accounts
    if($user && (password_verify($password, $user['password']) || $password === $user['password'])) {
        // reset throttle counter (valid credentials)
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_attempt_time'] = 0;

        $account_status = $user['account_status'] ?? 'active';

        // sellers must be approved by admin
        if(($user['user_type'] ?? '') === 'seller' && $account_status === 'pending') {
            $info = 'Your account is on review. We will update you by email. Thank you.';

            // Best-effort: send (throttled) reminder email on login attempt
            try {
                $last = $user['review_email_last_sent_at'] ?? null;
                $shouldSend = true;
                if($last) {
                    $ts = strtotime((string)$last);
                    if($ts && (time() - $ts) < 86400) { // 24 hours
                        $shouldSend = false;
                    }
                }

                if($shouldSend) {
                    app_send_mail_text(
                        $user['email'],
                        "Your seller account is under review",
                        "Your seller account is still under review by our admin team.\n\n" .
                        "We will update you by email once your account is approved.\n\n" .
                        "Thank you."
                    );

                    $upd = $pdo->prepare("UPDATE users SET review_email_last_sent_at = NOW(), review_email_sent_count = review_email_sent_count + 1 WHERE id = ?");
                    $upd->execute([$user['id']]);
                }
            } catch(Throwable $e) {
                // ignore (older schema / no columns / mail issues)
            }

            // do not login
        } elseif($account_status === 'rejected') {
            // behave like account doesn't exist
            $error = 'Invalid email or password';
        } else {
            login_user_into_session($pdo, $user);
            redirect_after_login($user['user_type']);
        }
    } else {
        $error = 'Invalid email or password';
    }
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ShopHub</title>
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
                        <h2 class="text-center mb-4">Login</h2>

                        <?php if($info): ?>
                            <div class="alert alert-info"><?= htmlspecialchars($info) ?></div>
                        <?php endif; ?>

                        <?php if($error): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-warning w-100 mb-3">Login</button>
                        </form>

                        <a href="google-login.php" class="btn btn-outline-dark w-100 mb-3">
                            <i class="fab fa-google"></i> Continue with Google
                        </a>

                        <div class="text-center">
                            <p>Don't have an account? <a href="register.php">Register here</a></p>
                            <p><a href="forgot-password.php">Forgot Password?</a></p>
                            <p class="text-muted small">Demo: admin@shophub.com / admin123</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
