<?php
require_once __DIR__ . '/../config/database.php';

function google_oauth_config(): array
{
    $baseUrl = str_replace(' ', '%20', BASE_URL);
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return [
        'client_id' => 'YOUR_GOOGLE_CLIENT_ID',
        'client_secret' => 'YOUR_GOOGLE_CLIENT_SECRET',
        'redirect_uri' => $scheme . '://' . $host . $baseUrl . '/google-callback.php',
        'auth_uri' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_uri' => 'https://oauth2.googleapis.com/token',
        'userinfo_uri' => 'https://openidconnect.googleapis.com/v1/userinfo',
        'scope' => 'openid email profile',
    ];
}

function google_oauth_is_configured(): bool
{
    $config = google_oauth_config();
    return $config['client_id'] !== 'YOUR_GOOGLE_CLIENT_ID'
        && $config['client_secret'] !== 'YOUR_GOOGLE_CLIENT_SECRET';
}

function ensure_google_auth_schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'google_id'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(191) DEFAULT NULL AFTER email");
            $pdo->exec("ALTER TABLE users ADD UNIQUE INDEX idx_users_google_id (google_id)");
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'google_picture'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN google_picture VARCHAR(255) DEFAULT NULL AFTER google_id");
        }
    } catch (Throwable $e) {
        // Best effort only; manual SQL file is also included.
    }

    $ensured = true;
}

function google_http_request(string $url, string $method = 'GET', array $data = [], array $headers = []): array
{
    $method = strtoupper($method);
    $allHeaders = array_merge(['Accept: application/json'], $headers);

    if (function_exists('curl_init')) {
        $ch = curl_init();
        if ($method === 'GET' && !empty($data)) {
            $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($data, '', '&', PHP_QUERY_RFC3986);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $allHeaders,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data, '', '&', PHP_QUERY_RFC3986));
        }

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $error) {
            throw new RuntimeException('Google request failed: ' . $error);
        }

        return [$status, $body];
    }

    $options = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $allHeaders),
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ];

    if ($method === 'POST') {
        $options['http']['header'] .= "\r\nContent-Type: application/x-www-form-urlencoded";
        $options['http']['content'] = http_build_query($data, '', '&', PHP_QUERY_RFC3986);
    } elseif (!empty($data)) {
        $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($data, '', '&', PHP_QUERY_RFC3986);
    }

    $context = stream_context_create($options);
    $body = @file_get_contents($url, false, $context);
    $status = 0;
    if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }

    if ($body === false) {
        throw new RuntimeException('Google request failed.');
    }

    return [$status, $body];
}

function google_exchange_code_for_token(string $code): array
{
    $config = google_oauth_config();
    [$status, $body] = google_http_request($config['token_uri'], 'POST', [
        'code' => $code,
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri' => $config['redirect_uri'],
        'grant_type' => 'authorization_code',
    ], ['Content-Type: application/x-www-form-urlencoded']);

    $data = json_decode($body, true);
    if ($status < 200 || $status >= 300 || !is_array($data) || empty($data['access_token'])) {
        throw new RuntimeException('Unable to verify your Google login.');
    }

    return $data;
}

function google_fetch_user_profile(string $accessToken): array
{
    $config = google_oauth_config();
    [$status, $body] = google_http_request($config['userinfo_uri'], 'GET', [], [
        'Authorization: Bearer ' . $accessToken,
    ]);

    $data = json_decode($body, true);
    if ($status < 200 || $status >= 300 || !is_array($data) || empty($data['sub']) || empty($data['email'])) {
        throw new RuntimeException('Unable to fetch your Google profile.');
    }

    return $data;
}
