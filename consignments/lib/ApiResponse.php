<?php
/**
 * CIS API Response Handler - Standard JSON Response Envelope
 * 
 * Provides standardized JSON response format across all CIS modules
 * with proper error handling, validation, and debugging support.
 * 
 * @package CIS\Core\API
 * @version 1.0.0
 * @created 2025-10-12
 */

class CISApiResponse {
    
    private static $correlationId;
    private static $debugMode = false;
    private static $startTime;
    
    /**
     * Initialize API response handler
     */
    public static function init($debugMode = false) {
        self::$correlationId = self::generateCorrelationId();
        self::$debugMode = $debugMode;
        self::$startTime = microtime(true);
        
        // Set standard headers
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Correlation-ID: ' . self::$correlationId);
        
        // CORS headers for AJAX requests
        header('Access-Control-Allow-Origin: ' . ($_ENV['CORS_ORIGIN'] ?? '*'));
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization, X-CSRF-Token');
        header('Access-Control-Expose-Headers: X-Correlation-ID');
        
        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
    
    /**
     * Send successful response
     */
    public static function success($data = null, $message = 'Success', $statusCode = 200) {
        http_response_code($statusCode);
        
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => self::buildMeta(),
            'correlation_id' => self::$correlationId
        ];
        
        if (self::$debugMode) {
            $response['debug'] = self::buildDebugInfo();
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Send error response
     */
    public static function error($message, $statusCode = 400, $errorCode = null, $details = null) {
        http_response_code($statusCode);
        
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $errorCode ?? $statusCode,
                'details' => $details
            ],
            'meta' => self::buildMeta(),
            'correlation_id' => self::$correlationId
        ];
        
        if (self::$debugMode) {
            $response['debug'] = self::buildDebugInfo();
        }
        
        // Log error for debugging
        self::logError($message, $statusCode, $errorCode, $details);
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Send validation error response
     */
    public static function validationError($errors, $message = 'Validation failed') {
        self::error($message, 422, 'VALIDATION_ERROR', $errors);
    }
    
    /**
     * Send unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized access') {
        self::error($message, 401, 'UNAUTHORIZED');
    }
    
    /**
     * Send forbidden response
     */
    public static function forbidden($message = 'Access forbidden') {
        self::error($message, 403, 'FORBIDDEN');
    }
    
    /**
     * Send not found response
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, 404, 'NOT_FOUND');
    }
    
    /**
     * Send method not allowed response
     */
    public static function methodNotAllowed($allowedMethods = []) {
        header('Allow: ' . implode(', ', $allowedMethods));
        self::error('Method not allowed', 405, 'METHOD_NOT_ALLOWED', [
            'allowed_methods' => $allowedMethods
        ]);
    }
    
    /**
     * Send server error response
     */
    public static function serverError($message = 'Internal server error', $details = null) {
        self::error($message, 500, 'INTERNAL_ERROR', $details);
    }
    
    /**
     * Handle exceptions and convert to error response
     */
    public static function handleException($exception) {
        $message = $exception->getMessage();
        $statusCode = 500;
        $errorCode = 'INTERNAL_ERROR';
        
        // Handle specific exception types
        if ($exception instanceof InvalidArgumentException) {
            $statusCode = 400;
            $errorCode = 'INVALID_ARGUMENT';
        } elseif ($exception instanceof UnauthorizedHttpException) {
            $statusCode = 401;
            $errorCode = 'UNAUTHORIZED';
        } elseif ($exception instanceof NotFoundHttpException) {
            $statusCode = 404;
            $errorCode = 'NOT_FOUND';
        }
        
        $details = null;
        if (self::$debugMode) {
            $details = [
                'exception_class' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }
        
        self::error($message, $statusCode, $errorCode, $details);
    }
    
    /**
     * Validate required fields in input data
     */
    public static function validateRequired($data, $requiredFields) {
        $missing = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            self::validationError([
                'missing_fields' => $missing
            ], 'Missing required fields: ' . implode(', ', $missing));
        }
    }
    
    /**
     * Validate JSON input
     */
    public static function validateJson($jsonString = null) {
        if ($jsonString === null) {
            $jsonString = file_get_contents('php://input');
        }
        
        if (empty($jsonString)) {
            self::validationError(['json' => 'Empty request body'], 'Request body cannot be empty');
        }
        
        $data = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            self::validationError([
                'json' => json_last_error_msg()
            ], 'Invalid JSON data');
        }
        
        return $data;
    }
    
    /**
     * Check if request is AJAX
     */
    public static function requireAjax() {
        $isAjax = (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) || (
            isset($_SERVER['CONTENT_TYPE']) && 
            strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false
        );
        
        if (!$isAjax) {
            self::error('AJAX request required', 400, 'AJAX_REQUIRED');
        }
    }
    
    /**
     * Generate unique correlation ID
     */
    private static function generateCorrelationId() {
        return sprintf(
            '%s-%04x%04x-%04x-%04x-%04x%04x%04x',
            date('ymd'),
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Build meta information
     */
    private static function buildMeta() {
        return [
            'timestamp' => date('Y-m-d\TH:i:s.u\Z'),
            'request_id' => self::$correlationId,
            'version' => '1.0.0',
            'execution_time_ms' => round((microtime(true) - self::$startTime) * 1000, 2)
        ];
    }
    
    /**
     * Build debug information
     */
    private static function buildDebugInfo() {
        return [
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'UNKNOWN',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN',
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            'server_time' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION
        ];
    }
    
    /**
     * Log error for debugging
     */
    private static function logError($message, $statusCode, $errorCode, $details) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'correlation_id' => self::$correlationId,
            'message' => $message,
            'status_code' => $statusCode,
            'error_code' => $errorCode,
            'details' => $details,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'UNKNOWN',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN'
        ];
        
        error_log('CIS API Error: ' . json_encode($logData));
    }
    
    /**
     * Send paginated response
     */
    public static function paginated($data, $total, $page, $perPage, $message = 'Success') {
        $totalPages = ceil($total / $perPage);
        
        $pagination = [
            'current_page' => (int)$page,
            'per_page' => (int)$perPage,
            'total_items' => (int)$total,
            'total_pages' => (int)$totalPages,
            'has_more' => $page < $totalPages
        ];
        
        $response = [
            'items' => $data,
            'pagination' => $pagination
        ];
        
        self::success($response, $message);
    }
    
    /**
     * Backward compatibility for existing APIs
     */
    public static function legacy($data, $success = true) {
        if ($success) {
            $response = array_merge(['ok' => true], $data);
        } else {
            $response = ['ok' => false, 'error' => $data];
        }
        
        echo json_encode($response);
        exit;
    }
}

/**
 * Custom exception classes for better error handling
 */
class UnauthorizedHttpException extends Exception {}
class NotFoundHttpException extends Exception {}
class ValidationException extends Exception {
    private $errors;
    
    public function __construct($errors, $message = 'Validation failed') {
        parent::__construct($message);
        $this->errors = $errors;
    }
    
    public function getErrors() {
        return $this->errors;
    }
}
?>