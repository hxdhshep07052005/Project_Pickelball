<?php
declare(strict_types=1);

/**
 * Google OAuth login initiation
 * Generates state token and redirects to Google OAuth consent screen
 */

require __DIR__ . '/session.php';
$config = require __DIR__ . '/config.php';

// Generate random state token for CSRF protection
$state = bin2hex(random_bytes(32));
$_SESSION['google_oauth_state'] = $state;

// Build OAuth authorization URL
$query = [
    'client_id' => $config['google']['client_id'],
    'redirect_uri' => $config['google']['redirect_uri'],
    'response_type' => 'code',
    'scope' => implode(' ', $config['google']['scopes']),
    'state' => $state, // CSRF protection
    'access_type' => 'offline', // Request refresh token
    'prompt' => 'select_account', // Force account selection
];

// Redirect to Google OAuth consent screen
header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($query));
exit;

