<?php
/**
 * ComponentsAPI - HTML Component Library Management
 *
 * Features:
 * - Component CRUD operations
 * - Version control for components
 * - Category management
 * - AI-powered component suggestions
 * - Usage analytics
 *
 * @package CIS\AdminUI\API
 * @version 6.0.0
 */

require_once __DIR__ . '/../lib/BaseAPI.php';

class ComponentsAPI extends BaseAPI {

    private $componentsPath;
    private $versionsPath;
    private $cisLogger;
    private $aiEnabled = false;

    public function __construct($config = []) {
        parent::__construct($config);

        $this->componentsPath = $this->config['base_path'] . '/components';
        $this->versionsPath = $this->config['base_path'] . '/component-versions';

        $this->ensureDirectory($this->componentsPath);
        $this->ensureDirectory($this->versionsPath);

        // Initialize CIS Logger with AI
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
            $this->cisLogger->info('ComponentsAPI initialized', [
                'module' => 'admin-ui',
                'component' => 'component-library',
                'version' => '6.0.0',
                'ai_powered' => true
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
     * Save component with AI suggestions
     */
    protected function handleSaveComponent($data) {
        $this->validateRequired($data, ['component_data']);

        $component = json_decode($data['component_data'], true);
        if (!$component) {
            throw new Exception('Invalid component data JSON');
        }

        $componentId = $component['id'] ?? 'comp_' . time();
        $componentId = $this->sanitizeFilename($componentId);

        // Add metadata
        $component['id'] = $componentId;
        $component['saved_at'] = date('Y-m-d H:i:s');
        $component['version'] = $component['version'] ?? '1.0.0';
        $component['usage_count'] = $component['usage_count'] ?? 0;

        // AI-powered component analysis
        if ($this->aiEnabled) {
            $aiAnalysis = $this->analyzeComponent($component);
            $component['ai_quality_score'] = $aiAnalysis['quality_score'];
            $component['ai_suggestions'] = $aiAnalysis['suggestions'];
        }

        $filePath = $this->componentsPath . '/' . $componentId . '.json';
        file_put_contents($filePath, json_encode($component, JSON_PRETTY_PRINT));

        // Create version snapshot
        $this->createComponentVersion($componentId, $component);

        $this->logWithAI('Component saved', [
            'component_id' => $componentId,
            'category' => $component['category'] ?? 'uncategorized',
            'ai_score' => $component['ai_quality_score'] ?? null
        ]);

        return $this->success([
            'component_id' => $componentId,
            'path' => 'components/' . $componentId . '.json'
        ], 'Component saved successfully');
    }

    /**
     * AI-powered component analysis
     */
    private function analyzeComponent($component) {
        if (!$this->aiEnabled) {
            return ['quality_score' => 50, 'suggestions' => []];
        }

        $suggestions = [];
        $qualityScore = 100;

        // Check if has description
        if (empty($component['description'])) {
            $suggestions[] = [
                'type' => 'documentation',
                'message' => 'Component lacks description',
                'suggestion' => 'Add a clear description for better usability',
                'severity' => 'medium'
            ];
            $qualityScore -= 10;
        }

        // Check HTML quality
        if (isset($component['html'])) {
            $html = $component['html'];

            // Check for accessibility
            if (stripos($html, 'aria-') === false && stripos($html, 'alt=') === false) {
                $suggestions[] = [
                    'type' => 'accessibility',
                    'message' => 'Missing accessibility attributes',
                    'suggestion' => 'Add ARIA labels and alt text for images',
                    'severity' => 'high'
                ];
                $qualityScore -= 15;
            }

            // Check for inline styles (should use classes)
            if (preg_match_all('/style="/', $html, $matches)) {
                $count = count($matches[0]);
                $suggestions[] = [
                    'type' => 'best-practice',
                    'message' => "Found {$count} inline styles",
                    'suggestion' => 'Use CSS classes instead of inline styles',
                    'severity' => 'low'
                ];
                $qualityScore -= 5;
            }

            // Check for deprecated tags
            $deprecatedTags = ['center', 'font', 'marquee', 'blink'];
            foreach ($deprecatedTags as $tag) {
                if (stripos($html, "<{$tag}") !== false) {
                    $suggestions[] = [
                        'type' => 'deprecated',
                        'message' => "Using deprecated <{$tag}> tag",
                        'suggestion' => 'Replace with modern HTML5 equivalents',
                        'severity' => 'high'
                    ];
                    $qualityScore -= 20;
                }
            }
        }

        // Check CSS quality
        if (isset($component['css'])) {
            $css = $component['css'];

            // Check for !important overuse
            if (preg_match_all('/!important/i', $css, $matches)) {
                $count = count($matches[0]);
                if ($count > 2) {
                    $suggestions[] = [
                        'type' => 'css-quality',
                        'message' => "Using !important {$count} times",
                        'suggestion' => 'Refactor CSS to avoid !important',
                        'severity' => 'medium'
                    ];
                    $qualityScore -= 10;
                }
            }
        }

        // Check JavaScript quality
        if (isset($component['js'])) {
            $js = $component['js'];

            // Check for console.log
            if (preg_match_all('/console\.(log|warn|error)/i', $js, $matches)) {
                $count = count($matches[0]);
                $suggestions[] = [
                    'type' => 'js-cleanup',
                    'message' => "Found {$count} console statements",
                    'suggestion' => 'Remove console statements for production',
                    'severity' => 'low'
                ];
                $qualityScore -= 5;
            }
        }

        // Check for dependencies
        if (empty($component['dependencies'])) {
            $suggestions[] = [
                'type' => 'metadata',
                'message' => 'No dependencies specified',
                'suggestion' => 'List required libraries (Bootstrap, jQuery, etc.)',
                'severity' => 'low'
            ];
        }

        // Check for tags
        if (empty($component['tags'])) {
            $suggestions[] = [
                'type' => 'metadata',
                'message' => 'No tags specified',
                'suggestion' => 'Add tags for better searchability',
                'severity' => 'low'
            ];
            $qualityScore -= 5;
        }

        $this->logWithAI('AI component analysis completed', [
            'component_id' => $component['id'] ?? 'unknown',
            'quality_score' => $qualityScore,
            'suggestions_count' => count($suggestions)
        ]);

        return [
            'quality_score' => max(0, $qualityScore),
            'suggestions' => $suggestions,
            'analyzed_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Create component version snapshot
     */
    private function createComponentVersion($componentId, $component) {
        $versionId = time() . '_' . substr(md5(json_encode($component)), 0, 8);
        $versionDir = $this->versionsPath . '/' . $componentId;

        $this->ensureDirectory($versionDir);

        $versionFile = $versionDir . '/' . $versionId . '.json';
        file_put_contents($versionFile, json_encode($component, JSON_PRETTY_PRINT));

        // Keep last 20 versions per component
        $this->cleanupOldVersions($versionDir, 20);
    }

    /**
     * Load component
     */
    protected function handleGetComponent($data) {
        $this->validateRequired($data, ['component_id']);

        $componentId = $this->sanitizeFilename($data['component_id']);
        $filePath = $this->componentsPath . '/' . $componentId . '.json';

        if (!file_exists($filePath)) {
            return $this->error('Component not found: ' . $componentId, 'COMPONENT_NOT_FOUND');
        }

        $component = json_decode(file_get_contents($filePath), true);

        // Increment usage count
        $component['usage_count'] = ($component['usage_count'] ?? 0) + 1;
        $component['last_used'] = date('Y-m-d H:i:s');
        file_put_contents($filePath, json_encode($component, JSON_PRETTY_PRINT));

        $this->logWithAI('Component loaded', [
            'component_id' => $componentId,
            'usage_count' => $component['usage_count']
        ]);

        return $this->success($component, 'Component loaded successfully', [
            'modified' => date('Y-m-d H:i:s', filemtime($filePath))
        ]);
    }

    /**
     * List components
     */
    protected function handleListComponents($data) {
        $category = $data['category'] ?? null;
        $search = $data['search'] ?? null;

        $components = [];

        foreach (glob($this->componentsPath . '/*.json') as $file) {
            $comp = json_decode(file_get_contents($file), true);

            // Filter by category
            if ($category && ($comp['category'] ?? '') !== $category) {
                continue;
            }

            // Filter by search
            if ($search) {
                $searchLower = strtolower($search);
                $name = strtolower($comp['name'] ?? '');
                $desc = strtolower($comp['description'] ?? '');
                $tags = strtolower(implode(' ', $comp['tags'] ?? []));

                if (stripos($name, $searchLower) === false &&
                    stripos($desc, $searchLower) === false &&
                    stripos($tags, $searchLower) === false) {
                    continue;
                }
            }

            $components[] = [
                'id' => $comp['id'] ?? basename($file, '.json'),
                'name' => $comp['name'] ?? 'Unnamed',
                'category' => $comp['category'] ?? 'uncategorized',
                'version' => $comp['version'] ?? '1.0.0',
                'usage_count' => $comp['usage_count'] ?? 0,
                'ai_quality_score' => $comp['ai_quality_score'] ?? null,
                'modified' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }

        // Sort by usage count descending
        usort($components, function($a, $b) {
            return $b['usage_count'] - $a['usage_count'];
        });

        $this->logWithAI('Components listed', [
            'count' => count($components),
            'category' => $category,
            'search' => $search
        ]);

        return $this->success($components, 'Components listed successfully', [
            'count' => count($components),
            'filtered' => $category || $search
        ]);
    }

    /**
     * Delete component
     */
    protected function handleDeleteComponent($data) {
        $this->validateRequired($data, ['component_id']);

        $componentId = $this->sanitizeFilename($data['component_id']);
        $filePath = $this->componentsPath . '/' . $componentId . '.json';

        if (!file_exists($filePath)) {
            return $this->error('Component not found: ' . $componentId, 'COMPONENT_NOT_FOUND');
        }

        // Backup before delete
        $backupDir = $this->config['base_path'] . '/components-deleted';
        $this->ensureDirectory($backupDir);
        $backupPath = $backupDir . '/' . $componentId . '_' . time() . '.json';
        copy($filePath, $backupPath);

        unlink($filePath);

        $this->logWithAI('Component deleted', [
            'component_id' => $componentId,
            'backup_path' => $backupPath
        ]);

        return $this->success(null, 'Component deleted successfully', [
            'component_id' => $componentId,
            'backup_created' => true
        ]);
    }

    /**
     * Get component categories
     */
    protected function handleGetCategories($data) {
        $categories = [];

        foreach (glob($this->componentsPath . '/*.json') as $file) {
            $comp = json_decode(file_get_contents($file), true);
            $cat = $comp['category'] ?? 'uncategorized';

            if (!isset($categories[$cat])) {
                $categories[$cat] = [
                    'name' => $cat,
                    'count' => 0
                ];
            }

            $categories[$cat]['count']++;
        }

        $categories = array_values($categories);

        // Sort by count descending
        usort($categories, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        $this->logWithAI('Categories retrieved', [
            'count' => count($categories)
        ]);

        return $this->success($categories, 'Categories retrieved successfully', [
            'count' => count($categories)
        ]);
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
            unlink($file);
        }
    }
}

// Handle request if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    session_start();
    $api = new ComponentsAPI(['base_path' => dirname(__DIR__)]);
    $api->handleRequest();
}
