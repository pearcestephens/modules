<?php

/**
 * System Access Logging Middleware
 *
 * Tracks all system access for Digital Twin behavioral profiling.
 * Captures logins, page views, actions, patterns, and anomalies.
 *
 * @package FraudDetection\Middleware
 * @version 1.0.0
 */

namespace FraudDetection\Middleware;

use PDO;
use Exception;

class SystemAccessLogger
{
    private PDO $pdo;
    private array $config;
    private ?int $staffId = null;
    private ?string $sessionId = null;
    private array $excludedPaths = [];
    private bool $enabled = true;

    /**
     * Action types for categorization
     */
    private const ACTION_TYPES = [
        'login' => ['login', 'signin', 'authenticate'],
        'logout' => ['logout', 'signout'],
        'view' => ['view', 'show', 'display', 'list', 'index'],
        'create' => ['create', 'add', 'new', 'insert'],
        'update' => ['update', 'edit', 'modify', 'change'],
        'delete' => ['delete', 'remove', 'destroy'],
        'export' => ['export', 'download', 'pdf', 'csv', 'excel'],
        'import' => ['import', 'upload', 'bulk'],
        'search' => ['search', 'filter', 'query'],
        'report' => ['report', 'analytics', 'dashboard']
    ];

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->enabled = $config['enabled'] ?? true;
        $this->excludedPaths = $config['excluded_paths'] ?? [
            '/assets/',
            '/css/',
            '/js/',
            '/images/',
            '/favicon.ico',
            '/health',
            '/ping'
        ];
    }

    /**
     * Log system access (main entry point)
     *
     * @param array $request Request data
     * @return array Access log entry
     */
    public function logAccess(array $request = []): array
    {
        if (!$this->enabled) {
            return ['logged' => false, 'reason' => 'disabled'];
        }

        // Build request data if not provided
        if (empty($request)) {
            $request = $this->captureRequest();
        }

        // Check if path should be excluded
        if ($this->shouldExclude($request['path'])) {
            return ['logged' => false, 'reason' => 'excluded_path'];
        }

        try {
            // Extract staff ID from session
            $this->staffId = $this->getStaffIdFromSession();
            $this->sessionId = session_id() ?: $this->generateSessionId();

            // Categorize the action
            $actionType = $this->categorizeAction($request);

            // Build log entry
            $logEntry = [
                'staff_id' => $this->staffId,
                'session_id' => $this->sessionId,
                'action_type' => $actionType,
                'path' => $request['path'],
                'method' => $request['method'],
                'query_params' => json_encode($request['query_params']),
                'post_params' => $this->sanitizePostParams($request['post_params']),
                'ip_address' => $request['ip_address'],
                'user_agent' => $request['user_agent'],
                'referer' => $request['referer'],
                'response_time' => $request['response_time'] ?? null,
                'response_code' => $request['response_code'] ?? null,
                'accessed_at' => date('Y-m-d H:i:s')
            ];

            // Store in database
            $logId = $this->storeAccessLog($logEntry);

            // Check for suspicious patterns
            if ($this->staffId) {
                $this->checkForAnomalies($this->staffId, $logEntry);
            }

            return [
                'logged' => true,
                'log_id' => $logId,
                'action_type' => $actionType
            ];
        } catch (Exception $e) {
            error_log("System access logging failed: " . $e->getMessage());
            return [
                'logged' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Capture current request data
     *
     * @return array
     */
    private function captureRequest(): array
    {
        return [
            'path' => $_SERVER['REQUEST_URI'] ?? '/',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'query_params' => $_GET ?? [],
            'post_params' => $_POST ?? [],
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'response_time' => null,
            'response_code' => http_response_code()
        ];
    }

    /**
     * Get client IP address (handles proxies)
     *
     * @return string
     */
    private function getClientIp(): string
    {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',    // Cloudflare
            'HTTP_X_FORWARDED_FOR',     // Standard proxy
            'HTTP_X_REAL_IP',           // Nginx proxy
            'REMOTE_ADDR'               // Direct connection
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (take first one)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Get staff ID from session
     *
     * @return int|null
     */
    private function getStaffIdFromSession(): ?int
    {
        // Try various session keys
        $possibleKeys = ['staff_id', 'user_id', 'id', 'userId'];

        foreach ($possibleKeys as $key) {
            if (isset($_SESSION[$key]) && is_numeric($_SESSION[$key])) {
                return (int)$_SESSION[$key];
            }
        }

        // Try extracting from JWT if present
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
            return $this->extractStaffIdFromJwt($token);
        }

        return null;
    }

    /**
     * Extract staff ID from JWT token
     *
     * @param string $token
     * @return int|null
     */
    private function extractStaffIdFromJwt(string $token): ?int
    {
        try {
            // Simple JWT decode (payload only, no verification)
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }

            $payload = json_decode(base64_decode($parts[1]), true);
            return $payload['staff_id'] ?? $payload['sub'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Generate session ID if none exists
     *
     * @return string
     */
    private function generateSessionId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Check if path should be excluded from logging
     *
     * @param string $path
     * @return bool
     */
    private function shouldExclude(string $path): bool
    {
        foreach ($this->excludedPaths as $excluded) {
            if (strpos($path, $excluded) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Categorize action based on path and method
     *
     * @param array $request
     * @return string
     */
    private function categorizeAction(array $request): string
    {
        $path = strtolower($request['path']);
        $method = strtoupper($request['method']);

        // Check each action type pattern
        foreach (self::ACTION_TYPES as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($path, $keyword) !== false) {
                    return $type;
                }
            }
        }

        // Fallback to HTTP method mapping
        return match ($method) {
            'GET' => 'view',
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'other'
        };
    }

    /**
     * Sanitize POST params (remove sensitive data)
     *
     * @param array $params
     * @return string
     */
    private function sanitizePostParams(array $params): string
    {
        $sensitiveKeys = [
            'password',
            'passwd',
            'pwd',
            'secret',
            'token',
            'api_key',
            'credit_card',
            'cvv',
            'ssn'
        ];

        $sanitized = $params;
        foreach ($sanitized as $key => $value) {
            $lowerKey = strtolower($key);
            foreach ($sensitiveKeys as $sensitive) {
                if (strpos($lowerKey, $sensitive) !== false) {
                    $sanitized[$key] = '[REDACTED]';
                    break;
                }
            }
        }

        return json_encode($sanitized);
    }

    /**
     * Store access log in database
     *
     * @param array $logEntry
     * @return int Log ID
     */
    private function storeAccessLog(array $logEntry): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO system_access_log
            (staff_id, session_id, action_type, path, method, query_params,
             post_params, ip_address, user_agent, referer, response_time,
             response_code, accessed_at)
            VALUES
            (:staff_id, :session_id, :action_type, :path, :method, :query_params,
             :post_params, :ip_address, :user_agent, :referer, :response_time,
             :response_code, :accessed_at)
        ");

        $stmt->execute($logEntry);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Check for suspicious patterns and anomalies
     *
     * @param int $staffId
     * @param array $logEntry
     */
    private function checkForAnomalies(int $staffId, array $logEntry): void
    {
        $anomalies = [];

        // Check 1: High frequency access (potential bot/scraping)
        if ($this->detectHighFrequency($staffId)) {
            $anomalies[] = 'high_frequency_access';
        }

        // Check 2: Unusual time (outside business hours)
        if ($this->detectUnusualTime()) {
            $anomalies[] = 'unusual_time_access';
        }

        // Check 3: New IP address
        if ($this->detectNewIpAddress($staffId, $logEntry['ip_address'])) {
            $anomalies[] = 'new_ip_address';
        }

        // Check 4: Sensitive resource access
        if ($this->detectSensitiveAccess($logEntry['path'])) {
            $anomalies[] = 'sensitive_resource_access';
        }

        // Check 5: Rapid page traversal
        if ($this->detectRapidTraversal($staffId)) {
            $anomalies[] = 'rapid_page_traversal';
        }

        // Store anomalies if detected
        if (!empty($anomalies)) {
            $this->recordAnomaly($staffId, $anomalies, $logEntry);
        }
    }

    /**
     * Detect high frequency access
     *
     * @param int $staffId
     * @return bool
     */
    private function detectHighFrequency(int $staffId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM system_access_log
                WHERE staff_id = :staff_id
                AND accessed_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
            ");
            $stmt->execute(['staff_id' => $staffId]);
            $count = $stmt->fetchColumn();

            // More than 30 requests per minute
            return $count > 30;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Detect unusual time access
     *
     * @return bool
     */
    private function detectUnusualTime(): bool
    {
        $hour = (int)date('G');
        // Flag access outside 6 AM - 10 PM
        return $hour < 6 || $hour >= 22;
    }

    /**
     * Detect new IP address
     *
     * @param int $staffId
     * @param string $ipAddress
     * @return bool
     */
    private function detectNewIpAddress(int $staffId, string $ipAddress): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM system_access_log
                WHERE staff_id = :staff_id
                AND ip_address = :ip_address
                AND accessed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute([
                'staff_id' => $staffId,
                'ip_address' => $ipAddress
            ]);
            $count = $stmt->fetchColumn();

            // IP not seen in last 30 days
            return $count === 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Detect sensitive resource access
     *
     * @param string $path
     * @return bool
     */
    private function detectSensitiveAccess(string $path): bool
    {
        $sensitivePatterns = [
            '/admin/',
            '/reports/',
            '/finance/',
            '/export/',
            '/download/',
            '/api/users',
            '/api/staff'
        ];

        foreach ($sensitivePatterns as $pattern) {
            if (strpos($path, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect rapid page traversal
     *
     * @param int $staffId
     * @return bool
     */
    private function detectRapidTraversal(int $staffId): bool
    {
        try {
            // Get distinct pages accessed in last 2 minutes
            $stmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT path)
                FROM system_access_log
                WHERE staff_id = :staff_id
                AND accessed_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
            ");
            $stmt->execute(['staff_id' => $staffId]);
            $distinctPages = $stmt->fetchColumn();

            // More than 15 different pages in 2 minutes
            return $distinctPages > 15;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Record detected anomaly
     *
     * @param int $staffId
     * @param array $anomalies
     * @param array $logEntry
     */
    private function recordAnomaly(int $staffId, array $anomalies, array $logEntry): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO access_anomalies
                (staff_id, anomaly_types, severity, log_entry, detected_at)
                VALUES (:staff_id, :anomaly_types, :severity, :log_entry, NOW())
            ");

            // Determine severity based on anomaly types
            $severity = $this->calculateSeverity($anomalies);

            $stmt->execute([
                'staff_id' => $staffId,
                'anomaly_types' => json_encode($anomalies),
                'severity' => $severity,
                'log_entry' => json_encode($logEntry)
            ]);

            // Trigger fraud analysis for high severity
            if ($severity === 'high' || $severity === 'critical') {
                $this->triggerFraudAnalysis($staffId, $anomalies, $logEntry);
            }
        } catch (Exception $e) {
            error_log("Failed to record anomaly: " . $e->getMessage());
        }
    }

    /**
     * Calculate severity based on anomaly types
     *
     * @param array $anomalies
     * @return string
     */
    private function calculateSeverity(array $anomalies): string
    {
        $highSeverityTypes = ['sensitive_resource_access', 'new_ip_address'];
        $mediumSeverityTypes = ['unusual_time_access', 'rapid_page_traversal'];

        foreach ($anomalies as $anomaly) {
            if (in_array($anomaly, $highSeverityTypes)) {
                return 'high';
            }
        }

        foreach ($anomalies as $anomaly) {
            if (in_array($anomaly, $mediumSeverityTypes)) {
                return 'medium';
            }
        }

        return 'low';
    }

    /**
     * Trigger fraud analysis
     *
     * @param int $staffId
     * @param array $anomalies
     * @param array $logEntry
     */
    private function triggerFraudAnalysis(int $staffId, array $anomalies, array $logEntry): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO fraud_analysis_queue
                (staff_id, trigger_source, trigger_data, priority, created_at)
                VALUES (:staff_id, 'system_access_anomaly', :trigger_data, 'high', NOW())
            ");
            $stmt->execute([
                'staff_id' => $staffId,
                'trigger_data' => json_encode([
                    'anomalies' => $anomalies,
                    'log_entry' => $logEntry
                ])
            ]);
        } catch (Exception $e) {
            error_log("Failed to trigger fraud analysis: " . $e->getMessage());
        }
    }

    /**
     * Get access statistics for a staff member
     *
     * @param int $staffId
     * @param int $days Number of days to analyze
     * @return array
     */
    public function getAccessStats(int $staffId, int $days = 30): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_accesses,
                    COUNT(DISTINCT DATE(accessed_at)) as active_days,
                    COUNT(DISTINCT session_id) as total_sessions,
                    COUNT(DISTINCT ip_address) as unique_ips,
                    AVG(response_time) as avg_response_time,
                    action_type,
                    COUNT(*) as action_count
                FROM system_access_log
                WHERE staff_id = :staff_id
                AND accessed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY action_type
            ");
            $stmt->execute([
                'staff_id' => $staffId,
                'days' => $days
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to get access stats: " . $e->getMessage());
            return [];
        }
    }
}
