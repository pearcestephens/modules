<?php
/**
 * Rate Limiting Middleware
 *
 * Prevents abuse by limiting request frequency
 */

namespace App\Middleware;

class RateLimitMiddleware
{
    private $maxRequests = 60; // Max requests per window
    private $windowSeconds = 60; // Time window in seconds

    /**
     * Handle incoming request
     */
    public function handle($request, $next)
    {
        $identifier = $this->getIdentifier();
        $key = "ratelimit:{$identifier}";

        // Get current count
        $data = $this->getData($key);

        // Check if limit exceeded
        if ($data['count'] >= $this->maxRequests) {
            $retryAfter = $data['reset_at'] - time();

            // Log rate limit violation
            error_log(sprintf(
                "[RATE LIMIT] Identifier: %s | IP: %s | URI: %s | Retry After: %ds",
                $identifier,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['REQUEST_URI'] ?? 'unknown',
                $retryAfter
            ));

            // Set headers before throwing exception
            header("Retry-After: {$retryAfter}");
            header("X-RateLimit-Limit: {$this->maxRequests}");
            header("X-RateLimit-Remaining: 0");
            header("X-RateLimit-Reset: {$data['reset_at']}");

            // Throw exception with 429 code (will be caught by bootstrap error handler)
            $exception = new \Exception('Rate limit exceeded. Please try again later.', 429);
            throw $exception;
        }

        // Increment counter
        $this->increment($key, $data);

        // Add headers
        $remaining = $this->maxRequests - $data['count'] - 1;
        header("X-RateLimit-Limit: {$this->maxRequests}");
        header("X-RateLimit-Remaining: {$remaining}");
        header("X-RateLimit-Reset: {$data['reset_at']}");

        // Continue to next middleware
        return $next($request);
    }

    /**
     * Get unique identifier for this client
     */
    private function getIdentifier()
    {
        // Use user ID if authenticated
        if (isset($_SESSION['user_id'])) {
            return 'user:' . $_SESSION['user_id'];
        }

        // Fall back to IP address
        return 'ip:' . $this->getClientIp();
    }

    /**
     * Get client IP address
     */
    private function getClientIp()
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get rate limit data
     */
    private function getData($key)
    {
        $filename = sys_get_temp_dir() . '/' . md5($key) . '.json';

        if (file_exists($filename)) {
            $data = json_decode(file_get_contents($filename), true);

            // Check if window has expired
            if ($data['reset_at'] > time()) {
                return $data;
            }
        }

        // Create new window
        return [
            'count' => 0,
            'reset_at' => time() + $this->windowSeconds
        ];
    }

    /**
     * Increment counter
     */
    private function increment($key, $data)
    {
        $data['count']++;

        $filename = sys_get_temp_dir() . '/' . md5($key) . '.json';
        file_put_contents($filename, json_encode($data));
    }

    /**
     * Check if request is AJAX
     */
    private function isAjaxRequest()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
