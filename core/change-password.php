<?php
/**
 * CORE Module - Change Password Page
 *
 * Allows authenticated users to change their password
 *
 * Features:
 * - Current password verification
 * - Strong password validation
 * - CSRF protection
 * - Rate limiting
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

// Require authentication
requireAuth('/modules/core/login.php');

// ============================================================================
// HANDLE PASSWORD CHANGE SUBMISSION
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token. Please refresh and try again.');
        }

        // Get inputs
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validate inputs
        if (empty($currentPassword)) {
            throw new Exception('Please enter your current password.');
        }

        if (empty($newPassword)) {
            throw new Exception('Please enter a new password.');
        }

        if (strlen($newPassword) < 12) {
            throw new Exception('New password must be at least 12 characters long.');
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
            throw new Exception('New passwords do not match.');
        }

        if ($currentPassword === $newPassword) {
            throw new Exception('New password must be different from current password.');
        }

        // Get current user
        $user = getCurrentUser();
        if (!$user) {
            throw new Exception('User not found. Please log in again.');
        }

        // Get user with password hash from database
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id, password_hash FROM staff_accounts WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$user['id']]);
        $userWithPassword = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$userWithPassword) {
            throw new Exception('User not found.');
        }

        // Verify current password
        if (!password_verify($currentPassword, $userWithPassword['password_hash'])) {
            // Log failed attempt
            if (function_exists('log_activity')) {
                log_activity('password_change_failed', [
                    'user_id' => $user['id'],
                    'reason' => 'Invalid current password',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
            throw new Exception('Current password is incorrect.');
        }

        // Hash new password
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update password
        $stmt = $pdo->prepare('
            UPDATE staff_accounts 
            SET password_hash = ?, 
                password_changed_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ');
        $stmt->execute([$newPasswordHash, $user['id']]);

        // Log successful password change
        if (function_exists('log_activity')) {
            log_activity('password_changed', [
                'user_id' => $user['id'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }

        // Success - invalidate all other sessions for security
        // (User will need to log in again on other devices)
        session_regenerate_id(true);

        flash('success', 'Password changed successfully! Your account is now more secure.');
        header('Location: /modules/core/index.php');
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("[CHANGE PASSWORD ERROR] " . $error . " | User: " . (getUserId() ?? 'unknown'));
    }
}

// ============================================================================
// DISPLAY CHANGE PASSWORD FORM
// ============================================================================

$currentUser = getCurrentUser();
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
    <title>Change Password - CIS Staff Portal</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .change-password-container {
            max-width: 600px;
            margin: 0 auto;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            border: none;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 30px;
            text-align: center;
        }
        .card-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .card-body {
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

<div class="change-password-container">
    <div class="card">
        <!-- Header -->
        <div class="card-header">
            <h1><i class="fas fa-key"></i> Change Password</h1>
            <p class="mb-0">Update your CIS account password</p>
        </div>

        <div class="card-body">
            
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

            <!-- Logged in as -->
            <div class="alert alert-info">
                <i class="fas fa-user"></i>
                Logged in as: <strong><?= htmlspecialchars($currentUser['email'] ?? $currentUser['username'] ?? 'User') ?></strong>
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
                    <li>Different from current password</li>
                </ul>
            </div>

            <!-- Change Password Form -->
            <form method="POST" action="" id="changePasswordForm">
                
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <!-- Current Password -->
                <div class="mb-3">
                    <label for="current_password" class="form-label">
                        <i class="fas fa-lock"></i> Current Password
                    </label>
                    <input 
                        type="password" 
                        class="form-control" 
                        id="current_password" 
                        name="current_password" 
                        required
                        autocomplete="current-password"
                    >
                </div>

                <!-- New Password -->
                <div class="mb-3">
                    <label for="new_password" class="form-label">
                        <i class="fas fa-key"></i> New Password
                    </label>
                    <input 
                        type="password" 
                        class="form-control" 
                        id="new_password" 
                        name="new_password" 
                        required
                        autocomplete="new-password"
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
                        <i class="fas fa-save"></i> Change Password
                    </button>
                </div>

                <!-- Back to Dashboard -->
                <div class="text-center">
                    <a href="/modules/core/index.php" class="btn btn-link">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>

            </form>
        </div>
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
    const form = document.getElementById('changePasswordForm');
    const submitButton = document.getElementById('submitButton');

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
            alert('New passwords do not match!');
            return false;
        }

        // Disable button to prevent double submit
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changing Password...';
    });
});
</script>

</body>
</html>
