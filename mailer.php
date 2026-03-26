<?php
/**
 * Minimal mail helper.
 * - Uses PHPMailer (composer) if available, otherwise falls back to PHP mail().
 * - Sends plain-text emails.
 */
function app_send_mail_text($to, $subject, $body) {
    $to = trim((string)$to);
    if ($to === '') {
        return false;
    }

    $subject = (string)$subject;
    $body = (string)$body;

    $fromEmail = 'no-reply@shophub.local';
    $fromName = 'ShopHub';

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (is_file($autoload)) {
        try {
            require_once $autoload;
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isMail();
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = $body;
            $mail->isHTML(false);
            $mail->send();
            return true;
        } catch (Throwable $e) {
            // fall back to mail()
        }
    }

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/plain; charset=UTF-8';
    $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';

    return @mail($to, $subject, $body, implode("\r\n", $headers));
}

function app_admin_emails(PDO $pdo) {
    $stmt = $pdo->query("SELECT email FROM users WHERE user_type = 'admin'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $emails = [];
    foreach ($rows as $r) {
        $email = strtolower(trim((string)($r['email'] ?? '')));
        if ($email !== '') {
            $emails[] = $email;
        }
    }
    return array_values(array_unique($emails));
}

function app_notify_admins(PDO $pdo, $subject, $body) {
    $ok = true;
    foreach (app_admin_emails($pdo) as $email) {
        $sent = app_send_mail_text($email, $subject, $body);
        $ok = $ok && $sent;
    }
    return $ok;
}
