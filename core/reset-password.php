<?php
/**
 * CORE Module - Reset Password Page
 *
 * Allows users to reset their password using a token from email
 *
 * Features:
 * - Token validation
 * - Expiry checking
 * - Strong password requirements
 * - CSRF protection
 * - One-time use tokens
 * - Audit logging
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
// VALIDATE TOKEN
// ============================================================================

$token = isset($_GET['token']) ? (string) $_GET['token'] : '';
$tokenValid = false;
$user = null;

if (empty($token)) {
    $error = 'Invalid or missing reset token.';
} else {
    // Hash the token to compare with database
    $tokenHash = hash('sha256', $token);

    // Look up token in database
    $pdo = db();
    $stmt = $pdo->prepare('
        SELECT pr.*, sa.id as user_id, sa.email, sa.username
        FROM password_resets pr
        JOIN staff_accounts sa ON pr.user_id = sa.id
        WHERE pr.token_hash = ?
        AND pr.expires_at > NOW()
        AND pr.used_at IS NULL
        AND sa.deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->execute([$tokenHash]);
    $resetRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resetRecord) {
        $tokenValid = true;
        $user = [
            'id' => $resetRecord['user_id'],
            'email' => $resetRecord['email'],
            'username' => $resetRecord['username']
        ];
    } else {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    }
}

// ============================================================================
// HANDLE PASSWORD RESET SUBMISSION
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    try {
        // Validate CSRF token
        if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token. Please refresh and try again.');
        }

        // Get passwords
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validate inputs
        if (empty($newPassword)) {
            throw new Exception('Please enter a new password.');
        }

        if (strlen($newPassword) < 12) {
            throw new Exception('Password must be at least 12 characters long.');
        }

        // Check password complexity
        if (!preg_match('/[A-Z]/', $newPassword)) {
            throw new Exception('Password must contain at least one uppercase letter.');
        }

        if (!preg_match('/[a-z]/', $newPassword)) {
            throw new Exception('Password must contain at least one lowercase letter.');
        }

        if (!preg_match('/[0-9]/', $newPassword)) {
            throw new Exception('Password must contain at least one number.');
        }

        if (!preg_match('/[^A-Za-z0-9]/', $newPassword)) {
            throw new Exception('Password must contain at least one special character.');
        }

        if ($newPassword !== $confirmPassword) {
            throw new Exception('Passwords do not match.');
        }

        // Hash new password
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Use transaction to update password and mark token as used atomically
        $pdo->beginTransaction();

        // Update password
        $stmt = $pdo->prepare('
            UPDATE staff_accounts
            SET password_hash = ?,
                password_changed_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ');
        $stmt->execute([$newPasswordHash, $user['id']]);

        // Mark token as used
        $tokenHash = hash('sha256', $token);
        $stmt = $pdo->prepare('
            UPDATE password_resets
            SET used_at = NOW()
            WHERE token_hash = ?
        ');
        $stmt->execute([$tokenHash]);

        $pdo->commit();

        // Log password reset
        if (function_exists('log_activity')) {
            log_activity('password_reset_completed', [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }

        // Success! Queue confirmation email (best-effort)
        try {
            $baseUrl = rtrim(($config->get('APP_URL', '') ?: 'https://staff.vapeshed.co.nz'), '/');
            $loginUrl = $baseUrl . '/modules/core/login.php';
            $fromEmail = (string)($config->get('MAIL_FROM_ADDRESS', 'noreply@vapeshed.co.nz'));

            $subject = 'Your CIS Staff Portal password was changed';
            $safeLogin = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');
            $htmlBody = '<p>Hello,</p>' .
                '<p>Your CIS Staff Portal password was just changed. If you made this change, no further action is required.</p>' .
                '<p>If you did not change your password, please <a href="' . $safeLogin . '">log in</a> and reset it immediately, and contact support.</p>' .
                '<p>— The Vape Shed</p>';
            $textBody = "Hello,\n\nYour CIS Staff Portal password was just changed. If you did not make this change, log in and reset it immediately and contact support.\n\n— The Vape Shed";

            $queueStmt = $pdo->prepare('
                INSERT INTO email_queue (email_from, email_to, subject, html_body, text_body, attachments, priority, status)
                VALUES (?, ?, ?, ?, ?, NULL, ?, "pending")
            ');
            $queueStmt->execute([
                $fromEmail,
                $user['email'],
                $subject,
                $htmlBody,
                $textBody,
                1
            ]);
        } catch (Exception $qe) {
            error_log('[PASSWORD CHANGED EMAIL QUEUE ERROR] ' . $qe->getMessage());
        }

        flash('success', 'Password reset successfully! You can now log in with your new password.');
        header('Location: /modules/core/login.php');
        exit;

    } catch (Exception $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
        error_log("[RESET PASSWORD ERROR] User: {$user['id']} | Error: " . $error);
    }
}

// ============================================================================
// DISPLAY RESET PASSWORD FORM
// ============================================================================

$csrfToken = generate_csrf_token();
$flashSuccess = getFlash('success');
$flashError = getFlash('error');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Reset Password - CIS Staff Portal</title>

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
        .reset-password-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            max-width: 550px;
            width: 100%;
            margin: 20px;
        }
        .reset-password-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .reset-password-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .reset-password-header p {
            margin: 10px 0 0;
            opacity: 0.9;
        }
        .reset-password-body {
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
        .password-requirements {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .password-requirements li {
            margin: 5px 0;
        }
        .password-strength {
            margin-top: 10px;
            height: 5px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            transition: width 0.3s, background-color 0.3s;
        }
    </style>
</head>
<body>

<div class="reset-password-container">
    <!-- Header -->
    <div class="reset-password-header">
        <h1><i class="fas fa-redo"></i> Reset Password</h1>
        <p>Create a new secure password</p>
    </div>

    <!-- Body -->
    <div class="reset-password-body">

        <?php if (!$tokenValid): ?>
            <!-- Invalid Token -->
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Invalid Reset Link</strong><br>
                <?= htmlspecialchars($error) ?>
            </div>
            <div class="text-center">
                <a href="/modules/core/forgot-password.php" class="btn btn-primary">
                    <i class="fas fa-key"></i> Request New Reset Link
                </a>
                <br><br>
                <a href="/modules/core/login.php" class="btn btn-link">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>
        <?php else: ?>
            <!-- Valid Token - Show Form -->

            <!-- Flash Messages -->
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- User Info -->
            <div class="alert alert-info">
                <i class="fas fa-user"></i>
                Resetting password for: <strong><?= htmlspecialchars($user['email']) ?></strong>
            </div>

            <!-- Password Requirements -->
            <div class="password-requirements">
                <h6><i class="fas fa-shield-alt"></i> Password Requirements:</h6>
                <ul class="mb-0">
                    <li>At least 12 characters long</li>
                    <li>One uppercase letter (A-Z)</li>
                    <li>One lowercase letter (a-z)</li>
                    <li>One number (0-9)</li>
                    <li>One special character (!@#$%^&*)</li>
                </ul>
            </div>

            <!-- Reset Password Form -->
            <form method="POST" action="" id="resetPasswordForm">

                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <!-- New Password -->
                <div class="mb-3">
                    <label for="new_password" class="form-label">
                        <i class="fas fa-lock"></i> New Password
                    </label>
                    <input
                        type="password"
                        class="form-control"
                        id="new_password"
                        name="new_password"
                        required
                        autocomplete="new-password"
                        autofocus
                    >
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strengthBar"></div>
                    </div>
                    <small class="text-muted" id="strengthText"></small>
                </div>

                <!-- Confirm New Password -->
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">
                        <i class="fas fa-check-double"></i> Confirm New Password
                    </label>
                    <input
                        type="password"
                        class="form-control"
                        id="confirm_password"
                        name="confirm_password"
                        required
                        autocomplete="new-password"
                    >
                </div>

                <!-- Submit Button -->
                <div class="d-grid gap-2 mb-3">
                    <button type="submit" class="btn btn-primary btn-lg" id="submitButton">
                        <i class="fas fa-save"></i> Reset Password
                    </button>
                </div>

                <!-- Back to Login -->
                <div class="text-center">
                    <a href="/modules/core/login.php" class="btn btn-link">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>

            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Password Strength Checker -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const form = document.getElementById('resetPasswordForm');
    const submitButton = document.getElementById('submitButton');

    if (!newPasswordInput) return; // Token invalid, form not shown

    // Password strength checker
    function checkPasswordStrength(password) {
        let strength = 0;
        let feedback = [];

        if (password.length >= 12) strength += 25;
        else feedback.push('At least 12 characters');

        if (/[A-Z]/.test(password)) strength += 25;
        else feedback.push('One uppercase letter');

        if (/[a-z]/.test(password)) strength += 25;
        else feedback.push('One lowercase letter');

        if (/[0-9]/.test(password)) strength += 12.5;
        else feedback.push('One number');

        if (/[^A-Za-z0-9]/.test(password)) strength += 12.5;
        else feedback.push('One special character');

        return { strength, feedback };
    }

    newPasswordInput.addEventListener('input', function() {
        const password = this.value;
        const result = checkPasswordStrength(password);

        // Update strength bar
        strengthBar.style.width = result.strength + '%';

        if (result.strength < 50) {
            strengthBar.style.backgroundColor = '#dc3545';
            strengthText.textContent = 'Weak - Missing: ' + result.feedback.join(', ');
        } else if (result.strength < 100) {
            strengthBar.style.backgroundColor = '#ffc107';
            strengthText.textContent = 'Good - Missing: ' + result.feedback.join(', ');
        } else {
            strengthBar.style.backgroundColor = '#28a745';
            strengthText.textContent = 'Strong password!';
        }
    });

    // Form submission
    form.addEventListener('submit', function(e) {
        const newPassword = newPasswordInput.value;
        const confirmPassword = confirmPasswordInput.value;

        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('Passwords do not match!');
            return false;
        }

        // Disable button to prevent double submit
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting Password...';
    });
});
</script>

</body>
</html>
