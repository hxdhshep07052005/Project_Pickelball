<?php
declare(strict_types=1);



require __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user'])) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($requestUri, '/pickelball/') === 0 || strpos($requestUri, '/') === 0) {
        $_SESSION['return_url'] = $requestUri;
    } else {
        $_SESSION['return_url'] = '/pickelball/main/frontend/index.php';
    }
    header('Location: /pickelball/user/frontend/login.php');
    exit;
}

$authUser = $_SESSION['user'];
