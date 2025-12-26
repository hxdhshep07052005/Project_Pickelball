<?php
/**
 * Backend handler for action video prediction
 * Uses trained LSTM model to predict DriveBackhand or DriveForehand
 */

// Disable error display, log errors instead
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Set JSON header immediately
header('Content-Type: application/json; charset=utf-8');

// Start output buffering to catch any unexpected output
ob_start();

try {
    session_start();
    
    // Load bootstrap first for database connection
    require_once __DIR__ . '/../../user/backend/bootstrap.php';
    
    // Check authentication manually (require_auth.php redirects, which doesn't work for JSON API)
    if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
        ob_end_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $authUser = $_SESSION['user'];
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Initialization error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Error $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Fatal initialization error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Handle file upload
if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
    ob_end_clean();
    $uploadError = $_FILES['video']['error'] ?? 'Unknown error';
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
    ];
    $errorMsg = $errorMessages[$uploadError] ?? 'Upload error: ' . $uploadError;
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $errorMsg], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = $_FILES['video'];
$allowedTypes = ['video/mp4', 'video/avi', 'video/quicktime', 'video/x-msvideo'];
$maxSize = 100 * 1024 * 1024; // 100MB

// Validate file type
try {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        throw new Exception('Failed to open fileinfo');
    }
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if ($mimeType === false) {
        throw new Exception('Failed to detect file type');
    }
    
    if (!in_array($mimeType, $allowedTypes)) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Please upload MP4, AVI, or MOV file.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (Exception $e) {
    ob_end_clean();
    error_log("File type validation error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File validation error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validate file size
if ($file['size'] > $maxSize) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 100MB.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Clear any output buffer
    ob_end_clean();
    
    // Create upload directory
    $uploadDir = __DIR__ . '/../../uploads/action_prediction/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $uniqueFileName = uniqid('action_', true) . '_' . time() . '.' . $extension;
    $uploadPath = $uploadDir . $uniqueFileName;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to save uploaded file');
    }
    
    // Get absolute paths
    $uploadPathAbs = realpath($uploadPath);
    $actionPredDir = __DIR__ . '/../../Action_Video_Prediction';
    $pythonScript = $actionPredDir . '/predict_action.py';
    $modelPath = $actionPredDir . '/Model_2dongtac.pth';
    
    // Check if files exist
    if (!file_exists($pythonScript)) {
        throw new Exception('Python prediction script not found: ' . $pythonScript);
    }
    
    if (!file_exists($modelPath)) {
        throw new Exception('Model file not found: ' . $modelPath);
    }
    
    // Get Python executable
    $pythonCmd = null;
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $testCommands = ['python', 'py', 'python3'];
        foreach ($testCommands as $cmd) {
            $testOutput = @shell_exec($cmd . ' --version 2>&1');
            if ($testOutput && strpos($testOutput, 'Python') !== false) {
                $pythonCmd = $cmd;
                break;
            }
        }
    } else {
        $testOutput = @shell_exec('python3 --version 2>&1');
        if ($testOutput && strpos($testOutput, 'Python') !== false) {
            $pythonCmd = 'python3';
        } else {
            $testOutput = @shell_exec('python --version 2>&1');
            if ($testOutput && strpos($testOutput, 'Python') !== false) {
                $pythonCmd = 'python';
            }
        }
    }
    
    if (!$pythonCmd) {
        throw new Exception('Python not found. Please install Python.');
    }
    
    // Run prediction
    $command = escapeshellarg($pythonCmd) . ' ' . 
               escapeshellarg($pythonScript) . ' ' . 
               escapeshellarg($uploadPathAbs) . 
               ' --model ' . escapeshellarg($modelPath) . 
               ' --device cpu 2>&1';
    
    $originalDir = getcwd();
    chdir($actionPredDir);
    $output = shell_exec($command);
    chdir($originalDir);
    
    if (!$output) {
        throw new Exception('No output from prediction script');
    }
    
    // Parse JSON response
    $jsonStart = strpos($output, '{');
    if ($jsonStart === false) {
        error_log("Python script output (first 500 chars): " . substr($output, 0, 500));
        throw new Exception('Invalid response format from prediction script. Output: ' . substr($output, 0, 200));
    }
    
    $braceCount = 0;
    $jsonEnd = $jsonStart;
    for ($i = $jsonStart; $i < strlen($output); $i++) {
        if ($output[$i] === '{') $braceCount++;
        if ($output[$i] === '}') {
            $braceCount--;
            if ($braceCount === 0) {
                $jsonEnd = $i + 1;
                break;
            }
        }
    }
    
    $jsonOutput = substr($output, $jsonStart, $jsonEnd - $jsonStart);
    $result = json_decode($jsonOutput, true);
    
    if (!$result) {
        error_log("Failed to parse JSON. Output: " . substr($output, 0, 500));
        throw new Exception('Failed to parse prediction result. JSON error: ' . json_last_error_msg());
    }
    
    if (!$result['success']) {
        throw new Exception($result['error'] ?? 'Prediction failed');
    }
    
    // Save prediction to database
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS action_predictions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            video_name VARCHAR(255) NOT NULL,
            video_path VARCHAR(500) NOT NULL,
            predicted_class VARCHAR(50) NOT NULL,
            confidence DECIMAL(5,2) NOT NULL,
            probabilities TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $stmt = $pdo->prepare('INSERT INTO action_predictions (user_id, video_name, video_path, predicted_class, confidence, probabilities) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $authUser['id'],
            $file['name'],
            '/pickelball/uploads/action_prediction/' . $uniqueFileName,
            $result['predicted_class'],
            $result['confidence'],
            json_encode($result['probabilities'])
        ]);
        
        $predictionId = $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Database error in action prediction: " . $e->getMessage());
        // Continue even if database save fails
    }
    
    // Return result
    echo json_encode([
        'success' => true,
        'prediction' => [
            'class' => $result['predicted_class'],
            'confidence' => $result['confidence'],
            'probabilities' => $result['probabilities'],
            'frames_processed' => $result['frames_processed'] ?? 0
        ],
        'video_path' => '/pickelball/uploads/action_prediction/' . $uniqueFileName,
        'video_name' => $file['name']
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    error_log("Action prediction error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Error $e) {
    error_log("Action prediction fatal error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
