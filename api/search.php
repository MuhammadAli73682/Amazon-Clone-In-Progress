<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

$term = $_GET['term'] ?? '';
if($term === '') {
    echo json_encode(['suggestions' => []]);
    exit;
}

// simple autocomplete: search name prefix
$stmt = $pdo->prepare("SELECT id, name FROM products WHERE status='active' AND name LIKE ? LIMIT 10");
$stmt->execute(["$term%"]);
$rows = $stmt->fetchAll();

$suggestions = array_map(function($r) {
    return ['id' => $r['id'], 'label' => htmlspecialchars($r['name'])];
}, $rows);

echo json_encode(['suggestions' => $suggestions]);
