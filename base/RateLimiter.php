<?php
/**
 * CIS Rate Limiter
 * 
 * Protects endpoints from abuse, brute force attacks, and DDoS
 * Prevents users from hammering APIs or forms too quickly
 * 
 * ============================================================================
 * ✅ WHEN TO USE RATE LIMITING
 * ============================================================================
 * 
 * USE FOR:
 * - Login attempts (prevent brute force)
 * - API endpoints (prevent abuse)
 * - Form submissions (prevent spam)
 * - Password reset requests
 * - Search/filter operations
 * - Resource-heavy operations (reports, exports)
 * - External API calls (to respect their limits)
 * 
 * DON'T USE FOR:
 * - Regular page views (too restrictive)
 * - Static asset loads (CSS, JS, images)
 * - Already-cached operations
 * - Internal system processes
 * 
 * ============================================================================
 * 
 * Usage:
 *   // Check if allowed (throws exception if limit exceeded)
 *   RateLimiter::check('login', 5, 300); // 5 attempts per 5 minutes
 *   
 *   // Check with custom key (per user)
 *   RateLimiter::check('api_call:' . $userId, 60, 60); // 60 per minute
 *   
 *   // Check without throwing exception
 *   if (!RateLimiter::attempt('search', 20, 60)) {
 *       Response::error('Too many searches. Please wait.', 429);
 *   }
 *   
 *   // Reset limit (after successful login, for example)
 *   RateLimiter::reset('login:' . $username);
 *   
 *   // Get remaining attempts
 *   $remaining = RateLimiter::remaining('api_call', 60, 60);
 * 
 * @package CIS\Base
 */

declare(strict_types=1);

namespace CIS\Base;

class RateLimiter
{
    private static bool $initialized = false;
    private static string $storage = 'database'; // 'database' or 'file'
    private static ?string $cacheDir = null;
    
    /**
     * Initialize rate limiter
     */
    public static function init(): void
    {
        if (self::$initialized) return;
        
        self::$storage = $_ENV['RATE_LIMIT_STORAGE'] ?? 'database';
        self::$cacheDir = $_ENV['RATE_LIMIT_CACHE_DIR'] ?? sys_get_temp_dir() . '/cis_rate_limits';
        
        if (self::$storage === 'file' && !is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
        
        self::$initialized = true;
    }
    
    /**
     * Check rate limit (throws exception if exceeded)
     * 
     * @param string $key Unique identifier (e.g., 'login', 'api_call:123')
     * @param int $maxAttempts Maximum number of attempts
     * @param int $decaySeconds Time window in seconds
     * @param string|null $identifier Optional identifier (defaults to IP address)
     * @throws \Exception If rate limit exceeded
     */
    public static function check(
        string $key,
        int $maxAttempts,
        int $decaySeconds,
        ?string $identifier = null
    ): void {
        if (!self::attempt($key, $maxAttempts, $decaySeconds, $identifier)) {
            $retryAfter = self::retryAfter($key, $decaySeconds, $identifier);
            
            // Log security event
            CISLogger::security('rate_limit_exceeded', 'warning', null, [
                'key' => $key,
                'max_attempts' => $maxAttempts,
                'decay_seconds' => $decaySeconds,
                'retry_after' => $retryAfter
            ]);
            
            throw new \Exception(
                "Rate limit exceeded for '$key'. Try again in $retryAfter seconds.",
                429
            );
        }
    }
    
    /**
     * Attempt an action (returns false if limit exceeded, true if allowed)
     * 
     * @param string $key Unique identifier
     * @param int $maxAttempts Maximum number of attempts
     * @param int $decaySeconds Time window in seconds
     * @param string|null $identifier Optional identifier (defaults to IP address)
     * @return bool True if allowed, false if limit exceeded
     */
    public static function attempt(
        string $key,
        int $maxAttempts,
        int $decaySeconds,
        ?string $identifier = null
    ): bool {
        self::init();
        
        $identifier = $identifier ?? self::getIdentifier();
        $fullKey = self::buildKey($key, $identifier);
        
        $data = self::get($fullKey);
        
        $now = time();
        $attempts = $data['attempts'] ?? 0;
        $firstAttempt = $data['first_attempt'] ?? $now;
        $expiresAt = $firstAttempt + $decaySeconds;
        
        // Reset if window expired
        if ($now > $expiresAt) {
            self::set($fullKey, [
                'attempts' => 1,
                'first_attempt' => $now,
                'expires_at' => $now + $decaySeconds
            ], $decaySeconds);
            return true;
        }
        
        // Check if limit exceeded
        if ($attempts >= $maxAttempts) {
            return false;
        }
        
        // Increment attempts
        self::set($fullKey, [
            'attempts' => $attempts + 1,
            'first_attempt' => $firstAttempt,
            'expires_at' => $expiresAt
        ], $decaySeconds);
        
        return true;
    }
    
    /**
     * Get remaining attempts
     */
    public static function remaining(
        string $key,
        int $maxAttempts,
        int $decaySeconds,
        ?string $identifier = null
    ): int {
        self::init();
        
        $identifier = $identifier ?? self::getIdentifier();
        $fullKey = self::buildKey($key, $identifier);
        
        $data = self::get($fullKey);
        
        if (!$data) {
            return $maxAttempts;
        }
        
        $now = time();
        $firstAttempt = $data['first_attempt'] ?? $now;
        $expiresAt = $firstAttempt + $decaySeconds;
        
        // Reset if window expired
        if ($now > $expiresAt) {
            return $maxAttempts;
        }
        
        $attempts = $data['attempts'] ?? 0;
        return max(0, $maxAttempts - $attempts);
    }
    
    /**
     * Get seconds until retry is allowed
     */
    public static function retryAfter(
        string $key,
        int $decaySeconds,
        ?string $identifier = null
    ): int {
        self::init();
        
        $identifier = $identifier ?? self::getIdentifier();
        $fullKey = self::buildKey($key, $identifier);
        
        $data = self::get($fullKey);
        
        if (!$data) {
            return 0;
        }
        
        $now = time();
        $firstAttempt = $data['first_attempt'] ?? $now;
        $expiresAt = $firstAttempt + $decaySeconds;
        
        return max(0, $expiresAt - $now);
    }
    
    /**
     * Reset rate limit for a key
     */
    public static function reset(string $key, ?string $identifier = null): void
    {
        self::init();
        
        $identifier = $identifier ?? self::getIdentifier();
        $fullKey = self::buildKey($key, $identifier);
        
        self::delete($fullKey);
    }
    
    /**
     * Clear all rate limits (admin/maintenance only)
     */
    public static function clearAll(): void
    {
        self::init();
        
        if (self::$storage === 'database') {
            global $con;
            mysqli_query($con, "DELETE FROM cis_rate_limits WHERE expires_at < NOW()");
        } else {
            $files = glob(self::$cacheDir . '/*.json');
            foreach ($files as $file) {
                $data = json_decode(file_get_contents($file), true);
                if ($data && $data['expires_at'] < time()) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * Build full key with identifier
     */
    private static function buildKey(string $key, string $identifier): string
    {
        return 'rate_limit:' . $key . ':' . $identifier;
    }
    
    /**
     * Get identifier (defaults to IP address)
     */
    private static function getIdentifier(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Get rate limit data
     */
    private static function get(string $key): ?array
    {
        if (self::$storage === 'database') {
            return self::getFromDatabase($key);
        }
        return self::getFromFile($key);
    }
    
    /**
     * Set rate limit data
     */
    private static function set(string $key, array $data, int $ttl): void
    {
        if (self::$storage === 'database') {
            self::setToDatabase($key, $data, $ttl);
        } else {
            self::setToFile($key, $data);
        }
    }
    
    /**
     * Delete rate limit data
     */
    private static function delete(string $key): void
    {
        if (self::$storage === 'database') {
            self::deleteFromDatabase($key);
        } else {
            self::deleteFromFile($key);
        }
    }
    
    /**
     * Get from database
     */
    private static function getFromDatabase(string $key): ?array
    {
        global $con;
        
        // Ensure table exists
        self::ensureTableExists();
        
        $stmt = $con->prepare("SELECT data FROM cis_rate_limits WHERE `key` = ? AND expires_at > NOW()");
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if (!$row) {
            return null;
        }
        
        return json_decode($row['data'], true);
    }
    
    /**
     * Set to database
     */
    private static function setToDatabase(string $key, array $data, int $ttl): void
    {
        global $con;
        
        self::ensureTableExists();
        
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
        $dataJson = json_encode($data);
        
        $stmt = $con->prepare("
            INSERT INTO cis_rate_limits (`key`, data, expires_at)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE data = VALUES(data), expires_at = VALUES(expires_at)
        ");
        $stmt->bind_param("sss", $key, $dataJson, $expiresAt);
        $stmt->execute();
    }
    
    /**
     * Delete from database
     */
    private static function deleteFromDatabase(string $key): void
    {
        global $con;
        
        $stmt = $con->prepare("DELETE FROM cis_rate_limits WHERE `key` = ?");
        $stmt->bind_param("s", $key);
        $stmt->execute();
    }
    
    /**
     * Ensure rate limits table exists
     */
    private static function ensureTableExists(): void
    {
        static $checked = false;
        if ($checked) return;
        
        global $con;
        
        $sql = "CREATE TABLE IF NOT EXISTS cis_rate_limits (
            `key` VARCHAR(255) PRIMARY KEY,
            data JSON NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        mysqli_query($con, $sql);
        $checked = true;
    }
    
    /**
     * Get from file cache
     */
    private static function getFromFile(string $key): ?array
    {
        $file = self::$cacheDir . '/' . md5($key) . '.json';
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($file), true);
        
        if (!$data || $data['expires_at'] < time()) {
            unlink($file);
            return null;
        }
        
        return $data;
    }
    
    /**
     * Set to file cache
     */
    private static function setToFile(string $key, array $data): void
    {
        $file = self::$cacheDir . '/' . md5($key) . '.json';
        file_put_contents($file, json_encode($data));
    }
    
    /**
     * Delete from file cache
     */
    private static function deleteFromFile(string $key): void
    {
        $file = self::$cacheDir . '/' . md5($key) . '.json';
        if (file_exists($file)) {
            unlink($file);
        }
    }
}
