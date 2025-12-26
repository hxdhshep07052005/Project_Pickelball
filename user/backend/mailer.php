<?php
declare(strict_types=1);

/**
 * SMTP mailer function
 * Sends OTP verification code via email using SMTP protocol
 * Supports login, registration, and password_change contexts
 */

function sendOtpMail(array $config, string $recipientEmail, string $code, string $context = 'login'): bool
{
    // Get mailer configuration
    $mailerConfig = $config['mailer'] ?? [];

    $username = trim($mailerConfig['username'] ?? '');
    $password = trim($mailerConfig['password'] ?? '');
    $fromEmail = trim($mailerConfig['from_email'] ?? '');
    $fromName = trim($mailerConfig['from_name'] ?? '');
    $host = trim($mailerConfig['host'] ?? 'smtp.gmail.com');
    $port = (int)($mailerConfig['port'] ?? 587);
    $encryption = strtolower(trim((string)($mailerConfig['encryption'] ?? 'tls')));

    // Validate required configuration
    if ($username === '' || $password === '' || $fromEmail === '' || $fromName === '' || $recipientEmail === '') {
        return false;
    }

    // Create SMTP connection
    $transport = $encryption === 'ssl' ? 'ssl://' . $host . ':' . $port : $host . ':' . $port;
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ]);

    // Connect to SMTP server
    $socket = @stream_socket_client($transport, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context);

    if (!$socket) {
        return false;
    }

    stream_set_timeout($socket, 20);

    // Helper function to read SMTP response
    $read = static function () use ($socket): ?string {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        return $response === '' ? null : $response;
    };

    // Helper function to send SMTP command and check response
    $command = static function (string $payload) use ($socket, $read): bool {
        fwrite($socket, $payload . "\r\n");
        $response = $read();
        if ($response === null) {
            return false;
        }
        $code = (int)substr($response, 0, 3);
        return $code >= 200 && $code < 400; // Success codes
    };

    // Read SMTP greeting
    $greeting = $read();

    if ($greeting === null || strncmp($greeting, '220', 3) !== 0) {
        fclose($socket);
        return false;
    }

    // Send EHLO command
    if (!$command('EHLO localhost')) {
        fclose($socket);
        return false;
    }

    // Enable TLS encryption if required
    if ($encryption === 'tls') {
        if (!$command('STARTTLS')) {
            fclose($socket);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }
        // Send EHLO again after TLS
        if (!$command('EHLO localhost')) {
            fclose($socket);
            return false;
        }
    }

    // Authenticate with SMTP server
    if (!$command('AUTH LOGIN')) {
        fclose($socket);
        return false;
    }

    if (!$command(base64_encode($username))) {
        fclose($socket);
        return false;
    }

    if (!$command(base64_encode($password))) {
        fclose($socket);
        return false;
    }

    // Set sender and recipient
    if (!$command('MAIL FROM:<' . $fromEmail . '>')) {
        fclose($socket);
        return false;
    }

    if (!$command('RCPT TO:<' . $recipientEmail . '>')) {
        fclose($socket);
        return false;
    }

    // Prepare to send email data
    if (!$command('DATA')) {
        fclose($socket);
        return false;
    }

    // Set email subject and body based on context
    if ($context === 'register') {
        $subject = 'Pickleball registration verification code';
        $bodyMessage = "Welcome to Pickleball Training.\nYour registration verification code is: $code";
    } elseif ($context === 'password_change') {
        $subject = 'Pickleball password change verification code';
        $bodyMessage = "You requested to change your password.\nYour password change verification code is: $code\n\nIf you didn't request this, please ignore this email.";
    } else {
        $subject = 'Pickleball sign-in verification code';
        $bodyMessage = "Your Pickleball sign-in verification code is: $code";
    }

    // Build email headers and message
    $headers = [
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'To: <' . $recipientEmail . '>',
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    $body = $bodyMessage;
    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";

    // Send email message
    fwrite($socket, $message . "\r\n");

    // Check if email was accepted
    $dataResponse = $read();

    if ($dataResponse === null || strncmp($dataResponse, '250', 3) !== 0) {
        fclose($socket);
        return false;
    }

    // Close SMTP connection
    $command('QUIT');
    fclose($socket);

    return true;
}

