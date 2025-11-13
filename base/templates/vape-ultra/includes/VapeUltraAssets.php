<?php
/**
 * VapeUltra Dynamic Asset Auto-Loader
 *
 * Automatically discovers and loads CSS/JS files from theme and modules
 * Supports numbered ordering (01_, 02_, etc.) for precise load sequence
 * Caches asset discovery for performance
 *
 * Usage:
 *   $loader = new VapeUltraAssets();
 *   $css = $loader->getCSS();
 *   $js = $loader->getJS();
 *
 * @package VapeUltra
 * @version 2.0
 */

class VapeUltraAssets {

    private $themePath;
    private $publicPath;
    private $cache = [];
    private $cacheEnabled = true;
    private $cacheFile;

    /**
     * Constructor
     */
    public function __construct($themePath = null, $publicPath = null) {
        $this->themePath = $themePath ?: dirname(__DIR__);
        $this->publicPath = $publicPath ?: dirname(__DIR__, 5) . '/public_html';
        $this->cacheFile = sys_get_temp_dir() . '/vapeultra_assets_' . md5($this->themePath) . '.json';
        $this->loadCache();
    }

    /**
     * Get all CSS files in load order
     *
     * @param array $additionalPaths Additional module paths to scan
     * @return array Array of CSS URLs
     */
    public function getCSS($additionalPaths = []) {
        $cacheKey = 'css_' . md5(serialize($additionalPaths));

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $assets = [];

        // 1. Theme core CSS
        $assets = array_merge($assets, $this->scanAssets($this->themePath . '/assets/css', 'css', '/modules/base/templates/vape-ultra/assets/css/'));

        // 2. Module CSS
        foreach ($additionalPaths as $modulePath) {
            $moduleName = basename($modulePath);
            $cssPath = $modulePath . '/assets/css';
            $urlPrefix = "/modules/{$moduleName}/assets/css/";

            $assets = array_merge($assets, $this->scanAssets($cssPath, 'css', $urlPrefix));
        }

        // Sort by priority
        $sorted = $this->sortAssets($assets);
        $urls = array_column($sorted, 'url');

        $this->cache[$cacheKey] = $urls;
        $this->saveCache();

        return $urls;
    }

    /**
     * Get all JS files in load order
     *
     * @param array $additionalPaths Additional module paths to scan
     * @return array Array of JS URLs
     */
    public function getJS($additionalPaths = []) {
        $cacheKey = 'js_' . md5(serialize($additionalPaths));

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $assets = [];

        // 1. Theme core JS
        $assets = array_merge($assets, $this->scanAssets($this->themePath . '/assets/js', 'js', '/modules/base/templates/vape-ultra/assets/js/'));

        // 2. Module JS
        foreach ($additionalPaths as $modulePath) {
            $moduleName = basename($modulePath);
            $jsPath = $modulePath . '/assets/js';
            $urlPrefix = "/modules/{$moduleName}/assets/js/";

            $assets = array_merge($assets, $this->scanAssets($jsPath, 'js', $urlPrefix));
        }

        // Sort by priority
        $sorted = $this->sortAssets($assets);
        $urls = array_column($sorted, 'url');

        $this->cache[$cacheKey] = $urls;
        $this->saveCache();

        return $urls;
    }

    /**
     * Scan directory for asset files
     *
     * @param string $dir Physical directory path
     * @param string $extension File extension (css or js)
     * @param string $urlPrefix URL prefix for web access
     * @return array Array of asset info
     */
    private function scanAssets($dir, $extension, $urlPrefix) {
        $assets = [];

        if (!is_dir($dir)) {
            return $assets;
        }

        $files = scandir($dir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $fullPath = $dir . '/' . $file;

            if (is_file($fullPath) && pathinfo($fullPath, PATHINFO_EXTENSION) === $extension) {
                $assets[] = [
                    'filename' => $file,
                    'path' => $fullPath,
                    'url' => $urlPrefix . $file,
                    'priority' => $this->extractPriority($file),
                    'size' => filesize($fullPath),
                    'modified' => filemtime($fullPath)
                ];
            }
        }

        return $assets;
    }

    /**
     * Sort assets by priority (numbered prefix first, then alphabetical)
     *
     * @param array $assets Array of asset info
     * @return array Sorted array
     */
    private function sortAssets($assets) {
        usort($assets, function($a, $b) {
            // Compare priorities
            if ($a['priority'] !== $b['priority']) {
                // No priority (999) goes to end
                if ($a['priority'] === 999) return 1;
                if ($b['priority'] === 999) return -1;
                return $a['priority'] - $b['priority'];
            }

            // Same priority, sort alphabetically
            return strcmp($a['filename'], $b['filename']);
        });

        return $assets;
    }

    /**
     * Extract priority number from filename
     *
     * Examples:
     *   01_core.js -> 1
     *   02_toolkit.js -> 2
     *   99_final.js -> 99
     *   core.js -> 999 (no priority)
     *
     * @param string $filename
     * @return int Priority (1-99) or 999 if no prefix
     */
    private function extractPriority($filename) {
        if (preg_match('/^(\d+)[-_]/', $filename, $matches)) {
            return (int)$matches[1];
        }
        return 999; // No priority, load last
    }

    /**
     * Load cache from disk
     */
    private function loadCache() {
        if (!$this->cacheEnabled || !file_exists($this->cacheFile)) {
            return;
        }

        $data = json_decode(file_get_contents($this->cacheFile), true);

        if ($data && isset($data['timestamp'])) {
            // Cache valid for 1 hour
            if ((time() - $data['timestamp']) < 3600) {
                $this->cache = $data['cache'] ?? [];
            }
        }
    }

    /**
     * Save cache to disk
     */
    private function saveCache() {
        if (!$this->cacheEnabled) return;

        $data = [
            'timestamp' => time(),
            'cache' => $this->cache
        ];

        file_put_contents($this->cacheFile, json_encode($data));
    }

    /**
     * Clear cache
     */
    public function clearCache() {
        $this->cache = [];
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    /**
     * Get cache statistics
     *
     * @return array Cache stats
     */
    public function getStats() {
        return [
            'cache_enabled' => $this->cacheEnabled,
            'cache_file' => $this->cacheFile,
            'cache_exists' => file_exists($this->cacheFile),
            'cache_size' => file_exists($this->cacheFile) ? filesize($this->cacheFile) : 0,
            'cached_keys' => count($this->cache),
            'theme_path' => $this->themePath,
            'public_path' => $this->publicPath
        ];
    }

    /**
     * Get all assets (CSS + JS) for debugging
     *
     * @return array
     */
    public function debug($additionalPaths = []) {
        return [
            'css' => $this->getCSS($additionalPaths),
            'js' => $this->getJS($additionalPaths),
            'stats' => $this->getStats()
        ];
    }
}

// Global helper function
if (!function_exists('vapeultra_assets')) {
    function vapeultra_assets() {
        static $instance = null;
        if ($instance === null) {
            $instance = new VapeUltraAssets();
        }
        return $instance;
    }
}
