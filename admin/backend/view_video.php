<?php
declare(strict_types=1);

/**
 * Admin backend: View video analysis details
 */

require __DIR__ . '/require_auth.php';
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

try {
    $videoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($videoId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid video ID']);
        exit;
    }
    
    // Get video analysis details
    $query = 'SELECT va.*, u.email, u.display_name 
              FROM video_analyses va 
              LEFT JOIN users u ON va.user_id = u.id 
              WHERE va.id = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$videoId]);
    $video = $stmt->fetch();
    
    if (!$video) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Video not found']);
        exit;
    }
    
    // Decode JSON fields
    $video['techniques_detected'] = json_decode($video['techniques_detected'] ?? '[]', true) ?: [];
    $video['coaching_feedback'] = json_decode($video['coaching_feedback'] ?? 'null', true);
    $video['raw_feedback'] = json_decode($video['raw_feedback'] ?? 'null', true);
    
    echo json_encode([
        'success' => true,
        'video' => $video
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (PDOException $e) {
    error_log("View video error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
} catch (Throwable $e) {
    error_log("View video fatal error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
