<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'seller') {
    header('Location: ../login.php');
    exit;
}

$seller_id = (int)$_SESSION['user_id'];
$msg = $_GET['msg'] ?? '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review_id'])) {
    require_csrf_or_fail();

    $review_id = (int)$_POST['delete_review_id'];
    if($review_id > 0) {
        // Only delete if the review belongs to a product owned by this seller
        $del = $pdo->prepare("
            DELETE r
            FROM reviews r
            JOIN products p ON p.id = r.product_id
            WHERE r.id = ? AND p.seller_id = ?
        ");
        $del->execute([$review_id, $seller_id]);
    }

    header('Location: reviews.php?msg=' . urlencode('Review deleted.'));
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.product_id,
        r.user_id,
        r.rating,
        r.comment,
        r.created_at,
        p.name AS product_name,
        u.full_name AS buyer_name
    FROM reviews r
    JOIN products p ON p.id = r.product_id
    JOIN users u ON u.id = r.user_id
    WHERE p.seller_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$seller_id]);
$reviews = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Reviews - Seller</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/backend-header.php'; ?>

    <div class="container my-5">
        <h2 class="mb-4">Product Reviews</h2>

        <?php if($msg): ?>
            <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <?php if(empty($reviews)): ?>
            <div class="alert alert-secondary">No reviews found for your products.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product</th>
                            <th>Buyer</th>
                            <th>Rating</th>
                            <th>Comment</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($reviews as $r): ?>
                            <tr>
                                <td><?= (int)$r['id'] ?></td>
                                <td>
                                    <?= htmlspecialchars($r['product_name']) ?><br>
                                    <a class="small" href="../product-detail.php?id=<?= (int)$r['product_id'] ?>" target="_blank">View product</a>
                                </td>
                                <td><?= htmlspecialchars($r['buyer_name']) ?></td>
                                <td><?= (int)$r['rating'] ?>/5</td>
                                <td style="max-width: 420px; white-space: normal;"><?= nl2br(htmlspecialchars($r['comment'])) ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($r['created_at'])) ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Delete this review?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                        <input type="hidden" name="delete_review_id" value="<?= (int)$r['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

