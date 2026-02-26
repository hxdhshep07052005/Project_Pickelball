<?php
declare(strict_types=1);

/**
 * Admin backend: View action prediction details
 */

require __DIR__ . '/require_auth.php';
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

try {
    $predictionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($predictionId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid prediction ID']);
        exit;
    }
    
    // Get prediction details
    $query = 'SELECT ap.*, u.email, u.display_name 
              FROM action_predictions ap 
              LEFT JOIN users u ON ap.user_id = u.id 
              WHERE ap.id = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$predictionId]);
    $prediction = $stmt->fetch();
    
    if (!$prediction) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Prediction not found']);
        exit;
    }
    
    // Decode JSON fields
    $prediction['probabilities'] = json_decode($prediction['probabilities'] ?? '{}', true) ?: [];
    
    echo json_encode([
        'success' => true,
        'prediction' => $prediction
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (PDOException $e) {
    error_log("View prediction error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
} catch (Throwable $e) {
    error_log("View prediction fatal error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
