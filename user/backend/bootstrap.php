<?php
declare(strict_types=1);



require __DIR__ . '/session.php';

$dsn = 'mysql:host=127.0.0.1;dbname=pickleball_training;charset=utf8mb4';
$username = 'root';
$password = '';
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Return associative arrays
    PDO::ATTR_EMULATE_PREPARES => false, // Use native prepared statements
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $exception) {
    http_response_code(500);
    echo 'Database connection failed';
    exit;
}
