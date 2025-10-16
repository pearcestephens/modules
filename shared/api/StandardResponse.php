<?php
declare(strict_types=1);

/**
 * CIS Standard API Response Envelope
 * 
 * ALL API endpoints MUST use this standard response format.
 * This ensures consistent client-side handling and error reporting.
 * 
 * @package CIS\Shared\API
 * @version 1.0.0
 * @author Ecigdis Limited
 * @since 2025-10-16
 */

namespace CIS\API;

/**
 * Standard API Response Envelope Contract
 * 
 * ALL responses from ANY CIS API must follow this exact structure.
 * No exceptions. No deviations. This is the contract.
 */
class StandardResponse
{
    /**
     * Success Response
     * 
     * @param mixed $data The response data (array, object, string, etc.)
     * @param string|null $message Optional success message
     * @param array $meta Optional metadata (pagination, timing, etc.)
     * @param int $httpCode HTTP status code (default 200)
     * @return void Outputs JSON and exits
     */
    public static function success(
        $data = null,
        ?string $message = null,
        array $meta = [],
        int $httpCode = 200
    ): void {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'success' => true,
            'data' => $data,
            'error' => null,
            'meta' => array_merge([
                'timestamp' => date('c'),
                'request_id' => self::getRequestId(),
                'version' => '1.0',
            ], $meta),
        ];
        
        if ($message !== null) {
            $response['message'] = $message;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Error Response
     * 
     * @param string $message Error message (user-friendly)
     * @param int $httpCode HTTP status code (400, 404, 500, etc.)
     * @param string|null $errorCode Machine-readable error code (e.g., 'INVALID_INPUT')
     * @param array $details Optional error details (validation errors, stack trace, etc.)
     * @param array $meta Optional metadata
     * @return void Outputs JSON and exits
     */
    public static function error(
        string $message,
        int $httpCode = 400,
        ?string $errorCode = null,
        array $details = [],
        array $meta = []
    ): void {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        
        // Log error for debugging
        error_log(sprintf(
            'API Error [%s]: %s (HTTP %d) - %s',
            $errorCode ?? 'UNKNOWN',
            $message,
            $httpCode,
            json_encode($details)
        ));
        
        $response = [
            'success' => false,
            'data' => null,
            'error' => [
                'message' => $message,
                'code' => $errorCode ?? self::getDefaultErrorCode($httpCode),
                'http_code' => $httpCode,
            ],
            'meta' => array_merge([
                'timestamp' => date('c'),
                'request_id' => self::getRequestId(),
                'version' => '1.0',
            ], $meta),
        ];
        
        // Include details if provided (validation errors, etc.)
        if (!empty($details)) {
            $response['error']['details'] = $details;
        }
        
        // Include message field at root for backwards compatibility
        $response['message'] = $message;
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Validation Error Response
     * 
     * Specialized error response for validation failures
     * 
     * @param array $errors Array of validation errors ['field' => 'error message']
     * @param string $message Optional custom message
     * @return void Outputs JSON and exits
     */
    public static function validationError(
        array $errors,
        string $message = 'Validation failed'
    ): void {
        self::error(
            $message,
            400,
            'VALIDATION_ERROR',
            ['validation_errors' => $errors]
        );
    }
    
    /**
     * Not Found Response
     * 
     * @param string $resource What was not found (e.g., 'Transfer', 'Product')
     * @param mixed $identifier The ID or identifier that was not found
     * @return void Outputs JSON and exits
     */
    public static function notFound(string $resource, $identifier): void
    {
        self::error(
            "{$resource} not found",
            404,
            'NOT_FOUND',
            ['resource' => $resource, 'identifier' => $identifier]
        );
    }
    
    /**
     * Unauthorized Response
     * 
     * @param string $message Optional custom message
     * @return void Outputs JSON and exits
     */
    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::error($message, 401, 'UNAUTHORIZED');
    }
    
    /**
     * Forbidden Response
     * 
     * @param string $message Optional custom message
     * @return void Outputs JSON and exits
     */
    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::error($message, 403, 'FORBIDDEN');
    }
    
    /**
     * Server Error Response
     * 
     * @param string $message Error message
     * @param \Throwable|null $exception Optional exception for logging
     * @return void Outputs JSON and exits
     */
    public static function serverError(
        string $message = 'Internal server error',
        ?\Throwable $exception = null
    ): void {
        // Log exception if provided
        if ($exception !== null) {
            error_log('Server Error Exception: ' . $exception->getMessage());
            error_log('Stack Trace: ' . $exception->getTraceAsString());
        }
        
        // Don't expose internal details in production
        $details = [];
        if (self::isDebugMode()) {
            $details['exception'] = [
                'message' => $exception?->getMessage(),
                'file' => $exception?->getFile(),
                'line' => $exception?->getLine(),
                'trace' => $exception?->getTrace(),
            ];
        }
        
        self::error($message, 500, 'SERVER_ERROR', $details);
    }
    
    /**
     * Get or generate request ID
     * 
     * @return string Request ID
     */
    private static function getRequestId(): string
    {
        // Check if request ID was sent from client
        if (!empty($_SERVER['HTTP_X_REQUEST_ID'])) {
            return $_SERVER['HTTP_X_REQUEST_ID'];
        }
        
        // Generate new request ID
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Get default error code based on HTTP status
     * 
     * @param int $httpCode HTTP status code
     * @return string Error code
     */
    private static function getDefaultErrorCode(int $httpCode): string
    {
        return match($httpCode) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            422 => 'UNPROCESSABLE_ENTITY',
            429 => 'TOO_MANY_REQUESTS',
            500 => 'SERVER_ERROR',
            503 => 'SERVICE_UNAVAILABLE',
            default => 'UNKNOWN_ERROR',
        };
    }
    
    /**
     * Check if debug mode is enabled
     * 
     * @return bool
     */
    private static function isDebugMode(): bool
    {
        return (
            !empty($_ENV['DEBUG']) ||
            !empty($_GET['debug']) ||
            (defined('APP_DEBUG') && APP_DEBUG === true)
        );
    }
    
    /**
     * Parse incoming request data
     * 
     * Handles JSON body, form data, and query params
     * 
     * @return array Parsed request data
     */
    public static function getRequestData(): array
    {
        $data = [];
        
        // GET parameters
        $data = array_merge($data, $_GET);
        
        // POST parameters
        $data = array_merge($data, $_POST);
        
        // JSON body
        $rawInput = file_get_contents('php://input');
        if (!empty($rawInput)) {
            $jsonData = json_decode($rawInput, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                $data = array_merge($data, $jsonData);
            }
        }
        
        return $data;
    }
}

/**
 * Global helper functions for convenience
 * 
 * These are shortcuts to StandardResponse methods
 */

if (!function_exists('apiSuccess')) {
    /**
     * Output success response
     * 
     * @param mixed $data Response data
     * @param string|null $message Optional message
     * @param array $meta Optional metadata
     * @param int $httpCode HTTP status code
     * @return void
     */
    function apiSuccess($data = null, ?string $message = null, array $meta = [], int $httpCode = 200): void
    {
        \CIS\API\StandardResponse::success($data, $message, $meta, $httpCode);
    }
}

if (!function_exists('apiError')) {
    /**
     * Output error response
     * 
     * @param string $message Error message
     * @param int $httpCode HTTP status code
     * @param string|null $errorCode Machine-readable error code
     * @param array $details Error details
     * @param array $meta Metadata
     * @return void
     */
    function apiError(string $message, int $httpCode = 400, ?string $errorCode = null, array $details = [], array $meta = []): void
    {
        \CIS\API\StandardResponse::error($message, $httpCode, $errorCode, $details, $meta);
    }
}

if (!function_exists('apiValidationError')) {
    /**
     * Output validation error response
     * 
     * @param array $errors Validation errors
     * @param string $message Error message
     * @return void
     */
    function apiValidationError(array $errors, string $message = 'Validation failed'): void
    {
        \CIS\API\StandardResponse::validationError($errors, $message);
    }
}

if (!function_exists('apiNotFound')) {
    /**
     * Output not found response
     * 
     * @param string $resource Resource type
     * @param mixed $identifier Resource identifier
     * @return void
     */
    function apiNotFound(string $resource, $identifier): void
    {
        \CIS\API\StandardResponse::notFound($resource, $identifier);
    }
}

if (!function_exists('getRequestData')) {
    /**
     * Parse incoming request data
     * 
     * @return array Request data
     */
    function getRequestData(): array
    {
        return \CIS\API\StandardResponse::getRequestData();
    }
}
