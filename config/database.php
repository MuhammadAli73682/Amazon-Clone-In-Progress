<?php
// base URL for links (adjust to match your project folder or domain)
// Changed from '/Ecommerce-Website' to use the actual project folder name
// (spaces may need encoding or you can use a hyphen/underscore if preferred)
define('BASE_URL', '/Amazon Clone');

$host = 'localhost';
$dbname = 'ecommerce_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
