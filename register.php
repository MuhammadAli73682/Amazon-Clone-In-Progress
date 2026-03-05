<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';

if(isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

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
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if($stmt->fetch()) {
        $error = 'Email already registered';
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (email, password, full_name, user_type, shop_name, phone) VALUES (?, ?, ?, ?, ?, ?)");
        if($stmt->execute([$email, $password, $full_name, $user_type, $shop_name, $phone])) {
            // automatically log the new user in
            $newId = $pdo->lastInsertId();
            $_SESSION['user_id'] = $newId;
            $_SESSION['user_type'] = $user_type;
            $_SESSION['full_name'] = $full_name;

            // merge any session cart
            if(!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                foreach($_SESSION['cart'] as $pid => $qty) {
                    $stmt2 = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
                    $stmt2->execute([$newId, $pid]);
                    $exist = $stmt2->fetch();
                    if($exist) {
                        $upd = $pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE id = ?");
                        $upd->execute([$qty, $exist['id']]);
                    } else {
                        $ins = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                        $ins->execute([$newId, $pid, $qty]);
                    }
                }
                unset($_SESSION['cart']);
            }

            // redirect to home
            header('Location: index.php');
            exit;
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
            } else {
                $('#sellerFields').hide();
            }
        });
    </script>
</body>
</html>
