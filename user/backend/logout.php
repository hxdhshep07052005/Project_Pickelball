<?php
declare(strict_types=1);

/**
 * Logout handler
 * Clears session data and destroys session cookie
 */

require __DIR__ . '/bootstrap.php';

// Clear all session data
$_SESSION = [];

// Destroy session cookie if cookies are enabled
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

// Destroy session
session_destroy();

// Redirect to login page
header('Location: ../frontend/login.php');
exit;

