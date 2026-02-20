<?php
session_start();
require_once '../config/database.php';

// Only seller can export
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'seller'){
    header('Location: ../login.php');
    exit;
}

$seller_id = $_SESSION['user_id'];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=products.csv');

$output = fopen('php://output', 'w');

// CSV Column headings
fputcsv($output, ['ID', 'Name', 'Description', 'Price', 'Currency', 'Language']);

// Get products for this seller
$lang = $_SESSION['language'] ?? 'en';
$currency = $_SESSION['currency'] ?? 'USD';

$stmt = $pdo->prepare("
    SELECT p.id, p.price, p.currency, pt.name, pt.description, pt.language
    FROM products p
    LEFT JOIN product_translations pt
        ON p.id = pt.product_id AND pt.language = ?
    WHERE p.seller_id = ?
");
$stmt->execute([$lang, $seller_id]);

while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    $price = $row['price'];
    // convert USD to PKR if needed
    if($currency == 'PKR' && $row['currency'] == 'USD'){
        $price = $price * 280; // example conversion rate
    }
    fputcsv($output, [
        $row['id'],
        $row['name'],
        $row['description'],
        $price,
        $currency,
        $row['language']
    ]);
}
fclose($output);
exit;
    