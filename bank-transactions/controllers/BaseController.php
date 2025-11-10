<?php
/**
 * BaseController - Abstract controller
 *
 * Provides common functionality for all controllers
 *
 * @package CIS\BankTransactions\Controllers
 */

declare(strict_types=1);

namespace CIS\BankTransactions\Controllers;

abstract class BaseController
{
    protected $currentUserId;
    protected $currentUser;
    protected $db;

    /**
     * Constructor - check authentication
     */
    public function __construct()
    {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check authentication
        if (!$this->isAuthenticated()) {
            $this->redirectToLogin();
        }

        $this->currentUserId = $_SESSION['userID'] ?? null;
        $this->currentUser = $_SESSION['user'] ?? null;

        // Get database connection from base module (PDO preferred)
        $this->db = \CIS\Base\Database::pdo();
    }

    /**
     * Check if user is authenticated
     *
     * @return bool
     */
    protected function isAuthenticated(): bool
    {
        // Allow bot bypass
        if (!empty($_GET['bot']) || !empty($_SERVER['HTTP_X_BOT_BYPASS'])) {
            return true;
        }

        return isset($_SESSION['userID']) && !empty($_SESSION['userID']);
    }

    /**
     * Check if user has permission (SIMPLIFIED: Always true if logged in)
     *
     * @param string $permission Permission name (ignored)
     * @return bool Always true
     */
    protected function hasPermission(string $permission): bool
    {
        // SIMPLIFIED: No permission checks - logged in only
        return $this->isAuthenticated();
    }

    /**
     * Require permission or abort (SIMPLIFIED: Only checks login)
     *
     * @param string $permission Permission name (ignored)
     */
    protected function requirePermission(string $permission): void
    {
        // SIMPLIFIED: Just check if logged in
        if (!$this->isAuthenticated()) {
            $this->abort(403, 'Access denied');
        }
    }

    /**
     * Validate CSRF token
     */
    protected function validateCsrfToken(): void
    {
        // Allow bot bypass
        if (!empty($_GET['bot']) || !empty($_SERVER['HTTP_X_BOT_BYPASS'])) {
            return;
        }

        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if (empty($token) || $token !== $sessionToken) {
            $this->abort(403, 'Invalid CSRF token');
        }
    }

    /**
     * Validate input data
     *
     * @param array $rules Validation rules
     * @param array|null $data Data to validate (defaults to $_POST)
     * @return array Validated data
     */
    protected function validate(array $rules, ?array $data = null): array
    {
        $data = $data ?? $_POST;
        $validated = [];
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            // Required check
            if (isset($fieldRules['required']) && $fieldRules['required']) {
                if (empty($value) && $value !== '0') {
                    $errors[$field] = "$field is required";
                    continue;
                }
            }

            // Skip further validation if value is empty and not required
            if (empty($value) && $value !== '0') {
                continue;
            }

            // Type validation
            if (isset($fieldRules['type'])) {
                switch ($fieldRules['type']) {
                    case 'int':
                        if (!is_numeric($value)) {
                            $errors[$field] = "$field must be an integer";
                        } else {
                            $value = (int)$value;
                        }
                        break;

                    case 'float':
                        if (!is_numeric($value)) {
                            $errors[$field] = "$field must be a number";
                        } else {
                            $value = (float)$value;
                        }
                        break;

                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = "$field must be a valid email";
                        }
                        break;

                    case 'date':
                        $date = \DateTime::createFromFormat('Y-m-d', $value);
                        if (!$date || $date->format('Y-m-d') !== $value) {
                            $errors[$field] = "$field must be a valid date (Y-m-d)";
                        }
                        break;
                }
            }

            // Min/max validation
            if (isset($fieldRules['min']) && $value < $fieldRules['min']) {
                $errors[$field] = "$field must be at least {$fieldRules['min']}";
            }

            if (isset($fieldRules['max']) && $value > $fieldRules['max']) {
                $errors[$field] = "$field must not exceed {$fieldRules['max']}";
            }

            $validated[$field] = $value;
        }

        if (!empty($errors)) {
            $this->error('Validation failed', $errors, 400);
        }

        return $validated;
    }

    /**
     * Render view with layout
     *
     * @param string $view View path relative to module root
     * @param array $data Data to pass to view
     */
    protected function render(string $view, array $data = []): void
    {
        // Generate CSRF token if not exists
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        // Add CSRF token to data
        $data['csrfToken'] = $_SESSION['csrf_token'];

        // Extract data to make variables available in view
        extract($data);

        $viewPath = BANK_TRANSACTIONS_MODULE_PATH . '/' . $view;
        if (!file_exists($viewPath)) {
            $this->abort(500, "View not found: $view");
        }

        // Capture view output into $content variable
        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        // Now render with layout
        $layout = BANK_TRANSACTIONS_MODULE_PATH . '/views/layout.php';
        if (file_exists($layout)) {
            require $layout;
        } else {
            echo $content;
        }
        exit;
    }    /**
     * Return JSON response
     *
     * @param mixed $data Response data
     * @param int $status HTTP status code
     */
    protected function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Return success JSON response
     *
     * @param mixed $data Response data
     * @param string|null $message Success message
     */
    protected function success($data = [], ?string $message = null): void
    {
        $response = [
            'success' => true,
            'data' => $data
        ];

        if ($message) {
            $response['message'] = $message;
        }

        $this->json($response);
    }

    /**
     * Return error JSON response
     *
     * @param string $message Error message
     * @param mixed $details Error details
     * @param int $status HTTP status code
     */
    protected function error(string $message, $details = null, int $status = 400): void
    {
        $response = [
            'success' => false,
            'error' => [
                'message' => $message
            ]
        ];

        if ($details) {
            $response['error']['details'] = $details;
        }

        $this->json($response, $status);
    }

    /**
     * Check if request is AJAX
     *
     * @return bool
     */
    protected function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Get request method
     *
     * @return string
     */
    protected function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Redirect to URL
     *
     * @param string $url URL to redirect to
     */
    protected function redirect(string $url): void
    {
        header("Location: $url");
        exit;
    }

    /**
     * Redirect to login page
     */
    protected function redirectToLogin(): void
    {
        $this->redirect('/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }

    /**
     * Abort with error
     *
     * @param int $status HTTP status code
     * @param string $message Error message
     */
    protected function abort(int $status, string $message): void
    {
        http_response_code($status);

        if ($this->isAjax()) {
            $this->error($message, null, $status);
        } else {
            die($message);
        }
    }

    /**
     * Log action to audit trail
     *
     * @param string $entityType Entity type (transaction, payment, order)
     * @param int $entityId Entity ID
     * @param string $action Action performed
     * @param array|null $details Additional details
     */
    protected function logAudit(string $entityType, int $entityId, string $action, ?array $details = null): void
    {
        try {
            $sql = "INSERT INTO bank_audit_trail (
                        entity_type, entity_id, action, user_id, user_name,
                        details, ip_address, user_agent, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $entityType,
                $entityId,
                $action,
                $this->currentUserId,
                $this->currentUser['name'] ?? 'Unknown',
                $details ? json_encode($details) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the request
            error_log("Audit log failed: " . $e->getMessage());
        }
    }
}
