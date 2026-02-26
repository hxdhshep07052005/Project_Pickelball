<?php
declare(strict_types=1);

/**
 * Admin login page frontend
 * Displays login form with username/password
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../backend/session.php';

// Redirect to dashboard if already logged in
if (isset($_SESSION['admin'])) {
    header('Location: dashboard.php');
    exit;
}

// Get error message and username from session (if any)
$error = $_SESSION['admin_login_error'] ?? null;
$username = $_SESSION['admin_login_username'] ?? '';
unset($_SESSION['admin_login_error'], $_SESSION['admin_login_username']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Pickleball Training</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            line-height: 1.6;
        }
        .auth-section {
            padding: 80px 24px;
            min-height: 100vh;
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
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
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
        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            background: #fee2e2;
            color: #b91c1c;
            font-size: 14px;
            text-align: center;
            border: 1px solid #fecaca;
            margin-bottom: 20px;
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
</head>
<body>
<section class="auth-section">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Admin Login</h1>
                <p>Pickleball Training System</p>
            </div>
            <?php if ($error): ?>
                <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="post" action="../backend/login.php" autocomplete="off" class="auth-form">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" required class="form-input" placeholder="Enter username" autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" required class="form-input" placeholder="Enter password">
                </div>
                <button type="submit" class="btn-primary">Sign In</button>
            </form>
        </div>
    </div>
</section>
</body>
</html>

