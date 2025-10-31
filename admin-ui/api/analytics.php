<?php
/**
 * AnalyticsAPI - Asset Usage Analytics
 *
 * Features:
 * - Component usage tracking
 * - File size metrics
 * - Code quality trends
 * - Build history analytics
 * - Performance monitoring
 * - AI-powered insights
 *
 * @package CIS\AdminUI\API
 * @version 6.0.0
 */

require_once __DIR__ . '/../lib/BaseAPI.php';

class AnalyticsAPI extends BaseAPI {

    private $analyticsPath;
    private $cisLogger;
    private $aiEnabled = false;

    public function __construct($config = []) {
        parent::__construct($config);

        $this->analyticsPath = $this->config['base_path'] . '/analytics';
        $this->ensureDirectory($this->analyticsPath);

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
            $this->cisLogger->info('AnalyticsAPI initialized', [
                'module' => 'admin-ui',
                'component' => 'analytics',
                'version' => '6.0.0'
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

        if ($this->cisLogger) {
            $this->cisLogger->$level($message, $context);
        }
    }

    /**
     * Get dashboard overview
     */
    protected function handleGetOverview($data) {
        $overview = [
            'components' => $this->getComponentStats(),
            'files' => $this->getFileStats(),
            'builds' => $this->getBuildStats(),
            'quality' => $this->getQualityStats()
        ];

        $this->logWithAI('Analytics overview generated', [
            'total_components' => $overview['components']['total'],
            'total_files' => $overview['files']['total'],
            'total_builds' => $overview['builds']['total']
        ]);

        return $this->success($overview, 'Analytics overview generated');
    }

    /**
     * Get component statistics
     */
    private function getComponentStats() {
        $componentsPath = $this->config['base_path'] . '/components';
        $stats = [
            'total' => 0,
            'by_category' => [],
            'most_used' => [],
            'avg_quality_score' => 0
        ];

        if (!is_dir($componentsPath)) {
            return $stats;
        }

        $totalQuality = 0;
        $qualityCount = 0;
        $components = [];

        foreach (glob($componentsPath . '/*.json') as $file) {
            $comp = json_decode(file_get_contents($file), true);
            $stats['total']++;

            $category = $comp['category'] ?? 'uncategorized';
            $stats['by_category'][$category] = ($stats['by_category'][$category] ?? 0) + 1;

            if (isset($comp['ai_quality_score'])) {
                $totalQuality += $comp['ai_quality_score'];
                $qualityCount++;
            }

            $components[] = [
                'id' => $comp['id'] ?? basename($file, '.json'),
                'name' => $comp['name'] ?? 'Unknown',
                'usage_count' => $comp['usage_count'] ?? 0
            ];
        }

        if ($qualityCount > 0) {
            $stats['avg_quality_score'] = round($totalQuality / $qualityCount, 2);
        }

        // Sort by usage
        usort($components, function($a, $b) {
            return $b['usage_count'] - $a['usage_count'];
        });

        $stats['most_used'] = array_slice($components, 0, 5);

        return $stats;
    }

    /**
     * Get file statistics
     */
    private function getFileStats() {
        $stats = [
            'total' => 0,
            'css' => ['count' => 0, 'total_size' => 0],
            'js' => ['count' => 0, 'total_size' => 0],
            'largest_files' => []
        ];

        $files = [];

        // CSS files
        $cssPath = $this->config['base_path'] . '/css';
        if (is_dir($cssPath)) {
            foreach (glob($cssPath . '/**/*.css') as $file) {
                $size = filesize($file);
                $stats['css']['count']++;
                $stats['css']['total_size'] += $size;
                $stats['total']++;

                $files[] = [
                    'path' => str_replace($this->config['base_path'], '', $file),
                    'size' => $size,
                    'type' => 'css'
                ];
            }
        }

        // JS files
        $jsPath = $this->config['base_path'] . '/js';
        if (is_dir($jsPath)) {
            foreach (glob($jsPath . '/**/*.js') as $file) {
                $size = filesize($file);
                $stats['js']['count']++;
                $stats['js']['total_size'] += $size;
                $stats['total']++;

                $files[] = [
                    'path' => str_replace($this->config['base_path'], '', $file),
                    'size' => $size,
                    'type' => 'js'
                ];
            }
        }

        // Format sizes
        $stats['css']['total_size_formatted'] = $this->formatBytes($stats['css']['total_size']);
        $stats['js']['total_size_formatted'] = $this->formatBytes($stats['js']['total_size']);

        // Sort by size
        usort($files, function($a, $b) {
            return $b['size'] - $a['size'];
        });

        $stats['largest_files'] = array_slice($files, 0, 10);
        foreach ($stats['largest_files'] as &$file) {
            $file['size_formatted'] = $this->formatBytes($file['size']);
        }

        return $stats;
    }

    /**
     * Get build statistics
     */
    private function getBuildStats() {
        $manifestFile = $this->config['base_path'] . '/build/manifest.json';
        $stats = [
            'total' => 0,
            'last_build' => null,
            'profile' => null
        ];

        if (file_exists($manifestFile)) {
            $manifest = json_decode(file_get_contents($manifestFile), true);
            $stats['total'] = 1; // Could track build history if we saved each one
            $stats['last_build'] = $manifest['built_at'] ?? null;
            $stats['profile'] = $manifest['profile'] ?? null;
        }

        return $stats;
    }

    /**
     * Get code quality statistics
     */
    private function getQualityStats() {
        $stats = [
            'avg_component_quality' => 0,
            'css_issues' => 0,
            'js_issues' => 0,
            'total_issues' => 0
        ];

        // Component quality
        $componentsPath = $this->config['base_path'] . '/components';
        if (is_dir($componentsPath)) {
            $totalQuality = 0;
            $count = 0;

            foreach (glob($componentsPath . '/*.json') as $file) {
                $comp = json_decode(file_get_contents($file), true);
                if (isset($comp['ai_quality_score'])) {
                    $totalQuality += $comp['ai_quality_score'];
                    $count++;
                }

                if (isset($comp['ai_suggestions'])) {
                    $stats['total_issues'] += count($comp['ai_suggestions']);
                }
            }

            if ($count > 0) {
                $stats['avg_component_quality'] = round($totalQuality / $count, 2);
            }
        }

        return $stats;
    }

    /**
     * Get component usage trends
     */
    protected function handleGetComponentTrends($data) {
        $componentsPath = $this->config['base_path'] . '/components';
        $trends = [];

        if (!is_dir($componentsPath)) {
            return $this->success($trends, 'No component data available');
        }

        foreach (glob($componentsPath . '/*.json') as $file) {
            $comp = json_decode(file_get_contents($file), true);

            $trends[] = [
                'id' => $comp['id'] ?? basename($file, '.json'),
                'name' => $comp['name'] ?? 'Unknown',
                'usage_count' => $comp['usage_count'] ?? 0,
                'last_used' => $comp['last_used'] ?? null,
                'quality_score' => $comp['ai_quality_score'] ?? null
            ];
        }

        // Sort by usage descending
        usort($trends, function($a, $b) {
            return $b['usage_count'] - $a['usage_count'];
        });

        $this->logWithAI('Component trends retrieved', [
            'count' => count($trends)
        ]);

        return $this->success($trends, 'Component trends retrieved', [
            'count' => count($trends)
        ]);
    }

    /**
     * Get file size trends
     */
    protected function handleGetFileSizeTrends($data) {
        $trends = [
            'css' => $this->getFileSizesForType('css'),
            'js' => $this->getFileSizesForType('js')
        ];

        $this->logWithAI('File size trends retrieved', [
            'css_files' => count($trends['css']),
            'js_files' => count($trends['js'])
        ]);

        return $this->success($trends, 'File size trends retrieved');
    }

    /**
     * Get file sizes for type
     */
    private function getFileSizesForType($type) {
        $path = $this->config['base_path'] . '/' . $type;
        $files = [];

        if (!is_dir($path)) {
            return $files;
        }

        foreach (glob($path . '/**/*.' . $type) as $file) {
            $size = filesize($file);
            $files[] = [
                'path' => str_replace($this->config['base_path'], '', $file),
                'size' => $size,
                'size_formatted' => $this->formatBytes($size),
                'modified' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }

        // Sort by size descending
        usort($files, function($a, $b) {
            return $b['size'] - $a['size'];
        });

        return $files;
    }

    /**
     * Track event
     */
    protected function handleTrackEvent($data) {
        $this->validateRequired($data, ['event_type', 'event_data']);

        $event = [
            'type' => $data['event_type'],
            'data' => json_decode($data['event_data'], true),
            'timestamp' => date('Y-m-d H:i:s'),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ];

        // Save event to analytics log
        $logFile = $this->analyticsPath . '/events-' . date('Y-m-d') . '.json';
        $events = [];

        if (file_exists($logFile)) {
            $events = json_decode(file_get_contents($logFile), true) ?? [];
        }

        $events[] = $event;
        file_put_contents($logFile, json_encode($events, JSON_PRETTY_PRINT));

        $this->logWithAI('Event tracked', [
            'event_type' => $event['type'],
            'timestamp' => $event['timestamp']
        ]);

        return $this->success(null, 'Event tracked successfully');
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

// Handle request if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    session_start();
    $api = new AnalyticsAPI(['base_path' => dirname(__DIR__)]);
    $api->handleRequest();
}
