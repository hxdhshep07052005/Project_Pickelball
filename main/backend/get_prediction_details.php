<?php


ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

ob_start();

try {
    session_start();

    require_once __DIR__ . '/../../user/backend/bootstrap.php';

    if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
        ob_end_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $authUser = $_SESSION['user'];

    $input = json_decode(file_get_contents('php://input'), true);
    $predictionId = isset($input['id']) ? (int)$input['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : null);

    if (!$predictionId) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Prediction ID is required'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT id, video_name, video_path, predicted_class, confidence, probabilities, analysis_session_id, analysis_success, analysis_feedback, analysis_coaching_feedback, analysis_raw_feedback, created_at FROM action_predictions WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$predictionId, $authUser['id']]);
        $prediction = $stmt->fetch();

        if (!$prediction) {
            ob_end_clean();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Prediction not found'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $prediction['probabilities'] = json_decode($prediction['probabilities'] ?? '{}', true) ?: [];
        $prediction['analysis_feedback'] = [];
        if (!empty($prediction['analysis_raw_feedback'])) {
            $decoded = json_decode($prediction['analysis_raw_feedback'], true);
            if (is_array($decoded)) {
                $prediction['analysis_feedback'] = $decoded;
            } elseif (is_string($prediction['analysis_raw_feedback'])) {
                $decoded = json_decode($prediction['analysis_raw_feedback'], true);
                $prediction['analysis_feedback'] = is_array($decoded) ? $decoded : [];
            }
        }

        $prediction['has_analysis'] = !empty($prediction['analysis_session_id']) && !empty($prediction['analysis_success']);

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'prediction' => $prediction
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    } catch (PDOException $e) {
        error_log("Database error in get prediction details: " . $e->getMessage());
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Database error'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

} catch (Exception $e) {
    ob_end_clean();
    error_log("Get prediction details error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Error $e) {
    ob_end_clean();
    error_log("Get prediction details fatal error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
