<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';

// Redirect if already logged in
if(isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

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
            // reset throttle counter
            $_SESSION['login_attempts'] = 0;
            $_SESSION['last_attempt_time'] = 0;

        // set fundamental session values
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['full_name'] = $user['full_name'];

        // merge any guest session cart into the user's database cart
        if(!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
            foreach($_SESSION['cart'] as $pid => $qty) {
                $stmt2 = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt2->execute([$user['id'], $pid]);
                $exist = $stmt2->fetch();
                if($exist) {
                    $upd = $pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE id = ?");
                    $upd->execute([$qty, $exist['id']]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                    $ins->execute([$user['id'], $pid, $qty]);
                }
            }
            unset($_SESSION['cart']);
        }

        header('Location: index.php');
        exit;
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