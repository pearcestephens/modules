<?php
/**
 * Logging Middleware
 *
 * Logs all requests for debugging and audit trail
 */

namespace App\Middleware;

class LoggingMiddleware
{
    private $logFile;

    public function __construct()
    {
        $this->logFile = __DIR__ . '/../../../_logs/requests.log';

        // Ensure log directory exists
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Handle incoming request
     */
    public function handle($request, $next)
    {
        $startTime = microtime(true);

        // Log request
        $requestData = $this->getRequestData();

        // Continue to next middleware and capture response
        $response = $next($request);

        // Calculate duration
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        // Log response
        $this->logRequest($requestData, $duration);

        return $response;
    }

    /**
     * Get request data
     */
    private function getRequestData()
    {
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'ip' => $this->getClientIp(),
            'user_id' => $_SESSION['user_id'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
        ];
    }

    /**
     * Log request to file
     */
    private function logRequest($data, $duration)
    {
        $logEntry = sprintf(
            "[%s] %s %s | IP: %s | User: %s | Duration: %sms | UA: %s\n",
            $data['timestamp'],
            $data['method'],
            $data['uri'],
            $data['ip'],
            $data['user_id'] ?? 'guest',
            $duration,
            substr($data['user_agent'] ?? '', 0, 100)
        );

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get client IP
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
}
