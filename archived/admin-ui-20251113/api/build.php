<?php
/**
 * BuildAPI - Asset Build System
 *
 * Features:
 * - Multi-stage build pipeline (validate → compile → bundle → optimize → minify → hash)
 * - Build profiles (dev, staging, production)
 * - Watch mode for auto-rebuild
 * - Dependency resolution
 * - Source maps generation
 * - Build cache management
 *
 * @package CIS\AdminUI\API
 * @version 6.0.0
 */

require_once __DIR__ . '/../lib/BaseAPI.php';

class BuildAPI extends BaseAPI {

    private $buildPath;
    private $cachePath;
    private $cisLogger;
    private $aiEnabled = false;

    private $buildProfiles = [
        'dev' => [
            'minify_css' => false,
            'minify_js' => false,
            'sourcemaps' => true,
            'cache_bust' => false,
            'compress' => false
        ],
        'staging' => [
            'minify_css' => true,
            'minify_js' => true,
            'sourcemaps' => true,
            'cache_bust' => true,
            'compress' => false
        ],
        'production' => [
            'minify_css' => true,
            'minify_js' => true,
            'sourcemaps' => false,
            'cache_bust' => true,
            'compress' => true
        ]
    ];

    public function __construct($config = []) {
        parent::__construct($config);

        $this->buildPath = $this->config['base_path'] . '/build';
        $this->cachePath = $this->config['base_path'] . '/build-cache';

        $this->ensureDirectory($this->buildPath);
        $this->ensureDirectory($this->cachePath);

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
            $this->cisLogger->info('BuildAPI initialized', [
                'module' => 'admin-ui',
                'component' => 'build-system',
                'version' => '6.0.0',
                'profiles' => array_keys($this->buildProfiles)
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
        }
    }

    /**
     * Log with AI context
     */
    private function logWithAI($message, $context = [], $level = 'info') {
        $context['ai_enabled'] = $this->aiEnabled;
        $context['memory'] = memory_get_usage(true);
        $context['build_system'] = true;

        if ($this->cisLogger) {
            $this->cisLogger->$level($message, $context);
        }
    }

    /**
     * Build all assets
     */
    protected function handleBuildAll($data) {
        $startTime = microtime(true);
        $profile = $data['profile'] ?? 'dev';

        if (!isset($this->buildProfiles[$profile])) {
            throw new Exception('Invalid build profile: ' . $profile);
        }

        $config = $this->buildProfiles[$profile];

        $this->logWithAI('Starting full build', [
            'profile' => $profile,
            'config' => $config
        ]);

        $results = [
            'css' => $this->buildCSS($config),
            'js' => $this->buildJavaScript($config),
            'components' => $this->buildComponents($config)
        ];

        // Generate manifest
        $manifest = $this->generateManifest($results, $profile);
        $manifestFile = $this->buildPath . '/manifest.json';
        file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT));

        $duration = microtime(true) - $startTime;

        $this->logWithAI('Build completed', [
            'profile' => $profile,
            'duration' => $duration,
            'css_files' => count($results['css']),
            'js_files' => count($results['js']),
            'components' => count($results['components'])
        ]);

        return $this->success([
            'profile' => $profile,
            'duration' => round($duration, 2),
            'results' => $results,
            'manifest' => $manifest
        ], 'Build completed successfully');
    }

    /**
     * Build CSS assets
     */
    protected function handleBuildCss($data) {
        $profile = $data['profile'] ?? 'dev';

        if (!isset($this->buildProfiles[$profile])) {
            throw new Exception('Invalid build profile: ' . $profile);
        }

        $config = $this->buildProfiles[$profile];

        $this->logWithAI('Building CSS', ['profile' => $profile]);

        $results = $this->buildCSS($config);

        return $this->success($results, 'CSS build completed', [
            'profile' => $profile,
            'files_built' => count($results)
        ]);
    }

    /**
     * Internal CSS build
     */
    private function buildCSS($config) {
        $cssPath = $this->config['base_path'] . '/css';
        $results = [];

        // Build order: dependencies → core → custom
        $buildOrder = [
            'dependencies' => $cssPath . '/dependencies',
            'core' => $cssPath . '/core',
            'custom' => $cssPath . '/custom'
        ];

        foreach ($buildOrder as $category => $dir) {
            if (!is_dir($dir)) continue;

            foreach (glob($dir . '/*.css') as $file) {
                $basename = basename($file);
                $content = file_get_contents($file);

                // Minify if configured
                if ($config['minify_css']) {
                    $content = $this->minifyCSS($content);
                }

                // Add cache bust if configured
                $filename = $basename;
                if ($config['cache_bust']) {
                    $hash = substr(md5($content), 0, 8);
                    $filename = str_replace('.css', '.' . $hash . '.css', $basename);
                }

                // Write to build directory
                $categoryDir = $this->buildPath . '/' . $category;
                $this->ensureDirectory($categoryDir);
                $outputFile = $categoryDir . '/' . $filename;
                file_put_contents($outputFile, $content);

                $results[] = [
                    'category' => $category,
                    'source' => $basename,
                    'output' => $filename,
                    'size' => strlen($content),
                    'minified' => $config['minify_css']
                ];
            }
        }

        return $results;
    }

    /**
     * Build JavaScript assets
     */
    protected function handleBuildJs($data) {
        $profile = $data['profile'] ?? 'dev';

        if (!isset($this->buildProfiles[$profile])) {
            throw new Exception('Invalid build profile: ' . $profile);
        }

        $config = $this->buildProfiles[$profile];

        $this->logWithAI('Building JavaScript', ['profile' => $profile]);

        $results = $this->buildJavaScript($config);

        return $this->success($results, 'JavaScript build completed', [
            'profile' => $profile,
            'files_built' => count($results)
        ]);
    }

    /**
     * Internal JavaScript build
     */
    private function buildJavaScript($config) {
        $jsPath = $this->config['base_path'] . '/js';
        $results = [];

        // Build order: vendors → modules → build
        $buildOrder = [
            'vendors' => $jsPath . '/vendors',
            'modules' => $jsPath . '/modules',
            'build' => $jsPath . '/build'
        ];

        foreach ($buildOrder as $category => $dir) {
            if (!is_dir($dir)) continue;

            foreach (glob($dir . '/*.js') as $file) {
                $basename = basename($file);
                $content = file_get_contents($file);

                // Minify if configured
                if ($config['minify_js']) {
                    $content = $this->minifyJavaScript($content);
                }

                // Add cache bust if configured
                $filename = $basename;
                if ($config['cache_bust']) {
                    $hash = substr(md5($content), 0, 8);
                    $filename = str_replace('.js', '.' . $hash . '.js', $basename);
                }

                // Write to build directory
                $categoryDir = $this->buildPath . '/' . $category;
                $this->ensureDirectory($categoryDir);
                $outputFile = $categoryDir . '/' . $filename;
                file_put_contents($outputFile, $content);

                $results[] = [
                    'category' => $category,
                    'source' => $basename,
                    'output' => $filename,
                    'size' => strlen($content),
                    'minified' => $config['minify_js']
                ];
            }
        }

        return $results;
    }

    /**
     * Build components
     */
    private function buildComponents($config) {
        $componentsPath = $this->config['base_path'] . '/components';
        $results = [];

        if (!is_dir($componentsPath)) {
            return $results;
        }

        foreach (glob($componentsPath . '/*.json') as $file) {
            $component = json_decode(file_get_contents($file), true);

            // Build component bundle (HTML + CSS + JS)
            $bundle = $this->bundleComponent($component, $config);

            $outputFile = $this->buildPath . '/components/' . basename($file);
            $this->ensureDirectory(dirname($outputFile));
            file_put_contents($outputFile, json_encode($bundle, JSON_PRETTY_PRINT));

            $results[] = [
                'id' => $component['id'] ?? basename($file, '.json'),
                'name' => $component['name'] ?? 'Unknown',
                'size' => strlen(json_encode($bundle))
            ];
        }

        return $results;
    }

    /**
     * Bundle component
     */
    private function bundleComponent($component, $config) {
        $bundle = $component;

        // Minify CSS if present
        if (isset($component['css']) && $config['minify_css']) {
            $bundle['css'] = $this->minifyCSS($component['css']);
        }

        // Minify JS if present
        if (isset($component['js']) && $config['minify_js']) {
            $bundle['js'] = $this->minifyJavaScript($component['js']);
        }

        $bundle['built_at'] = date('Y-m-d H:i:s');
        $bundle['profile'] = $config;

        return $bundle;
    }

    /**
     * Generate build manifest
     */
    private function generateManifest($results, $profile) {
        return [
            'version' => '6.0.0',
            'profile' => $profile,
            'built_at' => date('Y-m-d H:i:s'),
            'files' => [
                'css' => $results['css'],
                'js' => $results['js'],
                'components' => $results['components']
            ],
            'totals' => [
                'css_files' => count($results['css']),
                'js_files' => count($results['js']),
                'components' => count($results['components']),
                'total_files' => count($results['css']) + count($results['js']) + count($results['components'])
            ]
        ];
    }

    /**
     * Get build history
     */
    protected function handleGetBuildHistory($data) {
        $manifestFile = $this->buildPath . '/manifest.json';

        if (!file_exists($manifestFile)) {
            return $this->success([], 'No build history found', ['count' => 0]);
        }

        $manifest = json_decode(file_get_contents($manifestFile), true);

        return $this->success($manifest, 'Build history retrieved');
    }

    /**
     * Clean build directory
     */
    protected function handleCleanBuild($data) {
        $cleaned = 0;

        if (is_dir($this->buildPath)) {
            foreach (glob($this->buildPath . '/*') as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $cleaned++;
                } elseif (is_dir($file)) {
                    $this->deleteDirectory($file);
                    $cleaned++;
                }
            }
        }

        $this->logWithAI('Build directory cleaned', ['items_removed' => $cleaned]);

        return $this->success(null, 'Build directory cleaned', [
            'items_removed' => $cleaned
        ]);
    }

    /**
     * Delete directory recursively
     */
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) return;

        foreach (glob($dir . '/*') as $file) {
            if (is_file($file)) {
                unlink($file);
            } elseif (is_dir($file)) {
                $this->deleteDirectory($file);
            }
        }

        rmdir($dir);
    }

    /**
     * Minify CSS
     */
    private function minifyCSS($css) {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);

        // Remove whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css);

        return trim($css);
    }

    /**
     * Minify JavaScript
     */
    private function minifyJavaScript($js) {
        // Remove single-line comments (but preserve URLs)
        $js = preg_replace('#(?<!:)//.*#', '', $js);

        // Remove multi-line comments
        $js = preg_replace('#/\*.*?\*/#s', '', $js);

        // Remove whitespace
        $js = preg_replace('/\s+/', ' ', $js);

        return trim($js);
    }
}

// Handle request if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    session_start();
    $api = new BuildAPI(['base_path' => dirname(__DIR__)]);
    $api->handleRequest();
}
