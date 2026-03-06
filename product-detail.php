<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';

$product_id = $_GET['id'] ?? 0;

// fetch product and compute average rating/count
$stmt = $pdo->prepare("SELECT p.*, u.shop_name, u.email, 
                               COALESCE(AVG(r.rating),0) as avg_rating, 
                               COUNT(r.id) as review_count
                        FROM products p
                        JOIN users u ON p.seller_id = u.id
                        LEFT JOIN reviews r ON r.product_id = p.id
                        WHERE p.id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if(!$product) {
    header('Location: products.php');
    exit;
}

// handle review form
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rating']) && isset($_POST['comment']) && isset($_SESSION['user_id'])) {
    require_csrf_or_fail();

    $rating = max(1, min(5, intval($_POST['rating'])));
    $comment = trim($_POST['comment']);
    $user_id = $_SESSION['user_id'];
    if($comment !== '') {
        $stmt = $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
        $stmt->execute([$product_id, $user_id, $rating, $comment]);
        // reload page to show new review
        header('Location: product-detail.php?id=' . $product_id);
        exit;
    }
}

// Get reviews
$stmt = $pdo->prepare("SELECT r.*, u.full_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.created_at DESC");
$stmt->execute([$product_id]);
$reviews = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - ShopHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container my-5">
        <div class="row">
            <div class="col-md-6 position-relative">
                <?php if(isset($_SESSION['user_id']) && $_SESSION['user_type']==='seller' && $_SESSION['user_id']==$product['seller_id']): ?>
                    <a href="seller/edit-product.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-outline-primary position-absolute" style="top:10px; right:10px; z-index:10;">Edit</a>
                <?php endif; ?>
                <img src="<?= htmlspecialchars($product['image']) ?>" class="product-detail-img" alt="<?= htmlspecialchars($product['name']) ?>">
            </div>
            
            <div class="col-md-6">
                <h1><?= htmlspecialchars($product['name']) ?></h1>
                
                <div class="rating mb-2">
                    <?php 
                    $avg = round($product['avg_rating']);
                    for($i=1;$i<=5;$i++) {
                        if($i <= $avg) echo '<i class="fas fa-star"></i>';
                        else echo '<i class="fas fa-star text-muted"></i>';
                    }
                    ?>
                    <span>(<?= $product['review_count'] ?> reviews)</span>
                </div>
                
                <h2 class="price mb-3">$<?= number_format($product['price'], 2) ?></h2>
                
                <p class="lead"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                
                <div class="mb-3">
                    <strong>Category:</strong> <?= htmlspecialchars($product['category']) ?>
                </div>
                
                <div class="mb-3">
                    <strong>Sold by:</strong> <?= htmlspecialchars($product['shop_name']) ?>
                </div>
                
                <div class="mb-3">
                    <strong>Stock:</strong> 
                    <?php if($product['stock'] > 0): ?>
                        <span class="text-success"><?= $product['stock'] ?> available</span>
                    <?php else: ?>
                        <span class="text-danger">Out of stock</span>
                    <?php endif; ?>
                </div>
                
                <?php if($product['stock'] > 0): ?>
                <button class="btn btn-warning btn-lg add-to-cart" data-id="<?= $product['id'] ?>">
                    <i class="fas fa-cart-plus"></i> Add to Cart
                </button>
                <?php $inWishlist = false;
                if(isset($_SESSION['user_id'])) {
                    $wstmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
                    $wstmt->execute([$_SESSION['user_id'], $product['id']]);
                    $inWishlist = (bool)$wstmt->fetch();
                }
                ?>
                <button class="btn btn-lg add-to-wishlist <?= $inWishlist ? 'btn-danger remove-mode' : 'btn-outline-danger' ?>" data-id="<?= $product['id'] ?>">
                    <i class="fas fa-heart"></i> <?= $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist' ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Reviews Section -->
        <div class="row mt-5">
            <div class="col-12">
                <h3>Customer Reviews</h3>
                <hr>
                
                <?php if(empty($reviews)): ?>
                <p class="text-muted">No reviews yet. Be the first to review this product!</p>
                <?php else: ?>
                    <?php foreach($reviews as $review): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <h5><?= htmlspecialchars($review['full_name']) ?></h5>
                                <div class="rating">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?= $i <= $review['rating'] ? '' : 'text-muted' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <p class="text-muted small"><?= date('F j, Y', strtotime($review['created_at'])) ?></p>
                            <p><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <!-- review submission -->
        <?php if(isset($_SESSION['user_id'])): ?>
        <div class="row mt-4">
            <div class="col-12">
                <h4>Leave a Review</h4>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <div class="mb-3">
                        <label class="form-label">Rating</label><br>
                        <?php for($r=1;$r<=5;$r++): ?>
                        <input type="radio" name="rating" value="<?= $r ?>" <?= $r==5 ? 'checked' : '' ?>> <?= $r ?>
                        <?php endfor; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Comment</label>
                        <textarea name="comment" class="form-control" rows="3" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <p class="text-muted">Please <a href="login.php">login</a> to leave a review.</p>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
