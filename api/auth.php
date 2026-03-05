<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

if($action == 'status') {
    if(isset($_SESSION['user_id'])) {
        echo json_encode(['logged_in' => true, 'user_id' => $_SESSION['user_id'], 'user_type' => $_SESSION['user_type'], 'full_name' => $_SESSION['full_name']]);
    } else {
        echo json_encode(['logged_in' => false]);
    }
    exit;
}

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid method', 405);
}

if(!csrf_validate($_POST['csrf_token'] ?? '')) {
    json_error('Invalid CSRF token', 403);
}

if($action == 'login') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['full_name'] = $user['full_name'];
        echo json_encode(['success' => true]);
    } else {
        json_error('Invalid credentials', 401);
    }
    exit;
} elseif($action == 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
} elseif($action == 'register') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $user_type = $_POST['user_type'] ?? 'buyer';
    $shop_name = $_POST['shop_name'] ?? null;
    $phone = $_POST['phone'] ?? null;

    if(!$email || !$password || !$full_name) {
        json_error('Missing fields');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    // check email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if($stmt->fetch()) {
        json_error('Email already registered', 409);
    }
    $stmt = $pdo->prepare("INSERT INTO users (email,password,full_name,user_type,shop_name,phone) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$email,$hash,$full_name,$user_type,$shop_name,$phone]);
    $_SESSION['user_id'] = $pdo->lastInsertId();
    $_SESSION['user_type'] = $user_type;
    $_SESSION['full_name'] = $full_name;
    echo json_encode(['success' => true]);
    exit;
}

json_error('Unknown action', 400);
