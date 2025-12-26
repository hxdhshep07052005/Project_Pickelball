<?php
declare(strict_types=1);

/**
 * OTP verification page frontend
 * Displays OTP input form for login, registration, or password change verification
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../backend/session.php';

// Determine verification type from session (login, registration, or password_change)
$pending = null;
$verifyType = 'login';
$pageTitle = 'Verify Code - Pickleball Training';
$title = 'Verify OTP Code';
$description = 'Enter the verification code sent to your email';
$cancelLink = '../backend/logout.php';
$cancelText = 'Cancel sign-in';

if (isset($_SESSION['pending_password_change'])) {
    // Password change verification
    $pending = $_SESSION['pending_password_change'];
    $verifyType = 'password_change';
    $pageTitle = 'Verify Password Change - Pickleball Training';
    $title = 'Verify Password Change';
    $description = 'Enter the 6-digit code sent to your email';
    $cancelLink = 'profile.php';
    $cancelText = 'Cancel';
    require __DIR__ . '/../backend/require_auth.php'; // Require authentication
} elseif (isset($_SESSION['pending_registration'])) {
    // Registration verification
    $pending = $_SESSION['pending_registration'];
    $verifyType = 'registration';
    $pageTitle = 'Verify Registration - Pickleball Training';
    $title = 'Verify Registration';
    $description = 'Enter the verification code sent to your email';
    $cancelLink = 'login.php';
    $cancelText = 'Cancel registration';
} elseif (isset($_SESSION['pending_login'])) {
    // Login verification
    $pending = $_SESSION['pending_login'];
    $verifyType = 'login';
}

// Redirect if no pending verification session
if (!$pending) {
    if ($verifyType === 'password_change') {
        $_SESSION['password_change_error'] = 'Verification session has expired. Please try again.';
        header('Location: profile.php');
    } else {
        header('Location: login.php');
    }
    exit;
}

// Get messages
$notice = null;
$error = null;

if ($verifyType === 'password_change') {
    $notice = $_SESSION['password_change_notice'] ?? null;
    $error = $_SESSION['verify_password_change_error'] ?? null;
    unset($_SESSION['verify_password_change_error']);
} elseif ($verifyType === 'registration') {
    $notice = $_SESSION['register_notice'] ?? null;
    $error = $_SESSION['verify_registration_error'] ?? null;
    unset($_SESSION['verify_registration_error']);
} else {
    $notice = $_SESSION['verify_notice'] ?? null;
    $error = $_SESSION['verify_error'] ?? null;
    unset($_SESSION['verify_error'], $_SESSION['verify_notice']);
}

$maskedEmail = preg_replace('/(^.).+(@.*$)/', '$1***$2', $pending['email'] ?? '');

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
    .otp-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 24px;
        box-shadow: 0 8px 24px rgba(16, 185, 129, 0.2);
    }
    .otp-icon svg {
        width: 40px;
        height: 40px;
        fill: #ffffff;
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
    .otp-input {
        padding: 20px;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 28px;
        text-align: center;
        letter-spacing: 10px;
        font-weight: 600;
        transition: all 0.2s;
        font-family: 'Courier New', monospace;
        width: 100%;
        box-sizing: border-box;
    }
    .otp-input:focus {
        outline: none;
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }
    .otp-hint {
        text-align: center;
        font-size: 14px;
        color: #64748b;
        margin-top: -12px;
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
        width: 100%;
    }
    .btn-primary:hover {
        background: #059669;
    }
    .btn-primary:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25);
    }
    .btn-secondary {
        padding: 16px 28px;
        background: transparent;
        color: #64748b;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        text-align: center;
        font-family: inherit;
        display: block;
    }
    .btn-secondary:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
    }
    .alert {
        padding: 14px 16px;
        border-radius: 8px;
        font-size: 14px;
        text-align: center;
        margin-bottom: 20px;
    }
    .alert-error {
        background: #fee2e2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }
    .alert-notice {
        background: #dcfce7;
        color: #15803d;
        border: 1px solid #bbf7d0;
    }
    .alert-info {
        background: #eff6ff;
        color: #2563eb;
        border: 1px solid #bfdbfe;
    }
    .form-actions {
        display: grid;
        gap: 12px;
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
        .otp-input {
            font-size: 24px;
            letter-spacing: 8px;
        }
    }
</style>
<section class="auth-section">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="otp-icon">
                    <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                    </svg>
                </div>
                <h1><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?><br><strong><?php echo htmlspecialchars($maskedEmail, ENT_QUOTES, 'UTF-8'); ?></strong></p>
            </div>
            <?php if ($notice): ?>
                <div class="alert alert-notice"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="post" action="../backend/verify.php" autocomplete="off" class="auth-form" id="otp-form">
                <div class="form-group">
                    <label for="code" class="form-label">Verification Code</label>
                    <input 
                        type="text" 
                        id="code" 
                        name="code" 
                        maxlength="6" 
                        pattern="\d{6}" 
                        inputmode="numeric" 
                        required 
                        class="otp-input" 
                        placeholder="000000" 
                        autofocus
                    >
                    <p class="otp-hint">Enter the 6-digit code</p>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <?php echo $verifyType === 'password_change' ? 'Verify & Change Password' : 'Confirm'; ?>
                    </button>
                    <a href="<?php echo htmlspecialchars($cancelLink, ENT_QUOTES, 'UTF-8'); ?>" class="btn-secondary"><?php echo htmlspecialchars($cancelText, ENT_QUOTES, 'UTF-8'); ?></a>
                </div>
            </form>
        </div>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const otpInput = document.getElementById('code');
    
    // Auto-format: only allow digits
    otpInput.addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/\D/g, '');
    });
    
    // Auto-submit when 6 digits are entered
    otpInput.addEventListener('input', (e) => {
        if (e.target.value.length === 6) {
            document.getElementById('otp-form').submit();
        }
    });
    
    // Focus on input
    otpInput.focus();
});
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
