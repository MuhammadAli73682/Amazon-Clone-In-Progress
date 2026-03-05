<?php
session_start();
require_once 'config/database.php';

// Build query
$where = ["p.status = 'active'"];
$params = [];

// determine sort parameter
$sort = $_GET['sort'] ?? '';
$orderClause = "p.created_at DESC"; // default
if($sort == 'price_asc') {
    $orderClause = "p.price ASC";
} elseif($sort == 'price_desc') {
    $orderClause = "p.price DESC";
} elseif($sort == 'rating') {
    $orderClause = "avg_rating DESC";
} elseif($sort == 'popularity') {
    $orderClause = "review_count DESC";
}

if(isset($_GET['search']) && !empty($_GET['search'])) {
    $where[] = "(name LIKE ? OR description LIKE ?)";
    $search = '%' . $_GET['search'] . '%';
    $params[] = $search;
    $params[] = $search;
}

if(isset($_GET['category']) && !empty($_GET['category'])) {
    $where[] = "category = ?";
    $params[] = $_GET['category'];
}

if(isset($_GET['seller_id']) && is_numeric($_GET['seller_id'])) {
    $where[] = "p.seller_id = ?";
    $params[] = $_GET['seller_id'];
}

// pagination parameters
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// include average rating and review count
$sql = "SELECT p.*, u.shop_name, 
               COALESCE(AVG(r.rating),0) as avg_rating, 
               COUNT(r.id) as review_count
        FROM products p
        JOIN users u ON p.seller_id = u.id
        LEFT JOIN reviews r ON r.product_id = p.id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY p.id
        ORDER BY $orderClause
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// get total count for pagination
$countSql = "SELECT COUNT(DISTINCT p.id) as cnt FROM products p WHERE " . implode(' AND ', $where);
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalCount = $countStmt->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

// if logged in, get wishlist product ids for current user
$wishlistIds = [];
if(isset($_SESSION['user_id'])) {
    $wid = $_SESSION['user_id'];
    $wstmt = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $wstmt->execute([$wid]);
    $wishlistIds = array_column($wstmt->fetchAll(), 'product_id');
}

// Get categories (limit to seller when filtering by shop)
if(isset($_GET['seller_id']) && is_numeric($_GET['seller_id'])) {
    $stmt2 = $pdo->prepare("SELECT DISTINCT category FROM products WHERE status = 'active' AND seller_id = ?");
    $stmt2->execute([$_GET['seller_id']]);
    $categories = $stmt2->fetchAll();
} else {
    $categories = $pdo->query("SELECT DISTINCT category FROM products WHERE status = 'active'")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - ShopHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container my-5">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3">
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Categories</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="products.php" class="list-group-item">All Products</a>
                        <?php foreach($categories as $cat): ?>
                        <?php 
$linkParams = ['category'=> $cat['category']]; 
if(isset($_GET['seller_id'])) { $linkParams['seller_id'] = $_GET['seller_id']; }
if(isset($_GET['sort'])) { $linkParams['sort'] = $_GET['sort']; }
?>
                        <a href="products.php?<?= http_build_query($linkParams) ?>" 
                           class="list-group-item <?= (isset($_GET['category']) && $_GET['category'] == $cat['category']) ? 'active' : '' ?>">
                            <?= htmlspecialchars($cat['category']) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Products -->
            <div class="col-md-9">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="mb-0">
                        <?php if(isset($_GET['search'])): ?>
                            Search Results for "<?= htmlspecialchars($_GET['search']) ?>"
                        <?php elseif(isset($_GET['seller_id']) && !empty($products)): ?>
                            Products from "<?= htmlspecialchars($products[0]['shop_name']) ?>"
                        <?php elseif(isset($_GET['category'])): ?>
                            <?= htmlspecialchars($_GET['category']) ?>
                        <?php else: ?>
                            All Products
                        <?php endif; ?>
                    </h2>
                    <div>
                        <label>Sort by:</label>
                        <select id="sortSelect" class="form-select form-select-sm d-inline-block" style="width:auto;">
                            <option value="">Newest</option>
                            <option value="price_asc" <?= $sort=='price_asc' ? 'selected' : '' ?>>Price: Low &uarr;</option>
                            <option value="price_desc" <?= $sort=='price_desc' ? 'selected' : '' ?>>Price: High &darr;</option>
                            <option value="rating" <?= $sort=='rating' ? 'selected' : '' ?>>Rating</option>
                            <option value="popularity" <?= $sort=='popularity' ? 'selected' : '' ?>>Popularity</option>
                        </select>
                    </div>
                </div>
                
                <p class="text-muted"><?= $totalCount ?> products found</p>
                
                <div class="row">
                    <?php foreach($products as $product): ?>
                    <?php // ensure shop filter is preserved when listing products? handled in query ?>
                    <div class="col-md-4 col-sm-6 mb-4">
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
                                <p class="seller">by <a href="products.php?seller_id=<?= $product['seller_id'] ?>"><?= htmlspecialchars($product['shop_name']) ?></a></p>
                                <button class="btn btn-warning btn-sm add-to-cart" data-id="<?= $product['id'] ?>">
                                    <i class="fas fa-cart-plus"></i> Add to Cart
                                </button>
                                <button class="btn btn-sm add-to-wishlist <?= in_array($product['id'], $wishlistIds) ? 'btn-danger' : 'btn-outline-danger' ?>" data-id="<?= $product['id'] ?>">
                                    <i class="fas fa-heart"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if(empty($products)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">No products found</div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- pagination -->
                <?php if($totalPages > 1): ?>
                <nav>
                    <ul class="pagination">
                        <?php for($p=1; $p<=$totalPages; $p++): ?>
                        <?php
                        $paramsForPage = $_GET;
                        $paramsForPage['page'] = $p;
                        ?>
                        <li class="page-item <?= $p==$page ? 'active' : '' ?>">
                            <a class="page-link" href="products.php?<?= http_build_query($paramsForPage) ?>"><?= $p ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $('#sortSelect').change(function() {
            let params = new URLSearchParams(window.location.search);
            const val = $(this).val();
            if(val) {
                params.set('sort', val);
            } else {
                params.delete('sort');
            }
            // keep category and seller_id and search
            window.location.search = params.toString();
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
