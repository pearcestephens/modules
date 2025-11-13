<?php
/**
 * CORE Module Bootstrap
 *
 * Initializes the CORE module and provides helper functions.
 *
 * @version 2.0.0
 */

declare(strict_types=1);

// Module paths
define('CORE_PATH', __DIR__);
define('CORE_PUBLIC_PATH', CORE_PATH . '/public');
define('CORE_VIEWS_PATH', CORE_PATH . '/views');
define('CORE_CONTROLLERS_PATH', CORE_PATH . '/controllers');
define('CORE_MODELS_PATH', CORE_PATH . '/models');
define('CORE_MIDDLEWARE_PATH', CORE_PATH . '/middleware');

// Load BASE module
require_once dirname(__DIR__) . '/base/bootstrap.php';

// Load CORE configuration
$coreConfig = require CORE_PATH . '/config.php';

// Register CORE with application
if (isset($app)) {
    $app->registerModule('core', $coreConfig);
}

// ============================================================================
// CORE-SPECIFIC HELPER FUNCTIONS
// Note: Use BASE module functions (isAuthenticated, getCurrentUser, etc.)
// where possible. These are CORE-specific wrappers.
// ============================================================================

/**
 * Require authentication (redirect if not logged in)
 * Wrapper around BASE isAuthenticated() for CORE module
 */
function require_auth(string $redirectUrl = '/modules/core/login.php'): void
{
    if (!isAuthenticated()) {  // Use BASE function
        header('Location: ' . $redirectUrl);
        exit;
    }
}

/**
 * Require guest (redirect if already logged in)
 */
function require_guest(string $redirectUrl = '/modules/core/index.php'): void
{
    if (isAuthenticated()) {  // Use BASE function
        header('Location: ' . $redirectUrl);
        exit;
    }
}

/**
 * Redirect with message
 * Wrapper around BASE flash() function
 */
function redirect_with_message(string $url, string $message, string $type = 'success'): void
{
    flash('message', $message, $type);  // Use BASE flash function
    header('Location: ' . $url);
    exit;
}

/**
 * Get flash message
 * Wrapper around BASE getFlash() function
 */
function get_flash_message(): ?array
{
    $flash = getFlash('message');  // Use BASE getFlash function
    return $flash ? ['message' => $flash['message'], 'type' => $flash['type']] : null;
}

/**
 * Render CORE view
 * CORE-specific wrapper for rendering views in /modules/core/views/
 */
function render_view(string $view, array $data = []): void
{
    extract($data);
    $viewPath = CORE_VIEWS_PATH . '/' . $view . '.php';

    if (!file_exists($viewPath)) {
        throw new Exception("View not found: {$view}");
    }

    require $viewPath;
}

/**
 * Generate CSRF token
 * Uses session-based CSRF protection
 */
function generate_csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * @param string $token Token to validate
 * @return bool True if valid
 */
function validate_csrf_token(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token as hidden input field
 * @return string HTML input field
 */
function csrf_field(): string
{
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

// Note: Use BASE jsonResponse() instead of defining json_response()
// Note: Use BASE e() for escaping instead of sanitize_input()

/**
 * Check if user has specific role
 * Wrapper around BASE getUserRole() function
 */
function has_role(string $role): bool
{
    return getUserRole() === $role;  // Use BASE function
}

/**
 * Check if user is admin
 * Wrapper around BASE hasPermission() function
 */
function is_admin(): bool
{
    return has_role('admin') || hasPermission('admin');  // Use BASE functions
}

/**
 * Log activity (CORE-specific implementation)
 */
function log_activity(string $action, array $details = []): void
{
    if (!isAuthenticated()) {  // Use BASE function
        return;
    }

    $userId = getUserId();  // Use BASE function
    $logFile = CORE_PATH . '/../_logs/activity.log';
    $logLine = sprintf(
        "[%s] User %d: %s - %s\n",
        date('Y-m-d H:i:s'),
        $userId,
        $action,
        json_encode($details)
    );

    @file_put_contents($logFile, $logLine, FILE_APPEND);
}

/**
 * Get user by ID
 * Uses BASE db() function for database access
 */
function get_user_by_id(int $userId): ?array
{
    try {
        $pdo = db();  // Use BASE function
        $stmt = $pdo->prepare('SELECT * FROM staff_accounts WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    } catch (Exception $e) {
        error_log("Error fetching user: " . $e->getMessage());
        return null;
    }
}

/**
 * Get user by email
 */
function get_user_by_email(string $email): ?array
{
    try {
        $pdo = db();  // Use BASE function
        $stmt = $pdo->prepare('SELECT * FROM staff_accounts WHERE email = ? AND deleted_at IS NULL');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    } catch (Exception $e) {
        error_log("Error fetching user: " . $e->getMessage());
        return null;
    }
}

/**
 * Get user by username
 */
function get_user_by_username(string $username): ?array
{
    try {
        $pdo = db();  // Use BASE function
        $stmt = $pdo->prepare('SELECT * FROM staff_accounts WHERE username = ? AND deleted_at IS NULL');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    } catch (Exception $e) {
        error_log("Error fetching user: " . $e->getMessage());
        return null;
    }
}

// ============================================================================
// REMOVED DUPLICATE FUNCTIONS - Use BASE module instead:
// ============================================================================
// - is_authenticated()      → Use isAuthenticated() from BASE
// - auth_user_id()          → Use getUserId() from BASE
// - auth_user()             → Use getCurrentUser() from BASE
// - json_response()         → Use jsonResponse() from BASE
// - sanitize_input()        → Use e() from BASE
// - is_valid_email()        → Use Validator class from BASE
// - is_valid_username()     → Use Validator class from BASE
// - hash_password()         → Use password_hash() (native PHP)
// - verify_password()       → Use password_verify() (native PHP)
// ============================================================================

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true
    ]);
}

// Regenerate session ID periodically for security
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}
