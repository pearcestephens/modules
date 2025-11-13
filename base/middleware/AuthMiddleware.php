<?php
/**
 * Authentication Middleware
 *
 * Verifies user is authenticated and has valid session
 */

namespace App\Middleware;

class AuthMiddleware
{
    /**
     * Handle incoming request
     */
    public function handle($request, $next)
    {
        // Check if user is authenticated (CIS uses 'userID' not 'user_id')
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            // AJAX request - return JSON error
            if ($this->isAjaxRequest()) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => 'Authentication required'
                ]);
                exit;
            }

            // Regular request - redirect to login
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: /login');
            exit;
        }

        // Verify session is still valid
        if ($this->isSessionExpired()) {
            $this->destroySession();

            if ($this->isAjaxRequest()) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => 'Session expired'
                ]);
                exit;
            }

            header('Location: /login?expired=1');
            exit;
        }

        // Update last activity time
        $_SESSION['last_activity'] = time();

        // Continue to next middleware/route
        return $next($request);
    }

    /**
     * Check if session has expired
     */
    private function isSessionExpired()
    {
        $timeout = 7200; // 2 hours

        if (isset($_SESSION['last_activity']) &&
            (time() - $_SESSION['last_activity']) > $timeout) {
            return true;
        }

        return false;
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
     * Destroy session
     */
    private function destroySession()
    {
        $_SESSION = [];

        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }

        session_destroy();
    }
}
