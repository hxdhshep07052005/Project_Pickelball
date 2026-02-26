<?php
declare(strict_types=1);

/**
 * Admin login backend handler
 * Processes login form submission, validates credentials, and sends OTP
 */

require __DIR__ . '/bootstrap.php';
$config = require __DIR__ . '/../../user/backend/config.php';
require __DIR__ . '/mailer.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../frontend/login.php');
    exit;
}

// Validate username and password input
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    $_SESSION['admin_login_error'] = 'Please fill in all required fields.';
    $_SESSION['admin_login_username'] = $username;
    header('Location: ../frontend/login.php');
    exit;
}

// Verify admin credentials (hardcoded for security)
$adminUsername = 'admin';
$adminPassword = 'admin';

if ($username !== $adminUsername || $password !== $adminPassword) {
    $_SESSION['admin_login_error'] = 'Incorrect username or password.';
    $_SESSION['admin_login_username'] = $username;
    header('Location: ../frontend/login.php');
    exit;
}

// Admin email for OTP
$adminEmail = 'hxdhshep71052204@gmail.com';

// Generate and send OTP code
$otpLifetime = (int)($config['otp']['lifetime_seconds'] ?? 300);
$otpCode = (string)random_int(100000, 999999);

if (!sendOtpMail($config, $adminEmail, $otpCode, 'login')) {
    $_SESSION['admin_login_error'] = 'Unable to send verification code. Please try again later.';
    $_SESSION['admin_login_username'] = $username;
    header('Location: ../frontend/login.php');
    exit;
}

// Store pending login in session for OTP verification
$_SESSION['pending_admin_login'] = [
    'username' => $adminUsername,
    'email' => $adminEmail,
    'otp_hash' => password_hash($otpCode, PASSWORD_DEFAULT),
    'otp_expires_at' => time() + $otpLifetime,
    'attempts' => 0,
];

unset($_SESSION['admin_login_error'], $_SESSION['admin_login_username']);
$_SESSION['admin_verify_notice'] = 'A verification code has been sent to your email.';

// Redirect to verification page
header('Location: ../frontend/verify.php');
exit;

