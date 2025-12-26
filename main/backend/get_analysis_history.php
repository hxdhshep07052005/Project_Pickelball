<?php
declare(strict_types=1);

/**
 * Get video analysis history backend
 * Returns list of user's video analyses
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require authentication and database connection
require_once __DIR__ . '/../../user/backend/require_auth.php';
require_once __DIR__ . '/../../user/backend/bootstrap.php';

// Get user ID
$userId = (int)$authUser['id'];

// Get analysis history from database
try {
    $stmt = $pdo->prepare('SELECT id, video_name, video_path, techniques_detected, score, status, created_at FROM video_analyses WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
    $stmt->execute([$userId]);
    $analyses = $stmt->fetchAll();
    
    // Decode JSON techniques
    foreach ($analyses as &$analysis) {
        $analysis['techniques_detected'] = json_decode($analysis['techniques_detected'] ?? '[]', true) ?: [];
        $analysis['created_at'] = date('Y-m-d H:i', strtotime($analysis['created_at']));
    }
    
    return $analyses;
} catch (PDOException $e) {
    return [];
}

