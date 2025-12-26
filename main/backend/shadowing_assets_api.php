<?php
declare(strict_types=1);

/**
 * API endpoint to serve shadowing practice assets (ghost images, metadata, target poses)
 * Converts .npy files to JSON format for JavaScript consumption
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get parameters
$pose = $_GET['pose'] ?? '';
$type = $_GET['type'] ?? ''; // 'meta' or 'target'
$stage = $_GET['stage'] ?? '0';

// Validate inputs
$validPoses = ['Serve', 'DriveForehand', 'DriveBackhand'];
if (!in_array($pose, $validPoses)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid pose']);
    exit;
}

if (!in_array($type, ['meta', 'target'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid type']);
    exit;
}

$stage = intval($stage);
if ($stage < 0 || $stage > 3) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid stage']);
    exit;
}

// Path to .npy file
$npyPath = __DIR__ . '/../../assets/' . $pose . '/' . $type . '_' . $stage . '.npy';

if (!file_exists($npyPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
    exit;
}

// Read .npy file using Python (requires Python with numpy)
$pythonScript = __DIR__ . '/read_npy.py';

// Try different Python commands (python, python3, py)
$pythonCommands = ['python3', 'python', 'py'];
$output = null;
$error = null;

foreach ($pythonCommands as $pythonCmd) {
    $command = sprintf(
        '%s "%s" "%s" 2>&1',
        escapeshellarg($pythonCmd),
        escapeshellarg($pythonScript),
        escapeshellarg($npyPath)
    );
    
    $output = shell_exec($command);
    
    if ($output !== null && !empty(trim($output))) {
        // Check if output is valid JSON (not an error)
        $decoded = json_decode($output, true);
        if ($decoded !== null && !isset($decoded['error'])) {
            break; // Success
        }
        if (isset($decoded['error'])) {
            $error = $decoded['error'];
        }
    }
}

if ($output === null || empty(trim($output))) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to read .npy file. Please ensure Python with numpy is installed.',
        'details' => $error ?? 'Python script returned no output'
    ]);
    exit;
}

// Check if output contains error
$decoded = json_decode($output, true);
if ($decoded !== null && isset($decoded['error'])) {
    http_response_code(500);
    echo json_encode($decoded);
    exit;
}

echo $output;
?>

