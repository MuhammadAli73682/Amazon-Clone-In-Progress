<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'seller') {
    header('Location: ../login.php');
    exit;
}

$seller_id = $_SESSION['user_id'];
$product_id = $_GET['id'] ?? 0;

// Get product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND seller_id = ?");
$stmt->execute([$product_id, $seller_id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: products.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_csrf_or_fail();

    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $category = $_POST['category'];
    $status = $_POST['status'] ?? 'active';
    $language = $_POST['language'] ?? ($product['language'] ?? 'en');
    $currency = $_POST['currency'] ?? ($product['currency'] ?? 'USD');
    $reviews_enabled = isset($_POST['reviews_enabled']) ? 1 : 0;

    // handle image upload if present
    $imagePath = $product['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['image']['tmp_name'];
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $newName = 'prod_' . $product_id . '_' . time() . '.' . $ext;
        $dest = __DIR__ . '/../assets/images/products/' . $newName;
        if (move_uploaded_file($tmp, $dest)) {
            $imagePath = 'assets/images/products/' . $newName;
        }
    }

    if (!in_array($status, ['active', 'inactive'], true)) {
        $status = 'active';
    }
    $stock = max(0, (int) $stock);
    if ($stock <= 0) {
        $status = 'inactive';
    }
    $language = strtolower(trim((string) $language));
    if ($language === '' || strlen($language) > 10) {
        $language = 'en';
    }
    $currency = strtoupper(trim((string) $currency));
    if ($currency === '' || strlen($currency) > 3) {
        $currency = 'USD';
    }

    $stmt = $pdo->prepare("
        UPDATE products
        SET name = ?, description = ?, price = ?, stock = ?, category = ?, status = ?, image = ?, language = ?, currency = ?, reviews_enabled = ?
        WHERE id = ? AND seller_id = ?
    ");
    if ($stmt->execute([$name, $description, $price, $stock, $category, $status, $imagePath, $language, $currency, $reviews_enabled, $product_id, $seller_id])) {
        $success = 'Product updated successfully!';
        $product = array_merge($product, $_POST);
        $product['image'] = $imagePath;
        $product['reviews_enabled'] = $reviews_enabled;
        $product['stock'] = $stock;
        $product['status'] = $status;
    } else {
        $error = 'Failed to update product';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - ShopHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <?php include '../includes/backend-header.php'; ?>

    <div class="container my-5">
        <h2 class="mb-4">Edit Product</h2>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <div class="mb-3">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="name" class="form-control"
                            value="<?= htmlspecialchars($product['name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Current Image</label><br>
                        <?php if (!empty($product['image'])): ?>
                            <img src="../<?= htmlspecialchars($product['image']) ?>" alt="Product image"
                                style="max-width:150px; border:1px solid #ccc;">
                        <?php else: ?>
                            <span class="text-muted">No image uploaded</span>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Change Image</label>
                        <input type="file" name="image" accept="image/*" class="form-control">
                        <small class="text-muted">Leave blank to keep existing image.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4"
                            required><?= htmlspecialchars($product['description']) ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Price ($)</label>
                            <input type="number" name="price" class="form-control" step="0.01" min="0"
                                value="<?= $product['price'] ?>" required>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Stock</label>
                            <input type="number" name="stock" class="form-control" min="0"
                                value="<?= $product['stock'] ?>" required>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select" required>
                                <option value="Electronics" <?= $product['category'] == 'Electronics' ? 'selected' : '' ?>>
                                    Electronics</option>
                                <option value="Fashion" <?= $product['category'] == 'Fashion' ? 'selected' : '' ?>>Fashion
                                </option>
                                <option value="Books" <?= $product['category'] == 'Books' ? 'selected' : '' ?>>Books
                                </option>
                                <option value="Home & Kitchen" <?= $product['category'] == 'Home & Kitchen' ? 'selected' : '' ?>>Home & Kitchen</option>
                                <option value="Sports" <?= $product['category'] == 'Sports' ? 'selected' : '' ?>>Sports
                                </option>
                                <option value="Toys" <?= $product['category'] == 'Toys' ? 'selected' : '' ?>>Toys</option>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <option value="active" <?= $product['status'] == 'active' ? 'selected' : '' ?>>Active
                                </option>
                                <option value="inactive" <?= $product['status'] == 'inactive' ? 'selected' : '' ?>>Inactive
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Language</label>
                            <select name="language" class="form-select" required>
                                <option value="en" <?= ($product['language'] ?? 'en') == 'en' ? 'selected' : '' ?>>English
                                </option>
                                <option value="ur" <?= ($product['language'] ?? '') == 'ur' ? 'selected' : '' ?>>Urdu
                                </option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Currency</label>
                            <select name="currency" class="form-select" required>
                                <option value="USD" <?= ($product['currency'] ?? 'USD') == 'USD' ? 'selected' : '' ?>>USD
                                </option>
                                <option value="PKR" <?= ($product['currency'] ?? '') == 'PKR' ? 'selected' : '' ?>>PKR
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input class="form-check-input" type="checkbox" name="reviews_enabled" id="reviews_enabled"
                            <?= !empty($product['reviews_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="reviews_enabled">Enable reviews</label>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Product
                    </button>
                    <a href="products.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>