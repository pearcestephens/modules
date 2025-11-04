<?php
/**
 * Simple File Cache Service
 *
 * Provides basic caching for rate limiting and geo data
 *
 * @package CIS\Base\Services
 */

declare(strict_types=1);

namespace CIS\Base\Services;

class CacheService
{
    private string $cachePath;

    public function __construct(string $cachePath = null)
    {
        $this->cachePath = $cachePath ?? sys_get_temp_dir() . '/cis_cache';

        // Create cache directory if it doesn't exist
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    /**
     * Get value from cache
     */
    public function get(string $key)
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return null;
        }

        $data = @file_get_contents($file);

        if ($data === false) {
            return null;
        }

        $data = @unserialize($data);

        if ($data === false || !is_array($data)) {
            return null;
        }

        // Check if expired
        if (isset($data['expires_at']) && $data['expires_at'] < time()) {
            @unlink($file);
            return null;
        }

        return $data['value'] ?? null;
    }

    /**
     * Set value in cache
     */
    public function set(string $key, $value, int $ttl = 3600): bool
    {
        $file = $this->getFilePath($key);

        $data = [
            'value' => $value,
            'expires_at' => time() + $ttl,
        ];

        return @file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }

    /**
     * Delete value from cache
     */
    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);

        if (file_exists($file)) {
            return @unlink($file);
        }

        return true;
    }

    /**
     * Check if key exists in cache
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Clear all cache
     */
    public function clear(): bool
    {
        $files = glob($this->cachePath . '/*');

        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        return true;
    }

    /**
     * Increment counter (for rate limiting)
     */
    public function increment(string $key, int $ttl = 60): int
    {
        $current = (int)$this->get($key);
        $new = $current + 1;
        $this->set($key, $new, $ttl);

        return $new;
    }

    /**
     * Get cache file path for key
     */
    private function getFilePath(string $key): string
    {
        $hash = md5($key);
        return $this->cachePath . '/' . $hash . '.cache';
    }

    /**
     * Clean expired cache files
     */
    public function cleanExpired(): int
    {
        $cleaned = 0;
        $files = glob($this->cachePath . '/*.cache');

        foreach ($files as $file) {
            $data = @file_get_contents($file);

            if ($data === false) {
                continue;
            }

            $data = @unserialize($data);

            if (isset($data['expires_at']) && $data['expires_at'] < time()) {
                @unlink($file);
                $cleaned++;
            }
        }

        return $cleaned;
    }
}
