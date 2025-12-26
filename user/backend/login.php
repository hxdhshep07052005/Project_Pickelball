<?php
declare(strict_types=1);

/**
 * Login backend handler
 * Processes login form submission, validates credentials, and sends OTP
 */

require __DIR__ . '/bootstrap.php';
$config = require __DIR__ . '/config.php';
require __DIR__ . '/mailer.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../frontend/login.php');
    exit;
}

// Validate reCAPTCHA response
$captchaResponse = $_POST['g-recaptcha-response'] ?? '';

if ($captchaResponse === '') {
    $_SESSION['login_error'] = 'Please confirm you are not a robot.';
    $_SESSION['login_email'] = $_POST['email'] ?? '';
    header('Location: ../frontend/login.php');
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
    $_SESSION['login_error'] = 'CAPTCHA verification failed.';
    $_SESSION['login_email'] = $_POST['email'] ?? '';
    header('Location: ../frontend/login.php');
    exit;
}

// Validate email and password input
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';

if (!$email || $password === '') {
    $_SESSION['login_error'] = 'Please fill in all required information.';
    $_SESSION['login_email'] = $_POST['email'] ?? '';
    header('Location: ../frontend/login.php');
    exit;
}

// Check if user exists in database
$statement = $pdo->prepare('SELECT id, display_name, role, status, auth_provider, password_hash FROM users WHERE email = ? LIMIT 1');
$statement->execute([$email]);
$user = $statement->fetch();

if (!$user) {
    $_SESSION['login_error'] = 'Account does not exist.';
    $_SESSION['login_email'] = $email;
    header('Location: ../frontend/login.php');
    exit;
}

// Check if account is active
if ($user['status'] !== 'active') {
    $_SESSION['login_error'] = 'Account is not activated.';
    $_SESSION['login_email'] = $email;
    header('Location: ../frontend/login.php');
    exit;
}

// Verify password (must be password auth provider)
if ($user['auth_provider'] !== 'password' || !$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
    $_SESSION['login_error'] = 'Incorrect email or password.';
    $_SESSION['login_email'] = $email;
    header('Location: ../frontend/login.php');
    exit;
}

// Generate and send OTP code
$otpLifetime = (int)($config['otp']['lifetime_seconds'] ?? 300);
$otpCode = (string)random_int(100000, 999999);

if (!sendOtpMail($config, $email, $otpCode, 'login')) {
    $_SESSION['login_error'] = 'Unable to send verification code. Please try again later.';
    $_SESSION['login_email'] = $email;
    header('Location: ../frontend/login.php');
    exit;
}

// Store pending login in session for OTP verification
$_SESSION['pending_login'] = [
    'user_id' => (int)$user['id'],
    'name' => $user['display_name'],
    'role' => $user['role'],
    'email' => $email,
    'otp_hash' => password_hash($otpCode, PASSWORD_DEFAULT),
    'otp_expires_at' => time() + $otpLifetime,
    'attempts' => 0,
];
unset($_SESSION['login_error'], $_SESSION['login_email']);
$_SESSION['verify_notice'] = 'A verification code has been sent to your email.';

// Redirect to verification page
header('Location: ../frontend/verify.php');
exit;

