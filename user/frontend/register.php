<?php
declare(strict_types=1);

/**
 * Registration page frontend
 * Displays registration form with email, name, password fields and Google OAuth option
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../backend/session.php';
$config = require __DIR__ . '/../backend/config.php';

// Redirect to home if already logged in
if (isset($_SESSION['user'])) {
    header('Location: /pickelball/main/frontend/index.php');
    exit;
}

// Get form data and error from session (if any)
$form = $_SESSION['register_form'] ?? ['email' => '', 'name' => ''];
$error = $_SESSION['register_error'] ?? null;
unset($_SESSION['register_error']);

$pageTitle = 'Sign Up - Pickleball Training';
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
                <h1>Create Account</h1>
                <p>Start your pickleball training journey today</p>
            </div>
            <?php if ($error): ?>
                <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="post" action="../backend/register.php" autocomplete="off" class="auth-form">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($form['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required class="form-input" placeholder="John Doe">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($form['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required class="form-input" placeholder="your@email.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" required class="form-input" placeholder="At least 8 characters">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" required class="form-input" placeholder="Confirm your password">
                </div>
                <div class="recaptcha-wrapper">
                    <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($config['recaptcha_site_key'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                </div>
                <button type="submit" class="btn-primary">Sign Up</button>
            </form>
            <div class="auth-footer">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
        </div>
    </div>
</section>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>

