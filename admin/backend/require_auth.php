<?php
declare(strict_types=1);



require __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['admin'])) {
    header('Location: ../frontend/login.php');
    exit;
}

$authAdmin = $_SESSION['admin'];
