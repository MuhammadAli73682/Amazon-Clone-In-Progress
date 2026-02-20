<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login', 'redirect' => true]);
    exit;
}

$user_id = $_SESSION['user_id'];

if($_SERVER['REQUEST_METHOD'] == 'GET') {
    $action = $_GET['action'] ?? '';
    if($action == 'count') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        echo json_encode(['count' => $result['count'] ?? 0]);
    }
    exit;
}

$action = $_POST['action'] ?? '';
if($action == 'add') {
    $product_id = $_POST['product_id'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $product_id]);
    echo json_encode(['success' => true]);
} elseif($action == 'remove') {
    $product_id = $_POST['product_id'];
    $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    echo json_encode(['success' => true]);
} elseif($action == 'check') {
    $product_id = $_POST['product_id'];
    $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    echo json_encode(['in_wishlist' => (bool)$stmt->fetch()]);
}
?>