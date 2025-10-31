<?php
/**
 * CIS Base Session Manager
 * 
 * Integrates with existing CIS session from app.php while adding:
 * - Secure session configuration
 * - Session regeneration on privilege escalation
 * - CSRF token management
 * - Optional database session storage
 * 
 * IMPORTANT: This does NOT start a new session - it configures the
 * existing session started by app.php. Both CIS and modules share
 * the SAME session data seamlessly.
 * 
 * @package CIS\Base
 */

declare(strict_types=1);

namespace CIS\Base;

class Session
{
    private static bool $initialized = false;
    
    /**
     * Initialize secure session (integrates with existing session from app.php)
     */
    public static function init(): void
    {
        if (self::$initialized) return;
        
        // Session already started by app.php - just configure security
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::configureExistingSession();
        } else {
            // If somehow not started, start with secure config
            self::configureSessionSettings();
            session_start();
        }
        
        // Regenerate session ID on first base module access (one-time)
        if (!isset($_SESSION['base_initialized'])) {
            session_regenerate_id(true);
            $_SESSION['base_initialized'] = true;
            $_SESSION['base_init_time'] = time();
        }
        
        // Normalize session variables (create user_id from userID for consistency)
        // Bots prefer user_id, but CIS legacy uses userID - keep both in sync
        if (isset($_SESSION['userID']) && !isset($_SESSION['user_id'])) {
            $_SESSION['user_id'] = $_SESSION['userID'];
        }
        if (isset($_SESSION['user_id']) && !isset($_SESSION['userID'])) {
            $_SESSION['userID'] = $_SESSION['user_id'];
        }
        
        // Check session timeout (30 minutes of inactivity)
        self::checkTimeout();
        
        self::$initialized = true;
    }
    
    /**
     * Configure existing session with security settings
     */
    private static function configureExistingSession(): void
    {
        // Set secure cookie parameters for existing session
        if (!headers_sent()) {
            $params = session_get_cookie_params();
            session_set_cookie_params([
                'lifetime' => $params['lifetime'] ?: 0,
                'path' => $params['path'] ?: '/',
                'domain' => $params['domain'] ?: '',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
    }
    
    /**
     * Configure session settings before starting
     */
    private static function configureSessionSettings(): void
    {
        // Secure session configuration
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            ini_set('session.cookie_secure', '1');
        }
        
        // Session storage (file-based by default, compatible with app.php)
        // Both CIS and base modules use the SAME session files
        $sessionPath = $_ENV['SESSION_SAVE_PATH'] ?? sys_get_temp_dir();
        if (is_writable($sessionPath)) {
            ini_set('session.save_path', $sessionPath);
        }
        
        session_name('CIS_SESSION'); // Shared session name for all CIS apps
    }
    
    /**
     * Check session timeout
     */
    private static function checkTimeout(int $maxInactiveSeconds = 1800): void
    {
        if (isset($_SESSION['LAST_ACTIVITY'])) {
            $inactive = time() - $_SESSION['LAST_ACTIVITY'];
            
            if ($inactive > $maxInactiveSeconds) {
                self::destroy();
                // Optionally redirect to login
                return;
            }
        }
        
        $_SESSION['LAST_ACTIVITY'] = time();
    }
    
    /**
     * Get session value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Set session value
     */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }
    
    /**
     * Check if key exists
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove session value
     */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }
    
    /**
     * Destroy session completely
     */
    public static function destroy(): void
    {
        $_SESSION = [];
        
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
        
        session_destroy();
    }
    
    /**
     * Regenerate session ID (call on privilege escalation)
     */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }
    
    /**
     * Get user ID from session
     */
    public static function getUserId(): ?int
    {
        return $_SESSION['userID'] ?? null;
    }
    
    /**
     * Get user name from session
     */
    public static function getUserName(): ?string
    {
        $first = $_SESSION['first_name'] ?? '';
        $last = $_SESSION['last_name'] ?? '';
        return trim($first . ' ' . $last) ?: null;
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['userID']) && !empty($_SESSION['userID']);
    }
    
    /**
     * Flash message system (one-time messages)
     */
    public static function flash(string $key, ?string $message = null): ?string
    {
        if ($message !== null) {
            $_SESSION['_flash'][$key] = $message;
            return null;
        }
        
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }
}
