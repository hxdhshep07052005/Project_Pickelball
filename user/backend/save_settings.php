<?php
declare(strict_types=1);



if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/require_auth.php';
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../frontend/settings.php');
    exit;
}

$userId = (int)$authUser['id'];

$theme = $_POST['theme'] ?? 'light';
$language = $_POST['language'] ?? 'en';

if (!in_array($theme, ['light', 'dark'])) {
    $theme = 'light';
}

if (!in_array($language, ['en', 'vi'])) {
    $language = 'en';
}

try {
    $tableExists = false;
    try {
        $checkStmt = $pdo->query("SHOW TABLES LIKE 'user_preferences'");
        $tableExists = $checkStmt->rowCount() > 0;
    } catch (PDOException $e) {
    }

    if (!$tableExists) {
        try {
            $pdo->exec("CREATE TABLE user_preferences (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL UNIQUE,
                theme VARCHAR(10) DEFAULT 'light',
                language VARCHAR(5) DEFAULT 'en',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            $pdo->exec("CREATE TABLE user_preferences (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL UNIQUE,
                theme VARCHAR(10) DEFAULT 'light',
                language VARCHAR(5) DEFAULT 'en',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    }

    $checkStmt = $pdo->prepare('SELECT id FROM user_preferences WHERE user_id = ? LIMIT 1');
    $checkStmt->execute([$userId]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        $stmt = $pdo->prepare('UPDATE user_preferences SET theme = ?, language = ?, updated_at = NOW() WHERE user_id = ?');
        $stmt->execute([$theme, $language, $userId]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO user_preferences (user_id, theme, language) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $theme, $language]);
    }

    $_SESSION['user_theme'] = $theme;
    $_SESSION['user_language'] = $language;

    $_SESSION['settings_success'] = 'Settings saved successfully.';

    setcookie('user_theme', $theme, time() + (86400 * 30), '/'); // 30 days
    setcookie('user_language', $language, time() + (86400 * 30), '/'); // 30 days

} catch (PDOException $e) {
    error_log('Settings save error: ' . $e->getMessage() . ' | Code: ' . $e->getCode());
    $_SESSION['settings_error'] = 'Failed to save settings. Please try again.';
}

header('Location: ../frontend/settings.php');
exit;
