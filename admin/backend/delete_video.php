<?php
declare(strict_types=1);



require __DIR__ . '/require_auth.php';
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $videoId = isset($input['id']) ? (int)$input['id'] : 0;

    if ($videoId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid video ID']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT video_path FROM video_analyses WHERE id = ?');
    $stmt->execute([$videoId]);
    $video = $stmt->fetch();

    if (!$video) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Video not found']);
        exit;
    }

    $pdo->beginTransaction();

    try {
        $deleteStmt = $pdo->prepare('DELETE FROM video_analyses WHERE id = ?');
        $deleteStmt->execute([$videoId]);

        if ($video['video_path']) {
            $filePath = $_SERVER['DOCUMENT_ROOT'] . $video['video_path'];
            if (file_exists($filePath) && is_file($filePath)) {
                @unlink($filePath);
            }
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Video deleted successfully'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Delete video error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
} catch (Throwable $e) {
    error_log("Delete video fatal error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
