<?php
declare(strict_types=1);



return [
    'recaptcha_site_key' => 'YOUR_RECAPTCHA_SITE_KEY',
    'recaptcha_secret_key' => 'YOUR_RECAPTCHA_SECRET_KEY',

    'google' => [
        'client_id' => 'YOUR_GOOGLE_CLIENT_ID',
        'client_secret' => 'YOUR_GOOGLE_CLIENT_SECRET',
        'redirect_uri' => 'http://localhost/pickelball/user/backend/google_callback.php',
        'scopes' => [
            'openid',
            'email',
            'profile'
        ]
    ],

    'mailer' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'YOUR_EMAIL@gmail.com',
        'password' => 'YOUR_APP_PASSWORD',
        'from_email' => 'YOUR_EMAIL@gmail.com',
        'from_name' => 'Pickleball Training'
    ],

    'otp' => [
        'lifetime_seconds' => 300, // 5 minutes
        'max_attempts' => 5 // Maximum verification attempts
    ]
];
