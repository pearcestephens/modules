<?php
/**
 * Module Registry Service
 *
 * Discovers, tracks, and manages all CIS modules
 *
 * @package CIS\Modules\ControlPanel
 * @version 1.0.0
 */

namespace ControlPanel\Services;

class ModuleRegistry {

    private $db;
    private $modulesPath;

    public function __construct($db) {
        $this->db = $db;
        $this->modulesPath = dirname(dirname(__DIR__));
    }

    /**
     * Discover all modules in the modules directory
     */
    public function discoverModules() {
        $modules = [];
        $dirs = scandir($this->modulesPath);

        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') continue;

            $modulePath = $this->modulesPath . '/' . $dir;

            if (!is_dir($modulePath)) continue;

            // Check for bootstrap.php or index.php
            $hasBootstrap = file_exists($modulePath . '/bootstrap.php');
            $hasIndex = file_exists($modulePath . '/index.php');

            if ($hasBootstrap || $hasIndex) {
                $moduleInfo = $this->extractModuleInfo($dir, $modulePath);
                $modules[$dir] = $moduleInfo;
            }
        }

        return $modules;
    }

    /**
     * Extract module metadata
     */
    private function extractModuleInfo($moduleName, $modulePath) {
        $info = [
            'name' => $moduleName,
            'display_name' => ucwords(str_replace(['-', '_'], ' ', $moduleName)),
            'path' => $modulePath,
            'version' => 'Unknown',
            'description' => '',
            'author' => '',
            'status' => 'active',
            'has_bootstrap' => file_exists($modulePath . '/bootstrap.php'),
            'has_index' => file_exists($modulePath . '/index.php'),
            'has_readme' => file_exists($modulePath . '/README.md'),
            'has_api' => is_dir($modulePath . '/api'),
            'has_views' => is_dir($modulePath . '/views'),
            'has_assets' => is_dir($modulePath . '/assets'),
            'has_database' => file_exists($modulePath . '/database/schema.sql'),
            'file_count' => 0,
            'size_bytes' => 0,
            'last_modified' => null,
            'dependencies' => []
        ];

        // Try to extract version from README or bootstrap
        $readmePath = $modulePath . '/README.md';
        if (file_exists($readmePath)) {
            $readme = file_get_contents($readmePath);
            if (preg_match('/version[:\s]+([0-9\.]+)/i', $readme, $matches)) {
                $info['version'] = $matches[1];
            }
            // Extract description
            if (preg_match('/#\s+(.+)/m', $readme, $matches)) {
                $info['description'] = trim($matches[1]);
            }
        }

        // Try to extract from bootstrap
        $bootstrapPath = $modulePath . '/bootstrap.php';
        if (file_exists($bootstrapPath)) {
            $bootstrap = file_get_contents($bootstrapPath);
            if (preg_match('/@version\s+([0-9\.]+)/i', $bootstrap, $matches)) {
                $info['version'] = $matches[1];
            }
            if (preg_match('/@author\s+(.+)/i', $bootstrap, $matches)) {
                $info['author'] = trim($matches[1]);
            }
        }

        // Count files and calculate size
        $this->calculateModuleStats($modulePath, $info);

        return $info;
    }

    /**
     * Calculate module statistics
     */
    private function calculateModuleStats($path, &$info) {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $fileCount = 0;
        $totalSize = 0;
        $latestMod = 0;

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $fileCount++;
                $totalSize += $file->getSize();
                $modTime = $file->getMTime();
                if ($modTime > $latestMod) {
                    $latestMod = $modTime;
                }
            }
        }

        $info['file_count'] = $fileCount;
        $info['size_bytes'] = $totalSize;
        $info['size_formatted'] = $this->formatBytes($totalSize);
        $info['last_modified'] = $latestMod > 0 ? date('Y-m-d H:i:s', $latestMod) : null;
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get module by name
     */
    public function getModule($moduleName) {
        $modules = $this->discoverModules();
        return $modules[$moduleName] ?? null;
    }

    /**
     * Save module registry to database
     */
    public function saveToDatabase($modules) {
        try {
            // Create table if not exists
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `cis_module_registry` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `module_name` VARCHAR(100) NOT NULL,
                    `display_name` VARCHAR(200) NOT NULL,
                    `version` VARCHAR(20) DEFAULT 'Unknown',
                    `description` TEXT,
                    `author` VARCHAR(200),
                    `status` ENUM('active','inactive','development') DEFAULT 'active',
                    `path` VARCHAR(500),
                    `file_count` INT(11) DEFAULT 0,
                    `size_bytes` BIGINT DEFAULT 0,
                    `last_modified` DATETIME,
                    `metadata` JSON,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_module` (`module_name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Insert/update each module
            foreach ($modules as $module) {
                $metadata = json_encode([
                    'has_bootstrap' => $module['has_bootstrap'],
                    'has_index' => $module['has_index'],
                    'has_readme' => $module['has_readme'],
                    'has_api' => $module['has_api'],
                    'has_views' => $module['has_views'],
                    'has_assets' => $module['has_assets'],
                    'has_database' => $module['has_database'],
                    'dependencies' => $module['dependencies']
                ]);

                $stmt = $this->db->prepare("
                    INSERT INTO cis_module_registry (
                        module_name, display_name, version, description, author,
                        status, path, file_count, size_bytes, last_modified, metadata
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        display_name = VALUES(display_name),
                        version = VALUES(version),
                        description = VALUES(description),
                        author = VALUES(author),
                        file_count = VALUES(file_count),
                        size_bytes = VALUES(size_bytes),
                        last_modified = VALUES(last_modified),
                        metadata = VALUES(metadata),
                        updated_at = CURRENT_TIMESTAMP
                ");

                $stmt->execute([
                    $module['name'],
                    $module['display_name'],
                    $module['version'],
                    $module['description'],
                    $module['author'],
                    $module['status'],
                    $module['path'],
                    $module['file_count'],
                    $module['size_bytes'],
                    $module['last_modified'],
                    $metadata
                ]);
            }

            return true;

        } catch (\Exception $e) {
            error_log("Save Module Registry Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get modules from database
     */
    public function getFromDatabase() {
        try {
            $stmt = $this->db->query("
                SELECT * FROM cis_module_registry
                ORDER BY display_name ASC
            ");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
