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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

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

if ($file['size'] > $maxSize) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 100MB.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    ob_end_clean();

    $uploadDir = __DIR__ . '/../../uploads/action_prediction/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $uniqueFileName = uniqid('action_', true) . '_' . time() . '.' . $extension;
    $uploadPath = $uploadDir . $uniqueFileName;

    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to save uploaded file');
    }

    $uploadPathAbs = realpath($uploadPath);
    $actionPredDir = __DIR__ . '/../../Action_Video_Prediction';
    $pythonScript = $actionPredDir . '/predict_action.py';
    $modelPath = $actionPredDir . '/Model_2dongtac.pth';

    if (!file_exists($pythonScript)) {
        throw new Exception('Python prediction script not found: ' . $pythonScript);
    }

    if (!file_exists($modelPath)) {
        throw new Exception('Model file not found: ' . $modelPath);
    }

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

    $skillMap = [
        'DriveBackhand' => 'drive_two_backhand',
        'DriveForehand' => 'drive_forehand'
    ];
    $skill = $skillMap[$result['predicted_class']] ?? 'drive_forehand';

    $analysisResult = null;
    $analysisError = null;
    $chatbotDir = __DIR__ . '/../../chatbot_newest/chatbot/back_end';
    $pythonScript = $chatbotDir . '/run_analysis.py';

    if (file_exists($pythonScript) && file_exists($uploadPathAbs)) {
        try {
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

            if ($pythonCmd) {
                $pythonScriptAbs = realpath($pythonScript);
                $command = escapeshellarg($pythonCmd) . ' ' .
                           escapeshellarg($pythonScriptAbs) . ' ' .
                           escapeshellarg($uploadPathAbs) . ' ' .
                           '--skill ' . escapeshellarg($skill) . ' 2>' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'nul' : '/dev/null');

                $originalDir = getcwd();
                chdir($chatbotDir);
                $analysisOutput = shell_exec($command);
                chdir($originalDir);

                if ($analysisOutput) {
                    $jsonStart = strpos($analysisOutput, '{');
                    if ($jsonStart !== false) {
                        $braceCount = 0;
                        $jsonEnd = $jsonStart;
                        for ($i = $jsonStart; $i < strlen($analysisOutput); $i++) {
                            if ($analysisOutput[$i] === '{') $braceCount++;
                            if ($analysisOutput[$i] === '}') {
                                $braceCount--;
                                if ($braceCount === 0) {
                                    $jsonEnd = $i + 1;
                                    break;
                                }
                            }
                        }
                        $jsonOutput = substr($analysisOutput, $jsonStart, $jsonEnd - $jsonStart);
                        $analysisResult = json_decode($jsonOutput, true);

                        if (!$analysisResult || !isset($analysisResult['success'])) {
                            $analysisResult = null;
                            $analysisError = 'Failed to parse analysis result';
                        }
                    } else {
                        $analysisError = 'No JSON found in analysis output';
                    }
                } else {
                    $analysisError = 'No output from analysis script';
                }
            } else {
                $analysisError = 'Python not found';
            }
        } catch (Exception $e) {
            $analysisError = $e->getMessage();
            error_log("Chatbot analysis error in action prediction: " . $e->getMessage());
        }
    } else {
        $analysisError = 'Chatbot analysis script not found';
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS action_predictions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            video_name VARCHAR(255) NOT NULL,
            video_path VARCHAR(500) NOT NULL,
            predicted_class VARCHAR(50) NOT NULL,
            confidence DECIMAL(5,2) NOT NULL,
            probabilities TEXT,
            analysis_session_id VARCHAR(255) NULL,
            analysis_success TINYINT(1) DEFAULT 0,
            analysis_feedback TEXT NULL,
            analysis_coaching_feedback TEXT NULL,
            analysis_raw_feedback TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at),
            INDEX idx_analysis_session_id (analysis_session_id),
            INDEX idx_user_session (user_id, analysis_session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $columnsToAdd = [
            'analysis_session_id' => "ALTER TABLE action_predictions ADD COLUMN analysis_session_id VARCHAR(255) NULL AFTER probabilities",
            'analysis_success' => "ALTER TABLE action_predictions ADD COLUMN analysis_success TINYINT(1) DEFAULT 0 AFTER analysis_session_id",
            'analysis_feedback' => "ALTER TABLE action_predictions ADD COLUMN analysis_feedback TEXT NULL AFTER analysis_success",
            'analysis_coaching_feedback' => "ALTER TABLE action_predictions ADD COLUMN analysis_coaching_feedback TEXT NULL AFTER analysis_feedback",
            'analysis_raw_feedback' => "ALTER TABLE action_predictions ADD COLUMN analysis_raw_feedback TEXT NULL AFTER analysis_coaching_feedback"
        ];

        foreach ($columnsToAdd as $columnName => $alterSql) {
            try {
                $checkStmt = $pdo->query("SHOW COLUMNS FROM action_predictions LIKE '$columnName'");
                if ($checkStmt->rowCount() == 0) {
                    $pdo->exec($alterSql);
                    error_log("Action prediction: Added missing column: $columnName");
                }
            } catch (PDOException $e) {
                error_log("Action prediction: Column check/add for $columnName: " . $e->getMessage());
            }
        }

        try {
            $indexesToAdd = [
                'idx_analysis_session_id' => "CREATE INDEX idx_analysis_session_id ON action_predictions (analysis_session_id)",
                'idx_user_session' => "CREATE INDEX idx_user_session ON action_predictions (user_id, analysis_session_id)"
            ];

            foreach ($indexesToAdd as $indexName => $createIndexSql) {
                try {
                    $pdo->exec($createIndexSql);
                    error_log("Action prediction: Added index: $indexName");
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                        error_log("Action prediction: Index creation for $indexName: " . $e->getMessage());
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Action prediction: Index creation error: " . $e->getMessage());
        }

        $analysisSessionId = null;
        $analysisSuccess = 0;
        $analysisFeedbackJson = null;
        $coachingFeedback = null;
        $rawFeedbackJson = null;

        if ($analysisResult && $analysisResult['success']) {
            $analysisSessionId = $analysisResult['session_id'] ?? null;
            $analysisSuccess = 1;
            $rawFeedbackJson = json_encode($analysisResult['feedback'] ?? []);
            $coachingFeedback = $analysisResult['coaching_feedback'] ?? null;
            $analysisFeedbackJson = json_encode([
                'session_id' => $analysisSessionId,
                'frame_count' => $analysisResult['frame_count'] ?? 0,
                'pose_count' => $analysisResult['pose_count'] ?? 0,
                'phase_count' => $analysisResult['phase_count'] ?? 0,
                'techniques_detected' => $analysisResult['techniques_detected'] ?? []
            ]);

            error_log("Action prediction: Saving analysis with session_id: " . $analysisSessionId . " for user_id: " . $authUser['id']);
        } else {
            error_log("Action prediction: No analysis result or analysis failed. analysisResult: " . json_encode($analysisResult ?? null));
        }

        $stmt = $pdo->prepare('INSERT INTO action_predictions (user_id, video_name, video_path, predicted_class, confidence, probabilities, analysis_session_id, analysis_success, analysis_feedback, analysis_coaching_feedback, analysis_raw_feedback) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $authUser['id'],
            $file['name'],
            '/pickelball/uploads/action_prediction/' . $uniqueFileName,
            $result['predicted_class'],
            $result['confidence'],
            json_encode($result['probabilities']),
            $analysisSessionId,
            $analysisSuccess,
            $analysisFeedbackJson,
            $coachingFeedback,
            $rawFeedbackJson
        ]);

        $predictionId = $pdo->lastInsertId();

        if ($predictionId) {
            error_log("Action prediction: Successfully inserted prediction ID: " . $predictionId . " with analysis_session_id: " . ($analysisSessionId ?? 'NULL') . " for user_id: " . $authUser['id']);

            if ($analysisSessionId) {
                $verifyStmt = $pdo->prepare('SELECT id, user_id, analysis_session_id, analysis_success FROM action_predictions WHERE id = ?');
                $verifyStmt->execute([$predictionId]);
                $verifyResult = $verifyStmt->fetch();
                if ($verifyResult) {
                    error_log("Action prediction: Verified in DB - ID: " . $verifyResult['id'] . ", user_id: " . $verifyResult['user_id'] . ", analysis_session_id: " . ($verifyResult['analysis_session_id'] ?? 'NULL') . ", analysis_success: " . $verifyResult['analysis_success']);
                } else {
                    error_log("Action prediction: WARNING - Could not verify inserted record!");
                }
            }
        } else {
            error_log("Action prediction: ERROR - Failed to insert prediction (no ID returned). User ID: " . $authUser['id']);
        }
    } catch (PDOException $e) {
        error_log("Database error in action prediction: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        error_log("User ID: " . $authUser['id'] . ", Session ID: " . ($analysisSessionId ?? 'NULL'));
    }

    $response = [
        'success' => true,
        'prediction' => [
            'class' => $result['predicted_class'],
            'confidence' => $result['confidence'],
            'probabilities' => $result['probabilities'],
            'frames_processed' => $result['frames_processed'] ?? 0
        ],
        'video_path' => '/pickelball/uploads/action_prediction/' . $uniqueFileName,
        'video_name' => $file['name']
    ];

    if ($analysisResult && $analysisResult['success']) {
        $response['analysis'] = [
            'success' => true,
            'session_id' => $analysisResult['session_id'] ?? null,
            'frame_count' => $analysisResult['frame_count'] ?? 0,
            'pose_count' => $analysisResult['pose_count'] ?? 0,
            'phase_count' => $analysisResult['phase_count'] ?? 0,
            'techniques_detected' => $analysisResult['techniques_detected'] ?? [],
            'feedback' => $analysisResult['feedback'] ?? [],
            'coaching_feedback' => $analysisResult['coaching_feedback'] ?? null
        ];
    } elseif ($analysisError) {
        $response['analysis'] = [
            'success' => false,
            'error' => $analysisError
        ];
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

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
