<?php
declare(strict_types=1);



require_once __DIR__ . '/../../includes/i18n.php';
$lang = getLanguage();

$poses = [
    ['pose' => 'Serve', 'name' => 'Serve', 'description' => 'Practice your serve technique with ghost trainer'],
    ['pose' => 'DriveForehand', 'name' => 'Forehand Drive', 'description' => 'Master your forehand drive with real-time feedback'],
    ['pose' => 'DriveBackhand', 'name' => 'Backhand Drive', 'description' => 'Perfect your backhand drive technique'],
    ['pose' => 'Smash', 'name' => 'Smash', 'description' => 'Practice overhead smash with ghost overlay'],
    ['pose' => 'Volley', 'name' => 'Volley', 'description' => 'Improve your volley technique at the net']
];

$assetsDir = __DIR__ . '/../../shadowing_for_pickleball-main/shadowing_for_pickleball-main/assets/';
foreach ($poses as &$poseData) {
    $poseFolder = $assetsDir . $poseData['pose'];
    $poseData['hasAssets'] = is_dir($poseFolder) &&
                             file_exists($poseFolder . '/ghost_0.png') &&
                             file_exists($poseFolder . '/meta_0.npy') &&
                             file_exists($poseFolder . '/target_0.npy');
}

return $poses;
