<?php
/**
 * JSAPI - JavaScript File Management with Version Control
 *
 * Features:
 * - Git-style version control for JS
 * - Minification & optimization
 * - ESLint-style analysis
 * - AI-powered code quality suggestions
 * - Bundle management
 *
 * @package CIS\AdminUI\API
 * @version 6.0.0
 */

require_once __DIR__ . '/../lib/BaseAPI.php';

class JSAPI extends BaseAPI {

    private $jsPath;
    private $versionsPath;
    private $buildPath;
    private $cisLogger;
    private $aiEnabled = false;

    public function __construct($config = []) {
        parent::__construct($config);

        $this->jsPath = $this->config['base_path'] . '/js';
        $this->versionsPath = $this->config['base_path'] . '/js-versions';
        $this->buildPath = $this->jsPath . '/build';

        $this->ensureDirectory($this->jsPath . '/vendors');
        $this->ensureDirectory($this->jsPath . '/modules');
        $this->ensureDirectory($this->buildPath);
        $this->ensureDirectory($this->versionsPath);

        // Initialize CIS Logger with AI context
        $this->initializeCISLogger();
        $this->checkAIAvailability();
    }

    /**
     * Initialize CIS Logger
     */
    private function initializeCISLogger() {
        $loggerPath = dirname(dirname(dirname(__DIR__))) . '/base/lib/Log.php';

        if (file_exists($loggerPath)) {
            require_once $loggerPath;
            $this->cisLogger = new \Base\Lib\Log();
            $this->cisLogger->info('JSAPI initialized', [
                'module' => 'admin-ui',
                'component' => 'js-manager',
                'version' => '6.0.0',
                'ai_ready' => true
            ]);
        }
    }

    /**
     * Check AI availability
     */
    private function checkAIAvailability() {
        $aiConfigFile = $this->config['base_path'] . '/config/ai-agent-config.json';

        if (file_exists($aiConfigFile)) {
            $aiConfig = json_decode(file_get_contents($aiConfigFile), true);
            $this->aiEnabled = $aiConfig['enabled'] ?? false;

            if ($this->cisLogger) {
                $this->cisLogger->info('AI engine status', [
                    'enabled' => $this->aiEnabled,
                    'model' => $aiConfig['model'] ?? 'none'
                ]);
            }
        }
    }

    /**
     * Log with AI context enrichment
     */
    private function logWithAI($message, $context = [], $level = 'info') {
        $enrichedContext = array_merge($context, [
            'ai_enabled' => $this->aiEnabled,
            'timestamp_micro' => microtime(true),
            'memory_usage' => memory_get_usage(true)
        ]);

        if ($this->cisLogger) {
            $this->cisLogger->$level($message, $enrichedContext);
        } else {
            $this->logError($message, $enrichedContext);
        }
    }

    /**
     * Save JS version with AI analysis
     */
    protected function handleSaveJsVersion($data) {
        $startTime = microtime(true);

        $file = $data['js_file'] ?? $data['file'] ?? null;
        $content = $data['js_content'] ?? $data['content'] ?? null;

        if (!$file || !$content) {
            throw new Exception('Missing required fields: js_file and js_content');
        }

        $message = $data['message'] ?? 'JavaScript update';

        // Validate file is in modules directory
        if (strpos($file, '/modules/') === false && strpos($file, 'modules/') !== 0) {
            throw new Exception('Can only version control module JavaScript files');
        }

        $this->logWithAI('Saving JS version', [
            'file' => $file,
            'size' => strlen($content),
            'message' => $message,
            'lines' => substr_count($content, "\n") + 1
        ]);

        $fullPath = $this->jsPath . '/' . ltrim($file, '/');

        // Create version snapshot
        $versionInfo = $this->createJSVersion($fullPath, $content, $message);

        // AI-powered code analysis
        $aiAnalysis = null;
        if ($this->aiEnabled) {
            $aiAnalysis = $this->analyzeJavaScript($content);
            $versionInfo['ai_analysis'] = $aiAnalysis;
        }

        $duration = microtime(true) - $startTime;

        $this->logWithAI('JS version saved with AI analysis', [
            'file' => $file,
            'version_id' => $versionInfo['id'],
            'duration' => round($duration, 3),
            'ai_suggestions' => $aiAnalysis ? count($aiAnalysis['suggestions']) : 0,
            'code_quality_score' => $aiAnalysis['quality_score'] ?? null
        ]);

        return $this->success($versionInfo, 'JavaScript version saved successfully', [
            'duration' => round($duration, 3),
            'file_size' => strlen($content)
        ]);
    }

    /**
     * Create JS version snapshot
     */
    private function createJSVersion($filePath, $content, $message) {
        file_put_contents($filePath, $content);

        $versionId = time() . '_' . substr(md5($content), 0, 8);
        $fileName = basename($filePath);
        $versionDir = $this->versionsPath . '/' . str_replace('.js', '', $fileName);

        $this->ensureDirectory($versionDir);

        $versionFile = $versionDir . '/' . $versionId . '.js';
        file_put_contents($versionFile, $content);

        // Calculate code metrics
        $metrics = $this->calculateCodeMetrics($content);

        $meta = [
            'id' => $versionId,
            'file' => $fileName,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'size' => strlen($content),
            'lines' => $metrics['lines'],
            'functions' => $metrics['functions'],
            'complexity' => $metrics['complexity'],
            'hash' => md5($content),
            'user' => $_SESSION['user_name'] ?? 'System'
        ];

        $metaFile = $versionDir . '/' . $versionId . '.json';
        file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT));

        $this->cleanupOldVersions($versionDir, 50);

        return $meta;
    }

    /**
     * AI-powered JavaScript analysis
     */
    private function analyzeJavaScript($content) {
        if (!$this->aiEnabled) {
            return null;
        }

        $suggestions = [];
        $qualityScore = 100;

        // Check for console.log (should be removed in production)
        if (preg_match_all('/console\.(log|warn|error|debug)/i', $content, $matches)) {
            $count = count($matches[0]);
            $suggestions[] = [
                'type' => 'warning',
                'message' => "Found {$count} console statements",
                'suggestion' => 'Remove console statements before production deployment',
                'severity' => 'medium',
                'line_count' => $count
            ];
            $qualityScore -= 5;
        }

        // Check for var (ES5) - should use let/const (ES6+)
        if (preg_match_all('/\bvar\s+\w+/i', $content, $matches)) {
            $count = count($matches[0]);
            $suggestions[] = [
                'type' => 'refactor',
                'message' => "Found {$count} 'var' declarations",
                'suggestion' => 'Use let/const instead of var for better scoping',
                'severity' => 'low',
                'es_version' => 'ES5'
            ];
            $qualityScore -= 3;
        }

        // Check for == instead of === (loose equality)
        if (preg_match_all('/[^=!]==([^=]|$)/i', $content, $matches)) {
            $count = count($matches[0]);
            $suggestions[] = [
                'type' => 'warning',
                'message' => "Found {$count} loose equality (==) comparisons",
                'suggestion' => 'Use strict equality (===) for safer comparisons',
                'severity' => 'medium'
            ];
            $qualityScore -= 10;
        }

        // Check for eval() - security risk
        if (preg_match('/\beval\s*\(/i', $content)) {
            $suggestions[] = [
                'type' => 'security',
                'message' => 'Found eval() usage - SECURITY RISK',
                'suggestion' => 'Remove eval() - it can execute arbitrary code',
                'severity' => 'critical'
            ];
            $qualityScore -= 30;
        }

        // Check for TODO/FIXME comments
        if (preg_match_all('/\/\/(.*?)(TODO|FIXME)(.*?)$/mi', $content, $matches)) {
            $count = count($matches[0]);
            $suggestions[] = [
                'type' => 'info',
                'message' => "Found {$count} TODO/FIXME comments",
                'suggestion' => 'Address pending tasks before production',
                'severity' => 'low'
            ];
        }

        // Check for long functions (>50 lines)
        $functions = $this->extractFunctions($content);
        $longFunctions = array_filter($functions, function($f) { return $f['lines'] > 50; });
        if (count($longFunctions) > 0) {
            $suggestions[] = [
                'type' => 'refactor',
                'message' => 'Found ' . count($longFunctions) . ' long functions (>50 lines)',
                'suggestion' => 'Break down long functions into smaller, reusable pieces',
                'severity' => 'medium'
            ];
            $qualityScore -= 10;
        }

        // Check for unused parameters (basic check)
        $unusedParams = $this->findUnusedParameters($content);
        if (count($unusedParams) > 0) {
            $suggestions[] = [
                'type' => 'optimization',
                'message' => 'Found ' . count($unusedParams) . ' potentially unused parameters',
                'suggestion' => 'Remove unused parameters to improve code clarity',
                'severity' => 'low'
            ];
        }

        $this->logWithAI('AI JavaScript analysis completed', [
            'suggestions_count' => count($suggestions),
            'quality_score' => $qualityScore,
            'content_size' => strlen($content),
            'critical_issues' => count(array_filter($suggestions, function($s) { return $s['severity'] === 'critical'; }))
        ]);

        return [
            'suggestions' => $suggestions,
            'quality_score' => max(0, $qualityScore),
            'analyzed_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Calculate code metrics
     */
    private function calculateCodeMetrics($content) {
        $lines = substr_count($content, "\n") + 1;

        // Count functions
        preg_match_all('/function\s+\w+|(?:const|let|var)\s+\w+\s*=\s*(?:function|\(.*?\)\s*=>)/', $content, $matches);
        $functions = count($matches[0]);

        // Simple complexity calculation (count if/for/while/switch)
        $complexity = 1; // Base complexity
        $complexity += preg_match_all('/\b(if|for|while|switch|catch)\s*\(/i', $content);

        return [
            'lines' => $lines,
            'functions' => $functions,
            'complexity' => $complexity
        ];
    }

    /**
     * Extract function information
     */
    private function extractFunctions($content) {
        $functions = [];
        $lines = explode("\n", $content);

        foreach ($lines as $i => $line) {
            if (preg_match('/function\s+(\w+)|(?:const|let|var)\s+(\w+)\s*=\s*(?:function|\(.*?\)\s*=>)/', $line, $match)) {
                $name = $match[1] ?? $match[2];
                $startLine = $i;

                // Find function end (basic)
                $braceCount = 0;
                $endLine = $startLine;
                for ($j = $startLine; $j < count($lines); $j++) {
                    $braceCount += substr_count($lines[$j], '{');
                    $braceCount -= substr_count($lines[$j], '}');
                    if ($braceCount === 0 && $j > $startLine) {
                        $endLine = $j;
                        break;
                    }
                }

                $functions[] = [
                    'name' => $name,
                    'start_line' => $startLine + 1,
                    'end_line' => $endLine + 1,
                    'lines' => $endLine - $startLine + 1
                ];
            }
        }

        return $functions;
    }

    /**
     * Find unused parameters (basic check)
     */
    private function findUnusedParameters($content) {
        // This is a simplified check - real implementation would need AST parsing
        $unused = [];

        preg_match_all('/function\s+\w+\s*\((.*?)\)|(?:const|let|var)\s+\w+\s*=\s*\((.*?)\)\s*=>/', $content, $matches);

        foreach (array_merge($matches[1], $matches[2]) as $params) {
            if (empty($params)) continue;

            $paramList = array_map('trim', explode(',', $params));
            foreach ($paramList as $param) {
                $paramName = preg_replace('/\s*=.*$/', '', $param); // Remove default values
                $paramName = trim($paramName);

                if (!empty($paramName) && substr_count($content, $paramName) === 1) {
                    $unused[] = $paramName;
                }
            }
        }

        return $unused;
    }

    /**
     * Get JS versions
     */
    protected function handleGetJsVersions($data) {
        $file = $data['js_file'] ?? $data['file'] ?? null;

        if (!$file) {
            throw new Exception('Missing required field: js_file');
        }

        $fileName = basename($file);
        $versionDir = $this->versionsPath . '/' . str_replace('.js', '', $fileName);

        if (!is_dir($versionDir)) {
            return $this->success([], 'No versions found', ['count' => 0]);
        }

        $versions = [];
        foreach (glob($versionDir . '/*.json') as $metaFile) {
            $meta = json_decode(file_get_contents($metaFile), true);
            $versions[] = $meta;
        }

        usort($versions, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        $this->logWithAI('Retrieved JS versions', [
            'file' => $file,
            'count' => count($versions)
        ]);

        return $this->success($versions, 'Versions retrieved successfully', [
            'count' => count($versions)
        ]);
    }

    /**
     * Minify JavaScript
     */
    protected function handleMinifyJs($data) {
        $content = $data['js_content'] ?? $data['content'] ?? null;

        if (!$content) {
            throw new Exception('Missing required field: js_content');
        }

        $originalSize = strlen($content);

        $minified = $this->minifyJavaScript($content);
        $minifiedSize = strlen($minified);
        $savings = $originalSize - $minifiedSize;
        $savingsPercent = round(($savings / $originalSize) * 100, 2);

        $this->logWithAI('JavaScript minified', [
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
        ], 'JavaScript minified successfully');
    }

    /**
     * Simple JavaScript minification
     */
    private function minifyJavaScript($js) {
        // Remove comments
        $js = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $js);
        $js = preg_replace('/\/\/.*$/m', '', $js);

        // Remove whitespace
        $js = preg_replace('/\s+/', ' ', $js);
        $js = preg_replace('/\s*([{}();,:])\s*/', '$1', $js);

        return trim($js);
    }

    /**
     * List JS files
     */
    protected function handleListJsFiles($data) {
        $files = [
            'vendors' => $this->scanJSDirectory($this->jsPath . '/vendors'),
            'modules' => $this->scanJSDirectory($this->jsPath . '/modules'),
            'build' => $this->scanJSDirectory($this->buildPath)
        ];

        $totalCount = count($files['vendors']) + count($files['modules']) + count($files['build']);

        $this->logWithAI('Listed JS files', [
            'total' => $totalCount,
            'vendors' => count($files['vendors']),
            'modules' => count($files['modules']),
            'build' => count($files['build'])
        ]);

        return $this->success($files, 'JavaScript files listed successfully', [
            'total' => $totalCount
        ]);
    }

    /**
     * Scan JS directory
     */
    private function scanJSDirectory($dir) {
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        foreach (glob($dir . '/*.js') as $file) {
            $files[] = [
                'name' => basename($file),
                'path' => str_replace($this->jsPath . '/', '', $file),
                'size' => filesize($file),
                'modified' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }

        return $files;
    }

    /**
     * Cleanup old versions
     */
    private function cleanupOldVersions($versionDir, $maxVersions) {
        $versions = glob($versionDir . '/*.json');

        if (count($versions) <= $maxVersions) {
            return;
        }

        usort($versions, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        $toDelete = array_slice($versions, 0, count($versions) - $maxVersions);

        foreach ($toDelete as $file) {
            $jsFile = str_replace('.json', '.js', $file);
            if (file_exists($jsFile)) {
                unlink($jsFile);
            }
            unlink($file);
        }
    }
}

// Handle request if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    session_start();
    $api = new JSAPI(['base_path' => dirname(__DIR__)]);
    $api->handleRequest();
}
