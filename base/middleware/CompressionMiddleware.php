<?php
/**
 * Compression Middleware
 *
 * Compresses responses with gzip for faster delivery
 */

namespace App\Middleware;

class CompressionMiddleware
{
    /**
     * Handle incoming request
     */
    public function handle($request, $next)
    {
        // Check if client accepts gzip
        if (!$this->clientAcceptsGzip()) {
            return $next($request);
        }

        // Check if already compressed
        if ($this->isAlreadyCompressed()) {
            return $next($request);
        }

        // Start output buffering with gzip
        ob_start('ob_gzhandler');

        // Continue to route
        $response = $next($request);

        // Flush compressed output
        ob_end_flush();

        return $response;
    }

    /**
     * Check if client accepts gzip
     */
    private function clientAcceptsGzip()
    {
        return isset($_SERVER['HTTP_ACCEPT_ENCODING']) &&
               strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false;
    }

    /**
     * Check if response is already compressed
     */
    private function isAlreadyCompressed()
    {
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Encoding:') === 0) {
                return true;
            }
        }

        return false;
    }
}
