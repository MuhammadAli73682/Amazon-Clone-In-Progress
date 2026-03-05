<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$buyer_id = intval($_SESSION['user_id']);

$success = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_or_fail();

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
                mkdir($uploadDir, 0755, true);
            }
            $allowedMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['product_image']['tmp_name']);
            finfo_close($finfo);
            if(!isset($allowedMime[$mime])) {
                $error = 'Only JPG, PNG, or WEBP images are allowed.';
            }
            if($_FILES['product_image']['size'] > (2 * 1024 * 1024)) {
                $error = 'Image must be 2MB or smaller.';
            }

            $filename = bin2hex(random_bytes(16)) . '.' . ($allowedMime[$mime] ?? 'jpg');
            $targetPath = $uploadDir . $filename;
            if(!$error && move_uploaded_file($_FILES['product_image']['tmp_name'], $targetPath)) {
                $imagePath = 'assets/uploads/returns/' . $filename;
            } elseif(!$error) {
                $error = 'Failed to upload image.';
            }
        }

        if(!$error) {
            // ensure the order belongs to current buyer and product exists in that order
            $orderId = intval($order_number);
            if($orderId <= 0) {
                $error = 'Please provide a valid order number.';
            } else {
                $pstmt = $pdo->prepare("
                    SELECT oi.product_id, oi.seller_id, p.name
                    FROM orders o
                    JOIN order_items oi ON oi.order_id = o.id
                    JOIN products p ON p.id = oi.product_id
                    WHERE o.id = ? AND o.user_id = ? AND p.name = ?
                    LIMIT 1
                ");
                $pstmt->execute([$orderId, $buyer_id, $product_name]);
                $pinfo = $pstmt->fetch();

                if(!$pinfo) {
                    $error = 'Order/product not found for your account.';
                } else {
                    $prodId = $pinfo['product_id'];
                    $sellerId = $pinfo['seller_id'];
                }
            }
        }

        if(!$error) {
            // record this return request in the database
            $stmt = $pdo->prepare("INSERT INTO return_requests (order_number, product_name, product_id, seller_id, buyer_id, reason, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if($stmt->execute([$order_number, $product_name, $prodId, $sellerId, $buyer_id, $reason, $imagePath])) {
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
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
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

        <?php if(isset($_SESSION['user_id'])): ?>
            <hr>
            <h3>Your Return Requests</h3>
            <?php
                $ur = $pdo->prepare("SELECT * FROM return_requests WHERE buyer_id = ? ORDER BY created_at DESC");
                $ur->execute([$_SESSION['user_id']]);
                $user_returns = $ur->fetchAll();
            ?>
            <?php if(empty($user_returns)): ?>
                <p class="text-muted">You haven't submitted any return requests yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                <table class="table table-bordered">
                    <thead><tr><th>#</th><th>Order</th><th>Product</th><th>Reason</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach($user_returns as $urq): ?>
                        <tr>
                            <td><?= $urq['id'] ?></td>
                            <td><?= htmlspecialchars($urq['order_number']) ?></td>
                            <td><?= htmlspecialchars($urq['product_name']) ?></td>
                            <td><?= htmlspecialchars($urq['reason']) ?></td>
                            <td><?= ucfirst($urq['status'] ?? 'pending') ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($urq['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
