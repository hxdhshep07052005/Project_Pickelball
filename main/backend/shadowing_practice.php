<?php
declare(strict_types=1);

/**
 * Backend logic for shadowing practice page
 * Handles pose validation and information retrieval
 */

// Get pose parameter from URL
$poseName = $_GET['pose'] ?? '';

// Valid poses (matching Python model files)
$validPoses = ['Serve', 'DriveForehand', 'DriveBackhand'];

// Validate pose name
if (!in_array($poseName, $validPoses)) {
    // If invalid, redirect to selection page
    return [
        'valid' => false,
        'pose' => $poseName,
        'name' => '',
        'hasAssets' => false
    ];
}

// Load translations for technique names
require_once __DIR__ . '/../../includes/i18n.php';
$lang = getLanguage();

// Map pose names to display names
$poseDisplayNames = [
    'Serve' => 'Serve',
    'DriveForehand' => 'Forehand Drive',
    'DriveBackhand' => 'Backhand Drive'
];

// Check if assets exist for this pose
$assetsDir = __DIR__ . '/../../assets/' . $poseName . '/';
$hasAssets = is_dir($assetsDir) && 
             file_exists($assetsDir . 'ghost_0.png') &&
             file_exists($assetsDir . 'meta_0.npy') &&
             file_exists($assetsDir . 'target_0.npy');

// Get display name
$displayName = $poseDisplayNames[$poseName] ?? $poseName;

// Return pose data for use in frontend
return [
    'valid' => true,
    'pose' => $poseName,
    'name' => $displayName,
    'hasAssets' => $hasAssets,
    'assetsPath' => '/pickelball/assets/' . $poseName . '/'
];

