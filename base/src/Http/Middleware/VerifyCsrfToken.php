<?php
/**
 * CSRF Token Verification Middleware
 *
 * Validates CSRF tokens on POST/PUT/DELETE requests
 *
 * @package CIS\Base\Http\Middleware
 */

declare(strict_types=1);

namespace CIS\Base\Http\Middleware;

use CIS\Base\Core\Application;
use CIS\Base\Http\Request;
use CIS\Base\Http\Response;

class VerifyCsrfToken
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Handle incoming request
     */
    public function handle(Request $request): ?Response
    {
        // Check if CSRF protection is enabled
        if (!$this->app->config('security.csrf.enabled', true)) {
            return null;
        }

        // Only verify on state-changing methods
        if (!in_array($request->method(), ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return null;
        }

        // Check exclusion patterns
        $excludeUris = $this->app->config('security.csrf.exclude_uris', []);
        $endpoint = $request->query('endpoint', '');

        foreach ($excludeUris as $pattern) {
            if (fnmatch($pattern, $endpoint)) {
                return null; // Excluded from CSRF verification
            }
        }

        // Get token from request
        $token = $this->getTokenFromRequest($request);

        if (!$token) {
            $this->app->logger()->warning('CSRF token missing', [
                'endpoint' => $endpoint,
                'method' => $request->method(),
                'ip' => $request->ip(),
            ]);

            return Response::json([
                'error' => 'CSRF token mismatch',
                'message' => 'The request could not be verified. Please refresh and try again.',
            ], 419);
        }

        // Start session to get stored token
        if (session_status() === PHP_SESSION_NONE) {
            $this->app->session()->start();
        }

        $sessionToken = $this->app->session()->get('csrf_token');

        // Generate token if it doesn't exist
        if (!$sessionToken) {
            $sessionToken = $this->generateToken();
            $this->app->session()->set('csrf_token', $sessionToken);
        }

        // Verify token
        if (!hash_equals($sessionToken, $token)) {
            $this->app->logger()->warning('CSRF token invalid', [
                'endpoint' => $endpoint,
                'method' => $request->method(),
                'ip' => $request->ip(),
            ]);

            return Response::json([
                'error' => 'CSRF token mismatch',
                'message' => 'The request could not be verified. Please refresh and try again.',
            ], 419);
        }

        return null; // Token is valid, continue
    }

    /**
     * Get CSRF token from request
     */
    private function getTokenFromRequest(Request $request): ?string
    {
        $tokenName = $this->app->config('security.csrf.token_name', '_token');
        $headerName = $this->app->config('security.csrf.header_name', 'X-CSRF-TOKEN');

        // Check POST data
        $token = $request->post($tokenName);

        // Check header
        if (!$token) {
            $token = $request->header($headerName);
        }

        // Check JSON body
        if (!$token && $request->isJson()) {
            $json = $request->json();
            $token = $json[$tokenName] ?? null;
        }

        return $token;
    }

    /**
     * Generate CSRF token
     */
    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Get current CSRF token (for views)
     */
    public static function token(Application $app): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            $app->session()->start();
        }

        $token = $app->session()->get('csrf_token');

        if (!$token) {
            $token = bin2hex(random_bytes(32));
            $app->session()->set('csrf_token', $token);
        }

        return $token;
    }
}
