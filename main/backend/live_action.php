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

$action = $_POST['action'] ?? 'predict';

try {
    ob_end_clean();

    $liveActionDir = __DIR__ . '/../../Live_Action';
    $pythonScript = $liveActionDir . '/live_predict.py';
    $modelPath = $liveActionDir . '/Model_2dongtac.pth';

    if (!file_exists($pythonScript)) {
        throw new Exception('Python prediction script not found: ' . $pythonScript);
    }

    if (!file_exists($modelPath)) {
        throw new Exception('Model file not found: ' . $modelPath);
    }

    if ($action === 'reset') {
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
                   '--reset 2>&1';

        $originalDir = getcwd();
        chdir($liveActionDir);
        $output = shell_exec($command);
        chdir($originalDir);

        $jsonStart = strpos($output, '{');
        if ($jsonStart !== false) {
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
            if ($result && isset($result['success'])) {
                echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Buffer reset'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'predict') {
        $frameData = $_POST['frame'] ?? '';

        if (empty($frameData)) {
            throw new Exception('No frame data provided');
        }

        if (strpos($frameData, ',') !== false) {
            $frameData = explode(',', $frameData)[1];
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

        $tempDir = sys_get_temp_dir();
        if (!is_writable($tempDir)) {
            $tempDir = $liveActionDir;
        }

        $tempFile = tempnam($tempDir, 'live_frame_') . '.txt';

        $writeResult = @file_put_contents($tempFile, $frameData);
        if ($writeResult === false) {
            error_log("Failed to write temp file: " . $tempFile);
            throw new Exception('Failed to create temporary file for frame data');
        }

        $command = escapeshellarg($pythonCmd) . ' ' .
                   escapeshellarg($pythonScript) . ' ' .
                   '--frame_file ' . escapeshellarg($tempFile) . ' ' .
                   '--model ' . escapeshellarg($modelPath) . ' ' .
                   '--device cpu 2>&1';

        $originalDir = getcwd();
        chdir($liveActionDir);
        $output = shell_exec($command);
        chdir($originalDir);

        @unlink($tempFile);

        if (!$output) {
            error_log("Python script returned no output. Command: " . $command);
            error_log("Temp file was: " . $tempFile);
            throw new Exception('No output from prediction script. Check server logs for details.');
        }

        error_log("Python script output (first 1000 chars): " . substr($output, 0, 1000));

        $jsonStart = strpos($output, '{');
        if ($jsonStart === false) {
            error_log("Python script output (first 1000 chars): " . substr($output, 0, 1000));
            throw new Exception('Invalid response format from prediction script. Check server logs for details.');
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
            error_log("Failed to parse JSON. Output: " . substr($output, 0, 1000));
            error_log("JSON error: " . json_last_error_msg());
            throw new Exception('Failed to parse prediction result. Check server logs for details.');
        }

        if (!$result['success']) {
            $errorMsg = $result['error'] ?? 'Prediction failed';
            if (isset($result['traceback'])) {
                error_log("Python traceback: " . $result['traceback']);
            }
            throw new Exception($errorMsg);
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    throw new Exception('Unknown action: ' . $action);

} catch (Exception $e) {
    error_log("Live action error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("File: " . $e->getFile() . " Line: " . $e->getLine());

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} catch (Error $e) {
    error_log("Live action fatal error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("File: " . $e->getFile() . " Line: " . $e->getLine());

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
