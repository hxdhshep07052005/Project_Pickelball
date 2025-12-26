<?php
declare(strict_types=1);

/**
 * Authentication middleware
 * Redirects to login page if user is not authenticated
 * Saves return URL for redirect after successful login
 */

require __DIR__ . '/bootstrap.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    // Save the current URL to redirect back after login
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    // Validate URL to prevent open redirect attacks
    if (strpos($requestUri, '/pickelball/') === 0 || strpos($requestUri, '/') === 0) {
        $_SESSION['return_url'] = $requestUri;
    } else {
        $_SESSION['return_url'] = '/pickelball/main/frontend/index.php';
    }
    header('Location: /pickelball/user/frontend/login.php');
    exit;
}

// Set authenticated user data for use in protected pages
$authUser = $_SESSION['user'];

