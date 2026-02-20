<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT w.product_id, p.*, u.shop_name FROM wishlist w JOIN products p ON w.product_id = p.id JOIN users u ON p.seller_id = u.id WHERE w.user_id = ?");
$stmt->execute([$user_id]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wishlist - ShopHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container my-5">
        <h2 class="mb-4">Your Wishlist</h2>
        <?php if(empty($items)): ?>
            <div class="alert alert-info">Your wishlist is empty. <a href="products.php">Browse products</a></div>
        <?php else: ?>
            <div class="row">
                <?php foreach($items as $product): ?>
                <div class="col-md-4 col-sm-6 mb-4">
                    <div class="product-card">
                        <a href="product-detail.php?id=<?= $product['id'] ?>">
                            <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                        </a>
                        <div class="product-info">
                            <h5><?= htmlspecialchars($product['name']) ?></h5>
                            <p class="price">$<?= number_format($product['price'], 2) ?></p>
                            <p class="seller">by <?= htmlspecialchars($product['shop_name']) ?></p>
                            <button class="btn btn-warning btn-sm add-to-cart" data-id="<?= $product['id'] ?>">
                                <i class="fas fa-cart-plus"></i> Add to Cart
                            </button>
                            <button class="btn btn-outline-danger btn-sm remove-from-wishlist" data-product-id="<?= $product['id'] ?>">
                                <i class="fas fa-heart-broken"></i> Remove
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>