<?php
/**
 * API Helper Functions for Bank Transactions Module
 *
 * Provides common functions for API endpoints:
 * - Authentication checking with bot bypass
 * - Permission validation
 * - Response formatting
 * - Error handling
 */

declare(strict_types=1);

namespace CIS\BankTransactions\API;

class APIHelper
{
    /**
     * Check if user is authenticated (with bot bypass support)
     *
     * @return array ['authenticated' => bool, 'user_id' => int|null, 'bypass' => bool]
     */
    public static function checkAuth(): array
    {
        // Check for bot bypass
        $botBypass = defined('BOT_BYPASS_AUTH') && BOT_BYPASS_AUTH;

        if ($botBypass) {
            // Bot bypass enabled - allow with admin user
            return [
                'authenticated' => true,
                'user_id' => 1, // Admin user
                'bypass' => true
            ];
        }

        // Normal authentication check
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
            return [
                'authenticated' => true,
                'user_id' => (int)$_SESSION['user_id'],
                'bypass' => false
            ];
        }

        return [
            'authenticated' => false,
            'user_id' => null,
            'bypass' => false
        ];
    }

    /**
     * Check if user has permission (with bot bypass support)
     *
     * @param string $permission Permission to check
     * @return bool
     */
    public static function checkPermission(string $permission): bool
    {
        // Check for bot bypass
        $botBypass = defined('BOT_BYPASS_AUTH') && BOT_BYPASS_AUTH;

        if ($botBypass) {
            return true; // Bot bypass - grant all permissions
        }

        // Normal permission check
        if (!isset($_SESSION['permissions'])) {
            return false;
        }

        return in_array($permission, $_SESSION['permissions'], true);
    }

    /**
     * Send JSON response and exit
     *
     * @param array $data Response data
     * @param int $httpCode HTTP status code
     * @return never
     */
    public static function jsonResponse(array $data, int $httpCode = 200): never
    {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');

        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Send success response
     *
     * @param mixed $data Data to return
     * @param string|null $message Optional success message
     * @return never
     */
    public static function success($data, ?string $message = null): never
    {
        $response = [
            'success' => true,
            'data' => $data
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        self::jsonResponse($response, 200);
    }

    /**
     * Send error response
     *
     * @param string $code Error code
     * @param string $message Error message
     * @param int $httpCode HTTP status code
     * @param array $details Additional error details
     * @return never
     */
    public static function error(
        string $code,
        string $message,
        int $httpCode = 400,
        array $details = []
    ): never {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ];

        if (!empty($details)) {
            $response['error']['details'] = $details;
        }

        self::jsonResponse($response, $httpCode);
    }

    /**
     * Validate required parameters
     *
     * @param array $params Parameters to validate
     * @param array $required Required parameter names
     * @return array ['valid' => bool, 'missing' => array]
     */
    public static function validateParams(array $params, array $required): array
    {
        $missing = [];

        foreach ($required as $param) {
            if (!isset($params[$param]) || $params[$param] === '' || $params[$param] === null) {
                $missing[] = $param;
            }
        }

        return [
            'valid' => empty($missing),
            'missing' => $missing
        ];
    }

    /**
     * Require authentication or exit with error
     *
     * @return int User ID
     */
    public static function requireAuth(): int
    {
        $auth = self::checkAuth();

        if (!$auth['authenticated']) {
            self::error('UNAUTHENTICATED', 'Authentication required', 401);
        }

        return $auth['user_id'];
    }

    /**
     * Require permission or exit with error
     *
     * @param string $permission Permission to require
     * @return void
     */
    public static function requirePermission(string $permission): void
    {
        if (!self::checkPermission($permission)) {
            self::error('FORBIDDEN', "Permission required: {$permission}", 403);
        }
    }

    /**
     * Get JSON body from request
     *
     * @return array|null
     */
    public static function getJsonBody(): ?array
    {
        $body = file_get_contents('php://input');

        if (empty($body)) {
            return null;
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    /**
     * Validate CSRF token (with bot bypass support)
     *
     * @param string|null $token Token to validate
     * @return bool
     */
    public static function validateCSRF(?string $token): bool
    {
        // Check for bot bypass
        $botBypass = defined('BOT_BYPASS_AUTH') && BOT_BYPASS_AUTH;

        if ($botBypass) {
            return true; // Bot bypass - skip CSRF validation
        }

        // Normal CSRF validation
        if (empty($token)) {
            return false;
        }

        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
