<?php
declare(strict_types=1);



require __DIR__ . '/session.php';
$config = require __DIR__ . '/config.php';

$state = bin2hex(random_bytes(32));
$_SESSION['google_oauth_state'] = $state;

$query = [
    'client_id' => $config['google']['client_id'],
    'redirect_uri' => $config['google']['redirect_uri'],
    'response_type' => 'code',
    'scope' => implode(' ', $config['google']['scopes']),
    'state' => $state, // CSRF protection
    'access_type' => 'offline', // Request refresh token
    'prompt' => 'select_account', // Force account selection
];

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($query));
exit;
