<?php
declare(strict_types=1);

/**
 * Google OAuth callback handler
 * Processes OAuth callback, exchanges code for token, fetches user profile, and creates/updates user account
 */

require __DIR__ . '/bootstrap.php';
$config = require __DIR__ . '/config.php';
require __DIR__ . '/mailer.php';

// Helper function to handle errors and redirect to login
$fail = static function (string $message): void {
    $_SESSION['login_error'] = $message;
    header('Location: ../frontend/login.php');
    exit;
};

// Validate OAuth state and authorization code
if (!isset($_GET['state'], $_GET['code'], $_SESSION['google_oauth_state'])) {
    $fail('Invalid sign-in request.');
}

// Verify state token to prevent CSRF attacks
if (!hash_equals($_SESSION['google_oauth_state'], (string)$_GET['state'])) {
    unset($_SESSION['google_oauth_state']);
    $fail('Authentication session expired.');
}

unset($_SESSION['google_oauth_state']);

// Exchange authorization code for access token
$tokenPayload = http_build_query([
    'code' => $_GET['code'],
    'client_id' => $config['google']['client_id'],
    'client_secret' => $config['google']['client_secret'],
    'redirect_uri' => $config['google']['redirect_uri'],
    'grant_type' => 'authorization_code',
]);

$tokenContext = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
        'content' => $tokenPayload,
        'timeout' => 15,
    ],
]);

$tokenResponse = file_get_contents('https://oauth2.googleapis.com/token', false, $tokenContext);
$tokenData = $tokenResponse ? json_decode($tokenResponse, true) : null;

if (!$tokenData || !isset($tokenData['access_token'])) {
    $fail('Unable to retrieve information from Google.');
}

// Fetch user profile from Google API
$profileContext = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Authorization: Bearer ' . $tokenData['access_token'],
        'timeout' => 15,
    ],
]);

$profileResponse = file_get_contents('https://www.googleapis.com/oauth2/v2/userinfo', false, $profileContext);
$profile = $profileResponse ? json_decode($profileResponse, true) : null;

if (!$profile || !isset($profile['id'], $profile['email'])) {
    $fail('Unable to verify Google account.');
}

// Extract user information from Google profile
$googleId = (string)$profile['id'];
$email = strtolower((string)$profile['email']);
$name = trim((string)($profile['name'] ?? $profile['given_name'] ?? 'Player'));
$refreshToken = $tokenData['refresh_token'] ?? null;
$expiresAt = isset($tokenData['expires_in']) ? gmdate('Y-m-d H:i:s', time() + (int)$tokenData['expires_in']) : null;

// Create or update user account and identity
try {
    $pdo->beginTransaction();

    // Check if Google identity already exists
    $identityStatement = $pdo->prepare(
        'SELECT ui.id, ui.user_id, u.display_name, u.role, u.status FROM user_identities ui INNER JOIN users u ON u.id = ui.user_id WHERE ui.provider = ? AND ui.provider_user_id = ? LIMIT 1'
    );
    $identityStatement->execute(['google', $googleId]);
    $identity = $identityStatement->fetch();

    if ($identity) {
        // Existing Google identity - update tokens
        if ($identity['status'] !== 'active') {
            $pdo->rollBack();
            $fail('Your account is restricted.');
        }

        // Update access token and refresh token
        $pdo->prepare('UPDATE user_identities SET access_token = ?, refresh_token = ?, expires_at = ? WHERE id = ?')->execute([
            $tokenData['access_token'],
            $refreshToken,
            $expiresAt,
            $identity['id'],
        ]);

        $userId = (int)$identity['user_id'];
        $displayName = $identity['display_name'];
        $role = $identity['role'];
    } else {
        // New Google identity - check if user exists by email
        $userStatement = $pdo->prepare('SELECT id, display_name, role, status, auth_provider FROM users WHERE email = ? LIMIT 1');
        $userStatement->execute([$email]);
        $existingUser = $userStatement->fetch();

        if ($existingUser) {
            // User exists - link Google identity
            if ($existingUser['status'] !== 'active') {
                $pdo->rollBack();
                $fail('Your account is restricted.');
            }

            $userId = (int)$existingUser['id'];
            $displayName = $existingUser['display_name'];
            $role = $existingUser['role'];
        } else {
            // New user - create account
            $role = 'player';
            $userInsert = $pdo->prepare(
                'INSERT INTO users (email, display_name, role, status, auth_provider, provider_user_id, email_verified_at, last_login_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $userInsert->execute([
                $email,
                $name,
                $role,
                'active',
                'google',
                $googleId,
            ]);
            $userId = (int)$pdo->lastInsertId();
            $displayName = $name;
        }

        // Create Google identity record
        $identityInsert = $pdo->prepare(
            'INSERT INTO user_identities (user_id, provider, provider_user_id, access_token, refresh_token, expires_at) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $identityInsert->execute([
            $userId,
            'google',
            $googleId,
            $tokenData['access_token'],
            $refreshToken,
            $expiresAt,
        ]);
    }

    // Update user's auth provider to Google
    $pdo->prepare('UPDATE users SET auth_provider = ?, provider_user_id = ? WHERE id = ?')->execute([
        'google',
        $googleId,
        $userId,
    ]);

    $pdo->commit();
} catch (Throwable $exception) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $fail('Google sign-in failed.');
}

// Generate and send OTP for login verification
$otpLifetime = (int)($config['otp']['lifetime_seconds'] ?? 300);
$otpCode = (string)random_int(100000, 999999);

if (!sendOtpMail($config, $email, $otpCode, 'login')) {
    $fail('Unable to send verification code. Please try again.');
}

// Store pending login in session for OTP verification
$_SESSION['pending_login'] = [
    'user_id' => $userId,
    'name' => $displayName,
    'role' => $role,
    'email' => $email,
    'otp_hash' => password_hash($otpCode, PASSWORD_DEFAULT),
    'otp_expires_at' => time() + $otpLifetime,
    'attempts' => 0,
];
$_SESSION['verify_notice'] = 'A verification code has been sent to your email.';

// Redirect to verification page
header('Location: ../frontend/verify.php');
exit;

