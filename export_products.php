<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

// Only seller can export
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'seller'){
    header('Location: ../login.php');
    exit;
}

$seller_id = $_SESSION['user_id'];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=products.csv');

$output = fopen('php://output', 'w');

fputcsv($output, [
    'id',
    'name',
    'description',
    'price',
    'stock',
    'category',
    'status',
    'image',
    'language',
    'currency',
    'created_at',
]);

$stmt = $pdo->prepare("SELECT * FROM products WHERE seller_id = ? ORDER BY id ASC");
$stmt->execute([$seller_id]);

while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    fputcsv($output, [
        $row['id'],
        $row['name'] ?? '',
        $row['description'] ?? '',
        $row['price'] ?? '',
        $row['stock'] ?? 0,
        $row['category'] ?? '',
        $row['status'] ?? 'active',
        $row['image'] ?? '',
        $row['language'] ?? 'en',
        $row['currency'] ?? 'USD',
        $row['created_at'] ?? '',
    ]);
}
fclose($output);
exit;
    
