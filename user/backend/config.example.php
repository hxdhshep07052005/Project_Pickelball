<?php
declare(strict_types=1);

/**
 * Configuration file template - Application settings
 * Copy this file to config.php and fill in your actual credentials
 * 
 * IMPORTANT: Never commit config.php to Git! It contains sensitive information.
 */

return [
    // reCAPTCHA v2 keys for bot prevention
    // Get your keys from: https://www.google.com/recaptcha/admin
    'recaptcha_site_key' => 'YOUR_RECAPTCHA_SITE_KEY',
    'recaptcha_secret_key' => 'YOUR_RECAPTCHA_SECRET_KEY',
    
    // Google OAuth 2.0 configuration
    // Get your credentials from: https://console.cloud.google.com/apis/credentials
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
    
    // SMTP mailer configuration for sending OTP emails
    // For Gmail, you need to use App Password: https://support.google.com/accounts/answer/185833
    'mailer' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'YOUR_EMAIL@gmail.com',
        'password' => 'YOUR_APP_PASSWORD',
        'from_email' => 'YOUR_EMAIL@gmail.com',
        'from_name' => 'Pickleball Training'
    ],
    
    // OTP (One-Time Password) settings
    'otp' => [
        'lifetime_seconds' => 300, // 5 minutes
        'max_attempts' => 5 // Maximum verification attempts
    ]
];

