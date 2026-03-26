<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'seller') {
    header('Location: ../login.php');
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=products_template.csv');

$out = fopen('php://output', 'w');
fputcsv($out, [
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
]);
fputcsv($out, [
    '',
    'Example Product',
    'Short description here',
    '19.99',
    '10',
    'Electronics',
    'active',
    'assets/images/products/default.jpg',
    'en',
    'USD',
]);
fclose($out);
exit;

