<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/auth.php';
require_once 'includes/mailer.php';

if(isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if(!empty($_GET['google_error'])) {
    $error = (string)$_GET['google_error'];
}

// allow prefilling account type via query string
$user_type = $_GET['type'] ?? 'buyer';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_csrf_or_fail();

    // trim/normalize email
    $email = strtolower(trim($_POST['email']));
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $full_name = $_POST['full_name'];
    $user_type = $_POST['user_type'];
    $shop_name = $_POST['shop_name'] ?? null;
    $phone = $_POST['phone'] ?? null;
    
    // Check if email exists (emails stored lowercase)
    $existing = null;
    try {
        $stmt = $pdo->prepare("SELECT id, account_status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existing = $stmt->fetch();
    } catch(PDOException $e) {
        // fallback for older schemas
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        $existing = $row ? ['id' => $row['id'], 'account_status' => null] : null;
    }

    if($existing) {
        if(($existing['account_status'] ?? '') === 'rejected') {
            $error = 'This email is not eligible for registration.';
        } else {
            $error = 'Email already registered';
        }
    } else {
        $account_status = ($user_type === 'seller') ? 'pending' : 'active';

        $newId = null;
        try {
            $stmt = $pdo->prepare("INSERT INTO users (email, password, full_name, user_type, account_status, shop_name, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ok = $stmt->execute([$email, $password, $full_name, $user_type, $account_status, $shop_name, $phone]);
        } catch(PDOException $e) {
            // fallback for older schemas (no account_status)
            $stmt = $pdo->prepare("INSERT INTO users (email, password, full_name, user_type, shop_name, phone) VALUES (?, ?, ?, ?, ?, ?)");
            $ok = $stmt->execute([$email, $password, $full_name, $user_type, $shop_name, $phone]);
        }

        if($ok) {
            $newId = $pdo->lastInsertId();

            // Sellers must be reviewed by admin before they can login
            if($user_type === 'seller') {
                $subject = "New seller registration pending review";
                $body = "A new seller has registered and is pending review.\n\n" .
                        "Name: {$full_name}\n" .
                        "Email: {$email}\n" .
                        "Shop: " . ($shop_name ?: '-') . "\n" .
                        "Phone: " . ($phone ?: '-') . "\n\n" .
                        "Review: " . BASE_URL . "/admin/users.php\n";
                app_notify_admins($pdo, $subject, $body);

                app_send_mail_text(
                    $email,
                    "Your seller account is under review",
                    "Thanks for registering as a seller on ShopHub.\n\n" .
                    "Your account is currently under review by our admin team.\n" .
                    "We will update you by email once your account is approved.\n\n" .
                    "Thank you."
                );

                header('Location: login.php?review=1');
                exit;
            }

            login_user_into_session($pdo, [
                'id' => $newId,
                'user_type' => $user_type,
                'full_name' => $full_name,
            ]);
            redirect_after_login($user_type);
        } else {
            $error = 'Registration failed';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ShopHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Register</h2>
                        
                        <?php if($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <?php if($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" id="registerForm">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <div class="mb-3">
                                <label class="form-label">Account Type</label>
                                <select name="user_type" class="form-select" id="userType" required>
                                    <option value="buyer" <?= $user_type == 'buyer' ? 'selected' : '' ?>>Buyer</option>
                                    <option value="seller" <?= $user_type == 'seller' ? 'selected' : '' ?>>Seller</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" minlength="6" required>
                            </div>
                            
                            <div id="sellerFields" style="display: <?= $user_type == 'seller' ? 'block' : 'none' ?>;">
                                <div class="mb-3">
                                    <label class="form-label">Shop Name</label>
                                    <input type="text" name="shop_name" class="form-control" <?= $user_type == 'seller' ? 'required' : '' ?> >
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" name="phone" class="form-control">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-warning w-100 mb-3">Register</button>
                        </form>

                        <div id="googleBuyerSignup" style="display: <?= $user_type == 'seller' ? 'none' : 'block' ?>;">
                            <a href="google-login.php?intent=register" class="btn btn-outline-dark w-100 mb-3">
                                <i class="fab fa-google"></i> Sign up with Google
                            </a>
                        </div>
                        <p id="googleSellerNote" class="text-muted small text-center mb-3" style="display: <?= $user_type == 'seller' ? 'block' : 'none' ?>;">
                            Google signup currently creates buyer accounts only. Sellers should use the form above.
                        </p>
                        
                        <div class="text-center">
                            <p>Already have an account? <a href="login.php">Login here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $('#userType').change(function() {
            if($(this).val() == 'seller') {
                $('#sellerFields').show();
                $('#googleBuyerSignup').hide();
                $('#googleSellerNote').show();
            } else {
                $('#sellerFields').hide();
                $('#googleBuyerSignup').show();
                $('#googleSellerNote').hide();
            }
        });
    </script>
</body>
</html>
