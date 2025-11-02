<?php declare(strict_types=1);

namespace Consignments\Infra\Http;

/**
 * HTTP Security Helper
 *
 * Provides security utilities for request handling:
 * - Path traversal prevention
 * - CSRF token validation
 * - Input sanitization
 * - Security headers
 */
final class Security
{
    /**
     * Validate and normalize file path to prevent traversal attacks
     *
     * @param string $path User-provided path (relative or absolute)
     * @param string $baseDir Base directory to restrict access to
     * @param array<string> $allowedExtensions Whitelist of file extensions
     * @return string Safe normalized path
     * @throws \InvalidArgumentException if path is unsafe
     */
    public static function securePath(
        string $path,
        string $baseDir,
        array $allowedExtensions = []
    ): string {
        // Reject obvious traversal attempts
        if (str_contains($path, '..')) {
            throw new \InvalidArgumentException('Path traversal detected: ".." not allowed');
        }

        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Null byte in path not allowed');
        }

        // Normalize path
        $normalizedPath = str_replace(['\\', '/./'], ['/', '/'], $path);
        $normalizedPath = preg_replace('#/+#', '/', $normalizedPath);
        $normalizedPath = ltrim($normalizedPath, '/');

        // Build full path
        $fullPath = rtrim($baseDir, '/') . '/' . $normalizedPath;

        // Resolve base directory to canonical path
        $realBase = realpath($baseDir);

        if (!$realBase) {
            throw new \InvalidArgumentException('Invalid base directory');
        }

        // For the target path, check if it exists; if not, check its parent
        if (file_exists($fullPath)) {
            $realPath = realpath($fullPath);
        } else {
            // If file doesn't exist yet, check parent directory exists within base
            $parentDir = dirname($fullPath);
            if (file_exists($parentDir)) {
                $realPath = realpath($parentDir);
            } else {
                // Parent doesn't exist - still validate the constructed path
                $realPath = $realBase . '/' . dirname($normalizedPath);
            }
        }

        if (!$realPath) {
            throw new \InvalidArgumentException('Invalid path');
        }

        // Ensure resolved path is within base directory
        if (!str_starts_with($realPath . '/', $realBase . '/') && $realPath !== $realBase) {
            throw new \InvalidArgumentException('Path outside allowed directory');
        }

        // Check file extension if whitelist provided
        if (!empty($allowedExtensions)) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExtensions, true)) {
                throw new \InvalidArgumentException(
                    sprintf('File extension ".%s" not allowed. Allowed: %s',
                        $ext,
                        implode(', ', $allowedExtensions)
                    )
                );
            }
        }

        return $fullPath;
    }

    /**
     * Verify CSRF token from request
     *
     * @param string $token Token from request
     * @param string|null $sessionToken Token from session (if null, uses $_SESSION)
     * @throws \RuntimeException if CSRF validation fails
     */
    public static function verifyCsrf(string $token, ?string $sessionToken = null): void
    {
        $sessionToken = $sessionToken ?? ($_SESSION['csrf_token'] ?? '');

        if ($sessionToken === '') {
            throw new \RuntimeException('CSRF token not found in session');
        }

        if ($token === '') {
            throw new \RuntimeException('CSRF token missing from request');
        }

        if (!hash_equals($sessionToken, $token)) {
            throw new \RuntimeException('CSRF token validation failed');
        }
    }

    /**
     * Generate CSRF token for session
     *
     * @return string CSRF token (stored in $_SESSION['csrf_token'])
     */
    public static function generateCsrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('Session not started');
        }

        $length = (int)($_ENV['CSRF_TOKEN_LENGTH'] ?? 32);
        $token = bin2hex(random_bytes($length));

        $_SESSION['csrf_token'] = $token;

        return $token;
    }

    /**
     * Get current CSRF token (generate if not exists)
     */
    public static function getCsrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('Session not started');
        }

        return $_SESSION['csrf_token'] ?? self::generateCsrfToken();
    }

    /**
     * Sanitize HTML output (prevent XSS)
     */
    public static function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sanitize for JavaScript context
     */
    public static function escapeJs(string $value): string
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    /**
     * Set security headers for HTTP response
     */
    public static function setSecurityHeaders(): void
    {
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');

        // Enable XSS filter in browsers
        header('X-XSS-Protection: 1; mode=block');

        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');

        // Content Security Policy (adjust as needed)
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'");

        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // HSTS (HTTPS only)
        if (($_SERVER['HTTPS'] ?? '') === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Validate request method
     *
     * @param string $expected Expected HTTP method (GET, POST, etc.)
     * @param int $errorCode HTTP error code to return on mismatch
     * @throws \RuntimeException if method doesn't match
     */
    public static function requireMethod(string $expected, int $errorCode = 405): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method !== strtoupper($expected)) {
            http_response_code($errorCode);
            throw new \RuntimeException(
                sprintf('Method %s required, got %s', $expected, $method)
            );
        }
    }

    /**
     * Rate limit check (simple token bucket)
     *
     * @param string $key Unique identifier (user ID, IP, etc.)
     * @param int $maxRequests Maximum requests per window
     * @param int $windowSeconds Time window in seconds
     * @return bool True if allowed, false if rate limit exceeded
     */
    public static function checkRateLimit(
        string $key,
        int $maxRequests = 60,
        int $windowSeconds = 60
    ): bool {
        // This is a simple implementation using session
        // For production, use Redis or database
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return true; // No session, allow (fail open)
        }

        $now = time();
        $bucketKey = 'rate_limit_' . $key;

        if (!isset($_SESSION[$bucketKey])) {
            $_SESSION[$bucketKey] = [
                'count' => 1,
                'reset_at' => $now + $windowSeconds
            ];
            return true;
        }

        $bucket = $_SESSION[$bucketKey];

        // Reset if window expired
        if ($now >= $bucket['reset_at']) {
            $_SESSION[$bucketKey] = [
                'count' => 1,
                'reset_at' => $now + $windowSeconds
            ];
            return true;
        }

        // Increment and check limit
        $bucket['count']++;
        $_SESSION[$bucketKey] = $bucket;

        return $bucket['count'] <= $maxRequests;
    }
}
