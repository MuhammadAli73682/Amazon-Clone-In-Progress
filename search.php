<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

$term = $_GET['term'] ?? '';

$term = trim((string)$term);
if($term === '') {
    echo json_encode(['suggestions' => []]);
    exit;
}

if (mb_strlen($term) < 2) {
    echo json_encode(['suggestions' => []]);
    exit;
}

// "Smart" autocomplete: prefix + contains + popularity boost
$likePrefix = $term . '%';
$likeAny = '%' . $term . '%';

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.name,
        p.category,
        p.price,
        p.currency,
        (
            CASE
                WHEN p.name LIKE ? THEN 100
                WHEN p.name LIKE ? THEN 60
                WHEN p.category LIKE ? THEN 40
                WHEN p.description LIKE ? THEN 20
                ELSE 0
            END
        ) + LEAST(COALESCE(oi_cnt.cnt, 0), 50) AS score
    FROM products p
    LEFT JOIN (
        SELECT product_id, COUNT(*) AS cnt
        FROM order_items
        GROUP BY product_id
    ) oi_cnt ON oi_cnt.product_id = p.id
    WHERE p.status = 'active'
      AND (p.name LIKE ? OR p.description LIKE ? OR p.category LIKE ?)
    ORDER BY score DESC, p.id DESC
    LIMIT 10
");
$stmt->execute([
    $likePrefix,
    $likeAny,
    $likeAny,
    $likeAny,
    $likePrefix,
    $likeAny,
    $likeAny,
]);
$rows = $stmt->fetchAll();

$suggestions = array_map(function($r) {
    return [
        'id' => (int)$r['id'],
        'label' => (string)$r['name'],
        'category' => (string)($r['category'] ?? ''),
        'price' => (float)($r['price'] ?? 0),
        'currency' => (string)($r['currency'] ?? 'USD'),
    ];
}, $rows);

echo json_encode(['suggestions' => $suggestions]);
