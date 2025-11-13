<?php
/**
 * Cache Middleware
 *
 * Caches responses for improved performance
 */

namespace App\Middleware;

class CacheMiddleware
{
    private $cacheDir;
    private $ttl = 3600; // 1 hour default

    public function __construct()
    {
        $this->cacheDir = sys_get_temp_dir() . '/vapeultra_cache';

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Handle incoming request
     */
    public function handle($request, $next)
    {
        // Only cache GET requests
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $next($request);
        }

        // Don't cache authenticated requests (for now)
        if (isset($_SESSION['user_id'])) {
            return $next($request);
        }

        // Generate cache key
        $cacheKey = $this->getCacheKey();

        // Check if cached response exists
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            // Add cache header
            header('X-Cache: HIT');
            return $cached;
        }

        // No cache - continue to route
        ob_start();
        $response = $next($request);
        $output = ob_get_clean();

        // Cache the output
        $this->saveToCache($cacheKey, $output);

        // Add cache header
        header('X-Cache: MISS');

        echo $output;
        return $response;
    }

    /**
     * Generate cache key
     */
    private function getCacheKey()
    {
        $uri = $_SERVER['REQUEST_URI'];
        return md5($uri);
    }

    /**
     * Get from cache
     */
    private function getFromCache($key)
    {
        $filename = $this->cacheDir . '/' . $key;

        if (!file_exists($filename)) {
            return null;
        }

        // Check if expired
        if (filemtime($filename) < (time() - $this->ttl)) {
            unlink($filename);
            return null;
        }

        return file_get_contents($filename);
    }

    /**
     * Save to cache
     */
    private function saveToCache($key, $content)
    {
        $filename = $this->cacheDir . '/' . $key;
        file_put_contents($filename, $content, LOCK_EX);
    }

    /**
     * Clear cache
     */
    public function clearCache()
    {
        $files = glob($this->cacheDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
