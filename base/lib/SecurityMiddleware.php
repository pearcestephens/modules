<?php
/**
 * Security Middleware
 * 
 * Hardens the application with security headers, CSRF protection, rate limiting, etc.
 */

namespace CIS\Base;

class SecurityMiddleware {
    private static $initialized = false;
    private static $csrfToken = null;
    
    /**
     * Initialize security middleware
     */
    public static function init(): void {
        if (self::$initialized) {
            return;
        }
        
        self::setSecurityHeaders();
        self::initializeCSRF();
        self::preventClickjacking();
        self::sanitizeInputs();
        
        self::$initialized = true;
    }
    
    /**
     * Set comprehensive security headers
     */
    private static function setSecurityHeaders(): void {
        if (headers_sent()) {
            return;
        }
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // XSS Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Clickjacking protection
        header('X-Frame-Options: SAMEORIGIN');
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy (adjust as needed)
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
               "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
               "img-src 'self' data: https:; " .
               "font-src 'self' data: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
               "connect-src 'self'; " .
               "frame-ancestors 'self';";
        header("Content-Security-Policy: $csp");
        
        // HTTPS enforcement (if on HTTPS)
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Permissions policy
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
    
    /**
     * Initialize CSRF protection
     */
    private static function initializeCSRF(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        
        // Generate CSRF token if not exists
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        
        // Regenerate token every hour
        if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 3600) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        
        self::$csrfToken = $_SESSION['csrf_token'];
    }
    
    /**
     * Get CSRF token
     */
    public static function getCSRFToken(): string {
        return self::$csrfToken ?? '';
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRF(string $token): bool {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Require valid CSRF token for POST/PUT/DELETE requests
     */
    public static function requireCSRF(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return; // CSRF only for state-changing methods
        }
        
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        
        if (!self::validateCSRF($token)) {
            http_response_code(403);
            die('CSRF token validation failed');
        }
    }
    
    /**
     * Prevent clickjacking attacks
     */
    private static function preventClickjacking(): void {
        // Already handled in headers, but also check for suspicious iframe requests
        if (isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'iframe') {
            // Allow same-origin iframes only
            if (!isset($_SERVER['HTTP_SEC_FETCH_SITE']) || $_SERVER['HTTP_SEC_FETCH_SITE'] !== 'same-origin') {
                http_response_code(403);
                die('Embedding not allowed');
            }
        }
    }
    
    /**
     * Sanitize all input superglobals
     */
    private static function sanitizeInputs(): void {
        // Note: This is for logging/display purposes. Always use prepared statements for DB!
        array_walk_recursive($_GET, function(&$value) {
            if (is_string($value)) {
                // Remove null bytes
                $value = str_replace("\0", '', $value);
                // Trim whitespace
                $value = trim($value);
            }
        });
        
        array_walk_recursive($_POST, function(&$value) {
            if (is_string($value)) {
                $value = str_replace("\0", '', $value);
                $value = trim($value);
            }
        });
        
        // Log suspicious activity
        self::detectSuspiciousActivity();
    }
    
    /**
     * Detect and log suspicious activity
     */
    private static function detectSuspiciousActivity(): void {
        $suspicious_patterns = [
            'union.*select',
            'drop.*table',
            'insert.*into',
            'delete.*from',
            '<script',
            'javascript:',
            'onerror=',
            'onload=',
            '../',
            '..\\',
            '/etc/passwd',
            'cmd.exe',
            'powershell'
        ];
        
        $input = strtolower(serialize($_GET) . serialize($_POST) . serialize($_SERVER['REQUEST_URI'] ?? ''));
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match('/' . preg_quote($pattern, '/') . '/i', $input)) {
                error_log(sprintf(
                    "[SECURITY] Suspicious activity detected from %s: Pattern '%s' in request to %s",
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    $pattern,
                    $_SERVER['REQUEST_URI'] ?? 'unknown'
                ));
                
                // Optional: Block the request
                // http_response_code(403);
                // die('Suspicious activity detected');
                break;
            }
        }
    }
    
    /**
     * Rate limit check (simple implementation)
     */
    public static function checkRateLimit(string $key, int $maxRequests = 60, int $window = 60): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return true; // Can't check without session
        }
        
        $rateLimitKey = 'rate_limit_' . md5($key);
        
        if (!isset($_SESSION[$rateLimitKey])) {
            $_SESSION[$rateLimitKey] = [
                'count' => 1,
                'start' => time()
            ];
            return true;
        }
        
        $data = $_SESSION[$rateLimitKey];
        
        // Reset if window expired
        if (time() - $data['start'] > $window) {
            $_SESSION[$rateLimitKey] = [
                'count' => 1,
                'start' => time()
            ];
            return true;
        }
        
        // Increment counter
        $_SESSION[$rateLimitKey]['count']++;
        
        // Check if exceeded
        if ($_SESSION[$rateLimitKey]['count'] > $maxRequests) {
            error_log(sprintf(
                "[SECURITY] Rate limit exceeded for key '%s' from %s",
                $key,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ));
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate IP address is not from blacklist
     */
    public static function checkIPBlacklist(): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Check against known bad IP ranges (example)
        $blacklisted_ranges = [
            // Add blacklisted IP ranges here
            // '192.168.1.0/24',
        ];
        
        foreach ($blacklisted_ranges as $range) {
            if (self::ipInRange($ip, $range)) {
                error_log("[SECURITY] Blocked request from blacklisted IP: $ip");
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if IP is in range
     */
    private static function ipInRange(string $ip, string $range): bool {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        list($subnet, $mask) = explode('/', $range);
        
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask_long = -1 << (32 - (int)$mask);
        $subnet_long &= $mask_long;
        
        return ($ip_long & $mask_long) == $subnet_long;
    }
    
    /**
     * Generate secure random token
     */
    public static function generateToken(int $length = 32): string {
        return bin2hex(random_bytes($length));
    }
}
