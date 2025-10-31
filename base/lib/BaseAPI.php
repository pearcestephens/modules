<?php
/**
 * BaseAPI - Enterprise-Grade API Foundation
 *
 * Universal base class for all API endpoints across CIS.
 * Provides standardized request handling, response envelopes,
 * error management, logging, validation, and security.
 *
 * Design Pattern: Template Method + Strategy
 *
 * @package CIS\Base\Lib
 * @version 6.0.0
 * @author CIS Development Team
 *
 * USAGE EXAMPLE:
 *
 * class UserAPI extends BaseAPI {
 *     protected function handleGetUser($data) {
 *         $this->validateRequired($data, ['user_id']);
 *         $user = $this->db->getUserById($data['user_id']);
 *         return $this->success($user, 'User retrieved successfully');
 *     }
 * }
 *
 * // In your API endpoint file:
 * $api = new UserAPI(['db' => $dbConnection]);
 * $api->handleRequest();
 */

declare(strict_types=1);

namespace CIS\Base\Lib;

abstract class BaseAPI {

    /**
     * Configuration options
     * @var array
     */
    protected array $config;

    /**
     * CIS Logger instance
     * @var \Base\Lib\Log|null
     */
    protected ?\Base\Lib\Log $logger = null;

    /**
     * Request start time for performance tracking
     * @var float
     */
    private float $requestStartTime;

    /**
     * Current request ID for tracking
     * @var string
     */
    private string $requestId;

    /**
     * HTTP status codes
     */
    private const HTTP_OK = 200;
    private const HTTP_BAD_REQUEST = 400;
    private const HTTP_UNAUTHORIZED = 401;
    private const HTTP_FORBIDDEN = 403;
    private const HTTP_NOT_FOUND = 404;
    private const HTTP_METHOD_NOT_ALLOWED = 405;
    private const HTTP_INTERNAL_ERROR = 500;

    /**
     * Constructor
     *
     * @param array $config Configuration options
     */
    public function __construct(array $config = []) {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->requestStartTime = microtime(true);
        $this->requestId = $this->generateRequestId();

        // Initialize CIS Logger if available
        $this->initializeLogger();

        // Log request start
        $this->logInfo('API request started', [
            'request_id' => $this->requestId,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'endpoint' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A'
        ]);
    }

    /**
     * Get default configuration
     *
     * @return array
     */
    protected function getDefaultConfig(): array {
        return [
            'require_auth' => false,
            'allowed_methods' => ['POST'],
            'rate_limit' => null,
            'log_requests' => true,
            'log_responses' => true,
            'max_request_size' => 10485760, // 10MB
            'timezone' => 'Pacific/Auckland'
        ];
    }

    /**
     * Initialize CIS Logger
     */
    private function initializeLogger(): void {
        $loggerPath = __DIR__ . '/Log.php';

        if (file_exists($loggerPath)) {
            require_once $loggerPath;

            if (class_exists('\Base\Lib\Log')) {
                try {
                    $this->logger = new \Base\Lib\Log();
                    $this->logger->info('BaseAPI logger initialized', [
                        'class' => get_class($this),
                        'request_id' => $this->requestId
                    ]);
                } catch (\Exception $e) {
                    // Fallback to error_log
                    error_log("Failed to initialize CIS Logger: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Main request handler - Template Method Pattern
     *
     * This orchestrates the entire request lifecycle:
     * 1. Validate request
     * 2. Authenticate (if required)
     * 3. Rate limit check
     * 4. Parse input
     * 5. Route to handler
     * 6. Build response
     * 7. Log completion
     *
     * @return void
     */
    public function handleRequest(): void {
        try {
            // 1. Validate HTTP method
            $this->validateRequestMethod();

            // 2. Validate request size
            $this->validateRequestSize();

            // 3. Check authentication (if required)
            if ($this->config['require_auth']) {
                $this->authenticate();
            }

            // 4. Rate limiting (if enabled)
            if ($this->config['rate_limit']) {
                $this->checkRateLimit();
            }

            // 5. Get action from request
            $action = $this->getAction();

            if (!$action) {
                throw new \Exception('Action parameter is required');
            }

            // 6. Parse request data
            $data = $this->parseRequestData();

            // 7. Route to appropriate handler
            $handlerMethod = $this->getHandlerMethod($action);

            if (!method_exists($this, $handlerMethod)) {
                throw new \Exception("Invalid action: {$action}");
            }

            // 8. Execute handler
            $result = $this->$handlerMethod($data);

            // 9. Send response
            $this->sendResponse($result);

        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Validate HTTP request method
     *
     * @throws \Exception
     */
    private function validateRequestMethod(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (!in_array($method, $this->config['allowed_methods'])) {
            throw new \Exception(
                'Method not allowed. Allowed: ' . implode(', ', $this->config['allowed_methods']),
                self::HTTP_METHOD_NOT_ALLOWED
            );
        }
    }

    /**
     * Validate request size
     *
     * @throws \Exception
     */
    private function validateRequestSize(): void {
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;

        if ($contentLength > $this->config['max_request_size']) {
            throw new \Exception(
                'Request too large. Max: ' . $this->formatBytes($this->config['max_request_size']),
                self::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * Authenticate request (override in child classes)
     *
     * @throws \Exception
     */
    protected function authenticate(): void {
        // Default implementation - override in child class
        if (empty($_SESSION['user_id'])) {
            throw new \Exception('Authentication required', self::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * Check rate limiting (override in child classes)
     *
     * @throws \Exception
     */
    protected function checkRateLimit(): void {
        // Default implementation - override in child class
        // Could use Redis, database, or file-based rate limiting
    }

    /**
     * Get action from request
     *
     * @return string|null
     */
    private function getAction(): ?string {
        return $_POST['action'] ?? $_GET['action'] ?? null;
    }

    /**
     * Parse request data
     *
     * @return array
     */
    private function parseRequestData(): array {
        $data = [];

        // Merge GET and POST data
        $data = array_merge($_GET, $_POST);

        // Parse JSON body if present
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $json = file_get_contents('php://input');
            $jsonData = json_decode($json, true);
            if ($jsonData) {
                $data = array_merge($data, $jsonData);
            }
        }

        return $data;
    }

    /**
     * Convert action to handler method name
     *
     * Examples:
     * - get_user -> handleGetUser
     * - save_product -> handleSaveProduct
     * - delete_item -> handleDeleteItem
     *
     * @param string $action
     * @return string
     */
    private function getHandlerMethod(string $action): string {
        // Convert snake_case to camelCase
        $parts = explode('_', $action);
        $method = 'handle';

        foreach ($parts as $part) {
            $method .= ucfirst(strtolower($part));
        }

        return $method;
    }

    /**
     * Create success response
     *
     * Standard success envelope:
     * {
     *   "success": true,
     *   "message": "Operation successful",
     *   "timestamp": "2025-10-31 12:34:56",
     *   "request_id": "req_1730332496_a1b2c3d4",
     *   "data": { ... },
     *   "meta": { ... }
     * }
     *
     * @param mixed $data Response data
     * @param string $message Success message
     * @param array $meta Optional metadata
     * @return array
     */
    protected function success($data = null, string $message = 'Success', array $meta = []): array {
        $duration = microtime(true) - $this->requestStartTime;

        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'request_id' => $this->requestId,
            'data' => $data,
            'meta' => array_merge([
                'duration_ms' => round($duration * 1000, 2),
                'memory_usage' => $this->formatBytes(memory_get_usage(true))
            ], $meta)
        ];

        // Log success
        if ($this->config['log_responses']) {
            $this->logInfo('API request successful', [
                'request_id' => $this->requestId,
                'message' => $message,
                'duration_ms' => $response['meta']['duration_ms']
            ]);
        }

        return $response;
    }

    /**
     * Create error response
     *
     * Standard error envelope:
     * {
     *   "success": false,
     *   "error": {
     *     "code": "VALIDATION_ERROR",
     *     "message": "Missing required field: email",
     *     "timestamp": "2025-10-31 12:34:56",
     *     "details": { ... }
     *   },
     *   "request_id": "req_1730332496_a1b2c3d4"
     * }
     *
     * @param string $message Error message
     * @param string $code Error code (default: API_ERROR)
     * @param array $details Optional error details
     * @param int $httpCode HTTP status code
     * @return array
     */
    protected function error(
        string $message,
        string $code = 'API_ERROR',
        array $details = [],
        int $httpCode = self::HTTP_BAD_REQUEST
    ): array {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'timestamp' => date('Y-m-d H:i:s'),
                'details' => $details
            ],
            'request_id' => $this->requestId
        ];

        // Log error
        $this->logError('API error: ' . $message, [
            'request_id' => $this->requestId,
            'code' => $code,
            'http_code' => $httpCode,
            'details' => $details
        ]);

        // Set HTTP status code
        http_response_code($httpCode);

        return $response;
    }

    /**
     * Validate required fields in data
     *
     * @param array $data Input data
     * @param array $required Required field names
     * @throws \Exception
     */
    protected function validateRequired(array $data, array $required): void {
        $missing = [];

        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new \Exception(
                'Missing required fields: ' . implode(', ', $missing),
                self::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * Validate field types
     *
     * @param array $data Input data
     * @param array $types Field type definitions ['field' => 'int|string|email|...']
     * @throws \Exception
     */
    protected function validateTypes(array $data, array $types): void {
        foreach ($types as $field => $type) {
            if (!isset($data[$field])) {
                continue; // Field not present, skip validation
            }

            $value = $data[$field];
            $valid = false;

            switch ($type) {
                case 'int':
                case 'integer':
                    $valid = is_numeric($value) && (int)$value == $value;
                    break;

                case 'float':
                case 'double':
                    $valid = is_numeric($value);
                    break;

                case 'string':
                    $valid = is_string($value);
                    break;

                case 'email':
                    $valid = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                    break;

                case 'url':
                    $valid = filter_var($value, FILTER_VALIDATE_URL) !== false;
                    break;

                case 'bool':
                case 'boolean':
                    $valid = is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false']);
                    break;

                case 'array':
                    $valid = is_array($value);
                    break;

                case 'json':
                    json_decode($value);
                    $valid = json_last_error() === JSON_ERROR_NONE;
                    break;

                default:
                    // Custom validation (could be a regex pattern)
                    if (strpos($type, '/') === 0) {
                        $valid = preg_match($type, $value) === 1;
                    }
            }

            if (!$valid) {
                throw new \Exception(
                    "Invalid type for field '{$field}'. Expected: {$type}",
                    self::HTTP_BAD_REQUEST
                );
            }
        }
    }

    /**
     * Sanitize input data
     *
     * @param mixed $data Input data
     * @return mixed Sanitized data
     */
    protected function sanitize($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitize'], $data);
        }

        if (is_string($data)) {
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        }

        return $data;
    }

    /**
     * Send JSON response
     *
     * @param array $response Response data
     */
    private function sendResponse(array $response): void {
        header('Content-Type: application/json; charset=utf-8');

        // Add security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');

        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Handle exceptions
     *
     * @param \Exception $e
     */
    private function handleException(\Exception $e): void {
        $httpCode = $e->getCode() ?: self::HTTP_INTERNAL_ERROR;

        // Ensure valid HTTP code
        if ($httpCode < 100 || $httpCode > 599) {
            $httpCode = self::HTTP_INTERNAL_ERROR;
        }

        $errorCode = $this->getErrorCode($httpCode);

        $response = $this->error(
            $e->getMessage(),
            $errorCode,
            [
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ],
            $httpCode
        );

        $this->sendResponse($response);
    }

    /**
     * Get error code from HTTP status
     *
     * @param int $httpCode
     * @return string
     */
    private function getErrorCode(int $httpCode): string {
        $codes = [
            self::HTTP_BAD_REQUEST => 'BAD_REQUEST',
            self::HTTP_UNAUTHORIZED => 'UNAUTHORIZED',
            self::HTTP_FORBIDDEN => 'FORBIDDEN',
            self::HTTP_NOT_FOUND => 'NOT_FOUND',
            self::HTTP_METHOD_NOT_ALLOWED => 'METHOD_NOT_ALLOWED',
            self::HTTP_INTERNAL_ERROR => 'INTERNAL_ERROR'
        ];

        return $codes[$httpCode] ?? 'API_ERROR';
    }

    /**
     * Generate unique request ID
     *
     * @return string
     */
    private function generateRequestId(): string {
        return 'req_' . time() . '_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
    }

    /**
     * Format bytes to human readable
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    protected function formatBytes(int $bytes, int $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Log info message
     *
     * @param string $message
     * @param array $context
     */
    protected function logInfo(string $message, array $context = []): void {
        if ($this->logger) {
            $this->logger->info($message, $context);
        } elseif ($this->config['log_requests']) {
            error_log("[INFO] {$message} " . json_encode($context));
        }
    }

    /**
     * Log error message
     *
     * @param string $message
     * @param array $context
     */
    protected function logError(string $message, array $context = []): void {
        if ($this->logger) {
            $this->logger->error($message, $context);
        } else {
            error_log("[ERROR] {$message} " . json_encode($context));
        }
    }

    /**
     * Log warning message
     *
     * @param string $message
     * @param array $context
     */
    protected function logWarning(string $message, array $context = []): void {
        if ($this->logger) {
            $this->logger->warning($message, $context);
        } else {
            error_log("[WARNING] {$message} " . json_encode($context));
        }
    }
}
