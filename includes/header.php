<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load i18n functions
require_once __DIR__ . '/i18n.php';

if (!isset($pageTitle)) {
    $pageTitle = 'Pickleball Training';
}

$isAuthenticated = isset($_SESSION['user']);
$userName = $isAuthenticated ? $_SESSION['user']['name'] : '';

// Get user preferences for theme and language
$userTheme = 'light';
$userLanguage = 'en';
if ($isAuthenticated) {
    try {
        require_once __DIR__ . '/../user/backend/bootstrap.php';
        if (isset($pdo)) {
            $userId = (int)$_SESSION['user']['id'];
            $stmt = $pdo->prepare('SELECT theme, language FROM user_preferences WHERE user_id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $prefs = $stmt->fetch();
            if ($prefs) {
                $userTheme = $prefs['theme'] ?? 'light';
                $userLanguage = $prefs['language'] ?? 'en';
            }
        }
    } catch (Exception $e) {
        // Use defaults if error - don't break the page
        error_log('Header error loading user preferences: ' . $e->getMessage());
    } catch (Error $e) {
        // Use defaults if fatal error - don't break the page
        error_log('Header fatal error loading user preferences: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($userLanguage, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/pickelball/css/transitions.css">
    <script>
        // Set initial theme and language IMMEDIATELY before page loads (prevents flash)
        (function() {
            const theme = '<?php echo htmlspecialchars($userTheme, ENT_QUOTES, 'UTF-8'); ?>';
            const lang = '<?php echo htmlspecialchars($userLanguage, ENT_QUOTES, 'UTF-8'); ?>';
            document.documentElement.setAttribute('lang', lang);
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.classList.add('theme-' + theme);
            if (document.body) {
                document.body.setAttribute('data-theme', theme);
                document.body.classList.add('theme-' + theme);
            }
        })();
    </script>
    <link rel="stylesheet" href="/pickelball/css/dark-theme.css">
    <script src="/pickelball/js/theme-handler.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        :root {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: #0f172a;
        }
        body {
            margin: 0;
            background: #ffffff;
            min-height: 100vh;
        }
        .header {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .header-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 72px;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 48px;
        }
        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            gap: 12px;
        }
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 24px;
            font-weight: 700;
            font-family: 'Arial', sans-serif;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
        }
        .logo-text {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.5px;
        }
        .nav-menu {
            display: flex;
            align-items: center;
            gap: 32px;
            list-style: none;
        }
        .nav-item {
            position: relative;
        }
        .nav-link {
            color: #475569;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            padding: 8px 0;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .nav-link:hover {
            color: #10b981;
        }
        .nav-link.active {
            color: #10b981;
        }
        .nav-dropdown {
            position: relative;
        }
        .nav-dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            margin-top: 8px;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            min-width: 200px;
            padding: 8px 0;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s;
            list-style: none;
        }
        .dropdown-item {
            padding: 0;
        }
        .dropdown-link {
            display: block;
            padding: 10px 20px;
            color: #475569;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.2s;
        }
        .dropdown-link:hover {
            background: #f1f5f9;
            color: #10b981;
        }
        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .btn-login {
            padding: 10px 20px;
            background: #10b981;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s;
        }
        .btn-login:hover {
            background: #059669;
        }
        .user-menu {
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
        }
        .user-name {
            color: #0f172a;
            font-size: 14px;
            font-weight: 500;
        }
        .user-dropdown {
            position: relative;
        }
        .user-dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: #0f172a;
            cursor: pointer;
            padding: 8px;
        }
        @media (max-width: 968px) {
            .nav-menu {
                display: none;
            }
            .mobile-menu-toggle {
                display: block;
            }
            .header-container {
                padding: 0 16px;
            }
        }
        @media (max-width: 640px) {
            .logo-text {
                display: none;
            }
            .header-container {
                height: 64px;
            }
        }
    </style>
</head>
<body class="page-loading theme-<?php echo htmlspecialchars($userTheme, ENT_QUOTES, 'UTF-8'); ?>">
    <header class="header">
        <div class="header-container">
            <div class="header-left">
                <a href="/pickelball/main/frontend/index.php" class="logo">
                    <div class="logo-icon">P</div>
                    <span class="logo-text">Pickleball Training</span>
                </a>
                <nav>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a href="/pickelball/user/frontend/dashboard.php" class="nav-link">Home</a>
                        </li>
                        <li class="nav-item nav-dropdown">
                            <a href="#" class="nav-link">
                                Training
                                <span>▼</span>
                            </a>
                            <ul class="dropdown-menu">
                                <li class="dropdown-item">
                                    <a href="/pickelball/main/frontend/video_analysis.php" class="dropdown-link"><?php echo htmlspecialchars(t('nav_video_analysis'), ENT_QUOTES, 'UTF-8'); ?></a>
                                    <a href="/pickelball/main/frontend/action_prediction.php" class="dropdown-link"><?php echo htmlspecialchars(t('nav_action_prediction'), ENT_QUOTES, 'UTF-8'); ?></a>
                                    <a href="/pickelball/main/frontend/live_action.php" class="dropdown-link"><?php echo htmlspecialchars(t('nav_live_action'), ENT_QUOTES, 'UTF-8'); ?></a>
                                </li>
                                <li class="dropdown-item">
                                    <a href="/pickelball/main/frontend/shadowing_select.php" class="dropdown-link">Shadowing Mode</a>
                                </li>
                                <li class="dropdown-item">
                                    <a href="#" class="dropdown-link">Performance Dashboard</a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item nav-dropdown">
                            <a href="#" class="nav-link">
                                Techniques
                                <span>▼</span>
                            </a>
                            <ul class="dropdown-menu">
                                <li class="dropdown-item">
                                    <a href="#" class="dropdown-link">Serve</a>
                                </li>
                                <li class="dropdown-item">
                                    <a href="#" class="dropdown-link">Dink</a>
                                </li>
                                <li class="dropdown-item">
                                    <a href="#" class="dropdown-link">Drive Forehand</a>
                                </li>
                                <li class="dropdown-item">
                                    <a href="#" class="dropdown-link">Drive Backhand</a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">How It Works</a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">AI Coach</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <div class="header-right">
                <?php if ($isAuthenticated): ?>
                    <div class="user-menu user-dropdown">
                        <span class="user-name"><?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></span>
                        <ul class="dropdown-menu" style="right: 0; left: auto;">
                            <li class="dropdown-item">
                                <a href="/pickelball/user/frontend/dashboard.php" class="dropdown-link">Dashboard</a>
                            </li>
                            <li class="dropdown-item">
                                <a href="/pickelball/user/frontend/profile.php" class="dropdown-link">Profile</a>
                            </li>
                            <li class="dropdown-item">
                                <a href="/pickelball/user/frontend/settings.php" class="dropdown-link">Settings</a>
                            </li>
                            <li class="dropdown-item">
                                <a href="/pickelball/user/backend/logout.php" class="dropdown-link">Sign Out</a>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="/pickelball/user/frontend/login.php" class="btn-login">Login</a>
                <?php endif; ?>
                <button class="mobile-menu-toggle" aria-label="Menu">☰</button>
            </div>
        </div>
    </header>

