<?php

/**
 * Admin Only Middleware.
 *
 * Ensures user has admin privileges
 */

declare(strict_types=1);

namespace CIS\Base\Http\Middleware;

use CIS\Base\Core\Application;
use CIS\Base\Http\Request;
use CIS\Base\Http\Response;

use function in_array;

class AdminOnly
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
        // Get current user ID from session
        $userId = $_SESSION['userID'] ?? null;

        if (!$userId) {
            return Response::unauthorized([
                'error'   => 'Authentication required',
                'message' => 'You must be logged in to access this resource.',
            ]);
        }

        // Check if user is admin
        $adminUsers = $this->app->config('security.admin_users', [1]);

        if (!in_array((int) $userId, $adminUsers, true)) {
            $this->app->logger()->warning('Admin access denied', [
                'user_id'  => $userId,
                'endpoint' => $request->query('endpoint'),
                'ip'       => $request->ip(),
            ]);

            return Response::forbidden([
                'error'   => 'Access denied',
                'message' => 'You do not have permission to access this resource.',
            ]);
        }

        return null; // User is admin, continue
    }
}
