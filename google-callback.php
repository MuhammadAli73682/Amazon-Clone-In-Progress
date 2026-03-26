<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/google_auth.php';

function google_callback_redirect(string $message, string $type = 'error', ?string $intent = null): void
{
    $intent = $intent ?? ($_SESSION['google_oauth_intent'] ?? 'login');
    $target = $intent === 'register' ? 'register.php' : 'login.php';
    $param = $type === 'info' ? 'google_info' : 'google_error';
    header('Location: ' . $target . '?' . $param . '=' . urlencode($message));
    exit;
}

if (!google_oauth_is_configured()) {
    google_callback_redirect('Google login is not configured yet.');
}

$sessionState = $_SESSION['google_oauth_state'] ?? '';
$intent = $_SESSION['google_oauth_intent'] ?? 'login';
$state = $_GET['state'] ?? '';
$code = $_GET['code'] ?? '';

unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_intent']);

if (!$sessionState || !$state || !hash_equals($sessionState, $state)) {
    google_callback_redirect('Google login request could not be verified.');
}

if (!$code) {
    google_callback_redirect('Google login was cancelled or failed.');
}

try {
    ensure_google_auth_schema($pdo);

    $token = google_exchange_code_for_token($code);
    $profile = google_fetch_user_profile($token['access_token']);

    if (empty($profile['email_verified'])) {
        google_callback_redirect('Please use a Google account with a verified email address.');
    }

    $email = strtolower(trim((string) $profile['email']));
    $googleId = (string) $profile['sub'];
    $fullName = trim((string) ($profile['name'] ?? 'Google User'));
    $picture = trim((string) ($profile['picture'] ?? ''));

    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ? OR email = ? ORDER BY CASE WHEN google_id = ? THEN 0 ELSE 1 END LIMIT 1");
    $stmt->execute([$googleId, $email, $googleId]);
    $user = $stmt->fetch();

    if ($user) {
        $accountStatus = $user['account_status'] ?? 'active';

        if ($accountStatus === 'rejected') {
            google_callback_redirect('This account is not eligible for login.');
        }

        if (($user['user_type'] ?? '') === 'seller' && $accountStatus === 'pending') {
            google_callback_redirect('Your seller account is on review. We will update you by email.', 'info', 'login');
        }

        $fields = [];
        $params = [];

        if (($user['google_id'] ?? '') !== $googleId) {
            $fields[] = 'google_id = ?';
            $params[] = $googleId;
        }
        if ($picture !== '' && ($user['google_picture'] ?? '') !== $picture) {
            $fields[] = 'google_picture = ?';
            $params[] = $picture;
        }
        if ($fullName !== '' && ($user['full_name'] ?? '') === '') {
            $fields[] = 'full_name = ?';
            $params[] = $fullName;
        }

        if ($fields) {
            $params[] = $user['id'];
            $upd = $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?");
            $upd->execute($params);

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $user = $stmt->fetch();
        }
    } else {
        $password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (email, google_id, google_picture, password, full_name, user_type, account_status)
            VALUES (?, ?, ?, ?, ?, 'buyer', 'active')
        ");
        $stmt->execute([$email, $googleId, $picture ?: null, $password, $fullName]);

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$pdo->lastInsertId()]);
        $user = $stmt->fetch();
    }

    if (!$user) {
        google_callback_redirect('Google account login failed. Please try again.');
    }

    login_user_into_session($pdo, $user);
    redirect_after_login($user['user_type'] ?? 'buyer');
} catch (Throwable $e) {
    google_callback_redirect('Google login failed: ' . $e->getMessage(), 'error', $intent);
}
