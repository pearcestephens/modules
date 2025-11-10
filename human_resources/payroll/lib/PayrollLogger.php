<?php
declare(strict_types=1);

namespace PayrollModule\Lib;

/**
 * Payroll Logger
 *
 * Structured logging system that writes to payroll_activity_log table
 * Extends CIS Logger functionality with payroll-specific features:
 * - Request ID tracking
 * - Performance metrics
 * - Context snapshots
 * - Log level filtering
 *
 * @package PayrollModule\Lib
 * @version 1.0.0
 */

use PDO;
use PDOException;

class PayrollLogger
{
    // DB handle is optional; when unavailable we fallback to file logging only
    private ?PDO $db = null;
    private ?string $requestId = null;
    private ?int $userId = null;
    private array $context = [];

    // Log levels (PSR-3 compatible)
    const EMERGENCY = 'emergency';
    const ALERT     = 'alert';
    const CRITICAL  = 'critical';
    const ERROR     = 'error';
    const WARNING   = 'warning';
    const NOTICE    = 'notice';
    const INFO      = 'info';
    const DEBUG     = 'debug';

    private array $levelPriority = [
        'emergency' => 800,
        'alert'     => 700,
        'critical'  => 600,
        'error'     => 500,
        'warning'   => 400,
        'notice'    => 300,
        'info'      => 200,
        'debug'     => 100
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        // Never let logger construction break requests
        try {
            $this->initializeDatabase();
        } catch (\Throwable $e) {
            // Swallow to ensure logger never causes a 500; we will file-log instead
            $this->db = null;
            error_log('PayrollLogger bootstrap fallback: ' . $e->getMessage());
        }

        $this->requestId = $this->generateRequestId();

        // Get current user if in session (support both legacy and CIS keys)
        if (isset($_SESSION['userID'])) {
            $this->userId = (int)$_SESSION['userID'];
        } elseif (isset($_SESSION['userID'])) {
            $this->userId = (int)$_SESSION['userID'];
        }
    }

    /**
     * Initialize database connection
     */
    private function initializeDatabase(): void
    {
        // Prefer reusing the module's DB connection if available
        if (function_exists('getPayrollDb')) {
            try {
                $this->db = getPayrollDb();
                return;
            } catch (\Throwable $e) {
                // Continue to env-based fallback
                error_log('PayrollLogger: getPayrollDb() reuse failed - ' . $e->getMessage());
            }
        }

        // Env-based fallback (do not throw if it fails)
        try {
            $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $dbname = $_ENV['DB_NAME'] ?? 'jcepnzzkmj';
            $username = $_ENV['DB_USER'] ?? 'jcepnzzkmj';
            $password = $_ENV['DB_PASSWORD'] ?? ($_ENV['DB_PASS'] ?? '');

            if ($password === '') {
                // No credentials available; operate in file-only mode
                $this->db = null;
                return;
            }

            $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

            $this->db = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);

        } catch (PDOException $e) {
            // Fallback to file logging only
            $this->db = null;
            error_log('PayrollLogger: Database connection failed - ' . $e->getMessage());
        }
    }

    /**
     * Generate unique request ID
     */
    private function generateRequestId(): string
    {
        return 'req_' . uniqid() . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Set request ID (useful for tracking across services)
     */
    public function setRequestId(string $requestId): void
    {
        $this->requestId = $requestId;
    }

    /**
     * Get current request ID
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * Set user ID for logging
     */
    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    /**
     * Set context data (merged with each log)
     */
    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    /**
     * Add context data
     */
    public function addContext(string $key, $value): void
    {
        $this->context[$key] = $value;
    }

    // ========================================================================
    // PSR-3 LOG LEVEL METHODS
    // ========================================================================

    /**
     * System is unusable
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }

    /**
     * Critical conditions
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * Normal but significant events
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }

    /**
     * Interesting events
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * Detailed debug information
     */
    public function debug(string $message, array $context = []): void
    {
        // Only log debug in development
        if (($_ENV['APP_ENV'] ?? 'production') !== 'production') {
            $this->log(self::DEBUG, $message, $context);
        }
    }

    // ========================================================================
    // CORE LOGGING METHOD
    // ========================================================================

    /**
     * Log a message at specified level
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public function log(string $level, string $message, array $context = []): void
    {
        // Merge with persistent context
        $mergedContext = array_merge($this->context, $context);

        // Add system context
        $mergedContext['request_id'] = $this->requestId;
        $mergedContext['user_id'] = $this->userId;
        $mergedContext['ip_address'] = $this->getClientIp();
        $mergedContext['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $mergedContext['url'] = $_SERVER['REQUEST_URI'] ?? null;
        $mergedContext['method'] = $_SERVER['REQUEST_METHOD'] ?? null;

        // Try DB insert if available; otherwise, file-only
        if ($this->db instanceof PDO) {
            try {
                // Conservative column set to match existing schema (no 'module' column)
                $sql = "INSERT INTO payroll_activity_log (
                            user_id, action, message, log_level,
                            context_data, ip_address, user_agent, created_at
                        ) VALUES (
                            :user_id, :action, :message, :log_level,
                            :context_data, :ip_address, :user_agent, NOW()
                        )";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':user_id' => $this->userId,
                    ':action' => $this->extractAction($mergedContext),
                    ':message' => $message,
                    ':log_level' => $level,
                    ':context_data' => json_encode($mergedContext, JSON_UNESCAPED_UNICODE),
                    ':ip_address' => $this->getClientIp(),
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);
            } catch (PDOException $e) {
                // Downgrade to file log if DB write fails
                error_log('PayrollLogger: DB write failed - ' . $e->getMessage());
                $this->logToFile($level, $message, $mergedContext);
                return;
            }
        } else {
            $this->logToFile($level, $message, $mergedContext);
            return;
        }

        // Also log to file for critical errors
        if ($this->levelPriority[$level] >= $this->levelPriority[self::ERROR]) {
            $this->logToFile($level, $message, $mergedContext);
        }
    }

    /**
     * Extract action from context or message
     */
    private function extractAction(array $context): string
    {
        return $context['action'] ??
               $context['event'] ??
               substr($context['message'] ?? 'unknown', 0, 50);
    }

    /**
     * Extract module from context or default to 'payroll'
     */
    private function extractModule(array $context): string
    {
        return $context['module'] ?? 'payroll';
    }

    /**
     * Log to file (for critical errors)
     */
    private function logToFile(string $level, string $message, array $context): void
    {
        // Prefer configured LOG_PATH, else default to public_html/logs
        $defaultDir = ($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) . '/logs';
        $logDir = rtrim($_ENV['LOG_PATH'] ?? $defaultDir, '/');
        $logFile = $logDir . '/payroll_' . date('Y-m-d') . '.log';

        // Create log directory if it doesn't exist
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logLine = sprintf(
            "[%s] [%s] %s | Request: %s | User: %s | Context: %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $this->requestId,
            $this->userId ?? 'guest',
            json_encode($context, JSON_UNESCAPED_UNICODE)
        );

        @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get real client IP address (handles proxies)
     */
    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    // ========================================================================
    // PERFORMANCE TRACKING
    // ========================================================================

    /**
     * Start performance timer
     *
     * @param string $operation Operation name
     * @return float Start time
     */
    public function startTimer(string $operation): float
    {
        $startTime = microtime(true);
        $this->addContext('operation', $operation);
        $this->addContext('timer_start', $startTime);
        return $startTime;
    }

    /**
     * End performance timer and log
     *
     * @param float $startTime Start time from startTimer()
     * @param string $operation Operation name
     */
    public function endTimer(float $startTime, string $operation): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2); // milliseconds

        $this->info("Performance: {$operation}", [
            'operation' => $operation,
            'duration_ms' => $duration,
            'slow_query' => $duration > 300
        ]);

        // Log slow operations as warnings
        if ($duration > 1000) {
            $this->warning("Slow operation detected: {$operation}", [
                'duration_ms' => $duration
            ]);
        }
    }

    // ========================================================================
    // UTILITY METHODS
    // ========================================================================

    /**
     * Log database query
     */
    public function logQuery(string $sql, array $params = [], float $duration = 0.0): void
    {
        $this->debug('Database query', [
            'sql' => $sql,
            'params' => $params,
            'duration_ms' => round($duration, 2)
        ]);
    }

    /**
     * Log API call
     */
    public function logApiCall(string $service, string $endpoint, array $data = []): void
    {
        $this->info("API call: {$service}", [
            'service' => $service,
            'endpoint' => $endpoint,
            'data' => $data
        ]);
    }

    /**
     * Log authentication event
     */
    public function logAuth(string $event, ?int $userId = null, bool $success = true): void
    {
        $level = $success ? self::INFO : self::WARNING;

        $this->log($level, "Auth: {$event}", [
            'event' => $event,
            'user_id' => $userId,
            'success' => $success
        ]);
    }

    /**
     * Log AI decision
     */
    public function logAiDecision(string $decisionType, array $data): void
    {
        $this->info("AI Decision: {$decisionType}", [
            'decision_type' => $decisionType,
            'data' => $data
        ]);
    }
}
