<?php
/**
 * VapeUltra Dynamic Asset Loader
 *
 * Automatically discovers and loads CSS/JS files from theme and modules
 * Supports numbered ordering (01_, 02_, etc.) for load sequence
 * Caches asset lists for performance
 *
 * @package VapeUltra
 * @version 2.0
 */

class VapeUltraAssetLoader {

    private static $instance = null;
    private $assetCache = [];
    private $cacheEnabled = true;
    private $cacheFile = '/tmp/vapeultra_assets_cache.json';

    /**
     * Singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - load cache if available
     */
    private function __construct() {
        if ($this->cacheEnabled && file_exists($this->cacheFile)) {
            $cache = json_decode(file_get_contents($this->cacheFile), true);
            if ($cache && isset($cache['timestamp']) && (time() - $cache['timestamp']) < 3600) {
                $this->assetCache = $cache['assets'] ?? [];
            }
        }
    }

    /**
     * Discover all CSS files in theme and modules
     *
     * @param string $themePath Base theme directory
     * @param array $modulePaths Array of module directories to scan
     * @return array Sorted array of CSS file URLs
     */
    public function discoverCSS($themePath, $modulePaths = []) {
        $cacheKey = 'css_' . md5($themePath . implode('', $modulePaths));

        if (isset($this->assetCache[$cacheKey])) {
            return $this->assetCache[$cacheKey];
        }

        $cssFiles = [];

        // 1. Core theme CSS (ordered)
        $themeCSS = $this->scanDirectory($themePath . '/assets/css', 'css', '/assets/vape-ultra/css/');
        $cssFiles = array_merge($cssFiles, $themeCSS);

        // 2. Module-specific CSS (ordered)
        foreach ($modulePaths as $modulePath) {
            $moduleCSS = $this->scanDirectory($modulePath . '/assets/css', 'css', '/modules/' . basename($modulePath) . '/assets/css/');
            $cssFiles = array_merge($cssFiles, $moduleCSS);
        }

        // Sort by filename (respects 01_, 02_ prefixes)
        usort($cssFiles, function($a, $b) {
            return strcmp($a['filename'], $b['filename']);
        });

        $urls = array_map(function($file) { return $file['url']; }, $cssFiles);

        $this->assetCache[$cacheKey] = $urls;
        $this->saveCache();

        return $urls;
    }

    /**
     * Discover all JS files in theme and modules
     *
     * @param string $themePath Base theme directory
     * @param array $modulePaths Array of module directories to scan
     * @return array Sorted array of JS file URLs
     */
    public function discoverJS($themePath, $modulePaths = []) {
        $cacheKey = 'js_' . md5($themePath . implode('', $modulePaths));

        if (isset($this->assetCache[$cacheKey])) {
            return $this->assetCache[$cacheKey];
        }

        $jsFiles = [];

        // 1. Core theme JS (ordered)
        $themeJS = $this->scanDirectory($themePath . '/assets/js', 'js', '/assets/vape-ultra/js/');
        $jsFiles = array_merge($jsFiles, $themeJS);

        // 2. Module-specific JS (ordered)
        foreach ($modulePaths as $modulePath) {
            $moduleJS = $this->scanDirectory($modulePath . '/assets/js', 'js', '/modules/' . basename($modulePath) . '/assets/js/');
            $jsFiles = array_merge($jsFiles, $moduleJS);
        }

        // Sort by priority:
        // 1. Core files first (no number prefix)
        // 2. Then numbered files (01_, 02_, etc.)
        // 3. Then alphabetical
        usort($jsFiles, function($a, $b) {
            $aNum = $this->extractNumber($a['filename']);
            $bNum = $this->extractNumber($b['filename']);

            if ($aNum === null && $bNum === null) {
                return strcmp($a['filename'], $b['filename']);
            }
            if ($aNum === null) return -1;
            if ($bNum === null) return 1;

            return $aNum - $bNum;
        });

        $urls = array_map(function($file) { return $file['url']; }, $jsFiles);

        $this->assetCache[$cacheKey] = $urls;
        $this->saveCache();

        return $urls;
    }

    /**
     * Scan directory for asset files
     *
     * @param string $dir Physical directory path
     * @param string $extension File extension (css or js)
     * @param string $urlPrefix URL prefix for web access
     * @return array Array of file info
     */
    private function scanDirectory($dir, $extension, $urlPrefix) {
        $files = [];

        if (!is_dir($dir)) {
            return $files;
        }

        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $fullPath = $dir . '/' . $item;

            if (is_file($fullPath) && pathinfo($fullPath, PATHINFO_EXTENSION) === $extension) {
                $files[] = [
                    'filename' => $item,
                    'path' => $fullPath,
                    'url' => $urlPrefix . $item,
                    'size' => filesize($fullPath),
                    'modified' => filemtime($fullPath)
                ];
            }
        }

        return $files;
    }

    /**
     * Extract number prefix from filename (e.g., "01_core.js" -> 1)
     *
     * @param string $filename
     * @return int|null
     */
    private function extractNumber($filename) {
        if (preg_match('/^(\d+)_/', $filename, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    /**
     * Save cache to disk
     */
    private function saveCache() {
        if (!$this->cacheEnabled) return;

        $data = [
            'timestamp' => time(),
            'assets' => $this->assetCache
        ];

        file_put_contents($this->cacheFile, json_encode($data));
    }

    /**
     * Clear asset cache
     */
    public function clearCache() {
        $this->assetCache = [];
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats() {
        return [
            'enabled' => $this->cacheEnabled,
            'file' => $this->cacheFile,
            'exists' => file_exists($this->cacheFile),
            'size' => file_exists($this->cacheFile) ? filesize($this->cacheFile) : 0,
            'entries' => count($this->assetCache)
        ];
    }
}

/**
 * Helper function - Get asset loader instance
 */
function vapeultra_assets() {
    return VapeUltraAssetLoader::getInstance();
}
