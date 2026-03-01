<?php
declare(strict_types=1);



require __DIR__ . '/bootstrap.php';
require __DIR__ . '/require_auth.php';
$config = require __DIR__ . '/config.php';
require __DIR__ . '/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../frontend/profile.php');
    exit;
}

$userId = (int)$authUser['id'];
$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    $_SESSION['password_change_error'] = 'All fields are required.';
    header('Location: ../frontend/profile.php');
    exit;
}

$statement = $pdo->prepare('SELECT password_hash, email FROM users WHERE id = ? LIMIT 1');
$statement->execute([$userId]);
$user = $statement->fetch();

if (!$user) {
    $_SESSION['password_change_error'] = 'User not found.';
    header('Location: ../frontend/profile.php');
    exit;
}

if (!password_verify($currentPassword, $user['password_hash'])) {
    $_SESSION['password_change_error'] = 'Current password is incorrect.';
    header('Location: ../frontend/profile.php');
    exit;
}

if (strlen($newPassword) < 8) {
    $_SESSION['password_change_error'] = 'New password must be at least 8 characters.';
    header('Location: ../frontend/profile.php');
    exit;
}

if ($newPassword !== $confirmPassword) {
    $_SESSION['password_change_error'] = 'New passwords do not match.';
    header('Location: ../frontend/profile.php');
    exit;
}

if ($currentPassword === $newPassword) {
    $_SESSION['password_change_error'] = 'New password must be different from current password.';
    header('Location: ../frontend/profile.php');
    exit;
}

$otpLifetime = (int)($config['otp']['lifetime_seconds'] ?? 300);
$otpCode = (string)random_int(100000, 999999);

if (!sendOtpMail($config, $user['email'], $otpCode, 'password_change')) {
    $_SESSION['password_change_error'] = 'Unable to send verification code. Please try again.';
    header('Location: ../frontend/profile.php');
    exit;
}

$_SESSION['pending_password_change'] = [
    'user_id' => $userId,
    'email' => $user['email'],
    'new_password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
    'otp_hash' => password_hash($otpCode, PASSWORD_DEFAULT),
    'otp_expires_at' => time() + $otpLifetime,
    'attempts' => 0,
];

unset($_SESSION['password_change_error'], $_SESSION['password_change_success']);
$_SESSION['password_change_notice'] = 'A verification code has been sent to your email. Please enter it to complete the password change.';

header('Location: ../frontend/verify.php');
exit;
