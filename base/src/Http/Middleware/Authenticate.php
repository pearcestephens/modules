<?php

/**
 * Authentication Middleware.
 *
 * Ensures user is authenticated before accessing protected routes
 */

declare(strict_types=1);

namespace CIS\Base\Http\Middleware;

use CIS\Base\Core\Application;
use CIS\Base\Http\Request;
use CIS\Base\Http\Response;

use const PHP_SESSION_NONE;

class Authenticate
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Handle incoming request.
     */
    public function handle(Request $request): ?Response
    {
        // Check if auth is required
        if (!$this->app->config('security.auth.enabled', true)) {
            return null; // Auth disabled, continue
        }

        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            $this->app->session()->start();
        }

        // Check if user is authenticated
        $userId = $this->app->session()->get('auth_user_id');

        if (!$userId) {
            // Not authenticated
            $this->app->logger()->info('Authentication required', [
                'endpoint' => $request->query('endpoint'),
                'ip'       => $request->ip(),
            ]);

            if ($request->isAjax() || $request->isJson()) {
                // Return JSON response for AJAX/API requests
                return Response::unauthorized([
                    'message'  => 'Authentication required',
                    'redirect' => '/login',
                ]);
            }

            // Redirect to login for web requests
            return Response::redirect('/login?return=' . urlencode($request->fullUrl()));
        }

        // User is authenticated, add to request context
        $_SESSION['userID'] = $userId;

        return null; // Continue to next middleware
    }
}
