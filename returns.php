<?php
session_start();
require_once 'config/database.php';

$success = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_number = trim($_POST['order_number']);
    $product_name = trim($_POST['product_name']);
    $reason = trim($_POST['reason']);

    if($order_number === '' || $product_name === '' || $reason === '') {
        $error = 'All fields except image are required.';
    } else {
        $imagePath = null;
        // handle upload if provided
        if(isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/assets/uploads/returns/';
            if(!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $filename = time() . '_' . basename($_FILES['product_image']['name']);
            $targetPath = $uploadDir . $filename;
            if(move_uploaded_file($_FILES['product_image']['tmp_name'], $targetPath)) {
                $imagePath = 'assets/uploads/returns/' . $filename;
            } else {
                $error = 'Failed to upload image.';
            }
        }

        if(!$error) {
            // try to associate with an existing product/seller if possible
            $prodId = null;
            $sellerId = null;
            $pstmt = $pdo->prepare("SELECT id, seller_id FROM products WHERE name = ? LIMIT 1");
            $pstmt->execute([$product_name]);
            if($pinfo = $pstmt->fetch()) {
                $prodId = $pinfo['id'];
                $sellerId = $pinfo['seller_id'];
            }

            // record this return request in the database
            $stmt = $pdo->prepare("INSERT INTO return_requests (order_number, product_name, product_id, seller_id, reason, image) VALUES (?, ?, ?, ?, ?, ?)");
            if($stmt->execute([$order_number, $product_name, $prodId, $sellerId, $reason, $imagePath])) {
                $success = 'Return request submitted successfully.';
            } else {
                $error = 'Failed to save return request.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returns - ShopHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container my-5">
        <h2 class="mb-4">Product Return</h2>
        <?php if($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="order_number" class="form-label">Order Number</label>
                <input type="text" name="order_number" id="order_number" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="product_name" class="form-label">Product Name</label>
                <input type="text" name="product_name" id="product_name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="reason" class="form-label">Reason for Return</label>
                <textarea name="reason" id="reason" rows="4" class="form-control" required></textarea>
            </div>
            <div class="mb-3">
                <label for="product_image" class="form-label">Product Image (optional)</label>
                <input type="file" name="product_image" id="product_image" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Submit Return</button>
        </form>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>