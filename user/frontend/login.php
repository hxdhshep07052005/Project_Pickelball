<?php
declare(strict_types=1);

/**
 * Login page frontend
 * Displays login form with email/password and Google OAuth options
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    require __DIR__ . '/../backend/session.php';
    $config = require __DIR__ . '/../backend/config.php';
} catch (Throwable $e) {
    die('Error loading required files: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . ' in ' . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8') . ' on line ' . $e->getLine());
}

// Redirect to home if already logged in
if (isset($_SESSION['user'])) {
    header('Location: /pickelball/main/frontend/index.php');
    exit;
}

// Get error message and email from session (if any)
$error = $_SESSION['login_error'] ?? null;
$email = $_SESSION['login_email'] ?? '';
unset($_SESSION['login_error'], $_SESSION['login_email']);

$pageTitle = 'Sign In - Pickleball Training';
require __DIR__ . '/../../includes/header.php';
?>
<style>
    .auth-section {
        padding: 80px 24px;
        background: #f8fafc;
        min-height: calc(100vh - 72px);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .auth-container {
        max-width: 1280px;
        margin: 0 auto;
        width: 100%;
        display: flex;
        justify-content: center;
    }
    .auth-card {
        background: #ffffff;
        border-radius: 16px;
        padding: 56px;
        width: 100%;
        max-width: 520px;
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.1);
    }
    .auth-header {
        text-align: center;
        margin-bottom: 40px;
    }
    .auth-header h1 {
        font-size: 40px;
        font-weight: 700;
        margin-bottom: 12px;
        color: #0f172a;
        letter-spacing: -0.5px;
    }
    .auth-header p {
        font-size: 18px;
        color: #64748b;
        margin: 0;
    }
    .auth-form {
        display: grid;
        gap: 20px;
    }
    .form-group {
        display: grid;
        gap: 8px;
    }
    .form-label {
        font-size: 15px;
        font-weight: 500;
        color: #0f172a;
    }
    .form-input {
        padding: 16px 18px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 16px;
        transition: all 0.2s;
        font-family: inherit;
    }
    .form-input:focus {
        outline: none;
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }
    .recaptcha-wrapper {
        display: flex;
        justify-content: center;
        padding: 8px 0;
    }
    .btn-primary {
        padding: 16px 28px;
        background: #10b981;
        color: #ffffff;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
        font-family: inherit;
    }
    .btn-primary:hover {
        background: #059669;
    }
    .btn-primary:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.25);
    }
    .btn-google {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        padding: 16px 28px;
        background: #1f2937;
        color: #ffffff;
        text-decoration: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 500;
        transition: background 0.2s;
    }
    .btn-google:hover {
        background: #111827;
    }
    .btn-google:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.25);
    }
    .divider {
        display: flex;
        align-items: center;
        gap: 16px;
        margin: 8px 0;
    }
    .divider::before,
    .divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #e2e8f0;
    }
    .divider span {
        font-size: 14px;
        color: #64748b;
    }
    .alert {
        padding: 14px 16px;
        border-radius: 8px;
        background: #fee2e2;
        color: #b91c1c;
        font-size: 14px;
        text-align: center;
        border: 1px solid #fecaca;
    }
    .auth-footer {
        margin-top: 24px;
        text-align: center;
        font-size: 14px;
        color: #64748b;
    }
    .auth-footer a {
        color: #10b981;
        text-decoration: none;
        font-weight: 500;
    }
    .auth-footer a:hover {
        text-decoration: underline;
    }
    @media (max-width: 640px) {
        .auth-card {
            padding: 40px 28px;
            max-width: 100%;
        }
        .auth-header h1 {
            font-size: 32px;
        }
        .auth-header p {
            font-size: 16px;
        }
    }
</style>
<section class="auth-section">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Sign In</h1>
                <p>Welcome back to Pickleball Training</p>
            </div>
            <?php if ($error): ?>
                <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="post" action="../backend/login.php" autocomplete="off" class="auth-form">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required class="form-input" placeholder="your@email.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" required class="form-input" placeholder="Enter your password">
                </div>
                <div class="recaptcha-wrapper">
                    <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($config['recaptcha_site_key'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                </div>
                <button type="submit" class="btn-primary">Continue</button>
                <div class="divider">
                    <span>or</span>
                </div>
                <a href="../backend/google_login.php" class="btn-google">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                    </svg>
                    Sign in with Google
                </a>
            </form>
            <div class="auth-footer">
                Don't have an account? <a href="register.php">Sign up</a>
            </div>
        </div>
    </div>
</section>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php 
try {
    require __DIR__ . '/../../includes/footer.php';
} catch (Throwable $e) {
    die('Error loading footer: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . ' in ' . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8') . ' on line ' . $e->getLine());
}
?>

