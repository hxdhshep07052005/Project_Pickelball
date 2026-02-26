<?php
declare(strict_types=1);

/**
 * Admin backend: Get chart data for dashboard
 */

require __DIR__ . '/require_auth.php';
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

try {
    $days = isset($_GET['days']) ? min(30, max(7, (int)$_GET['days'])) : 7;
    
    // Get video analyses data by date
    $analysesQuery = "SELECT DATE(created_at) as date, COUNT(*) as count 
                      FROM video_analyses 
                      WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                      GROUP BY DATE(created_at)
                      ORDER BY date ASC";
    $stmt = $pdo->prepare($analysesQuery);
    $stmt->execute([$days]);
    $analysesData = $stmt->fetchAll();
    
    // Get action predictions data by date
    $predictionsQuery = "SELECT DATE(created_at) as date, COUNT(*) as count 
                         FROM action_predictions 
                         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                         GROUP BY DATE(created_at)
                         ORDER BY date ASC";
    $stmt = $pdo->prepare($predictionsQuery);
    $stmt->execute([$days]);
    $predictionsData = $stmt->fetchAll();
    
    // Get user registrations by date
    $usersQuery = "SELECT DATE(created_at) as date, COUNT(*) as count 
                   FROM users 
                   WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                   GROUP BY DATE(created_at)
                   ORDER BY date ASC";
    $stmt = $pdo->prepare($usersQuery);
    $stmt->execute([$days]);
    $usersData = $stmt->fetchAll();
    
    // Format data for charts
    $labels = [];
    $analysesCounts = [];
    $predictionsCounts = [];
    $usersCounts = [];
    
    // Create date range
    $startDate = date('Y-m-d', strtotime("-$days days"));
    $endDate = date('Y-m-d');
    
    $currentDate = $startDate;
    $dataMap = [
        'analyses' => [],
        'predictions' => [],
        'users' => []
    ];
    
    // Map existing data
    foreach ($analysesData as $row) {
        $dataMap['analyses'][$row['date']] = (int)$row['count'];
    }
    foreach ($predictionsData as $row) {
        $dataMap['predictions'][$row['date']] = (int)$row['count'];
    }
    foreach ($usersData as $row) {
        $dataMap['users'][$row['date']] = (int)$row['count'];
    }
    
    // Generate labels and counts
    while ($currentDate <= $endDate) {
        $labels[] = date('M d', strtotime($currentDate));
        $analysesCounts[] = $dataMap['analyses'][$currentDate] ?? 0;
        $predictionsCounts[] = $dataMap['predictions'][$currentDate] ?? 0;
        $usersCounts[] = $dataMap['users'][$currentDate] ?? 0;
        $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'labels' => $labels,
            'analyses' => $analysesCounts,
            'predictions' => $predictionsCounts,
            'users' => $usersCounts
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (PDOException $e) {
    error_log("Chart data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
} catch (Throwable $e) {
    error_log("Chart data fatal error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
