<?php
session_start();
require_once 'config/database.php';

// determine whether we have a logged-in user or guest
$user_id = $_SESSION['user_id'] ?? null;

// Fetch cart items from database for logged-in users, session for guests
if($user_id) {
    $stmt = $pdo->prepare("
        SELECT c.*, p.name, p.price, p.image, p.stock, u.shop_name 
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        JOIN users u ON p.seller_id = u.id 
        WHERE c.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll();
} else {
    $cart_items = [];
    if(!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        $session_cart = $_SESSION['cart'];
        $product_ids = array_keys($session_cart);
        if(!empty($product_ids)) {
            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
            $stmt = $pdo->prepare("SELECT p.*, u.shop_name FROM products p JOIN users u ON p.seller_id = u.id WHERE p.id IN ($placeholders)");
            $stmt->execute($product_ids);
            $products = $stmt->fetchAll();
            foreach($products as $p) {
                $cart_items[] = [
                    'id' => null,
                    'product_id' => $p['id'],
                    'quantity' => $session_cart[$p['id']],
                    'name' => $p['name'],
                    'price' => $p['price'],
                    'image' => $p['image'],
                    'stock' => $p['stock'],
                    'shop_name' => $p['shop_name']
                ];
            }
        }
    }
}

$total = 0;
foreach($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - ShopHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container my-5">
        <h2 class="mb-4">Shopping Cart</h2>
        <?php if(!$user_id): ?>
        <div class="alert alert-info">You are viewing the cart as a guest. Your items will be saved temporarily; you'll be asked for your details at checkout.</div>
        <?php endif; ?>
        
        <?php if(empty($cart_items)): ?>
        <div class="alert alert-info">Your cart is empty. <a href="products.php">Continue shopping</a></div>
        <?php else: ?>
        
        <div class="row">
            <div class="col-md-8">
                <?php foreach($cart_items as $item): ?>
                <div class="cart-item">
                    <div class="row align-items-center">
                        <div class="col-md-2">
                            <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                        </div>
                        <div class="col-md-4">
                            <h5><?= htmlspecialchars($item['name']) ?></h5>
                            <p class="text-muted">Sold by: <?= htmlspecialchars($item['shop_name']) ?></p>
                        </div>
                        <div class="col-md-2">
                            <p class="price mb-0">$<?= number_format($item['price'], 2) ?></p>
                        </div>
                        <div class="col-md-2">
                            <input type="number" class="form-control update-quantity" 
                                   value="<?= $item['quantity'] ?>" 
                                   min="1" max="<?= $item['stock'] ?>"
                                   data-cart-id="<?= $item['id'] ?? '' ?>" data-product-id="<?= $item['product_id'] ?>">
                        </div>
                        <div class="col-md-2 text-end">
                            <button class="btn btn-danger btn-sm remove-from-cart" data-cart-id="<?= $item['id'] ?? '' ?>" data-product-id="<?= $item['product_id'] ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h4>Order Summary</h4>
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span>$<?= number_format($total, 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Shipping:</span>
                            <span>$5.00</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total:</strong>
                            <strong>$<?= number_format($total + 5, 2) ?></strong>
                        </div>
                        <a href="checkout.php" class="btn btn-warning w-100">Proceed to Checkout</a>
                    </div>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
