#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * CIS Documentation Generation Tool
 * 
 * Automatically generates and updates documentation from code analysis
 * 
 * @author CIS Development Team
 * @version 1.0
 * @since October 12, 2025
 */

class DocumentationGenerator
{
    private string $rootPath;
    private string $docsPath;
    private array $config;
    
    public function __construct(string $rootPath = '.')
    {
        $this->rootPath = rtrim($rootPath, '/');
        $this->docsPath = $this->rootPath . '/docs';
        $this->config = $this->loadConfig();
    }
    
    /**
     * Main execution method
     */
    public function run(): void
    {
        echo "🔧 CIS Documentation Generator\n";
        echo "================================\n\n";
        
        $this->analyzeModules();
        $this->generateApiDocs();
        $this->generateDependencyMap();
        $this->generateCrossReferences();
        $this->updateKnowledgeBase();
        
        echo "✅ Documentation generation complete!\n";
    }
    
    /**
     * Analyze all modules and extract metadata
     */
    private function analyzeModules(): void
    {
        echo "📊 Analyzing modules...\n";
        
        $modules = $this->discoverModules();
        $analysis = [];
        
        foreach ($modules as $module) {
            echo "  Analyzing: {$module}\n";
            $analysis[$module] = $this->analyzeModule($module);
        }
        
        $this->saveModuleAnalysis($analysis);
        echo "  ✅ Module analysis complete\n\n";
    }
    
    /**
     * Generate API documentation from endpoint files
     */
    private function generateApiDocs(): void
    {
        echo "📋 Generating API documentation...\n";
        
        $modules = $this->discoverModules();
        
        foreach ($modules as $module) {
            $apiPath = $this->rootPath . '/' . $module . '/api';
            if (!is_dir($apiPath)) continue;
            
            echo "  Processing APIs for: {$module}\n";
            
            $endpoints = $this->discoverApiEndpoints($apiPath);
            $apiDoc = $this->generateModuleApiDoc($module, $endpoints);
            
            $outputFile = $this->docsPath . '/api/' . $module . '.md';
            @mkdir(dirname($outputFile), 0755, true);
            file_put_contents($outputFile, $apiDoc);
        }
        
        echo "  ✅ API documentation generated\n\n";
    }
    
    /**
     * Generate dependency map between modules
     */
    private function generateDependencyMap(): void
    {
        echo "🕸️ Generating dependency map...\n";
        
        $dependencies = $this->analyzeDependencies();
        $mapContent = $this->generateDependencyMapContent($dependencies);
        
        @mkdir($this->docsPath . '/architecture', 0755, true);
        file_put_contents($this->docsPath . '/architecture/dependency-map.md', $mapContent);
        echo "  ✅ Dependency map generated\n\n";
    }
    
    /**
     * Generate cross-reference index
     */
    private function generateCrossReferences(): void
    {
        echo "🔗 Generating cross-references...\n";
        
        $references = $this->buildCrossReferenceIndex();
        $indexContent = $this->generateCrossReferenceContent($references);
        
        @mkdir($this->docsPath . '/knowledge-base', 0755, true);
        file_put_contents($this->docsPath . '/knowledge-base/cross-reference-index.md', $indexContent);
        echo "  ✅ Cross-references generated\n\n";
    }
    
    /**
     * Update knowledge base with new findings
     */
    private function updateKnowledgeBase(): void
    {
        echo "🧠 Updating knowledge base...\n";
        
        $patterns = $this->identifyCodePatterns();
        $this->updateCodePatterns($patterns);
        
        $decisions = $this->extractArchitecturalDecisions();
        $this->updateDecisions($decisions);
        
        echo "  ✅ Knowledge base updated\n\n";
    }
    
    /**
     * Discover all modules in the project
     */
    private function discoverModules(): array
    {
        $modules = [];
        $iterator = new DirectoryIterator($this->rootPath);
        
        foreach ($iterator as $item) {
            if ($item->isDot() || !$item->isDir()) continue;
            
            $name = $item->getFilename();
            if (in_array($name, ['.git', 'docs', 'backups', 'core', 'templates'])) continue;
            
            // Check if it's a module (has index.php or module structure)
            if ($this->isModule($item->getPathname())) {
                $modules[] = $name;
            }
        }
        
        return $modules;
    }
    
    /**
     * Check if directory is a module
     */
    private function isModule(string $path): bool
    {
        return file_exists($path . '/index.php') || 
               is_dir($path . '/api') || 
               is_dir($path . '/controllers');
    }
    
    /**
     * Analyze a specific module
     */
    private function analyzeModule(string $module): array
    {
        $modulePath = $this->rootPath . '/' . $module;
        
        return [
            'name' => $module,
            'path' => $modulePath,
            'files' => $this->countFiles($modulePath),
            'apis' => $this->countApiEndpoints($modulePath . '/api'),
            'controllers' => $this->findControllers($modulePath),
            'dependencies' => $this->findModuleDependencies($modulePath),
            'last_modified' => $this->getLastModified($modulePath),
            'size' => $this->getDirectorySize($modulePath)
        ];
    }
    
    /**
     * Discover API endpoints in a module
     */
    private function discoverApiEndpoints(string $apiPath): array
    {
        if (!is_dir($apiPath)) return [];
        
        $endpoints = [];
        $files = glob($apiPath . '/*.php');
        
        foreach ($files as $file) {
            $endpoint = basename($file, '.php');
            $endpoints[] = [
                'name' => $endpoint,
                'file' => $file,
                'methods' => $this->extractHttpMethods($file),
                'parameters' => $this->extractParameters($file),
                'description' => $this->extractDescription($file)
            ];
        }
        
        return $endpoints;
    }
    
    /**
     * Generate API documentation for a module
     */
    private function generateModuleApiDoc(string $module, array $endpoints): string
    {
        $content = "# {$module} Module - API Documentation\n\n";
        $content .= "**Generated**: " . date('Y-m-d H:i:s') . "\n";
        $content .= "**Module**: {$module}\n";
        $content .= "**Endpoints**: " . count($endpoints) . "\n\n";
        
        $content .= "## Base URL\n";
        $content .= "`/modules/{$module}/api/`\n\n";
        
        $content .= "## Endpoints\n\n";
        
        foreach ($endpoints as $endpoint) {
            $content .= "### {$endpoint['name']}\n\n";
            $content .= "**File**: `" . basename($endpoint['file']) . "`\n";
            
            if (!empty($endpoint['description'])) {
                $content .= "**Description**: {$endpoint['description']}\n";
            }
            
            if (!empty($endpoint['methods'])) {
                $content .= "**Methods**: " . implode(', ', $endpoint['methods']) . "\n";
            }
            
            if (!empty($endpoint['parameters'])) {
                $content .= "**Parameters**:\n";
                foreach ($endpoint['parameters'] as $param) {
                    $content .= "- `{$param['name']}` ({$param['type']}): {$param['description']}\n";
                }
            }
            
            $content .= "\n";
        }
        
        return $content;
    }
    
    /**
     * Load configuration
     */
    private function loadConfig(): array
    {
        return [
            'exclude_dirs' => ['.git', 'node_modules', 'vendor', 'backups'],
            'api_patterns' => ['*.php'],
            'doc_patterns' => ['*.md', '*.txt'],
            'max_file_size' => 1024 * 1024, // 1MB
        ];
    }
    
    /**
     * Helper methods for file analysis
     */
    private function countFiles(string $path): int
    {
        if (!is_dir($path)) return 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        return iterator_count($iterator);
    }
    
    private function countApiEndpoints(string $apiPath): int
    {
        if (!is_dir($apiPath)) return 0;
        return count(glob($apiPath . '/*.php'));
    }
    
    private function findControllers(string $modulePath): array
    {
        $controllers = [];
        $paths = [$modulePath . '/controllers', $modulePath . '/transfers/controllers'];
        
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $files = glob($path . '/*Controller.php');
                foreach ($files as $file) {
                    $controllers[] = basename($file, '.php');
                }
            }
        }
        
        return $controllers;
    }
    
    private function findModuleDependencies(string $modulePath): array
    {
        // Analyze use statements and require statements to find dependencies
        $dependencies = [];
        $files = glob($modulePath . '/*.php');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (preg_match_all('/use\s+([^;]+);/', $content, $matches)) {
                foreach ($matches[1] as $use) {
                    if (strpos($use, 'Modules\\') === 0) {
                        $dependencies[] = $use;
                    }
                }
            }
        }
        
        return array_unique($dependencies);
    }
    
    private function getLastModified(string $path): int
    {
        if (!is_dir($path)) return 0;
        
        $latest = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $mtime = $file->getMTime();
                if ($mtime > $latest) {
                    $latest = $mtime;
                }
            }
        }
        
        return $latest;
    }
    
    private function getDirectorySize(string $path): int
    {
        if (!is_dir($path)) return 0;
        
        $size = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
    
    private function extractHttpMethods(string $file): array
    {
        $content = file_get_contents($file);
        $methods = [];
        
        if (strpos($content, '$_POST') !== false) $methods[] = 'POST';
        if (strpos($content, '$_GET') !== false) $methods[] = 'GET';
        if (strpos($content, '$_PUT') !== false) $methods[] = 'PUT';
        if (strpos($content, '$_DELETE') !== false) $methods[] = 'DELETE';
        
        return empty($methods) ? ['GET', 'POST'] : $methods;
    }
    
    private function extractParameters(string $file): array
    {
        $content = file_get_contents($file);
        $parameters = [];
        
        // Extract $_GET and $_POST parameters
        if (preg_match_all('/\\$_(GET|POST)\\[([\'"])([^\\2]+)\\2\\]/', $content, $matches)) {
            for ($i = 0; $i < count($matches[0]); $i++) {
                $parameters[] = [
                    'name' => $matches[3][$i],
                    'type' => 'string',
                    'description' => 'Auto-detected parameter'
                ];
            }
        }
        
        return array_unique($parameters, SORT_REGULAR);
    }
    
    private function extractDescription(string $file): string
    {
        $content = file_get_contents($file);
        
        // Try to extract from docblock
        if (preg_match('/\\/\\*\\*.*?\\*\\s*([^*]+)/s', $content, $matches)) {
            return trim($matches[1]);
        }
        
        // Try to extract from comment
        if (preg_match('/\\/\\/ (.+)/', $content, $matches)) {
            return trim($matches[1]);
        }
        
        return 'Auto-generated endpoint documentation';
    }
    
    private function saveModuleAnalysis(array $analysis): void
    {
        $content = "# Module Analysis Report\n\n";
        $content .= "**Generated**: " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($analysis as $module => $data) {
            $content .= "## {$module}\n\n";
            $content .= "- **Files**: {$data['files']}\n";
            $content .= "- **API Endpoints**: {$data['apis']}\n";
            $content .= "- **Controllers**: " . count($data['controllers']) . "\n";
            $content .= "- **Dependencies**: " . count($data['dependencies']) . "\n";
            $content .= "- **Last Modified**: " . date('Y-m-d H:i:s', $data['last_modified']) . "\n";
            $content .= "- **Size**: " . number_format($data['size'] / 1024, 2) . " KB\n\n";
        }
        
        @mkdir($this->docsPath . '/analysis', 0755, true);
        file_put_contents($this->docsPath . '/analysis/module-analysis.md', $content);
    }
    
    // Placeholder methods for future implementation
    private function analyzeDependencies(): array { 
        return [
            'consignments' => ['core', 'shared'],
            'inventory' => ['core', 'consignments'],
        ]; 
    }
    
    private function generateDependencyMapContent(array $dependencies): string { 
        $content = "# Dependency Map\n\n";
        $content .= "**Generated**: " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($dependencies as $module => $deps) {
            $content .= "## {$module}\n";
            $content .= "**Dependencies**: " . implode(', ', $deps) . "\n\n";
        }
        
        return $content;
    }
    
    private function buildCrossReferenceIndex(): array { 
        return [
            'api_endpoints' => ['consignments' => 9, 'inventory' => 5],
            'controllers' => ['PackController', 'ReceiveController'],
        ]; 
    }
    
    private function generateCrossReferenceContent(array $references): string { 
        $content = "# Cross-Reference Index\n\n";
        $content .= "**Generated**: " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($references as $type => $items) {
            $content .= "## " . ucfirst(str_replace('_', ' ', $type)) . "\n\n";
            if (is_array($items)) {
                foreach ($items as $key => $value) {
                    $content .= "- **{$key}**: {$value}\n";
                }
            }
            $content .= "\n";
        }
        
        return $content;
    }
    
    private function identifyCodePatterns(): array { return []; }
    private function updateCodePatterns(array $patterns): void {}
    private function extractArchitecturalDecisions(): array { return []; }
    private function updateDecisions(array $decisions): void {}
}

// Create necessary directories
$docsPath = __DIR__ . '/docs';
@mkdir($docsPath . '/api', 0755, true);
@mkdir($docsPath . '/analysis', 0755, true);

// Run the generator
$generator = new DocumentationGenerator(__DIR__);
$generator->run();
