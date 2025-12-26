<?php
declare(strict_types=1);

/**
 * Internationalization (i18n) helper
 * Loads translation files based on user's language preference
 */

// Get language from session, cookie, or default to English
function getLanguage(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Try session first
    if (isset($_SESSION['user_language'])) {
        return $_SESSION['user_language'];
    }
    
    // Try cookie
    if (isset($_COOKIE['user_language'])) {
        return $_COOKIE['user_language'];
    }
    
    // Try database if user is authenticated
    if (isset($_SESSION['user'])) {
        try {
            // Check if bootstrap is already loaded to avoid re-requiring
            if (!isset($GLOBALS['pdo']) && !isset($pdo)) {
                require_once __DIR__ . '/../user/backend/bootstrap.php';
            }
            // Use global pdo or local pdo
            $db = $GLOBALS['pdo'] ?? ($pdo ?? null);
            if ($db) {
                $userId = (int)$_SESSION['user']['id'];
                $stmt = $db->prepare('SELECT language FROM user_preferences WHERE user_id = ? LIMIT 1');
                $stmt->execute([$userId]);
                $prefs = $stmt->fetch();
                if ($prefs && !empty($prefs['language'])) {
                    return $prefs['language'];
                }
            }
        } catch (Exception $e) {
            // Use default if error - don't throw, just return default
            error_log("i18n getLanguage error: " . $e->getMessage());
        }
    }
    
    // Default to English
    return 'en';
}

// Load translation file
function loadTranslations(string $lang): array {
    try {
        $langFile = __DIR__ . '/../lang/' . $lang . '.php';
        
        if (file_exists($langFile)) {
            $translations = require $langFile;
            // Ensure it's an array
            if (is_array($translations)) {
                return $translations;
            }
        }
        
        // Fallback to English if language file doesn't exist or is invalid
        $enFile = __DIR__ . '/../lang/en.php';
        if (file_exists($enFile)) {
            $translations = require $enFile;
            if (is_array($translations)) {
                return $translations;
            }
        }
    } catch (Exception $e) {
        error_log("Error loading translations for '$lang': " . $e->getMessage());
    }
    
    // Return empty array if no translation files exist
    return [];
}

// Get translation string
function t(string $key, array $params = []): string {
    static $translations = null;
    static $currentLang = null;
    
    try {
        if ($translations === null || $currentLang === null) {
            $currentLang = getLanguage();
            $translations = loadTranslations($currentLang);
        }
        
        // Get translation value
        $value = $translations[$key] ?? $key;
        
        // Replace parameters if provided
        if (!empty($params)) {
            foreach ($params as $paramKey => $paramValue) {
                $value = str_replace(':' . $paramKey, $paramValue, $value);
            }
        }
        
        return $value;
    } catch (Exception $e) {
        // If translation fails, return the key itself
        error_log("Translation error for key '$key': " . $e->getMessage());
        return $key;
    }
}

