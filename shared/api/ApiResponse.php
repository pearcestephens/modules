<?php
declare(strict_types=1);

/**
 * Shared API Response Envelope
 * 
 * Provides consistent JSON response structure across all APIs
 * 
 * @package CIS\Shared\API
 * @version 1.0.0
 */

class ApiResponse
{
    /**
     * Send success response
     * 
     * @param mixed $data The data payload
     * @param string|null $message Optional success message
     * @param array $meta Optional metadata (pagination, timing, etc.)
     * @param int $httpCode HTTP status code (default: 200)
     */
    public static function success($data = null, ?string $message = null, array $meta = [], int $httpCode = 200): void
    {
        http_response_code($httpCode);
        
        $response = [
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $data
        ];
        
        if ($message !== null) {
            $response['message'] = $message;
        }
        
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Send error response
     * 
     * @param string $message Error message
     * @param int $httpCode HTTP status code (default: 400)
     * @param string|null $errorCode Optional error code for client handling
     * @param array $details Optional error details (validation errors, etc.)
     */
    public static function error(string $message, int $httpCode = 400, ?string $errorCode = null, array $details = []): void
    {
        http_response_code($httpCode);
        
        $response = [
            'success' => false,
            'timestamp' => date('Y-m-d H:i:s'),
            'error' => [
                'message' => $message,
                'http_code' => $httpCode
            ]
        ];
        
        if ($errorCode !== null) {
            $response['error']['code'] = $errorCode;
        }
        
        if (!empty($details)) {
            $response['error']['details'] = $details;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Send validation error response
     * 
     * @param array $validationErrors Array of field => error message pairs
     * @param string $message General validation message
     */
    public static function validationError(array $validationErrors, string $message = 'Validation failed'): void
    {
        self::error($message, 422, 'VALIDATION_ERROR', $validationErrors);
    }
    
    /**
     * Send not found error
     * 
     * @param string $resource Resource that was not found
     */
    public static function notFound(string $resource = 'Resource'): void
    {
        self::error("{$resource} not found", 404, 'NOT_FOUND');
    }
    
    /**
     * Send unauthorized error
     * 
     * @param string $message Optional custom message
     */
    public static function unauthorized(string $message = 'Unauthorized access'): void
    {
        self::error($message, 401, 'UNAUTHORIZED');
    }
    
    /**
     * Send forbidden error
     * 
     * @param string $message Optional custom message
     */
    public static function forbidden(string $message = 'Access forbidden'): void
    {
        self::error($message, 403, 'FORBIDDEN');
    }
    
    /**
     * Send server error
     * 
     * @param string $message Error message
     * @param \Exception|null $exception Optional exception for logging
     */
    public static function serverError(string $message = 'Internal server error', ?\Exception $exception = null): void
    {
        if ($exception !== null) {
            error_log('API Server Error: ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
        }
        
        self::error($message, 500, 'SERVER_ERROR');
    }
}

/**
 * Parse incoming JSON request data
 * 
 * @return array Parsed request data
 */
function getRequestData(): array
{
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        ApiResponse::error('Invalid JSON data: ' . json_last_error_msg(), 400, 'INVALID_JSON');
    }
    
    // Merge with POST and GET for flexibility
    return array_merge($_GET, $_POST, $data ?? []);
}
