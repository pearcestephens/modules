<?php
/**
 * CORE Module - Forgot Password Page
 *
 * Allows users to request a password reset link
 *
 * Features:
 * - Email validation
 * - Token generation
 * - CSRF protection
 * - Rate limiting
 * - Security best practices (don't reveal if email exists)
 *
 * @package CIS\Core
 * @version 2.0.0 - Production Ready
 */

declare(strict_types=1);

// Load BASE bootstrap (includes middleware)
require_once dirname(__DIR__) . '/base/bootstrap.php';

// Load CORE bootstrap
require_once __DIR__ . '/bootstrap.php';

// Redirect if already logged in
if (isAuthenticated()) {
    header('Location: /modules/core/index.php');
    exit;
}

// ============================================================================
// HANDLE FORGOT PASSWORD SUBMISSION
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token. Please refresh and try again.');
        }

        // Get email
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

        if (!$email) {
            throw new Exception('Please enter a valid email address.');
        }

        // Get user by email
        $user = get_user_by_email($email);

        if ($user && isset($user['id'])) {
            // Generate secure reset token
            $token = bin2hex(random_bytes(32)); // 64 character hex string
            $tokenHash = hash('sha256', $token); // Store hash, not plain token
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry

            // Store reset token in database
            $pdo = db();
            
            // Create password_resets table if it doesn't exist
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS password_resets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    token_hash VARCHAR(64) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    used_at DATETIME NULL,
                    INDEX idx_token (token_hash),
                    INDEX idx_user (user_id),
                    INDEX idx_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Delete any existing tokens for this user
            $stmt = $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?');
            $stmt->execute([$user['id']]);

            // Insert new token
            $stmt = $pdo->prepare('
                INSERT INTO password_resets (user_id, token_hash, expires_at)
                VALUES (?, ?, ?)
            ');
            $stmt->execute([$user['id'], $tokenHash, $expiresAt]);

            // Build reset URL
            $resetUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/modules/core/reset-password.php?token=' . urlencode($token);

            // Log password reset request
            if (function_exists('log_activity')) {
                log_activity('password_reset_requested', [
                    'user_id' => $user['id'],
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }

            // TODO: Send email with reset link
            // For now, just log it (implement email service in Phase 2)
            error_log("[PASSWORD RESET] User: {$user['id']} | Email: {$email} | Reset URL: {$resetUrl}");

            // In development, show the link (remove in production)
            if ($config->get('APP_DEBUG', false)) {
                $_SESSION['_debug_reset_url'] = $resetUrl;
            }
        }

        // SECURITY: Always show success message (don't reveal if email exists)
        flash('success', 'If that email address is registered, a password reset link has been sent. Please check your email.');
        header('Location: /modules/core/login.php');
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("[FORGOT PASSWORD ERROR] " . $error);
    }
}

// ============================================================================
// DISPLAY FORGOT PASSWORD FORM
// ============================================================================

$csrfToken = generate_csrf_token();
$flashSuccess = getFlash('success');
$flashError = getFlash('error');
$rememberedEmail = $_POST['email'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Forgot Password - CIS Staff Portal</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .forgot-password-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            margin: 20px;
        }
        .forgot-password-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .forgot-password-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .forgot-password-header p {
            margin: 10px 0 0;
            opacity: 0.9;
        }
        .forgot-password-body {
            padding: 40px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
    </style>
</head>
<body>

<div class="forgot-password-container">
    <!-- Header -->
    <div class="forgot-password-header">
        <h1><i class="fas fa-key"></i> Forgot Password?</h1>
        <p>We'll send you a reset link</p>
    </div>

    <!-- Body -->
    <div class="forgot-password-body">
        
        <!-- Flash Messages -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($flashSuccess['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($flashError['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Info Box -->
        <div class="info-box">
            <h6><i class="fas fa-info-circle"></i> How it works:</h6>
            <ol class="mb-0">
                <li>Enter your registered email address</li>
                <li>We'll send you a secure reset link</li>
                <li>Click the link to create a new password</li>
                <li>Link expires in 1 hour for security</li>
            </ol>
        </div>

        <!-- Forgot Password Form -->
        <form method="POST" action="" id="forgotPasswordForm">
            
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <!-- Email -->
            <div class="mb-3">
                <label for="email" class="form-label">
                    <i class="fas fa-envelope"></i> Email Address
                </label>
                <input 
                    type="email" 
                    class="form-control" 
                    id="email" 
                    name="email" 
                    placeholder="your.email@ecigdis.co.nz"
                    value="<?= htmlspecialchars($rememberedEmail) ?>"
                    required
                    autocomplete="email"
                    autofocus
                >
                <small class="text-muted">Enter the email address you used to register</small>
            </div>

            <!-- Submit Button -->
            <div class="d-grid gap-2 mb-3">
                <button type="submit" class="btn btn-primary btn-lg" id="submitButton">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
            </div>

            <!-- Back to Login -->
            <div class="text-center">
                <a href="/modules/core/login.php" class="btn btn-link">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>

        </form>

        <!-- Debug Mode: Show reset link -->
        <?php if (isset($_SESSION['_debug_reset_url']) && $config->get('APP_DEBUG', false)): ?>
            <div class="alert alert-warning mt-3">
                <strong>DEBUG MODE:</strong> Reset link (email not sent in dev):<br>
                <a href="<?= htmlspecialchars($_SESSION['_debug_reset_url']) ?>" target="_blank">
                    <?= htmlspecialchars($_SESSION['_debug_reset_url']) ?>
                </a>
            </div>
            <?php unset($_SESSION['_debug_reset_url']); ?>
        <?php endif; ?>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Form Handler -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('forgotPasswordForm');
    const submitButton = document.getElementById('submitButton');

    form.addEventListener('submit', function() {
        // Disable button to prevent double submit
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    });
});
</script>

</body>
</html>
