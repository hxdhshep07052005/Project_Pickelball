<?php
declare(strict_types=1);

/**
 * Video analysis backend handler
 * Handles video upload and returns analysis result (model placeholder)
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require authentication and database connection
require_once __DIR__ . '/../../user/backend/require_auth.php';
require_once __DIR__ . '/../../user/backend/bootstrap.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pickelball/main/frontend/video_analysis.php');
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['analysis_error'] = 'Please select a video file to upload.';
    header('Location: /pickelball/main/frontend/video_analysis.php');
    exit;
}

$file = $_FILES['video'];
$fileName = $file['name'];
$fileTmpName = $file['tmp_name'];
$fileSize = $file['size'];
$fileError = $file['error'];

// Validate file type (only video files)
$allowedExtensions = ['mp4', 'webm', 'mov', 'avi', 'mkv'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (!in_array($fileExtension, $allowedExtensions)) {
    $_SESSION['analysis_error'] = 'Invalid file type. Please upload a video file (mp4, webm, mov, avi, mkv).';
    header('Location: /pickelball/main/frontend/video_analysis.php');
    exit;
}

// Validate file size (max 100MB)
$maxSize = 100 * 1024 * 1024; // 100MB in bytes
if ($fileSize > $maxSize) {
    $_SESSION['analysis_error'] = 'File size exceeds 100MB limit. Please upload a smaller video.';
    header('Location: /pickelball/main/frontend/video_analysis.php');
    exit;
}

// Create upload directory if it doesn't exist
$uploadDir = __DIR__ . '/../../uploads/video_analysis/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename to prevent overwrites
$uniqueFileName = uniqid('video_', true) . '_' . time() . '.' . $fileExtension;
$uploadPath = $uploadDir . $uniqueFileName;

// Move uploaded file to upload directory
if (!move_uploaded_file($fileTmpName, $uploadPath)) {
    $_SESSION['analysis_error'] = 'Failed to upload video. Please try again.';
    header('Location: /pickelball/main/frontend/video_analysis.php');
    exit;
}

// Get skill from form (default: drive_forehand)
$skill = $_POST['skill'] ?? 'drive_forehand';
$allowedSkills = ['drive_forehand', 'drive_backhand', 'serve', 'dink'];
if (!in_array($skill, $allowedSkills)) {
    $skill = 'drive_forehand';
}

// Run analysis directly using Python script
$techniquesDetected = [];
$score = null;
$feedback = null;
$coachingFeedback = null;
$sessionId = null;
$status = 'api_unavailable'; // Default: assume script not found, will be updated if script runs
$analysisData = null;
$debugInfo = [];

// Path to Python script
$chatboxDir = __DIR__ . '/../../ChatBox';
$pythonScript = $chatboxDir . '/run_analysis.py';

// Debug: Log paths
$debugInfo['chatbox_dir'] = $chatboxDir;
$debugInfo['chatbox_dir_exists'] = is_dir($chatboxDir);
$debugInfo['python_script'] = $pythonScript;
$debugInfo['script_exists'] = file_exists($pythonScript);
$debugInfo['script_realpath'] = file_exists($pythonScript) ? realpath($pythonScript) : null;
$debugInfo['upload_path'] = $uploadPath;
$debugInfo['upload_exists'] = file_exists($uploadPath);
$debugInfo['upload_realpath'] = file_exists($uploadPath) ? realpath($uploadPath) : null;
$debugInfo['php_os'] = PHP_OS;
$debugInfo['current_dir'] = getcwd();

// Check if Python script exists
if (file_exists($pythonScript)) {
    try {
        // Get Python executable (try common paths)
        $pythonCmd = null;
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows: try python, python3, py
            $testCommands = ['python', 'py', 'python3'];
            foreach ($testCommands as $cmd) {
                $testOutput = @shell_exec($cmd . ' --version 2>&1');
                if ($testOutput && strpos($testOutput, 'Python') !== false) {
                    $pythonCmd = $cmd;
                    break;
                }
            }
            if (!$pythonCmd) {
                throw new Exception("Python not found. Please install Python and add it to PATH.");
            }
        } else {
            // Linux/Mac: try python3 first
            $testOutput = @shell_exec('python3 --version 2>&1');
            if ($testOutput && strpos($testOutput, 'Python') !== false) {
                $pythonCmd = 'python3';
            } else {
                $testOutput = @shell_exec('python --version 2>&1');
                if ($testOutput && strpos($testOutput, 'Python') !== false) {
                    $pythonCmd = 'python';
                } else {
                    throw new Exception("Python not found. Please install Python.");
                }
            }
        }
        
        // Build command with absolute paths
        $pythonScriptAbs = realpath($pythonScript);
        $uploadPathAbs = realpath($uploadPath);
        
        if (!$pythonScriptAbs) {
            throw new Exception("Python script not found: " . $pythonScript);
        }
        if (!$uploadPathAbs) {
            throw new Exception("Video file not found: " . $uploadPath);
        }
        
        $command = escapeshellarg($pythonCmd) . ' ' . 
                   escapeshellarg($pythonScriptAbs) . ' ' . 
                   escapeshellarg($uploadPathAbs) . ' ' . 
                   '--skill ' . escapeshellarg($skill) . ' 2>&1';
        
        // Change to ChatBox directory for relative imports
        $originalDir = getcwd();
        if (!chdir($chatboxDir)) {
            throw new Exception("Cannot change to ChatBox directory: " . $chatboxDir);
        }
        
        // Execute Python script
        $output = shell_exec($command);
        
        chdir($originalDir);
        
        if ($output === null) {
            // Command failed or no output
            error_log("ChatBox analysis: Python command returned null. Command: " . $command);
            $status = 'analysis_failed';
            $debugInfo['python_output'] = 'null';
            $debugInfo['python_command'] = $command;
        } elseif ($output) {
            $debugInfo['python_output_length'] = strlen($output);
            $debugInfo['python_output_preview'] = substr($output, 0, 500);
            // Try to parse JSON output
            $jsonStart = strpos($output, '{');
            if ($jsonStart !== false) {
                // Try to find the end of JSON
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
                $analysisData = json_decode($jsonOutput, true);
                
                if ($analysisData && isset($analysisData['success']) && $analysisData['success']) {
                    $sessionId = $analysisData['session_id'] ?? null;
                    $techniquesDetected = $analysisData['techniques_detected'] ?? [];
                    $coachingFeedback = $analysisData['coaching_feedback'] ?? null;
                    $feedback = $analysisData['feedback'] ?? null;
                    
                    // Store analysis data for display
                    $analysisData['frame_count'] = $analysisData['frame_count'] ?? 0;
                    $analysisData['pose_count'] = $analysisData['pose_count'] ?? 0;
                    $analysisData['phase_count'] = $analysisData['phase_count'] ?? 0;
                    
                    $status = 'completed';
                } else {
                    // Analysis failed
                    $status = 'analysis_failed';
                    if (isset($analysisData['session_id'])) {
                        $sessionId = $analysisData['session_id'];
                    }
                    error_log("ChatBox analysis failed: " . ($analysisData['error'] ?? 'Unknown error'));
                }
            } else {
                // No JSON in output, might be error
                $debugInfo['no_json_error'] = true;
                $debugInfo['raw_output'] = substr($output, 0, 2000);
                error_log("ChatBox analysis output (no JSON found): " . substr($output, 0, 1000));
                $status = 'analysis_failed';
            }
        } else {
            // No output from script
            $debugInfo['no_output'] = true;
            $debugInfo['python_command'] = $command;
            error_log("ChatBox analysis: No output from Python script. Command: " . $command);
            $status = 'analysis_failed';
        }
    } catch (Exception $e) {
        error_log("ChatBox analysis error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        error_log("Debug info: " . json_encode($debugInfo));
        $status = 'api_error';
    }
} else {
    // Python script not found
    error_log("ChatBox Python script not found: " . $pythonScript);
    error_log("Current directory: " . __DIR__);
    error_log("ChatBox directory: " . $chatboxDir);
    error_log("ChatBox dir exists: " . (is_dir($chatboxDir) ? 'Yes' : 'No'));
    if (is_dir($chatboxDir)) {
        $files = @scandir($chatboxDir);
        error_log("Files in ChatBox: " . json_encode(array_slice($files ?: [], 0, 10)));
    }
    error_log("Debug info: " . json_encode($debugInfo));
    $status = 'api_unavailable';
    $debugInfo['error'] = 'Python script not found at: ' . $pythonScript;
    $debugInfo['chatbox_dir_exists'] = is_dir($chatboxDir);
    if (is_dir($chatboxDir)) {
        $files = @scandir($chatboxDir);
        $debugInfo['chatbox_files'] = array_slice($files ?: [], 0, 20); // First 20 files
    }
}

// Save analysis record to database
try {
    // Create table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS video_analyses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        video_name VARCHAR(255) NOT NULL,
        video_path VARCHAR(500) NOT NULL,
        techniques_detected TEXT,
        score INT,
        status VARCHAR(50) DEFAULT 'model_not_working',
        session_id VARCHAR(255) DEFAULT NULL,
        coaching_feedback TEXT DEFAULT NULL,
        raw_feedback TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at),
        INDEX idx_session_id (session_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Insert analysis record
    $userId = (int)$authUser['id'];
    $techniquesJson = json_encode($techniquesDetected);
    $coachingFeedbackJson = $coachingFeedback ? json_encode($coachingFeedback) : null;
    $rawFeedbackJson = $feedback ? json_encode($feedback) : null;
    
    $insertStmt = $pdo->prepare('INSERT INTO video_analyses (user_id, video_name, video_path, techniques_detected, score, status, session_id, coaching_feedback, raw_feedback) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insertStmt->execute([
        $userId,
        $fileName,
        '/pickelball/uploads/video_analysis/' . $uniqueFileName,
        $techniquesJson,
        $score,
        $status,
        $sessionId,
        $coachingFeedbackJson,
        $rawFeedbackJson
    ]);
    
    $analysisId = (int)$pdo->lastInsertId();
    
    // Prepare result for display
    $message = 'Video uploaded successfully.';
    if ($status === 'completed') {
        $message = 'Video analyzed successfully!';
    } elseif ($status === 'analysis_failed') {
        $message = 'Video uploaded but analysis failed. Please check that Python is installed and all required packages are available.';
    } elseif ($status === 'api_unavailable') {
        $message = 'Analysis service is currently unavailable. Please ensure the ChatBox/run_analysis.py file exists.';
    } elseif ($status === 'api_error') {
        $message = 'Error running analysis service. Please check server logs for details.';
    }
    
    // Prepare analysis_data for display
    $displayAnalysisData = null;
    if ($status === 'completed' && $analysisData && isset($analysisData['frame_count'])) {
        $displayAnalysisData = [
            'analysis' => [
                'frame_count' => $analysisData['frame_count'] ?? 0,
                'pose_count' => $analysisData['pose_count'] ?? 0,
                'phase_count' => $analysisData['phase_count'] ?? 0
            ]
        ];
    }
    
    $analysisResult = [
        'id' => $analysisId,
        'status' => $status,
        'message' => $message,
        'video_path' => '/pickelball/uploads/video_analysis/' . $uniqueFileName,
        'video_name' => $fileName,
        'uploaded_at' => date('Y-m-d H:i:s'),
        'techniques_detected' => $techniquesDetected,
        'score' => $score,
        'feedback' => $feedback,
        'coaching_feedback' => $coachingFeedback,
        'session_id' => $sessionId,
        'analysis_data' => $displayAnalysisData,
        'debug_info' => $debugInfo ?? []
    ];
    
    // Store analysis result in session
    $_SESSION['analysis_result'] = $analysisResult;
    $_SESSION['analysis_success'] = $message;
    
} catch (PDOException $e) {
    error_log("Database error in video_analysis.php: " . $e->getMessage());
    // If database error, still show success but don't save to DB
    $message = 'Video uploaded successfully.';
    if ($status === 'completed') {
        $message = 'Video analyzed successfully! (Database save failed)';
    }
    
    $analysisResult = [
        'status' => $status,
        'message' => $message,
        'video_path' => '/pickelball/uploads/video_analysis/' . $uniqueFileName,
        'video_name' => $fileName,
        'uploaded_at' => date('Y-m-d H:i:s'),
        'techniques_detected' => $techniquesDetected,
        'score' => $score,
        'feedback' => $feedback,
        'coaching_feedback' => $coachingFeedback,
        'session_id' => $sessionId
    ];
    
    $_SESSION['analysis_result'] = $analysisResult;
    $_SESSION['analysis_success'] = $message;
}

// Redirect back to analysis page
header('Location: /pickelball/main/frontend/video_analysis.php');
exit;

