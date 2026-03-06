<?php
session_start();
require_once 'config/database.php';

// Get featured products along with ratings
$stmt = $pdo->query("SELECT p.*, u.shop_name, 
                            COALESCE(AVG(r.rating),0) as avg_rating,
                            COUNT(r.id) as review_count
                     FROM products p
                     JOIN users u ON p.seller_id = u.id
                     LEFT JOIN reviews r ON r.product_id = p.id
                     WHERE p.status = 'active'
                     GROUP BY p.id
                     ORDER BY p.created_at DESC LIMIT 12");
$products = $stmt->fetchAll();

// Get categories
$categories = $pdo->query("SELECT DISTINCT category FROM products WHERE status = 'active'")->fetchAll();

// Get shops (sellers with at least one active product)
$shops = $pdo->prepare("SELECT DISTINCT u.id, u.shop_name FROM users u JOIN products p ON p.seller_id = u.id WHERE u.user_type = 'seller' AND u.shop_name <> '' AND p.status = 'active'");
$shops->execute();
$shops = $shops->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShopHub - Online Shopping</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <!-- Banner Carousel -->
    <div id="mainCarousel" class="carousel slide" data-bs-ride="carousel">
      <div class="carousel-inner">
        <div class="carousel-item active">
          <img src="https://source.unsplash.com/1600x600/?shopping,store" class="d-block w-100" alt="Banner 1">
        </div>
        <div class="carousel-item">
          <img src="https://source.unsplash.com/1600x600/?fashion,clothes" class="d-block w-100" alt="Banner 2">
        </div>
        <div class="carousel-item">
          <img src="https://source.unsplash.com/1600x600/?electronics,gadgets" class="d-block w-100" alt="Banner 3">
        </div>
      </div>
      <button class="carousel-control-prev" type="button" data-bs-target="#mainCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
      </button>
      <button class="carousel-control-next" type="button" data-bs-target="#mainCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
      </button>
    </div>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="display-4">Welcome to ShopHub</h1>
                    <p class="lead">Discover millions of products from trusted sellers</p>
                    <a href="products.php" class="btn btn-warning btn-lg">Shop Now</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Categories -->
    <div class="container my-5">
        <h2 class="mb-4">Shop by Category</h2>
        <div class="row">
            <?php
            // simple image mapping for categories; override or expand as needed
            $catImages = [
                'Electronics' => 'https://source.unsplash.com/400x400/?electronics',
                'Fashion'     => 'https://source.unsplash.com/400x400/?fashion',
                'Home'        => 'https://source.unsplash.com/400x400/?home',
                'Beauty'      => 'https://source.unsplash.com/400x400/?beauty',
            ];
            ?>
            <?php foreach($categories as $cat): ?>
            <div class="col-md-3 col-6 mb-3">
                <a href="products.php?category=<?= urlencode($cat['category']) ?>" class="category-card">
                    <div class="card text-center">
                        <?php $img = $catImages[$cat['category']] ?? null; ?>
                        <?php if($img): ?>
                        <img src="<?= $img ?>" class="card-img-top" alt="<?= htmlspecialchars($cat['category']) ?>">
                        <?php endif; ?>
                        <div class="card-body">
                            <h5><?= htmlspecialchars($cat['category']) ?></h5>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Shops -->
    <div class="container my-5">
        <h2 class="mb-4">Shop by Store</h2>
        <div class="row">
            <?php foreach($shops as $shop): ?>
            <div class="col-md-3 col-6 mb-3">
                <a href="products.php?seller_id=<?= $shop['id'] ?>" class="category-card">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-store fa-3x mb-3"></i>
                            <h5><?= htmlspecialchars($shop['shop_name']) ?></h5>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Featured Products -->
    <div class="container my-5">
        <h2 class="mb-4">Featured Products</h2>
        <div class="row">
            <?php foreach($products as $product): ?>
            <div class="col-md-3 col-sm-6 mb-4">
                <div class="product-card">
                    <a href="product-detail.php?id=<?= $product['id'] ?>">
                        <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                    </a>
                    <div class="product-info">
                        <h5><?= htmlspecialchars($product['name']) ?></h5>
                        <div class="rating">
                            <?php 
                                $avg = round($product['avg_rating']);
                                for($i=1;$i<=5;$i++) {
                                    if($i <= $avg) echo '<i class="fas fa-star"></i>';
                                    else echo '<i class="fas fa-star text-muted"></i>';
                                }
                            ?>
                            <span>(<?= $product['review_count'] ?>)</span>
                        </div>
                        <p class="price">$<?= number_format($product['price'], 2) ?></p>
                        <p class="seller">by <?= htmlspecialchars($product['shop_name']) ?></p>
                        <button class="btn btn-warning btn-sm add-to-cart" data-id="<?= $product['id'] ?>">
                            <i class="fas fa-cart-plus"></i> Add to Cart
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
