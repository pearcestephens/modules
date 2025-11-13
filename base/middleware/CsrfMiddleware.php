<?php
/**
 * CSRF Protection Middleware
 *
 * Protects against Cross-Site Request Forgery attacks
 */

namespace App\Middleware;

class CsrfMiddleware
{
    /**
     * Handle incoming request
     */
    public function handle($request, $next)
    {
        // Only check POST, PUT, PATCH, DELETE requests
        $method = $_SERVER['REQUEST_METHOD'];
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        // Generate token if not exists
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = $this->generateToken();
        }

        // Verify token
        $submittedToken = $this->getSubmittedToken();

        if (!$submittedToken || !$this->verifyToken($submittedToken)) {
            http_response_code(403);

            if ($this->isAjaxRequest()) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid CSRF token'
                ]);
            } else {
                echo 'CSRF token validation failed';
            }

            exit;
        }

        // Continue to next middleware
        return $next($request);
    }

    /**
     * Generate CSRF token
     */
    private function generateToken()
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Get submitted token from request
     */
    private function getSubmittedToken()
    {
        // Check header first (AJAX requests)
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        // Check POST data
        if (isset($_POST['csrf_token'])) {
            return $_POST['csrf_token'];
        }

        // Check JSON body
        $input = file_get_contents('php://input');
        if ($input) {
            $data = json_decode($input, true);
            if (isset($data['csrf_token'])) {
                return $data['csrf_token'];
            }
        }

        return null;
    }

    /**
     * Verify token matches session token
     */
    private function verifyToken($token)
    {
        return isset($_SESSION['csrf_token']) &&
               hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Check if request is AJAX
     */
    private function isAjaxRequest()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Get current token (for embedding in forms/meta tags)
     */
    public static function getToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}
