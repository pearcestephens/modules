<?php
/**
 * CIS Base Response
 * 
 * Standard response helpers for JSON and HTML.
 * 
 * @package CIS\Base
 * @version 1.0.0
 */

declare(strict_types=1);

namespace CIS\Base;

class Response
{
    /**
     * Send JSON success response
     */
    public static function success(mixed $data = null, string $message = 'Success', int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'timestamp' => date('c')
        ], JSON_PRETTY_PRINT);
        
        exit;
    }
    
    /**
     * Send JSON error response
     */
    public static function error(string $message, string $code = 'ERROR', int $httpCode = 400, array $details = []): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code
            ],
            'timestamp' => date('c')
        ];
        
        if (!empty($details)) {
            $response['error']['details'] = $details;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Redirect to URL
     */
    public static function redirect(string $url, int $code = 302): void
    {
        http_response_code($code);
        header('Location: ' . $url);
        exit;
    }
}
