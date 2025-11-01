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
     * Require POST request method
     * 
     * @throws \Exception If request method is not POST
     * @return void
     */
    protected function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->logger->warning('Method not allowed', [
                'request_id' => $this->requestId,
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
                'expected' => 'POST'
            ]);
            
            http_response_code(405);
            header('Allow: POST');
            $this->error('Method not allowed. POST required.', [], 405);
            exit;
        }
    }

    /**
     * Verify CSRF token (enforce)
     * 
     * @throws \Exception If CSRF validation fails
     * @return void
     */
    protected function verifyCsrf(): void
    {
        if (!$this->validateCsrf()) {
            http_response_code(403);
            $this->error('CSRF validation failed', ['csrf' => 'Invalid or missing CSRF token'], 403);
            exit;
        }
    }

    /**
     * Get JSON input from request body
     * 
     * @param bool $assoc Return as associative array
     * @return array|object Parsed JSON data
     * @throws \InvalidArgumentException If JSON is invalid
     */
    protected function getJsonInput(bool $assoc = true)
    {
        $input = file_get_contents('php://input');
        
        if (empty($input)) {
            return $assoc ? [] : new \stdClass();
        }
        
        $data = json_decode($input, $assoc);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->warning('Invalid JSON input', [
                'request_id' => $this->requestId,
                'error' => json_last_error_msg()
            ]);
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }
        
        return $data;
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
     * 
     * Supports two calling signatures:
     * 1. validateInput($data, $rules) - explicit data and rules
     * 2. validateInput($rules) - auto-detects data from $_POST
     * 
     * @param array $dataOrRules Either the data array or rules array
     * @param array|null $rules Optional rules array (if first param is data)
     * @return array Validated data
     * @throws \InvalidArgumentException If validation fails
     */
    protected function validateInput(array $dataOrRules, ?array $rules = null): array
    {
        // Determine calling signature
        if ($rules === null) {
            // Called with just rules: validateInput($rules)
            // Auto-detect data from request
            $rules = $dataOrRules;
            $data = $_POST;
        } else {
            // Called with data and rules: validateInput($data, $rules)
            $data = $dataOrRules;
        }

        // Implement basic validation
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $fieldRules = is_array($fieldRules) ? $fieldRules : [$fieldRules];

            // Check if optional
            $isOptional = in_array('optional', $fieldRules);
            
            // Required validation
            if (!$isOptional && in_array('required', $fieldRules)) {
                if ($value === null || $value === '') {
                    $errors[$field] = "Field '{$field}' is required";
                    continue;
                }
            }

            // Skip further validation if optional and empty
            if ($isOptional && ($value === null || $value === '')) {
                $validated[$field] = null;
                continue;
            }

            // Type validation
            if (in_array('integer', $fieldRules)) {
                if (!is_numeric($value) || (int)$value != $value) {
                    $errors[$field] = "Field '{$field}' must be an integer";
                    continue;
                }
                $validated[$field] = (int)$value;
            } elseif (in_array('float', $fieldRules) || in_array('numeric', $fieldRules)) {
                if (!is_numeric($value)) {
                    $errors[$field] = "Field '{$field}' must be numeric";
                    continue;
                }
                $validated[$field] = (float)$value;
            } elseif (in_array('boolean', $fieldRules)) {
                $validated[$field] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif (in_array('email', $fieldRules)) {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = "Field '{$field}' must be a valid email";
                    continue;
                }
                $validated[$field] = $value;
            } elseif (in_array('string', $fieldRules)) {
                $validated[$field] = (string)$value;
            } elseif (in_array('datetime', $fieldRules)) {
                try {
                    new \DateTime($value);
                    $validated[$field] = $value;
                } catch (\Exception $e) {
                    $errors[$field] = "Field '{$field}' must be a valid datetime";
                    continue;
                }
            } elseif (in_array('date', $fieldRules)) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    $errors[$field] = "Field '{$field}' must be a valid date (Y-m-d)";
                    continue;
                }
                $validated[$field] = $value;
            } else {
                // Default: keep as-is
                $validated[$field] = $value;
            }

            // Min length validation
            foreach ($fieldRules as $rule) {
                if (is_string($rule) && strpos($rule, 'min:') === 0) {
                    $minLength = (int)substr($rule, 4);
                    if (is_string($value) && strlen($value) < $minLength) {
                        $errors[$field] = "Field '{$field}' must be at least {$minLength} characters";
                    }
                }
            }

            // Max length validation
            foreach ($fieldRules as $rule) {
                if (is_string($rule) && strpos($rule, 'max:') === 0) {
                    $maxLength = (int)substr($rule, 4);
                    if (is_string($value) && strlen($value) > $maxLength) {
                        $errors[$field] = "Field '{$field}' must not exceed {$maxLength} characters";
                    }
                }
            }

            // Enum validation
            foreach ($fieldRules as $rule) {
                if (is_string($rule) && strpos($rule, 'in:') === 0) {
                    $allowedValues = explode(',', substr($rule, 3));
                    if (!in_array($value, $allowedValues)) {
                        $errors[$field] = "Field '{$field}' must be one of: " . implode(', ', $allowedValues);
                    }
                }
            }
        }

        if (!empty($errors)) {
            $this->logger->warning('Input validation failed', [
                'request_id' => $this->requestId,
                'errors' => $errors
            ]);
            throw new \InvalidArgumentException('Validation failed: ' . json_encode($errors));
        }

        return $validated;
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
