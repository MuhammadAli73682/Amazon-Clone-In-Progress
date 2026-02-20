<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$success = '';
$error = '';

// if admin, collect site-wide statistics
if($user['user_type'] === 'admin') {
    $stats = [];
    $s = $pdo->query("SELECT COUNT(*) as cnt FROM products WHERE status = 'active'");
    $stats['live_products'] = $s->fetch()['cnt'];
    $s = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE user_type = 'buyer'");
    $stats['buyers'] = $s->fetch()['cnt'];
    $s = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE user_type = 'seller'");
    $stats['sellers'] = $s->fetch()['cnt'];
    $s = $pdo->query("SELECT COUNT(*) as cnt FROM orders");
    $stats['orders'] = $s->fetch()['cnt'];
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    
    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?");
    if($stmt->execute([$full_name, $phone, $address, $user_id])) {
        $success = 'Profile updated successfully!';
        $_SESSION['full_name'] = $full_name;
    } else {
        $error = 'Failed to update profile';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - ShopHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container my-5">
        <h2 class="mb-4">My Profile</h2>
        
        <?php if($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                                <small class="text-muted">Email cannot be changed</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                            </div>
                            
                            <?php if($user['user_type'] == 'seller'): ?>
                            <div class="mb-3">
                                <label class="form-label">Shop Name</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['shop_name']) ?>" disabled>
                            </div>
                            <?php endif; ?>
                            
                            <button type="submit" class="btn btn-warning">Update Profile</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5>Account Information</h5>
                        <hr>
                        <p><strong>Account Type:</strong> <?= ucfirst($user['user_type']) ?></p>
                        <p><strong>Member Since:</strong> <?= date('F Y', strtotime($user['created_at'])) ?></p>
                    </div>
                </div>
                <?php if(isset($stats)): ?>
                <div class="card mt-4">
                    <div class="card-body">
                        <h5>Site Statistics</h5>
                        <hr>
                        <p><strong>Live Products:</strong> <?= $stats['live_products'] ?></p>
                        <p><strong>Buyers:</strong> <?= $stats['buyers'] ?></p>
                        <p><strong>Sellers:</strong> <?= $stats['sellers'] ?></p>
                        <p><strong>Total Orders:</strong> <?= $stats['orders'] ?></p>
                        <a href="admin/dashboard.php" class="btn btn-sm btn-primary mt-2">Go to Admin Dashboard</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
