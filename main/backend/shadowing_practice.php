<?php
declare(strict_types=1);



$poseName = $_GET['pose'] ?? '';

$validPoses = ['Serve', 'DriveForehand', 'DriveBackhand', 'Smash', 'Volley'];

if (!in_array($poseName, $validPoses)) {
    return [
        'valid' => false,
        'pose' => $poseName,
        'name' => '',
        'hasAssets' => false
    ];
}

require_once __DIR__ . '/../../includes/i18n.php';
$lang = getLanguage();

$poseDisplayNames = [
    'Serve' => 'Serve',
    'DriveForehand' => 'Forehand Drive',
    'DriveBackhand' => 'Backhand Drive',
    'Smash' => 'Smash',
    'Volley' => 'Volley'
];

$assetsDir = __DIR__ . '/../../shadowing_for_pickleball-main/shadowing_for_pickleball-main/assets/' . $poseName . '/';
$hasAssets = is_dir($assetsDir) &&
             file_exists($assetsDir . 'ghost_0.png') &&
             file_exists($assetsDir . 'meta_0.npy') &&
             file_exists($assetsDir . 'target_0.npy');

$displayName = $poseDisplayNames[$poseName] ?? $poseName;

$assetsBase = __DIR__ . '/../../shadowing_for_pickleball-main/shadowing_for_pickleball-main/assets/';
$availablePoses = [];
foreach ($validPoses as $p) {
    $dir = $assetsBase . $p . '/';
    if (is_dir($dir) && file_exists($dir . 'ghost_0.png') && file_exists($dir . 'meta_0.npy') && file_exists($dir . 'target_0.npy')) {
        $availablePoses[] = $p;
    }
}

return [
    'valid' => true,
    'pose' => $poseName,
    'name' => $displayName,
    'hasAssets' => $hasAssets,
    'assetsPath' => '/pickelball/shadowing_for_pickleball-main/shadowing_for_pickleball-main/assets/' . $poseName . '/',
    'availablePoses' => $availablePoses
];
