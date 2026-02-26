<?php
declare(strict_types=1);

/**
 * Session initialization
 * Starts PHP session if not already started
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

