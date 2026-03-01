<?php
declare(strict_types=1);



function sendOtpMail(array $config, string $recipientEmail, string $code, string $context = 'login'): bool
{
    $mailerConfig = $config['mailer'] ?? [];

    $username = trim($mailerConfig['username'] ?? '');
    $password = trim($mailerConfig['password'] ?? '');
    $fromEmail = trim($mailerConfig['from_email'] ?? '');
    $fromName = trim($mailerConfig['from_name'] ?? '');
    $host = trim($mailerConfig['host'] ?? 'smtp.gmail.com');
    $port = (int)($mailerConfig['port'] ?? 587);
    $encryption = strtolower(trim((string)($mailerConfig['encryption'] ?? 'tls')));

    if ($username === '' || $password === '' || $fromEmail === '' || $fromName === '' || $recipientEmail === '') {
        return false;
    }

    $transport = $encryption === 'ssl' ? 'ssl://' . $host . ':' . $port : $host . ':' . $port;
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ]);

    $socket = @stream_socket_client($transport, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context);

    if (!$socket) {
        return false;
    }

    stream_set_timeout($socket, 20);

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

    $command = static function (string $payload) use ($socket, $read): bool {
        fwrite($socket, $payload . "\r\n");
        $response = $read();
        if ($response === null) {
            return false;
        }
        $code = (int)substr($response, 0, 3);
        return $code >= 200 && $code < 400; // Success codes
    };

    $greeting = $read();

    if ($greeting === null || strncmp($greeting, '220', 3) !== 0) {
        fclose($socket);
        return false;
    }

    if (!$command('EHLO localhost')) {
        fclose($socket);
        return false;
    }

    if ($encryption === 'tls') {
        if (!$command('STARTTLS')) {
            fclose($socket);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }
        if (!$command('EHLO localhost')) {
            fclose($socket);
            return false;
        }
    }

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

    if (!$command('MAIL FROM:<' . $fromEmail . '>')) {
        fclose($socket);
        return false;
    }

    if (!$command('RCPT TO:<' . $recipientEmail . '>')) {
        fclose($socket);
        return false;
    }

    if (!$command('DATA')) {
        fclose($socket);
        return false;
    }

    $subject = 'Admin Login Verification Code';
    $bodyMessage = "Your admin login verification code is: $code\n\nThis code will expire in 5 minutes.";

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

    fwrite($socket, $message . "\r\n");

    $dataResponse = $read();

    if ($dataResponse === null || strncmp($dataResponse, '250', 3) !== 0) {
        fclose($socket);
        return false;
    }

    $command('QUIT');
    fclose($socket);

    return true;
}
