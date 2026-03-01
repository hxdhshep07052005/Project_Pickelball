<?php
declare(strict_types=1);



require __DIR__ . '/bootstrap.php';
$config = require __DIR__ . '/../../user/backend/config.php';
require __DIR__ . '/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../frontend/login.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    $_SESSION['admin_login_error'] = 'Please fill in all required fields.';
    $_SESSION['admin_login_username'] = $username;
    header('Location: ../frontend/login.php');
    exit;
}

$adminUsername = 'admin';
$adminPassword = 'admin';

if ($username !== $adminUsername || $password !== $adminPassword) {
    $_SESSION['admin_login_error'] = 'Incorrect username or password.';
    $_SESSION['admin_login_username'] = $username;
    header('Location: ../frontend/login.php');
    exit;
}

$adminEmail = 'hxdhshep71052204@gmail.com';

$otpLifetime = (int)($config['otp']['lifetime_seconds'] ?? 300);
$otpCode = (string)random_int(100000, 999999);

if (!sendOtpMail($config, $adminEmail, $otpCode, 'login')) {
    $_SESSION['admin_login_error'] = 'Unable to send verification code. Please try again later.';
    $_SESSION['admin_login_username'] = $username;
    header('Location: ../frontend/login.php');
    exit;
}

$_SESSION['pending_admin_login'] = [
    'username' => $adminUsername,
    'email' => $adminEmail,
    'otp_hash' => password_hash($otpCode, PASSWORD_DEFAULT),
    'otp_expires_at' => time() + $otpLifetime,
    'attempts' => 0,
];

unset($_SESSION['admin_login_error'], $_SESSION['admin_login_username']);
$_SESSION['admin_verify_notice'] = 'A verification code has been sent to your email.';

header('Location: ../frontend/verify.php');
exit;
