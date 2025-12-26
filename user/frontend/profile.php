<?php
declare(strict_types=1);

/**
 * User profile page frontend
 * Displays user information and password change form
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../backend/require_auth.php';
require __DIR__ . '/../backend/bootstrap.php';
$config = require __DIR__ . '/../backend/config.php';

// Get authenticated user ID
$userId = (int)$authUser['id'];

// Get error and success messages from session
$error = $_SESSION['password_change_error'] ?? null;
$success = $_SESSION['password_change_success'] ?? null;
unset($_SESSION['password_change_error'], $_SESSION['password_change_success']);

// Fetch user information from database
$statement = $pdo->prepare('SELECT email, display_name, created_at FROM users WHERE id = ? LIMIT 1');
$statement->execute([$userId]);
$user = $statement->fetch();

if (!$user) {
    header('Location: /pickelball/main/frontend/index.php');
    exit;
}

$pageTitle = 'Profile - Pickleball Training';
require __DIR__ . '/../../includes/header.php';
?>
<style>
    .profile-section {
        padding: 80px 24px;
        background: #f8fafc;
        min-height: calc(100vh - 72px);
    }
    .profile-container {
        max-width: 900px;
        margin: 0 auto;
    }
    .profile-header {
        background: #ffffff;
        border-radius: 16px;
        padding: 40px;
        margin-bottom: 32px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
    .profile-header h1 {
        font-size: 36px;
        font-weight: 700;
        margin-bottom: 24px;
        color: #0f172a;
        letter-spacing: -0.5px;
    }
    .profile-info {
        display: grid;
        gap: 20px;
    }
    .info-item {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .info-label {
        font-size: 14px;
        font-weight: 500;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .info-value {
        font-size: 18px;
        font-weight: 500;
        color: #0f172a;
    }
    .password-card {
        background: #ffffff;
        border-radius: 16px;
        padding: 40px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
    .password-card h2 {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 8px;
        color: #0f172a;
        letter-spacing: -0.5px;
    }
    .password-card p {
        font-size: 16px;
        color: #64748b;
        margin-bottom: 32px;
    }
    .password-form {
        display: grid;
        gap: 24px;
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
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }
    .alert {
        padding: 16px 20px;
        border-radius: 8px;
        margin-bottom: 24px;
        font-size: 15px;
    }
    .alert-error {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
    }
    .alert-success {
        background: #f0fdf4;
        color: #16a34a;
        border: 1px solid #bbf7d0;
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
    .btn-primary:disabled {
        background: #94a3b8;
        cursor: not-allowed;
    }
    @media (max-width: 640px) {
        .profile-header,
        .password-card {
            padding: 24px;
        }
        .profile-header h1 {
            font-size: 28px;
        }
        .password-card h2 {
            font-size: 24px;
        }
    }
</style>
<section class="profile-section">
    <div class="profile-container">
        <div class="profile-header">
            <h1>Profile</h1>
            <div class="profile-info">
                <div class="info-item">
                    <span class="info-label">Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['display_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Member Since</span>
                    <span class="info-value"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                </div>
            </div>
        </div>

        <div class="password-card">
            <h2>Change Password</h2>
            <p>To change your password, we'll send a verification code to your email address for security purposes.</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" action="../backend/change_password.php" class="password-form" autocomplete="off">
                <div class="form-group">
                    <label for="current_password" class="form-label">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required class="form-input" placeholder="Enter your current password">
                </div>
                <div class="form-group">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" id="new_password" name="new_password" required class="form-input" placeholder="Enter your new password (min. 8 characters)">
                </div>
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required class="form-input" placeholder="Confirm your new password">
                </div>
                <button type="submit" class="btn-primary">Request Password Change</button>
            </form>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>

