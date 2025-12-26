<?php
declare(strict_types=1);

/**
 * Registration backend handler
 * Processes registration form, creates user account, and sends OTP
 */

require __DIR__ . '/bootstrap.php';
$config = require __DIR__ . '/config.php';
require __DIR__ . '/mailer.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../frontend/register.php');
    exit;
}

// Validate reCAPTCHA response
$captchaResponse = $_POST['g-recaptcha-response'] ?? '';

if ($captchaResponse === '') {
    $_SESSION['register_error'] = 'Please confirm you are not a robot.';
    $_SESSION['register_form'] = $_POST;
    header('Location: ../frontend/register.php');
    exit;
}

// Verify reCAPTCHA with Google's API
$captchaPayload = http_build_query([
    'secret' => $config['recaptcha_secret_key'],
    'response' => $captchaResponse,
    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
]);

$captchaContext = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
        'content' => $captchaPayload,
        'timeout' => 10,
    ],
]);

$captchaVerification = file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $captchaContext);
$captchaResult = $captchaVerification ? json_decode($captchaVerification, true) : null;

if (!$captchaResult || ($captchaResult['success'] ?? false) !== true) {
    $_SESSION['register_error'] = 'CAPTCHA verification failed.';
    $_SESSION['register_form'] = $_POST;
    header('Location: ../frontend/register.php');
    exit;
}

// Validate and sanitize form inputs
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$name = trim((string)($_POST['name'] ?? ''));
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

$_SESSION['register_form'] = [
    'email' => $email ?: ($_POST['email'] ?? ''),
    'name' => $name,
];

// Validate all required fields
if (!$email || $name === '' || $password === '' || $confirmPassword === '') {
    $_SESSION['register_error'] = 'Please fill in all required information.';
    header('Location: ../frontend/register.php');
    exit;
}

// Validate password length
if (strlen($password) < 8) {
    $_SESSION['register_error'] = 'Password must be at least 8 characters.';
    header('Location: ../frontend/register.php');
    exit;
}

// Validate password confirmation
if ($password !== $confirmPassword) {
    $_SESSION['register_error'] = 'Passwords do not match.';
    header('Location: ../frontend/register.php');
    exit;
}

// Check if email already exists (allow pending accounts to re-register)
$pdo->beginTransaction();

$statement = $pdo->prepare('SELECT id, status, auth_provider FROM users WHERE email = ? LIMIT 1');
$statement->execute([$email]);
$existing = $statement->fetch();

if ($existing && $existing['status'] !== 'pending') {
    $pdo->rollBack();
    $_SESSION['register_error'] = 'Email is already in use.';
    header('Location: ../frontend/register.php');
    exit;
}

// Hash password and set default role
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$role = 'player';

// Update existing pending user or create new user
if ($existing) {
    $pdo->prepare('UPDATE users SET display_name = ?, password_hash = ?, role = ?, status = ?, auth_provider = ?, updated_at = NOW() WHERE id = ?')->execute([
        $name,
        $passwordHash,
        $role,
        'pending',
        'password',
        $existing['id'],
    ]);
    $userId = (int)$existing['id'];
} else {
    $insert = $pdo->prepare('INSERT INTO users (email, display_name, role, status, auth_provider, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
    $insert->execute([
        $email,
        $name,
        $role,
        'pending',
        'password',
        $passwordHash,
    ]);
    $userId = (int)$pdo->lastInsertId();
}

$pdo->commit();

// Generate and send OTP code
$otpLifetime = (int)($config['otp']['lifetime_seconds'] ?? 300);
$otpCode = (string)random_int(100000, 999999);

if (!sendOtpMail($config, $email, $otpCode, 'register')) {
    $_SESSION['register_error'] = 'Unable to send verification code. Please try again.';
    header('Location: ../frontend/register.php');
    exit;
}

// Store pending registration in session for OTP verification
$_SESSION['pending_registration'] = [
    'user_id' => $userId,
    'email' => $email,
    'name' => $name,
    'role' => $role,
    'otp_hash' => password_hash($otpCode, PASSWORD_DEFAULT),
    'otp_expires_at' => time() + $otpLifetime,
    'attempts' => 0,
];

unset($_SESSION['register_form'], $_SESSION['register_error']);
$_SESSION['register_notice'] = 'A verification code has been sent to your email.';

// Redirect to verification page
header('Location: ../frontend/verify.php');
exit;

