<?php
/**
 * Backend handler to get action prediction history
 * Returns JSON with prediction history for the current user
 */

// Disable error display, log errors instead
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Set JSON header immediately
header('Content-Type: application/json; charset=utf-8');

// Start output buffering
ob_start();

try {
    session_start();
    
    // Load bootstrap first for database connection
    require_once __DIR__ . '/../../user/backend/bootstrap.php';
    
    // Check authentication manually
    if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
        ob_end_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $authUser = $_SESSION['user'];
    
    // Get prediction history
    try {
        $stmt = $pdo->prepare('SELECT id, video_name, video_path, predicted_class, confidence, probabilities, created_at FROM action_predictions WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
        $stmt->execute([$authUser['id']]);
        $history = $stmt->fetchAll();
        
        foreach ($history as &$item) {
            $item['probabilities'] = json_decode($item['probabilities'] ?? '{}', true) ?: [];
        }
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'history' => $history
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
    } catch (PDOException $e) {
        error_log("Database error in get prediction history: " . $e->getMessage());
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Database error',
            'history' => []
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
} catch (Exception $e) {
    ob_end_clean();
    error_log("Get prediction history error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'history' => []
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Error $e) {
    ob_end_clean();
    error_log("Get prediction history fatal error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage(),
        'history' => []
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

