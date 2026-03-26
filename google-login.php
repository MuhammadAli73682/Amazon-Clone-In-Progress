<?php
session_start();
require_once 'includes/google_auth.php';

if (!google_oauth_is_configured()) {
    header('Location: login.php?google_error=' . urlencode('Google login is not configured yet.'));
    exit;
}

$intent = ($_GET['intent'] ?? 'login') === 'register' ? 'register' : 'login';
$state = bin2hex(random_bytes(32));
$_SESSION['google_oauth_state'] = $state;
$_SESSION['google_oauth_intent'] = $intent;

$config = google_oauth_config();
$params = [
    'client_id' => $config['client_id'],
    'redirect_uri' => $config['redirect_uri'],
    'response_type' => 'code',
    'scope' => $config['scope'],
    'state' => $state,
    'prompt' => 'select_account',
];

header('Location: ' . $config['auth_uri'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
exit;
