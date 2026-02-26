<?php
declare(strict_types=1);

/**
 * Admin backend: Delete action prediction
 */

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
    $predictionId = isset($input['id']) ? (int)$input['id'] : 0;
    
    if ($predictionId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid prediction ID']);
        exit;
    }
    
    // Get prediction path before deleting
    $stmt = $pdo->prepare('SELECT video_path FROM action_predictions WHERE id = ?');
    $stmt->execute([$predictionId]);
    $prediction = $stmt->fetch();
    
    if (!$prediction) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Prediction not found']);
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Delete from database
        $deleteStmt = $pdo->prepare('DELETE FROM action_predictions WHERE id = ?');
        $deleteStmt->execute([$predictionId]);
        
        // Delete video file if exists
        if ($prediction['video_path']) {
            $filePath = $_SERVER['DOCUMENT_ROOT'] . $prediction['video_path'];
            if (file_exists($filePath) && is_file($filePath)) {
                @unlink($filePath);
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Prediction deleted successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Delete prediction error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
} catch (Throwable $e) {
    error_log("Delete prediction fatal error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
