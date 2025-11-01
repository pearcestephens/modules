<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Controllers;

/**
 * Base Controller for Payroll Module
 *
 * Provides common functionality for all payroll controllers:
 * - Request validation
 * - Response formatting
 * - Error handling
 * - Session management
 * - CSRF protection
 * - Logging integration
 *
 * @package HumanResources\Payroll\Controllers
 * @version 1.0.0
 */

use PayrollModule\Lib\PayrollLogger;

abstract class BaseController
{
    protected PayrollLogger $logger;
    protected array $user;
    protected string $requestId;
    protected ?\stdClass $validator = null;
    protected ?\stdClass $response = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = new PayrollLogger();
        $this->requestId = $this->generateRequestId();

        // Initialize validator and response objects (placeholders for now)
        $this->validator = new \stdClass();
        $this->response = new \stdClass();

        // Load user session - use CIS session structure
        $this->user = [];
        if (!empty($_SESSION['userID']) && !empty($_SESSION['authenticated'])) {
            $this->user = [
                'id' => (int)$_SESSION['userID'],
                'email' => $_SESSION['username'] ?? '',
                'name' => $_SESSION['username'] ?? 'User',
                'role' => $_SESSION['role'] ?? 'staff',
                'permissions' => $_SESSION['permissions'] ?? []
            ];
        }

        // Log request
        $this->logRequest();
    }

    /**
     * Generate unique request ID for tracing
     */
    protected function generateRequestId(): string
    {
        return uniqid('req_', true);
    }

    /**
     * Log incoming request
     */
    protected function logRequest(): void
    {
        $this->logger->info('Request received', [
            'request_id' => $this->requestId,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'user_id' => $this->user['id'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A'
        ]);
    }

    /**
     * Validate CSRF token
     */
    protected function validateCsrf(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            $sessionToken = $_SESSION['csrf_token'] ?? '';

            if (!hash_equals($sessionToken, $token)) {
                $this->logger->warning('CSRF validation failed', [
                    'request_id' => $this->requestId,
                    'user_id' => $this->user['id'] ?? null
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user is authenticated
     */
    protected function requireAuth(): bool
    {
        if (empty($this->user)) {
            $this->logger->warning('Unauthorized access attempt', [
                'request_id' => $this->requestId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A'
            ]);
            return false;
        }

        return true;
    }

    /**
     * Redirect to login if not authenticated
     *
     * @param string|null $message Optional message to show on login page
     * @return void (exits)
     */
    protected function requireAuthOrRedirect(?string $message = null): void
    {
        if (empty($_SESSION['authenticated']) || empty($_SESSION['userID'])) {
            $redirect = $_SERVER['REQUEST_URI'] ?? '/';
            $loginUrl = '/login.php?redirect=' . urlencode($redirect);

            if ($message) {
                $loginUrl .= '&message=' . urlencode($message);
            }

            header('Location: ' . $loginUrl);
            exit;
        }
    }

    /**
     * Check if user has required permission
     */
    protected function requirePermission(string $permission): bool
    {
        if (!$this->requireAuth()) {
            return false;
        }

        // Check user permissions
        $permissions = $this->user['permissions'] ?? [];

        if (!in_array($permission, $permissions) && !in_array('admin', $permissions)) {
            $this->logger->warning('Permission denied', [
                'request_id' => $this->requestId,
                'user_id' => $this->user['id'],
                'required_permission' => $permission
            ]);
            return false;
        }

        return true;
    }

    /**
     * Check if user has permission (non-blocking check)
     *
     * @param string $permission Permission to check
     * @return bool True if user has permission
     */
    protected function hasPermission(string $permission): bool
    {
        if (!$this->requireAuth()) {
            return false;
        }

        // Check user permissions
        $permissions = $this->user['permissions'] ?? [];

        // Admin role has all permissions
        if (in_array('admin', $permissions) || in_array('payroll_admin', $permissions)) {
            return true;
        }

        // Check specific permission
        return in_array($permission, $permissions);
    }

    /**
     * Get current user ID
     *
     * @return int|null User ID or null if not authenticated
     */
    protected function getCurrentUserId(): ?int
    {
        return $this->user['id'] ?? null;
    }

    /**
     * Validate input data against rules
     */
    protected function validateInput(array $data, array $rules): array
    {
        $errors = $this->validator->validate($data, $rules);

        if (!empty($errors)) {
            $this->logger->warning('Input validation failed', [
                'request_id' => $this->requestId,
                'errors' => $errors
            ]);
            throw new \InvalidArgumentException('Validation failed: ' . json_encode($errors));
        }

        return $data;
    }

    /**
     * Return JSON response
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        $this->response->json($data, $statusCode);
    }

    /**
     * Return JSON response (alias for compatibility)
     */
    protected function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Return success response
     */
    protected function success(string $message, array $data = [], int $statusCode = 200): void
    {
        $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'request_id' => $this->requestId,
            'timestamp' => date('Y-m-d H:i:s')
        ], $statusCode);
    }

    /**
     * Return success response (alias for compatibility)
     */
    protected function jsonSuccess(string $message, array $data = [], int $statusCode = 200): void
    {
        $this->success($message, $data, $statusCode);
    }

    /**
     * Return error response
     */
    protected function error(string $message, array $errors = [], int $statusCode = 400): void
    {
        $this->logger->error('Request failed', [
            'request_id' => $this->requestId,
            'message' => $message,
            'errors' => $errors
        ]);

        $this->json([
            'success' => false,
            'error' => [
                'message' => $message,
                'details' => $errors,
                'code' => $statusCode
            ],
            'request_id' => $this->requestId,
            'timestamp' => date('Y-m-d H:i:s')
        ], $statusCode);
    }

    /**
     * Return error response (alias for compatibility)
     */
    protected function jsonError(string $message, array $errors = [], int $statusCode = 400): void
    {
        $this->error($message, $errors, $statusCode);
    }

    /**
     * Handle exceptions consistently
     */
    protected function handleException(\Throwable $e): void
    {
        $this->logger->error('Exception caught', [
            'request_id' => $this->requestId,
            'exception_class' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        // Don't expose internal errors in production
        $message = ($_ENV['APP_ENV'] ?? 'production') === 'development'
            ? $e->getMessage()
            : 'An error occurred processing your request';

        $this->error($message, [], 500);
    }

    /**
     * Render view template
     */
    protected function render(string $view, array $data = []): void
    {
        // Add common data
        $data['user'] = $this->user;
        $data['request_id'] = $this->requestId;
        $data['csrf_token'] = $_SESSION['csrf_token'] ?? '';

        // Extract data for use in view
        extract($data);

        // Include header
        include __DIR__ . '/../views/layouts/header.php';

        // Include view
        include __DIR__ . "/../views/{$view}.php";

        // Include footer
        include __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Redirect to URL
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        header("Location: {$url}", true, $statusCode);
        exit;
    }

    /**
     * Get request input (POST/GET)
     */
    protected function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    /**
     * Check if request is AJAX
     */
    protected function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Get client IP address
     */
    protected function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get user agent string
     */
    protected function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }
}
