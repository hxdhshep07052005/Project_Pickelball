<?php
declare(strict_types=1);

/**
 * OTP verification backend handler
 * Handles OTP verification for login, registration, and password change
 */

require __DIR__ . '/bootstrap.php';
$config = require __DIR__ . '/config.php';
require __DIR__ . '/mailer.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../frontend/verify.php');
    exit;
}

// Determine verification type from session (login, registration, or password_change)
$pending = null;
$verifyType = null;

if (isset($_SESSION['pending_password_change'])) {
    $pending = $_SESSION['pending_password_change'];
    $verifyType = 'password_change';
    require __DIR__ . '/require_auth.php'; // Require auth for password change
} elseif (isset($_SESSION['pending_registration'])) {
    $pending = $_SESSION['pending_registration'];
    $verifyType = 'registration';
} elseif (isset($_SESSION['pending_login'])) {
    $pending = $_SESSION['pending_login'];
    $verifyType = 'login';
}

// Check if pending session exists
if (!$pending) {
    if ($verifyType === 'password_change') {
        $_SESSION['password_change_error'] = 'Verification session has expired. Please try again.';
        header('Location: ../frontend/profile.php');
    } else {
        $_SESSION['login_error'] = 'Authentication session is invalid. Please sign in again.';
        header('Location: ../frontend/login.php');
    }
    exit;
}

// Validate and sanitize OTP code (must be 6 digits)
$code = $_POST['code'] ?? '';
$code = preg_replace('/\D/', '', $code); // Remove non-digits

if (!preg_match('/^\d{6}$/', $code)) {
    if ($verifyType === 'password_change') {
        $_SESSION['verify_password_change_error'] = 'Enter exactly 6 digits.';
    } elseif ($verifyType === 'registration') {
        $_SESSION['verify_registration_error'] = 'Enter exactly 6 digits.';
    } else {
        $_SESSION['verify_error'] = 'Enter exactly 6 digits.';
    }
    header('Location: ../frontend/verify.php');
    exit;
}

// Check if OTP has expired
$expiresAt = (int)($pending['otp_expires_at'] ?? 0);

if ($expiresAt < time()) {
    if ($verifyType === 'password_change') {
        unset($_SESSION['pending_password_change']);
        $_SESSION['password_change_error'] = 'Verification code expired. Please try again.';
        header('Location: ../frontend/profile.php');
    } elseif ($verifyType === 'registration') {
        unset($_SESSION['pending_registration']);
        $_SESSION['login_error'] = 'Verification code expired. Please register again.';
        header('Location: ../frontend/login.php');
    } else {
        unset($_SESSION['pending_login']);
        $_SESSION['login_error'] = 'Verification code expired. Please sign in again.';
        header('Location: ../frontend/login.php');
    }
    exit;
}

// Check attempt limit
$attempts = (int)($pending['attempts'] ?? 0) + 1;
$maxAttempts = (int)($config['otp']['max_attempts'] ?? 5);

// Verify OTP code
if (!password_verify($code, $pending['otp_hash'])) {
    if ($verifyType === 'password_change') {
        $_SESSION['pending_password_change']['attempts'] = $attempts;
        
        if ($attempts >= $maxAttempts) {
            unset($_SESSION['pending_password_change']);
            $_SESSION['password_change_error'] = 'Too many incorrect attempts. Please try again.';
            header('Location: ../frontend/profile.php');
            exit;
        }
        
        $_SESSION['verify_password_change_error'] = 'Incorrect verification code. Please try again.';
        header('Location: ../frontend/verify.php');
        exit;
    } elseif ($verifyType === 'registration') {
        $_SESSION['pending_registration']['attempts'] = $attempts;
        
        if ($attempts >= $maxAttempts) {
            unset($_SESSION['pending_registration']);
            $_SESSION['login_error'] = 'Too many incorrect attempts. Please register again.';
            header('Location: ../frontend/login.php');
            exit;
        }
        
        $_SESSION['verify_registration_error'] = 'Incorrect verification code. Please try again.';
        header('Location: ../frontend/verify.php');
        exit;
    } else {
        $_SESSION['pending_login']['attempts'] = $attempts;
        
        if ($attempts >= $maxAttempts) {
            unset($_SESSION['pending_login']);
            $_SESSION['login_error'] = 'Too many incorrect attempts. Please sign in again.';
            header('Location: ../frontend/login.php');
            exit;
        }
        
        $_SESSION['verify_error'] = 'Incorrect verification code. Please try again.';
        header('Location: ../frontend/verify.php');
        exit;
    }
}

// Handle successful verification based on type
if ($verifyType === 'password_change') {
    // Update user password in database
    $userId = (int)$pending['user_id'];
    $newPasswordHash = $pending['new_password_hash'];
    
    $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?')->execute([
        $newPasswordHash,
        $userId,
    ]);
    
    unset($_SESSION['pending_password_change'], $_SESSION['verify_password_change_error'], $_SESSION['password_change_notice']);
    $_SESSION['password_change_success'] = 'Your password has been changed successfully.';
    
    header('Location: ../frontend/profile.php');
    exit;
} elseif ($verifyType === 'registration') {
    // Activate user account and mark email as verified
    $pdo->prepare('UPDATE users SET status = ?, email_verified_at = NOW(), last_login_at = NOW() WHERE id = ?')->execute([
        'active',
        $pending['user_id'],
    ]);
    
    // Create pending login session for immediate login after registration
    $_SESSION['pending_login'] = [
        'user_id' => (int)$pending['user_id'],
        'name' => (string)$pending['name'],
        'role' => (string)($pending['role'] ?? 'player'),
        'email' => $pending['email'],
        'otp_hash' => password_hash((string)random_int(100000, 999999), PASSWORD_DEFAULT),
        'otp_expires_at' => time() + (int)($config['otp']['lifetime_seconds'] ?? 300),
        'attempts' => 0,
    ];
    
    // Send login OTP for immediate login
    $code = (string)random_int(100000, 999999);
    
    if (!sendOtpMail($config, $pending['email'], $code, 'login')) {
        unset($_SESSION['pending_login'], $_SESSION['pending_registration']);
        $_SESSION['login_error'] = 'Unable to send login verification code. Please sign in again.';
        header('Location: ../frontend/login.php');
        exit;
    }
    
    $_SESSION['pending_login']['otp_hash'] = password_hash($code, PASSWORD_DEFAULT);
    $_SESSION['pending_login']['otp_expires_at'] = time() + (int)($config['otp']['lifetime_seconds'] ?? 300);
    
    unset($_SESSION['pending_registration'], $_SESSION['verify_registration_error'], $_SESSION['register_notice']);
    
    header('Location: ../frontend/verify.php');
    exit;
} else {
    // Login verification - create user session
    $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$pending['user_id']]);
    
    // Set user session data
    $_SESSION['user'] = [
        'id' => (int)$pending['user_id'],
        'name' => (string)$pending['name'],
        'role' => (string)$pending['role'],
    ];
    
    // Get return URL and validate to prevent open redirect attacks
    $returnUrl = $_SESSION['return_url'] ?? '/pickelball/main/frontend/index.php';
    if (!empty($returnUrl) && (strpos($returnUrl, '/pickelball/') === 0 || strpos($returnUrl, '/') === 0)) {
        $finalUrl = $returnUrl;
    } else {
        $finalUrl = '/pickelball/main/frontend/index.php';
    }
    unset($_SESSION['return_url'], $_SESSION['pending_login'], $_SESSION['pending_registration'], $_SESSION['verify_error'], $_SESSION['verify_notice'], $_SESSION['register_notice'], $_SESSION['verify_registration_error']);
    
    header('Location: ' . $finalUrl);
    exit;
}
