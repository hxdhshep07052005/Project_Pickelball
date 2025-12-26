<?php
declare(strict_types=1);

/**
 * Save user settings backend handler
 * Saves theme and language preferences to database
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require authentication and database connection
require_once __DIR__ . '/require_auth.php';
require_once __DIR__ . '/bootstrap.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../frontend/settings.php');
    exit;
}

// Get user ID
$userId = (int)$authUser['id'];

// Get and validate settings
$theme = $_POST['theme'] ?? 'light';
$language = $_POST['language'] ?? 'en';

// Validate theme
if (!in_array($theme, ['light', 'dark'])) {
    $theme = 'light';
}

// Validate language (only English and Vietnamese)
if (!in_array($language, ['en', 'vi'])) {
    $language = 'en';
}

try {
    // Check if table exists, if not create it
    $tableExists = false;
    try {
        $checkStmt = $pdo->query("SHOW TABLES LIKE 'user_preferences'");
        $tableExists = $checkStmt->rowCount() > 0;
    } catch (PDOException $e) {
        // Table doesn't exist, will create it
    }
    
    if (!$tableExists) {
        // Try to create table with foreign key first
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
            // If foreign key fails, create without it
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
    
    // Check if user preferences already exist
    $checkStmt = $pdo->prepare('SELECT id FROM user_preferences WHERE user_id = ? LIMIT 1');
    $checkStmt->execute([$userId]);
    $existing = $checkStmt->fetch();
    
    if ($existing) {
        // Update existing preferences
        $stmt = $pdo->prepare('UPDATE user_preferences SET theme = ?, language = ?, updated_at = NOW() WHERE user_id = ?');
        $stmt->execute([$theme, $language, $userId]);
    } else {
        // Insert new preferences
        $stmt = $pdo->prepare('INSERT INTO user_preferences (user_id, theme, language) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $theme, $language]);
    }
    
    // Update session immediately
    $_SESSION['user_theme'] = $theme;
    $_SESSION['user_language'] = $language;
    
    $_SESSION['settings_success'] = 'Settings saved successfully.';
    
    // Set cookie for immediate theme application (before redirect)
    setcookie('user_theme', $theme, time() + (86400 * 30), '/'); // 30 days
    setcookie('user_language', $language, time() + (86400 * 30), '/'); // 30 days
    
} catch (PDOException $e) {
    // Log error for debugging
    error_log('Settings save error: ' . $e->getMessage() . ' | Code: ' . $e->getCode());
    // Show user-friendly message (hide technical details in production)
    $_SESSION['settings_error'] = 'Failed to save settings. Please try again.';
}

// Redirect back to settings page
header('Location: ../frontend/settings.php');
exit;

