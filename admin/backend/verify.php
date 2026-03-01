<?php
declare(strict_types=1);



require __DIR__ . '/bootstrap.php';
$config = require __DIR__ . '/../../user/backend/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../frontend/verify.php');
    exit;
}

if (!isset($_SESSION['pending_admin_login'])) {
    $_SESSION['admin_login_error'] = 'Authentication session is invalid. Please sign in again.';
    header('Location: ../frontend/login.php');
    exit;
}

$pending = $_SESSION['pending_admin_login'];

$code = $_POST['code'] ?? '';
$code = preg_replace('/\D/', '', $code); // Remove non-digits

if (!preg_match('/^\d{6}$/', $code)) {
    $_SESSION['admin_verify_error'] = 'Enter exactly 6 digits.';
    header('Location: ../frontend/verify.php');
    exit;
}

$expiresAt = (int)($pending['otp_expires_at'] ?? 0);

if ($expiresAt < time()) {
    unset($_SESSION['pending_admin_login']);
    $_SESSION['admin_login_error'] = 'Verification code expired. Please sign in again.';
    header('Location: ../frontend/login.php');
    exit;
}

$attempts = (int)($pending['attempts'] ?? 0) + 1;
$maxAttempts = (int)($config['otp']['max_attempts'] ?? 5);

if (!password_verify($code, $pending['otp_hash'])) {
    $_SESSION['pending_admin_login']['attempts'] = $attempts;

    if ($attempts >= $maxAttempts) {
        unset($_SESSION['pending_admin_login']);
        $_SESSION['admin_login_error'] = 'Too many incorrect attempts. Please sign in again.';
        header('Location: ../frontend/login.php');
        exit;
    }

    $_SESSION['admin_verify_error'] = 'Incorrect verification code. Please try again.';
    header('Location: ../frontend/verify.php');
    exit;
}

$_SESSION['admin'] = [
    'username' => $pending['username'],
    'email' => $pending['email'],
    'logged_in_at' => time(),
];

unset($_SESSION['pending_admin_login'], $_SESSION['admin_verify_error'], $_SESSION['admin_verify_notice']);

header('Location: ../frontend/dashboard.php');
exit;
