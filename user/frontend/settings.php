<?php
declare(strict_types=1);

/**
 * User settings page frontend
 * Allows users to change theme and language preferences
 */

require_once __DIR__ . '/../backend/require_auth.php';
require_once __DIR__ . '/../backend/bootstrap.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/header.php';

// Get user ID
$userId = (int)$authUser['id'];

// Get current preferences from database
$currentTheme = 'light';
$currentLanguage = 'en';

try {
    $stmt = $pdo->prepare('SELECT theme, language FROM user_preferences WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $prefs = $stmt->fetch();
    
    if ($prefs) {
        $currentTheme = $prefs['theme'] ?? 'light';
        $currentLanguage = $prefs['language'] ?? 'en';
    }
} catch (PDOException $e) {
    // Use defaults if table doesn't exist or error
    $currentTheme = $_SESSION['user_theme'] ?? 'light';
    $currentLanguage = $_SESSION['user_language'] ?? 'en';
}

// Get messages from session
$error = $_SESSION['settings_error'] ?? null;
$success = $_SESSION['settings_success'] ?? null;
unset($_SESSION['settings_error'], $_SESSION['settings_success']);
?>
<style>
    .settings-section {
        min-height: calc(100vh - 72px);
        padding: 80px 24px;
        background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
    }
    .settings-container {
        max-width: 900px;
        margin: 0 auto;
    }
    .page-header {
        text-align: center;
        margin-bottom: 60px;
    }
    .page-title {
        font-size: 56px;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 16px;
        letter-spacing: -1px;
    }
    .page-subtitle {
        font-size: 20px;
        color: #64748b;
        max-width: 600px;
        margin: 0 auto;
        line-height: 1.6;
    }
    .settings-card {
        background: #ffffff;
        border-radius: 16px;
        padding: 48px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        margin-bottom: 32px;
    }
    .settings-group {
        margin-bottom: 40px;
    }
    .settings-group:last-child {
        margin-bottom: 0;
    }
    .group-title {
        font-size: 24px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 8px;
    }
    .group-description {
        font-size: 14px;
        color: #64748b;
        margin-bottom: 24px;
    }
    .option-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .option-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px;
        background: #f8fafc;
        border-radius: 12px;
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    .option-item:hover {
        border-color: #10b981;
        background: #f0fdf4;
    }
    .option-item.selected {
        border-color: #10b981;
        background: #f0fdf4;
    }
    .option-label {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .option-icon {
        width: 24px;
        height: 24px;
        color: #10b981;
    }
    .option-text {
        font-size: 16px;
        font-weight: 600;
        color: #0f172a;
    }
    .option-subtext {
        font-size: 14px;
        color: #64748b;
        margin-top: 4px;
    }
    .radio-input {
        width: 20px;
        height: 20px;
        cursor: pointer;
    }
    .alert {
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .alert-error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }
    .alert-success {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #166534;
    }
    .alert-icon {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
    }
    .save-btn {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #ffffff;
        border: none;
        padding: 16px 32px;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        width: 100%;
        margin-top: 32px;
    }
    .save-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
    }
    @media (max-width: 640px) {
        .page-title {
            font-size: 36px;
        }
        .settings-card {
            padding: 32px 24px;
        }
    }
</style>
<section class="settings-section">
    <div class="settings-container">
        <div class="page-header animate-on-scroll fade-in-up">
            <h1 class="page-title"><?php echo htmlspecialchars(t('settings_title'), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="page-subtitle"><?php echo htmlspecialchars(t('settings_subtitle'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <form action="/pickelball/user/backend/save_settings.php" method="POST" class="settings-card animate-on-scroll fade-in-up">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg class="alert-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                    <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <svg class="alert-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                    </svg>
                    <span><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <!-- Theme Settings -->
            <div class="settings-group">
                <h2 class="group-title"><?php echo htmlspecialchars(t('theme_settings'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="group-description"><?php echo htmlspecialchars(t('theme_description'), ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="option-list">
                    <label class="option-item <?php echo $currentTheme === 'light' ? 'selected' : ''; ?>" onclick="selectOption(this, 'theme', 'light')">
                        <div class="option-label">
                            <svg class="option-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zM2 13h2c.55 0 1-.45 1-1s-.45-1-1-1H2c-.55 0-1 .45-1 1s.45 1 1 1zm18 0h2c.55 0 1-.45 1-1s-.45-1-1-1h-2c-.55 0-1 .45-1 1s.45 1 1 1zM11 2v2c0 .55.45 1 1 1s1-.45 1-1V2c0-.55-.45-1-1-1s-1 .45-1 1zm0 18v2c0 .55.45 1 1 1s1-.45 1-1v-2c0-.55-.45-1-1-1s-1 .45-1 1zM5.99 4.58c-.39-.39-1.03-.39-1.41 0-.39.39-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41L5.99 4.58zm12.37 12.37c-.39-.39-1.03-.39-1.41 0-.39.39-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0 .39-.39.39-1.03 0-1.41l-1.06-1.06zm1.06-10.96c.39-.39.39-1.03 0-1.41-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06zM7.05 18.36c.39-.39.39-1.03 0-1.41-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06z"/>
                            </svg>
                            <div>
                                <div class="option-text"><?php echo htmlspecialchars(t('light_mode'), ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="option-subtext"><?php echo htmlspecialchars(t('light_mode_desc'), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>
                        <input type="radio" name="theme" value="light" class="radio-input" <?php echo $currentTheme === 'light' ? 'checked' : ''; ?>>
                    </label>
                    
                    <label class="option-item <?php echo $currentTheme === 'dark' ? 'selected' : ''; ?>" onclick="selectOption(this, 'theme', 'dark')">
                        <div class="option-label">
                            <svg class="option-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12.34 2.02C6.59 1.82 2 6.42 2 12c0 5.52 4.48 10 10 10 3.71 0 6.93-2.02 8.66-5.02-7.51-.25-13.12-6.01-13.12-13.5 0-.78.07-1.53.2-2.25C8.51 1.92 5.61 3.51 3.72 6.12c.17-.35.37-.68.6-1 .23-.32.48-.62.75-.91C5.5 3.5 6.5 2.5 7.5 1.5c1-.5 2-.5 3 0 1 1 2 2 3 3 .5 1 .5 2 0 3-.5 1-1 2-1.5 3-.29.27-.59.52-.91.75-.32.23-.65.43-1 .6C9.49 8.39 11.22 9.49 13 9.49c1.78 0 3.51-1.1 4.21-2.64-.35-.17-.68-.37-1-.6-.32-.23-.62-.48-.91-.75-.5-1-1-2-1.5-3-.5-1-.5-2 0-3 1-1 2-2 3-3 .5-1 1-.5 2-.5s1.5.5 2 1.5c.5 1 1 2 1.5 3 .5 1 .5 2 0 3-1 1-2 2-3 3-.5 1-1 2-1.5 3-.27.29-.52.59-.75.91-.23.32-.43.65-.6 1C20.49 8.39 22 10.22 22 12.34c0 1.78-1.1 3.51-2.64 4.21-.17-.35-.37-.68-.6-1-.23-.32-.48-.62-.75-.91-.5-1-1-2-1.5-3-.5-1-.5-2 0-3 1-1 2-2 3-3 .5-1 1-.5 2-.5s1.5.5 2 1.5c.5 1 1 2 1.5 3 .5 1 .5 2 0 3-1 1-2 2-3 3-.5 1-1 2-1.5 3-.29.27-.59.52-.91.75-.32.23-.65.43-1 .6C20.49 18.39 18.78 19.49 17 19.49c-1.78 0-3.51-1.1-4.21-2.64.35-.17.68-.37 1-.6.32-.23.62-.48.91-.75.5-1 1-2 1.5-3 .5-1 .5-2 0-3-1-1-2-2-3-3-.5-1-1-.5-2-.5s-1.5.5-2 1.5c-.5 1-1 2-1.5 3-.27.29-.52.59-.75.91-.23.32-.43.65-.6 1C3.51 15.61 2 13.78 2 12c0-5.58 4.42-10 10-10 5.58 0 10 4.42 10 10 0 1.78-1.1 3.51-2.64 4.21z"/>
                            </svg>
                            <div>
                                <div class="option-text"><?php echo htmlspecialchars(t('dark_mode'), ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="option-subtext"><?php echo htmlspecialchars(t('dark_mode_desc'), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>
                        <input type="radio" name="theme" value="dark" class="radio-input" <?php echo $currentTheme === 'dark' ? 'checked' : ''; ?>>
                    </label>
                </div>
            </div>

            <!-- Language Settings -->
            <div class="settings-group">
                <h2 class="group-title"><?php echo htmlspecialchars(t('language_settings'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="group-description"><?php echo htmlspecialchars(t('language_description'), ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="option-list">
                    <label class="option-item <?php echo $currentLanguage === 'en' ? 'selected' : ''; ?>" onclick="selectOption(this, 'language', 'en')">
                        <div class="option-label">
                            <svg class="option-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12.87 15.07l-2.54-2.51.03-.03c1.74-1.94 2.98-4.17 3.71-6.53H17V4h-7V2H8v2H1v1.99h11.17C11.5 7.92 10.44 9.75 9 11.35 8.07 10.32 7.3 9.19 6.69 8h-2c.73 1.63 1.73 3.17 2.98 4.56l-5.09 5.02L4 19l5-5 3.11 3.11.76-2.04zM18.5 10h-2L12 22h2l1.12-3h4.75L21 22h2l-4.5-12zm-2.62 7l1.62-4.33L19.12 17h-3.24z"/>
                            </svg>
                            <div>
                                <div class="option-text"><?php echo htmlspecialchars(t('english'), ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="option-subtext">English</div>
                            </div>
                        </div>
                        <input type="radio" name="language" value="en" class="radio-input" <?php echo $currentLanguage === 'en' ? 'checked' : ''; ?>>
                    </label>
                    
                    <label class="option-item <?php echo $currentLanguage === 'vi' ? 'selected' : ''; ?>" onclick="selectOption(this, 'language', 'vi')">
                        <div class="option-label">
                            <svg class="option-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12.87 15.07l-2.54-2.51.03-.03c1.74-1.94 2.98-4.17 3.71-6.53H17V4h-7V2H8v2H1v1.99h11.17C11.5 7.92 10.44 9.75 9 11.35 8.07 10.32 7.3 9.19 6.69 8h-2c.73 1.63 1.73 3.17 2.98 4.56l-5.09 5.02L4 19l5-5 3.11 3.11.76-2.04zM18.5 10h-2L12 22h2l1.12-3h4.75L21 22h2l-4.5-12zm-2.62 7l1.62-4.33L19.12 17h-3.24z"/>
                            </svg>
                            <div>
                                <div class="option-text"><?php echo htmlspecialchars(t('vietnamese'), ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="option-subtext">Vietnamese</div>
                            </div>
                        </div>
                        <input type="radio" name="language" value="vi" class="radio-input" <?php echo $currentLanguage === 'vi' ? 'checked' : ''; ?>>
                    </label>
                </div>
            </div>

            <button type="submit" class="save-btn"><?php echo htmlspecialchars(t('save'), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars(t('settings'), ENT_QUOTES, 'UTF-8'); ?></button>
        </form>
    </div>
</section>

<script src="/pickelball/main/frontend/js/scroll-animation.js"></script>
<script>
// Handle option selection
function selectOption(element, type, value) {
    // Remove selected class from all options of the same type
    const group = element.closest('.settings-group');
    group.querySelectorAll('.option-item').forEach(item => {
        item.classList.remove('selected');
    });
    
    // Add selected class to clicked option
    element.classList.add('selected');
    
    // Update radio input
    const radio = element.querySelector('input[type="radio"]');
    if (radio) {
        radio.checked = true;
    }
    
    // If theme changed, apply immediately (preview)
    if (type === 'theme' && window.themeHandler) {
        window.themeHandler.applyTheme(value);
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

