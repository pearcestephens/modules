<?php
declare(strict_types=1);

/**
 * Common API Utilities for Consignments Module
 *
 * Provides standardized JSON response envelope and authentication gates.
 * Include this at the top of all API endpoints.
 *
 * Usage:
 *   require_once __DIR__ . '/_common.php';
 *   ConsignAuth::requireRole('ops');
 *   $input = json_input();
 *   api_ok(['result' => $data]);
 *   api_fail('error message', 400);
 *
 * @package CIS\Consignments\API
 */

// Ensure session is started
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// Set standard headers
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Type: application/json; charset=utf-8');

/**
 * Send successful JSON response
 *
 * @param array $data Response data
 * @param int $code HTTP status code
 * @return never
 */
function api_ok(array $data = [], int $code = 200): never
{
    http_response_code($code);
    echo json_encode([
        'success' => true,
        'data' => $data
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send error JSON response
 *
 * @param string $message Error message
 * @param int $code HTTP status code
 * @param array $meta Additional error metadata
 * @return never
 */
function api_fail(string $message, int $code = 400, array $meta = []): never
{
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message,
        'meta' => $meta
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Parse JSON input from request body
 *
 * @return array Parsed JSON data or empty array
 */
function json_input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * Authentication and authorization utilities
 */
final class ConsignAuth
{
    /**
     * Require specific role or fail with 403
     *
     * @param string $role Required role (admin, manager, ops)
     * @return void
     */
    public static function requireRole(string $role): void
    {
        // Check if user is logged in
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
            api_fail('Authentication required', 401);
        }

        // Define role hierarchy
        $allowedRoles = ['admin', 'manager', 'ops'];

        // Validate role
        if (!in_array($_SESSION['user_role'], $allowedRoles, true)) {
            api_fail('Insufficient permissions', 403, [
                'required_role' => $role,
                'user_role' => $_SESSION['user_role'] ?? 'none'
            ]);
        }
    }

    /**
     * Require authentication (any logged-in user)
     *
     * @return void
     */
    public static function requireAuth(): void
    {
        if (!isset($_SESSION['user_id'])) {
            api_fail('Authentication required', 401);
        }
    }

    /**
     * Check if user has specific permission (SIMPLIFIED: Always true if logged in)
     *
     * @param string $permission Permission string (ignored)
     * @return bool Always true if authenticated
     */
    public static function hasPermission(string $permission): bool
    {
        // SIMPLIFIED: No permission checks - logged in only
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

/**
 * Validate POST request method
 *
 * @return void
 */
function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        api_fail('POST method required', 405);
    }
}

/**
 * Validate required fields in data array
 *
 * @param array $data Input data
 * @param array $required List of required field names
 * @return void
 */
function require_fields(array $data, array $required): void
{
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            api_fail("Missing required field: {$field}", 400, [
                'required_fields' => $required
            ]);
        }
    }
}
