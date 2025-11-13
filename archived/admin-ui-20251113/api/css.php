<?php
/**
 * CSSAPI - CSS File Management with Version Control
 *
 * Features:
 * - Git-style version control
 * - Rollback capabilities
 * - Diff viewer
 * - Minification
 * - AI-powered optimization suggestions
 *
 * @package CIS\AdminUI\API
 * @version 6.0.0
 */

require_once __DIR__ . '/../lib/BaseAPI.php';

class CSSAPI extends BaseAPI {

    private $cssPath;
    private $versionsPath;
    private $cisLogger;
    private $aiEnabled = false;

    public function __construct($config = []) {
        parent::__construct($config);

        $this->cssPath = $this->config['base_path'] . '/css';
        $this->versionsPath = $this->config['base_path'] . '/css-versions';

        $this->ensureDirectory($this->cssPath . '/core');
        $this->ensureDirectory($this->cssPath . '/dependencies');
        $this->ensureDirectory($this->cssPath . '/custom');
        $this->ensureDirectory($this->versionsPath);

        // Initialize CIS Logger
        $this->initializeCISLogger();

        // Check if AI is available
        $this->checkAIAvailability();
    }

    /**
     * Initialize CIS Logger for comprehensive logging
     */
    private function initializeCISLogger() {
        $loggerPath = dirname(dirname(dirname(__DIR__))) . '/base/lib/Log.php';

        if (file_exists($loggerPath)) {
            require_once $loggerPath;
            $this->cisLogger = new \Base\Lib\Log();
            $this->cisLogger->info('CSSAPI initialized', [
                'module' => 'admin-ui',
                'component' => 'asset-control-center',
                'version' => '6.0.0'
            ]);
        } else {
            // Fallback to basic logging
            $this->logError('CIS Logger not found, using fallback logging');
        }
    }

    /**
     * Check AI availability and initialize
     */
    private function checkAIAvailability() {
        $aiConfigFile = $this->config['base_path'] . '/config/ai-agent-config.json';

        if (file_exists($aiConfigFile)) {
            $aiConfig = json_decode(file_get_contents($aiConfigFile), true);
            $this->aiEnabled = $aiConfig['enabled'] ?? false;

            if ($this->cisLogger) {
                $this->cisLogger->info('AI availability checked', [
                    'ai_enabled' => $this->aiEnabled,
                    'ai_model' => $aiConfig['model'] ?? 'unknown'
                ]);
            }
        }
    }

    /**
     * Log with AI context
     */
    private function logWithAI($message, $context = [], $level = 'info') {
        $context['ai_enabled'] = $this->aiEnabled;
        $context['timestamp'] = microtime(true);

        if ($this->cisLogger) {
            $this->cisLogger->$level($message, $context);
        } else {
            $this->logError($message, $context);
        }
    }

    /**
     * Save CSS version with AI analysis
     */
    protected function handleSaveCssVersion($data) {
        $startTime = microtime(true);

        // Accept both css_file/css_content and file/content for compatibility
        $file = $data['css_file'] ?? $data['file'] ?? null;
        $content = $data['css_content'] ?? $data['content'] ?? null;

        if (!$file || !$content) {
            throw new Exception('Missing required fields: css_file and css_content');
        }

        $message = $data['message'] ?? 'CSS update';

        // Validate file is in custom directory (version control only custom CSS)
        if (strpos($file, '/custom/') === false && strpos($file, 'custom/') !== 0) {
            throw new Exception('Can only version control custom CSS files');
        }

        $this->logWithAI('Saving CSS version', [
            'file' => $file,
            'size' => strlen($content),
            'message' => $message
        ]);

        $fullPath = $this->cssPath . '/' . ltrim($file, '/');

        // Create version snapshot
        $versionInfo = $this->createCSSVersion($fullPath, $content, $message);

        // AI Analysis if enabled
        $aiSuggestions = null;
        if ($this->aiEnabled) {
            $aiSuggestions = $this->analyzeCSS($content);
            $versionInfo['ai_analysis'] = $aiSuggestions;
        }

        $duration = microtime(true) - $startTime;

        $this->logWithAI('CSS version saved successfully', [
            'file' => $file,
            'version_id' => $versionInfo['id'],
            'duration' => round($duration, 3),
            'ai_suggestions' => $aiSuggestions !== null
        ]);

        return $this->success($versionInfo, 'CSS version saved successfully', [
            'duration' => round($duration, 3),
            'file_size' => strlen($content)
        ]);
    }

    /**
     * Create CSS version snapshot
     */
    private function createCSSVersion($filePath, $content, $message) {
        // Save current version
        file_put_contents($filePath, $content);

        // Create version snapshot
        $versionId = time() . '_' . substr(md5($content), 0, 8);
        $fileName = basename($filePath);
        $versionDir = $this->versionsPath . '/' . str_replace('.css', '', $fileName);

        $this->ensureDirectory($versionDir);

        $versionFile = $versionDir . '/' . $versionId . '.css';
        file_put_contents($versionFile, $content);

        // Save metadata
        $meta = [
            'id' => $versionId,
            'file' => $fileName,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'size' => strlen($content),
            'lines' => substr_count($content, "\n") + 1,
            'hash' => md5($content),
            'user' => $_SESSION['user_name'] ?? 'System'
        ];

        $metaFile = $versionDir . '/' . $versionId . '.json';
        file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT));

        // Cleanup old versions (keep last 50)
        $this->cleanupOldVersions($versionDir, 50);

        return $meta;
    }

    /**
     * AI-powered CSS analysis
     */
    private function analyzeCSS($content) {
        if (!$this->aiEnabled) {
            return null;
        }

        $suggestions = [];

        // Check for common issues
        if (preg_match_all('/!important/i', $content, $matches)) {
            $suggestions[] = [
                'type' => 'warning',
                'message' => 'Found ' . count($matches[0]) . ' !important declarations',
                'suggestion' => 'Consider refactoring to avoid !important',
                'severity' => 'medium'
            ];
        }

        // Check for unused prefixes
        if (preg_match_all('/-webkit-|-moz-|-ms-|-o-/', $content, $matches)) {
            $suggestions[] = [
                'type' => 'info',
                'message' => 'Found ' . count($matches[0]) . ' vendor prefixes',
                'suggestion' => 'Consider using autoprefixer for automatic prefix management',
                'severity' => 'low'
            ];
        }

        // Check for color inconsistencies
        if (preg_match_all('/#[0-9a-fA-F]{6}/', $content, $colorMatches)) {
            $uniqueColors = array_unique($colorMatches[0]);
            if (count($uniqueColors) > 15) {
                $suggestions[] = [
                    'type' => 'warning',
                    'message' => 'Using ' . count($uniqueColors) . ' different colors',
                    'suggestion' => 'Consider using CSS variables for color consistency',
                    'severity' => 'medium'
                ];
            }
        }

        // Log AI analysis
        $this->logWithAI('AI CSS analysis completed', [
            'suggestions_count' => count($suggestions),
            'content_size' => strlen($content)
        ]);

        return $suggestions;
    }

    /**
     * Get CSS versions history
     */
    protected function handleGetCssVersions($data) {
        $file = $data['css_file'] ?? $data['file'] ?? null;

        if (!$file) {
            throw new Exception('Missing required field: css_file');
        }

        $fileName = basename($file);
        $versionDir = $this->versionsPath . '/' . str_replace('.css', '', $fileName);

        if (!is_dir($versionDir)) {
            return $this->success([], 'No versions found', ['count' => 0]);
        }

        $versions = [];
        foreach (glob($versionDir . '/*.json') as $metaFile) {
            $meta = json_decode(file_get_contents($metaFile), true);
            $versions[] = $meta;
        }

        // Sort by timestamp descending
        usort($versions, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        $this->logWithAI('Retrieved CSS versions', [
            'file' => $file,
            'count' => count($versions)
        ]);

        return $this->success($versions, 'Versions retrieved successfully', [
            'count' => count($versions)
        ]);
    }

    /**
     * Rollback to previous CSS version
     */
    protected function handleRollbackCss($data) {
        $this->validateRequired($data, ['file', 'version_id']);

        $file = $data['file'];
        $versionId = $data['version_id'];
        $fileName = basename($file);
        $versionDir = $this->versionsPath . '/' . str_replace('.css', '', $fileName);

        $versionFile = $versionDir . '/' . $versionId . '.css';
        $metaFile = $versionDir . '/' . $versionId . '.json';

        if (!file_exists($versionFile)) {
            return $this->error('Version not found: ' . $versionId, 'VERSION_NOT_FOUND');
        }

        $content = file_get_contents($versionFile);
        $meta = json_decode(file_get_contents($metaFile), true);

        // Save current state before rollback
        $currentPath = $this->cssPath . '/' . ltrim($file, '/');
        if (file_exists($currentPath)) {
            $currentContent = file_get_contents($currentPath);
            $this->createCSSVersion($currentPath, $currentContent, 'Auto-backup before rollback');
        }

        // Restore old version
        file_put_contents($currentPath, $content);

        $this->logWithAI('CSS rollback completed', [
            'file' => $file,
            'version_id' => $versionId,
            'restored_size' => strlen($content)
        ]);

        return $this->success([
            'content' => $content,
            'version' => $meta
        ], 'Rolled back to version ' . $versionId);
    }

    /**
     * Generate diff between two versions
     */
    protected function handleDiffCss($data) {
        $this->validateRequired($data, ['file', 'version1', 'version2']);

        $file = $data['file'];
        $version1 = $data['version1'];
        $version2 = $data['version2'];
        $fileName = basename($file);
        $versionDir = $this->versionsPath . '/' . str_replace('.css', '', $fileName);

        $content1 = file_get_contents($versionDir . '/' . $version1 . '.css');
        $content2 = file_get_contents($versionDir . '/' . $version2 . '.css');

        $diff = $this->generateDiff($content1, $content2);

        $this->logWithAI('Generated CSS diff', [
            'file' => $file,
            'version1' => $version1,
            'version2' => $version2,
            'changes' => count($diff)
        ]);

        return $this->success($diff, 'Diff generated successfully', [
            'changes' => count($diff)
        ]);
    }

    /**
     * Generate line-by-line diff
     */
    private function generateDiff($content1, $content2) {
        $lines1 = explode("\n", $content1);
        $lines2 = explode("\n", $content2);

        $diff = [];
        $maxLines = max(count($lines1), count($lines2));

        for ($i = 0; $i < $maxLines; $i++) {
            $line1 = $lines1[$i] ?? '';
            $line2 = $lines2[$i] ?? '';

            if ($line1 !== $line2) {
                if ($line1 && !$line2) {
                    $diff[] = ['type' => 'removed', 'line' => $i + 1, 'content' => $line1];
                } elseif (!$line1 && $line2) {
                    $diff[] = ['type' => 'added', 'line' => $i + 1, 'content' => $line2];
                } else {
                    $diff[] = ['type' => 'changed', 'line' => $i + 1, 'old' => $line1, 'new' => $line2];
                }
            }
        }

        return $diff;
    }

    /**
     * List all CSS files
     */
    protected function handleListCssFiles($data) {
        $files = [
            'core' => $this->scanCSSDirectory($this->cssPath . '/core'),
            'dependencies' => $this->scanCSSDirectory($this->cssPath . '/dependencies'),
            'custom' => $this->scanCSSDirectory($this->cssPath . '/custom')
        ];

        $totalCount = count($files['core']) + count($files['dependencies']) + count($files['custom']);

        $this->logWithAI('Listed CSS files', [
            'total' => $totalCount,
            'core' => count($files['core']),
            'dependencies' => count($files['dependencies']),
            'custom' => count($files['custom'])
        ]);

        return $this->success($files, 'CSS files listed successfully', [
            'total' => $totalCount
        ]);
    }

    /**
     * Scan CSS directory
     */
    private function scanCSSDirectory($dir) {
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        foreach (glob($dir . '/*.css') as $file) {
            $files[] = [
                'name' => basename($file),
                'path' => str_replace($this->cssPath . '/', '', $file),
                'size' => filesize($file),
                'modified' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }

        return $files;
    }

    /**
     * Minify CSS
     */
    protected function handleMinifyCss($data) {
        $content = $data['css_content'] ?? $data['content'] ?? null;

        if (!$content) {
            throw new Exception('Missing required field: css_content');
        }

        $originalSize = strlen($content);

        // Simple CSS minification
        $minified = $this->minifyCSS($content);
        $minifiedSize = strlen($minified);
        $savings = $originalSize - $minifiedSize;
        $savingsPercent = round(($savings / $originalSize) * 100, 2);

        $this->logWithAI('CSS minified', [
            'original_size' => $originalSize,
            'minified_size' => $minifiedSize,
            'savings' => $savings,
            'savings_percent' => $savingsPercent
        ]);

        return $this->success([
            'minified' => $minified,
            'original_size' => $originalSize,
            'minified_size' => $minifiedSize,
            'savings' => $savings,
            'savings_percent' => $savingsPercent
        ], 'CSS minified successfully');
    }

    /**
     * Simple CSS minification
     */
    private function minifyCSS($css) {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);

        // Remove whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        $css = preg_replace('/\s+/', ' ', $css);

        // Remove spaces around operators
        $css = preg_replace('/\s*([:;,{}])\s*/', '$1', $css);

        // Remove last semicolon
        $css = preg_replace('/;([\s]?})/', '$1', $css);

        return trim($css);
    }

    /**
     * Cleanup old versions
     */
    private function cleanupOldVersions($versionDir, $maxVersions) {
        $versions = glob($versionDir . '/*.json');

        if (count($versions) <= $maxVersions) {
            return;
        }

        // Sort by modification time
        usort($versions, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        // Delete oldest versions
        $toDelete = array_slice($versions, 0, count($versions) - $maxVersions);

        foreach ($toDelete as $file) {
            $cssFile = str_replace('.json', '.css', $file);
            if (file_exists($cssFile)) {
                unlink($cssFile);
            }
            unlink($file);
        }

        $this->logWithAI('Cleaned up old versions', [
            'deleted' => count($toDelete),
            'kept' => $maxVersions
        ]);
    }
}

// Handle request if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    session_start();
    $api = new CSSAPI(['base_path' => dirname(__DIR__)]);
    $api->handleRequest();
}
