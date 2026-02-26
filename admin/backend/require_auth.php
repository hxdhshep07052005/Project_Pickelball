<?php
declare(strict_types=1);

/**
 * Admin authentication middleware
 * Redirects to login page if admin is not authenticated
 */

require __DIR__ . '/bootstrap.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header('Location: ../frontend/login.php');
    exit;
}

// Set authenticated admin data for use in protected pages
$authAdmin = $_SESSION['admin'];

