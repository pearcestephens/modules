<?php
/**
 * CIS Base Bootstrap
 *
 * Universal initialization for all CIS modules
 * Load this at the top of every module file:
 *
 *   require_once __DIR__ . '/../base/bootstrap.php';
 *
 * Provides:
 *   - $config (Services\Config singleton)
 *   - $db (Services\Database singleton)
 *   - Sessions (auto-started, secured)
 *   - Auth functions (isAuthenticated, requireAuth, etc.)
 *   - Permission functions (hasPermission, requirePermission, etc.)
 *   - Template functions (render, component, theme)
 *   - Helper functions (e, redirect, jsonResponse, flash, etc.)
 */

// =============================================================================
// 0. CLI ENVIRONMENT SETUP
// =============================================================================

// Ensure DOCUMENT_ROOT is set (required for CLI mode)
if (!isset($_SERVER['DOCUMENT_ROOT']) || empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
}

// =============================================================================
// 1. COMPOSER AUTOLOADER (PSR-4)
// =============================================================================

// Load Composer autoloader (handles all PSR-4 namespaces)
$vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

// Legacy compatibility: Manual class loader for old code
function loadClass($className) {
    $classFile = __DIR__ . '/' . $className . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
    }
}

// =============================================================================
// 2. LOAD SERVICES\CONFIG (FIRST)
// =============================================================================

$servicesPath = realpath(__DIR__ . '/../../assets/services');
require_once $servicesPath . '/Config.php';

// Initialize Config singleton
$config = \Services\Config::getInstance();

// =============================================================================
// EARLY ERROR HANDLER (catches bootstrap errors)
// =============================================================================

// Load ErrorHandler class early
require_once __DIR__ . '/lib/ErrorHandler.php';

// Initialize with debug mode from config
\CIS\Base\ErrorHandler::init($config->get('APP_DEBUG', false));


// =============================================================================
// 3. LOAD SERVICES\DATABASE
// =============================================================================

require_once $servicesPath . '/Database.php';

// Initialize Database singleton
$db = \Services\Database::getInstance();

// =============================================================================
// 4. SESSION MANAGEMENT
// =============================================================================

if (session_status() === PHP_SESSION_NONE) {
    // Force CIS standard session name
    if (session_name() !== 'CIS_SESSION') {
        @session_name('CIS_SESSION');
    }

    // Secure session settings
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_secure', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_path', '/');

    session_start();

    // Regenerate session ID periodically (every 30 minutes)
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// =============================================================================
// 5. MIDDLEWARE PIPELINE (Activate security, logging, rate limiting)
// =============================================================================

// Only run middleware for HTTP requests (not CLI)
if (PHP_SAPI !== 'cli') {
    try {
        // Load middleware classes
        require_once __DIR__ . '/middleware/MiddlewarePipeline.php';
        require_once __DIR__ . '/middleware/LoggingMiddleware.php';
        require_once __DIR__ . '/middleware/RateLimitMiddleware.php';
        require_once __DIR__ . '/middleware/CsrfMiddleware.php';

        // Create middleware pipeline
        $middlewarePipeline = new \App\Middleware\MiddlewarePipeline();

        // Add middleware in execution order
        $middlewarePipeline
            ->add(new \App\Middleware\LoggingMiddleware())      // Log all requests
            ->add(new \App\Middleware\RateLimitMiddleware())    // Prevent abuse
            ->add(new \App\Middleware\CsrfMiddleware());        // CSRF protection

        // Execute middleware pipeline
        $middlewarePipeline->handle($_REQUEST, function() {
            // Continue to application
            return null;
        });

    } catch (Exception $e) {
        // Log middleware errors but don't break application
        error_log("[MIDDLEWARE ERROR] " . $e->getMessage());

        // Handle specific middleware failures
        if ($e->getCode() === 429) {
            http_response_code(429);
            header('Retry-After: 60');
            die(json_encode(['error' => 'Too many requests. Please try again later.']));
        } elseif ($e->getCode() === 403) {
            http_response_code(403);
            die(json_encode(['error' => 'Forbidden. CSRF validation failed.']));
        }
    }
}

// =============================================================================
// 6. LOAD THEME MANAGER
// =============================================================================

require_once __DIR__ . '/lib/ThemeManager.php';
\CIS\Base\ThemeManager::init();

// =============================================================================
// 7. LOAD OTHER SERVICES
// =============================================================================

// Legacy services (existing CIS code)
// Load Database wrapper class FIRST (needed by RateLimiter)
loadClass('Database');  // CIS\Base\Database wrapper

$legacyServices = [
    'CISLogger',
    'ErrorHandler',
    'RateLimiter',
    'Cache',
    'Auth',
    'Encryption',
    'Sanitizer'
];

foreach ($legacyServices as $service) {
    $serviceFile = $servicesPath . '/' . $service . '.php';
    if (file_exists($serviceFile)) {
        require_once $serviceFile;
    }
}

// Base module classes
loadClass('Response');
loadClass('Router');

// =============================================================================
// 7. AUTHENTICATION FUNCTIONS
// =============================================================================

/**
 * Check if user is authenticated
 * Supports both new standard (user_id) and legacy (userID) for backwards compatibility
 */
function isAuthenticated(): bool {
    // Check new standard (user_id) or legacy (userID)
    return (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) ||
           (isset($_SESSION['userID']) && !empty($_SESSION['userID']));
}

/**
 * Get current user data
 */
function getCurrentUser(): ?array {
    if (!isAuthenticated()) {
        return null;
    }

    global $db;

    // Try from session cache first
    if (isset($_SESSION['user_data'])) {
        return $_SESSION['user_data'];
    }

    // Load from database - support both new (user_id) and legacy (userID)
    $userId = $_SESSION['user_id'] ?? $_SESSION['userID'] ?? null;

    if (!$userId) {
        return null;
    }

    $stmt = $db->prepare("
        SELECT id, username, email, role, outlet_id, status
        FROM staff_accounts
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['user_data'] = $user;
        return $user;
    }

    return null;
}

/**
 * Require authentication (redirect to login if not authenticated)
 *
 * BOT BYPASS: Add HTTP header "X-Bot-Bypass: your_token_here"
 * Token is stored in .env as BOT_BYPASS_TOKEN
 *
 * Example usage:
 * curl -H "X-Bot-Bypass: your_token_from_env" https://example.com/admin
 *
 * @param string $redirectUrl Where to redirect if not authenticated
 */
function requireAuth(string $redirectUrl = '/login.php'): void {
    global $config;

    // BOT BYPASS via HTTP header (simple, secure, works every time)
    $botBypassToken = $config->get('BOT_BYPASS_TOKEN', '');

    if (!empty($botBypassToken) &&
        isset($_SERVER['HTTP_X_BOT_BYPASS']) &&
        hash_equals($botBypassToken, $_SERVER['HTTP_X_BOT_BYPASS'])) {

        // Log bot bypass usage for monitoring
        error_log(sprintf(
            "[BOT BYPASS] IP: %s | URI: %s | Time: %s",
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['REQUEST_URI'] ?? 'unknown',
            date('Y-m-d H:i:s')
        ));

        // Create bot test session if not already logged in
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['user_id'] = 1;
            $_SESSION['userID'] = 1; // Legacy compatibility
            $_SESSION['username'] = 'BotTestUser';
            $_SESSION['userRole'] = 'admin';
            $_SESSION['authenticated'] = true;
            $_SESSION['_bot_bypass'] = true;
            $_SESSION['_bot_bypass_time'] = time();
        }
        return; // Skip authentication check
    }

    if (!isAuthenticated()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: {$redirectUrl}");
        exit;
    }
}

/**
 * Get user ID
 * Supports both new standard (user_id) and legacy (userID) for backwards compatibility
 */
function getUserId(): ?int {
    // Prefer new standard, fallback to legacy
    return $_SESSION['user_id'] ?? $_SESSION['userID'] ?? null;
}

/**
 * Get user role
 */
function getUserRole(): ?string {
    $user = getCurrentUser();
    return $user['role'] ?? null;
}

// =============================================================================
// 8. PERMISSION FUNCTIONS
// =============================================================================

/**
 * Check if user has permission
 */
function hasPermission(string $permission): bool {
    if (!isAuthenticated()) {
        return false;
    }

    global $db;
    $userId = getUserId();

    // Admin role has all permissions
    $role = getUserRole();
    if ($role === 'admin' || $role === 'super_admin') {
        return true;
    }

    // Check permission in database
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM staff_permissions
        WHERE user_id = ? AND permission = ? AND active = 1
    ");
    $stmt->execute([$userId, $permission]);

    return $stmt->fetchColumn() > 0;
}

/**
 * Require permission (die with error if not granted)
 */
function requirePermission(string $permission): void {
    if (!hasPermission($permission)) {
        http_response_code(403);
        die('Access Denied: You do not have permission to access this resource.');
    }
}

/**
 * Check if user has ANY of the given permissions
 */
function hasAnyPermission(array $permissions): bool {
    foreach ($permissions as $permission) {
        if (hasPermission($permission)) {
            return true;
        }
    }
    return false;
}

/**
 * Check if user has ALL of the given permissions
 */
function hasAllPermissions(array $permissions): bool {
    foreach ($permissions as $permission) {
        if (!hasPermission($permission)) {
            return false;
        }
    }
    return true;
}

// =============================================================================
// 8.5 DATABASE HELPER FUNCTION
// =============================================================================

/**
 * Get database connection (PDO)
 *
 * @return PDO
 */
function db(): PDO {
    global $db;
    return $db->getConnection();
}

// =============================================================================
// 9. TEMPLATE FUNCTIONS
// =============================================================================

/**
 * Render page with theme layout
 *
 * @param string $layout Layout name (dashboard, centered, blank, print)
 * @param string $content Page content (HTML)
 * @param array $data Additional data (pageTitle, breadcrumbs, etc.)
 */
function render(string $layout, string $content, array $data = []): void {
    \CIS\Base\ThemeManager::render($layout, $content, $data);
}

/**
 * Render a component (header, sidebar, footer, etc.)
 */
function component(string $name, array $data = []): void {
    \CIS\Base\ThemeManager::component($name, $data);
}

/**
 * Get theme asset URL
 */
function themeAsset(string $path): string {
    return \CIS\Base\ThemeManager::asset($path);
}

/**
 * Get active theme name
 */
function theme(): string {
    return \CIS\Base\ThemeManager::getActive();
}

// =============================================================================
// 10. HELPER FUNCTIONS
// =============================================================================

/**
 * Escape output for HTML
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Get asset URL (CSS, JS, images)
 */
function asset(string $path): string {
    return '/assets/' . ltrim($path, '/');
}

/**
 * Get module URL
 */
function moduleUrl(string $module, string $page = ''): string {
    $url = '/modules/' . $module . '/';
    if ($page) {
        $url .= ltrim($page, '/');
    }
    return $url;
}

/**
 * Redirect to URL
 */
function redirect(string $url, int $code = 302): void {
    header("Location: $url", true, $code);
    exit;
}

/**
 * Send JSON response
 */
function jsonResponse($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Set flash message
 */
function flash(string $key, string $message, string $type = 'info'): void {
    $_SESSION['flash'][$key] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Get and clear flash message
 */
function getFlash(string $key): ?array {
    if (isset($_SESSION['flash'][$key])) {
        $flash = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $flash;
    }
    return null;
}

/**
 * Get all flash messages and clear
 */
function getAllFlashes(): array {
    $flashes = $_SESSION['flash'] ?? [];
    $_SESSION['flash'] = [];
    return $flashes;
}

/**
 * Dump and die (for debugging)
 */
function dd(...$vars): void {
    echo '<pre>';
    foreach ($vars as $var) {
        var_dump($var);
    }
    echo '</pre>';
    die();
}

// =============================================================================
// =============================================================================
// 12. TIMEZONE & LOCALE
// =============================================================================

date_default_timezone_set($config->get('APP_TIMEZONE', 'Pacific/Auckland'));

// =============================================================================
// 13. AUTHENTICATION HELPERS (PRODUCTION GRADE)
// =============================================================================

/**
 * Login user and create secure session
 *
 * PRODUCTION GRADE: Handles session regeneration, standardized user data,
 * security best practices, backwards compatibility, and prevents concurrent
 * login race conditions with file locking.
 *
 * @param array $user User data from database (must include 'id')
 * @return void
 * @throws InvalidArgumentException If user ID is missing
 * @throws RuntimeException If concurrent login detected
 */
function loginUser(array $user): void
{
    // Validate user data
    if (empty($user['id'])) {
        throw new \InvalidArgumentException('User ID is required for login');
    }

    // CRITICAL: Prevent concurrent login race condition with file locking
    $lockKey = "login_lock_user_{$user['id']}";
    $lockFile = sys_get_temp_dir() . "/{$lockKey}.lock";

    // Try to acquire exclusive lock (non-blocking)
    $lockHandle = fopen($lockFile, 'c');
    if ($lockHandle === false) {
        error_log("[LOGIN ERROR] Failed to create lock file: {$lockFile}");
        throw new \RuntimeException('Unable to process login. Please try again.');
    }

    if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
        // Another login attempt in progress
        fclose($lockHandle);
        error_log("[LOGIN] Concurrent login attempt blocked for user {$user['id']}");
        throw new \RuntimeException('Login already in progress. Please wait a moment and try again.');
    }

    try {
        // Check if already logged in (prevent duplicate session creation)
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] === (int)$user['id']) {
            // Already logged in - update activity timestamp and return
            $_SESSION['last_activity'] = time();
            if (isset($_SESSION['user']['last_activity'])) {
                $_SESSION['user']['last_activity'] = time();
            }
            return;
        }

        // Security: Regenerate session ID to prevent session fixation
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        // Modern PHP standard: user_id (snake_case)
        $_SESSION['user_id'] = (int) $user['id'];

        // Legacy compatibility: Also set userID (camelCase)
        $_SESSION['userID'] = (int) $user['id'];

        // Store complete user data with safe defaults
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'username' => $user['username'] ?? '',
            'email' => $user['email'] ?? '',
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?? '',
            'display_name' => $user['display_name'] ??
                trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?:
                ($user['username'] ?? 'User'),
            'avatar_url' => $user['avatar_url'] ?? '/images/default-avatar.png',
            'role' => $user['role'] ?? 'user',
            'availability_status' => $user['availability_status'] ?? 'online',
            'logged_in_at' => time(),
            'last_activity' => time()
        ];

        // Security: Mark session as authenticated
        $_SESSION['authenticated'] = true;
        $_SESSION['auth_time'] = time();
        $_SESSION['_login_nonce'] = bin2hex(random_bytes(16)); // Prevent replay attacks

        // Production: Log successful login (audit trail)
        if (function_exists('log_activity')) {
            log_activity('user_login_session_created', [
                'user_id' => $user['id'],
                'email' => $user['email'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'session_id' => session_id()
            ]);
        }

    } finally {
        // Always release lock
        if ($lockHandle) {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }

        // Cleanup old lock files (older than 1 hour)
        try {
            $oldLocks = glob(sys_get_temp_dir() . "/login_lock_user_*.lock");
            if ($oldLocks !== false) {
                foreach ($oldLocks as $oldLock) {
                    $mtime = filemtime($oldLock);
                    if ($mtime !== false && $mtime < time() - 3600) {
                        unlink($oldLock);
                    }
                }
            }
        } catch (\Exception $e) {
            // Log but don't fail - cleanup is non-critical
            error_log("[LOGIN] Failed to cleanup old lock files: " . $e->getMessage());
        }
    }
}

/**
 * Logout user and destroy session securely
 *
 * PRODUCTION GRADE: Proper session cleanup, cookie removal,
 * audit logging, and fresh session for flash messages.
 *
 * @param bool $startFreshSession Start new session for flash messages (default: true)
 * @return void
 */
function logoutUser(bool $startFreshSession = true): void
{
    // Production: Log logout before destroying session
    if (function_exists('log_activity') && isset($_SESSION['user_id'])) {
        log_activity('user_logout_session_destroyed', [
            'user_id' => $_SESSION['user_id'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }

    // Security: Clear all session data
    $_SESSION = [];

    // Security: Delete session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    // Security: Destroy session
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    // Convenience: Start fresh session for flash messages
    if ($startFreshSession) {
        session_start();
        session_regenerate_id(true);
    }
}

/**
 * Update session activity timestamp
 *
 * PRODUCTION GRADE: Prevents session timeout for active users.
 * Call this on each authenticated request.
 *
 * @return void
 */
function updateSessionActivity(): void
{
    if (isAuthenticated()) {
        $_SESSION['last_activity'] = time();
        if (isset($_SESSION['user']['last_activity'])) {
            $_SESSION['user']['last_activity'] = time();
        }
    }
}

/**
 * Check if session has timed out
 *
 * PRODUCTION GRADE: Configurable timeout, automatic logout on timeout.
 *
 * @param int $timeoutSeconds Timeout in seconds (default: 7200 = 2 hours)
 * @return bool True if session timed out
 */
function isSessionTimedOut(int $timeoutSeconds = 7200): bool
{
    if (!isset($_SESSION['last_activity'])) {
        return false;
    }

    $inactive = time() - $_SESSION['last_activity'];

    if ($inactive > $timeoutSeconds) {
        logoutUser(false);
        return true;
    }

    return false;
}

// =============================================================================
// BOOTSTRAP COMPLETE
// =============================================================================

// Optional: Log bootstrap completion (only in debug mode)
if ($config->get('APP_DEBUG', false)) {
    error_log("[BOOTSTRAP] CIS Base loaded successfully for " . ($_SERVER['REQUEST_URI'] ?? 'CLI'));
}
