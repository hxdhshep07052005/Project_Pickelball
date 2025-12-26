<?php
declare(strict_types=1);

/**
 * Backend logic for shadowing select page
 * Handles fetching available poses/techniques
 */

// Load translations for technique names
require_once __DIR__ . '/../../includes/i18n.php';
$lang = getLanguage();

// Define available poses (matching the Python model files)
$poses = [
    [
        'pose' => 'Serve',
        'name' => 'Serve',
        'description' => 'Practice your serve technique with ghost trainer'
    ],
    [
        'pose' => 'DriveForehand',
        'name' => 'Forehand Drive',
        'description' => 'Master your forehand drive with real-time feedback'
    ],
    [
        'pose' => 'DriveBackhand',
        'name' => 'Backhand Drive',
        'description' => 'Perfect your backhand drive technique'
    ]
];

// Check if assets exist for each pose
$assetsDir = __DIR__ . '/../../assets/';
foreach ($poses as &$poseData) {
    $poseFolder = $assetsDir . $poseData['pose'];
    $poseData['hasAssets'] = is_dir($poseFolder) && 
                             file_exists($poseFolder . '/ghost_0.png') &&
                             file_exists($poseFolder . '/meta_0.npy') &&
                             file_exists($poseFolder . '/target_0.npy');
}

// Return poses array for use in frontend
return $poses;

