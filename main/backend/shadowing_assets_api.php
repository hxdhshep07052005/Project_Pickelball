<?php
declare(strict_types=1);



header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$pose = $_GET['pose'] ?? '';
$type = $_GET['type'] ?? ''; // 'meta' or 'target'
$stage = $_GET['stage'] ?? '0';

$validPoses = ['Serve', 'DriveForehand', 'DriveBackhand', 'Smash', 'Volley'];
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

$npyPath = __DIR__ . '/../../shadowing_for_pickleball-main/shadowing_for_pickleball-main/assets/' . $pose . '/' . $type . '_' . $stage . '.npy';

if (!file_exists($npyPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
    exit;
}

$pythonScript = __DIR__ . '/read_npy.py';

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

$decoded = json_decode($output, true);
if ($decoded !== null && isset($decoded['error'])) {
    http_response_code(500);
    echo json_encode($decoded);
    exit;
}

echo $output;
?>
