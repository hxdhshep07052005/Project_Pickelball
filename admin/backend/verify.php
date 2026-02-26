<?php
declare(strict_types=1);

/**
 * Admin OTP verification backend handler
 * Handles OTP verification for admin login
 */

require __DIR__ . '/bootstrap.php';
$config = require __DIR__ . '/../../user/backend/config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../frontend/verify.php');
    exit;
}

// Check if pending login session exists
if (!isset($_SESSION['pending_admin_login'])) {
    $_SESSION['admin_login_error'] = 'Authentication session is invalid. Please sign in again.';
    header('Location: ../frontend/login.php');
    exit;
}

$pending = $_SESSION['pending_admin_login'];

// Validate and sanitize OTP code (must be 6 digits)
$code = $_POST['code'] ?? '';
$code = preg_replace('/\D/', '', $code); // Remove non-digits

if (!preg_match('/^\d{6}$/', $code)) {
    $_SESSION['admin_verify_error'] = 'Enter exactly 6 digits.';
    header('Location: ../frontend/verify.php');
    exit;
}

// Check if OTP has expired
$expiresAt = (int)($pending['otp_expires_at'] ?? 0);

if ($expiresAt < time()) {
    unset($_SESSION['pending_admin_login']);
    $_SESSION['admin_login_error'] = 'Verification code expired. Please sign in again.';
    header('Location: ../frontend/login.php');
    exit;
}

// Check attempt limit
$attempts = (int)($pending['attempts'] ?? 0) + 1;
$maxAttempts = (int)($config['otp']['max_attempts'] ?? 5);

// Verify OTP code
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

// Successful verification - create admin session
$_SESSION['admin'] = [
    'username' => $pending['username'],
    'email' => $pending['email'],
    'logged_in_at' => time(),
];

unset($_SESSION['pending_admin_login'], $_SESSION['admin_verify_error'], $_SESSION['admin_verify_notice']);

// Redirect to dashboard
header('Location: ../frontend/dashboard.php');
exit;

