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
    $userId = isset($input['id']) ? (int)$input['id'] : 0;

    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
        exit;
    }


    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    $pdo->beginTransaction();

    try {
        $videoStmt = $pdo->prepare('SELECT video_path FROM video_analyses WHERE user_id = ?');
        $videoStmt->execute([$userId]);
        $videos = $videoStmt->fetchAll();

        foreach ($videos as $video) {
            if ($video['video_path']) {
                $filePath = $_SERVER['DOCUMENT_ROOT'] . $video['video_path'];
                if (file_exists($filePath) && is_file($filePath)) {
                    @unlink($filePath);
                }
            }
        }

        $predStmt = $pdo->prepare('SELECT video_path FROM action_predictions WHERE user_id = ?');
        $predStmt->execute([$userId]);
        $predictions = $predStmt->fetchAll();

        foreach ($predictions as $pred) {
            if ($pred['video_path']) {
                $filePath = $_SERVER['DOCUMENT_ROOT'] . $pred['video_path'];
                if (file_exists($filePath) && is_file($filePath)) {
                    @unlink($filePath);
                }
            }
        }

        $pdo->prepare('DELETE FROM video_analyses WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM action_predictions WHERE user_id = ?')->execute([$userId]);

        $pdo->prepare('DELETE FROM user_preferences WHERE user_id = ?')->execute([$userId]);

        $pdo->prepare('DELETE FROM user_sessions WHERE user_id = ?')->execute([$userId]);

        $pdo->prepare('DELETE FROM user_identities WHERE user_id = ?')->execute([$userId]);

        $deleteStmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $deleteStmt->execute([$userId]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Delete user error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
} catch (Throwable $e) {
    error_log("Delete user fatal error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
