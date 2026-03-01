<?php
declare(strict_types=1);



if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../user/backend/require_auth.php';
require_once __DIR__ . '/../../user/backend/bootstrap.php';

$userId = (int)$authUser['id'];

try {
    $stmt = $pdo->prepare('SELECT id, video_name, video_path, techniques_detected, score, status, created_at FROM video_analyses WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
    $stmt->execute([$userId]);
    $analyses = $stmt->fetchAll();

    foreach ($analyses as &$analysis) {
        $analysis['techniques_detected'] = json_decode($analysis['techniques_detected'] ?? '[]', true) ?: [];
        $analysis['created_at'] = date('Y-m-d H:i', strtotime($analysis['created_at']));
    }

    return $analyses;
} catch (PDOException $e) {
    return [];
}
