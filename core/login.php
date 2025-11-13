<?php
/**
 * CORE Module - Production-Grade Login Page
 *
 * Features:
 * - CSRF protection (middleware enforced)
 * - Rate limiting (middleware enforced)  
 * - Request logging (middleware enforced)
 * - Bot bypass support (X-Bot-Bypass header)
 * - Comprehensive validation
 * - Secure session handling
 * - Audit logging
 *
 * @package CIS\Core
 * @version 3.0.0 - Production Ready
 */

declare(strict_types=1);

// Load BASE bootstrap (includes middleware)
require_once dirname(__DIR__) . '/base/bootstrap.php';

// Load CORE bootstrap
require_once __DIR__ . '/bootstrap.php';

// ============================================================================
// HANDLE LOGIN SUBMISSION
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token (enforced by middleware, but double-check)
        if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token. Please refresh and try again.');
        }

        // Get and validate inputs
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if (!$email) {
            throw new Exception('Please enter a valid email address.');
        }

        if (empty($password)) {
            throw new Exception('Please enter your password.');
        }

        // Get user by email
        $user = get_user_by_email($email);

        if (!$user) {
            // Don't reveal if user exists (security best practice)
            throw new Exception('Invalid email or password.');
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            // Log failed attempt
            if (function_exists('log_activity')) {
                log_activity('login_failed', [
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'reason' => 'Invalid password'
                ]);
            }
            throw new Exception('Invalid email or password.');
        }

        // Check if account is active
        if (isset($user['status']) && $user['status'] !== 'active') {
            throw new Exception('Your account is not active. Please contact support.');
        }

        if (isset($user['is_active']) && !$user['is_active']) {
            throw new Exception('Your account has been deactivated. Please contact support.');
        }

        // Login successful - create secure session
        loginUser($user);

        // Update last login timestamp
        try {
            $pdo = db();
            $stmt = $pdo->prepare('UPDATE staff_accounts SET last_login_at = NOW() WHERE id = ?');
            $stmt->execute([$user['id']]);
        } catch (Exception $e) {
            error_log("Failed to update last_login_at: " . $e->getMessage());
        }

        // Handle "remember me" (simplified for now - full implementation in Phase 2)
        if ($remember) {
            // TODO: Implement secure remember me tokens (Phase 2)
            // For now, just extend session lifetime
            ini_set('session.cookie_lifetime', (string)(86400 * 30)); // 30 days
        }

        // Redirect to dashboard or original destination
        $redirectUrl = $_SESSION['redirect_after_login'] ?? '/modules/core/index.php';
        unset($_SESSION['redirect_after_login']);

        flash('success', 'Welcome back, ' . ($user['first_name'] ?? $user['username']) . '!');
        header("Location: {$redirectUrl}");
        exit;

    } catch (Exception $e) {
        // Store error message
        $loginError = $e->getMessage();
        
        // Log error
        error_log("[LOGIN ERROR] " . $loginError . " | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }
}

// ============================================================================
// DISPLAY LOGIN FORM
// ============================================================================

// Redirect if already logged in
if (isAuthenticated()) {
    header('Location: /modules/core/index.php');
    exit;
}

// Get flash messages
$flashSuccess = getFlash('success');
$flashError = getFlash('error');

// Generate CSRF token
$csrfToken = generate_csrf_token();

// Remember email from previous attempt
$rememberedEmail = $_POST['email'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Login - CIS</title>
    
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
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
            margin: 20px;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .login-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .login-header p {
            margin: 10px 0 0;
            opacity: 0.9;
        }
        .login-body {
            padding: 40px;
        }
        .form-floating > label {
            color: #6c757d;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: transform 0.2s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .login-footer {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }
        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        .login-footer a:hover {
            text-decoration: underline;
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
    </style>
</head>
<body>

<div class="login-container">
    <!-- Header -->
    <div class="login-header">
        <h1><i class="fas fa-user-shield"></i> CIS Login</h1>
        <p>Secure Staff Portal Access</p>
    </div>

    <!-- Body -->
    <div class="login-body">
        
        <!-- Flash Messages -->
        <?php if (isset($loginError)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                <strong>Login Failed:</strong> <?= htmlspecialchars($loginError) ?>
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

        <!-- Login Form -->
        <form method="POST" action="" id="loginForm" autocomplete="on">
            
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <!-- Email -->
            <div class="form-floating mb-3">
                <input 
                    type="email" 
                    class="form-control" 
                    id="email" 
                    name="email" 
                    placeholder="name@example.com"
                    value="<?= htmlspecialchars($rememberedEmail) ?>"
                    required
                    autocomplete="email"
                    autofocus
                >
                <label for="email">
                    <i class="fas fa-envelope"></i> Email Address
                </label>
            </div>

            <!-- Password -->
            <div class="form-floating mb-3">
                <input 
                    type="password" 
                    class="form-control" 
                    id="password" 
                    name="password" 
                    placeholder="Password"
                    required
                    autocomplete="current-password"
                >
                <label for="password">
                    <i class="fas fa-lock"></i> Password
                </label>
            </div>

            <!-- Remember Me -->
            <div class="form-check mb-3">
                <input 
                    class="form-check-input" 
                    type="checkbox" 
                    id="remember" 
                    name="remember"
                >
                <label class="form-check-label" for="remember">
                    Remember me for 30 days
                </label>
            </div>

            <!-- Submit Button -->
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg btn-login" id="loginButton">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </div>

        </form>
    </div>

    <!-- Footer -->
    <div class="login-footer">
        <a href="/modules/core/forgot-password.php">
            <i class="fas fa-question-circle"></i> Forgot Password?
        </a>
        <br>
        <small class="text-muted">
            <i class="fas fa-shield-alt"></i> Protected by CSRF & Rate Limiting
        </small>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Prevent Double Submit -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const loginButton = document.getElementById('loginButton');
    
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            // Prevent double submit
            if (loginButton.disabled) {
                e.preventDefault();
                return false;
            }
            
            // Disable button
            loginButton.disabled = true;
            loginButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
            
            // Re-enable after 5 seconds (timeout protection)
            setTimeout(() => {
                loginButton.disabled = false;
                loginButton.innerHTML = '<i class="fas fa-sign-in-alt"></i> Login';
            }, 5000);
        });
    }
});
</script>

</body>
</html>
