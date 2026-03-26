<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/mailer.php';

// only admin
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$update_message = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_or_fail();

    $targetId = intval($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if($targetId > 0 && in_array($action, ['approve_seller','reject_seller'], true)) {
        $stmt = $pdo->prepare("SELECT id, email, full_name, user_type, account_status FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$targetId]);
        $u = $stmt->fetch();

        if($u && $u['user_type'] === 'seller') {
            $status = $u['account_status'] ?? 'active';

            if($status !== 'pending') {
                $update_message = 'This seller is not pending review.';
            } else {
                if($action === 'approve_seller') {
                    $upd = $pdo->prepare("UPDATE users SET account_status = 'active', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
                    $upd->execute([$_SESSION['user_id'], $targetId]);

                    app_send_mail_text(
                        $u['email'],
                        "Your seller account is approved",
                        "Your seller account has been approved.\n\n" .
                        "You can now login and start selling.\n\n" .
                        "Thank you."
                    );

                    $update_message = "Seller approved: " . $u['full_name'];
                } else {
                    $upd = $pdo->prepare("UPDATE users SET account_status = 'rejected', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
                    $upd->execute([$_SESSION['user_id'], $targetId]);

                    app_send_mail_text(
                        $u['email'],
                        "Your seller account request was rejected",
                        "Your seller account request has been rejected.\n\n" .
                        "If you think this is a mistake, please contact support.\n\n" .
                        "Thank you."
                    );

                    $update_message = "Seller rejected: " . $u['full_name'];
                }
            }
        }
    }

    header('Location: users.php?msg=' . urlencode($update_message));
    exit;
}

$update_message = $_GET['msg'] ?? '';
$users = $pdo->query("
    SELECT id, full_name, email, user_type, shop_name, account_status, created_at
    FROM users
    ORDER BY (account_status = 'pending') DESC, id DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/backend-header.php'; ?>
    <div class="container my-5">
        <h2 class="mb-4">All Users</h2>
        <?php if($update_message): ?>
            <div class="alert alert-info"><?= htmlspecialchars($update_message) ?></div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr><th>ID</th><th>Name</th><th>Email</th><th>Type</th><th>Status</th><th>Shop</th><th>Joined</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['full_name']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= ucfirst($u['user_type']) ?></td>
                    <td>
                        <?php
                            $st = $u['account_status'] ?? 'active';
                            $badge = 'secondary';
                            if($st === 'active') $badge = 'success';
                            if($st === 'pending') $badge = 'warning';
                            if($st === 'rejected') $badge = 'danger';
                        ?>
                        <span class="badge bg-<?= $badge ?>"><?= ucfirst($st) ?></span>
                    </td>
                    <td><?= htmlspecialchars($u['shop_name'] ?? '') ?></td>
                    <td><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                    <td>
                        <?php if($u['user_type'] === 'seller' && ($u['account_status'] ?? '') === 'pending'): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <button class="btn btn-sm btn-success" name="action" value="approve_seller">Approve</button>
                            </form>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <button class="btn btn-sm btn-danger" name="action" value="reject_seller">Reject</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
       <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</body>
</html>
</body>
</html>
