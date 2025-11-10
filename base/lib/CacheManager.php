<?php
/**
 * Cache Manager
 * 
 * Simple file-based caching system with TTL support
 */

namespace CIS\Base;

class CacheManager {
    private static $cacheDir;
    private static $defaultTtl = 3600; // 1 hour
    
    /**
     * Initialize cache manager
     */
    public static function init(string $cacheDir = null): void {
        self::$cacheDir = $cacheDir ?? __DIR__ . '/../../../private_html/cache';
        
        if (!file_exists(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }
    
    /**
     * Get cache key path
     */
    private static function getPath(string $key): string {
        return self::$cacheDir . '/' . md5($key) . '.cache';
    }
    
    /**
     * Get cached value
     */
    public static function get(string $key, $default = null) {
        $path = self::getPath($key);
        
        if (!file_exists($path)) {
            return $default;
        }
        
        $data = unserialize(file_get_contents($path));
        
        // Check expiry
        if ($data['expires_at'] < time()) {
            self::forget($key);
            return $default;
        }
        
        return $data['value'];
    }
    
    /**
     * Store value in cache
     */
    public static function put(string $key, $value, int $ttl = null): bool {
        $ttl = $ttl ?? self::$defaultTtl;
        
        $data = [
            'value' => $value,
            'expires_at' => time() + $ttl
        ];
        
        return file_put_contents(self::getPath($key), serialize($data)) !== false;
    }
    
    /**
     * Store value forever (1 year)
     */
    public static function forever(string $key, $value): bool {
        return self::put($key, $value, 31536000);
    }
    
    /**
     * Check if key exists
     */
    public static function has(string $key): bool {
        return self::get($key) !== null;
    }
    
    /**
     * Remove key from cache
     */
    public static function forget(string $key): bool {
        $path = self::getPath($key);
        
        if (file_exists($path)) {
            return unlink($path);
        }
        
        return true;
    }
    
    /**
     * Clear all cache
     */
    public static function flush(): int {
        $count = 0;
        $files = glob(self::$cacheDir . '/*.cache');
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Remember value (get or store)
     */
    public static function remember(string $key, callable $callback, int $ttl = null) {
        $value = self::get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        self::put($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Clean expired cache entries
     */
    public static function cleanExpired(): int {
        $count = 0;
        $files = glob(self::$cacheDir . '/*.cache');
        
        foreach ($files as $file) {
            $data = unserialize(file_get_contents($file));
            
            if ($data['expires_at'] < time()) {
                if (unlink($file)) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Get cache statistics
     */
    public static function getStats(): array {
        $files = glob(self::$cacheDir . '/*.cache');
        $totalSize = 0;
        $expired = 0;
        
        foreach ($files as $file) {
            $totalSize += filesize($file);
            
            $data = unserialize(file_get_contents($file));
            if ($data['expires_at'] < time()) {
                $expired++;
            }
        }
        
        return [
            'total_entries' => count($files),
            'expired_entries' => $expired,
            'total_size' => $totalSize,
            'formatted_size' => self::formatBytes($totalSize)
        ];
    }
    
    /**
     * Format bytes to human readable
     */
    private static function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
