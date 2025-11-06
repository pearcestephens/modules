<?php
/**
 * DocumentationBuilder - Auto-generate documentation for CIS modules
 *
 * @package CIS\ControlPanel
 * @version 1.0.0
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 */

namespace CIS\ControlPanel;

class DocumentationBuilder
{
    private $pdo;
    private $config;
    private $modulesPath;
    private $docsPath;

    public function __construct($pdo, $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'modules_path' => dirname(__DIR__, 2),
            'docs_path' => __DIR__ . '/../docs',
            'auto_generate' => $_ENV['DOCS_AUTO_GENERATE'] ?? false,
            'include_api_docs' => true,
            'include_db_schema' => true,
            'include_code_examples' => true,
        ], $config);

        $this->modulesPath = $this->config['modules_path'];
        $this->docsPath = $this->config['docs_path'];
        $this->ensureDocsDirectory();
    }

    /**
     * Ensure docs directory exists
     */
    private function ensureDocsDirectory()
    {
        if (!is_dir($this->docsPath)) {
            mkdir($this->docsPath, 0755, true);
        }
    }

    /**
     * Generate documentation for a specific module
     */
    public function generateModuleDocs($moduleName)
    {
        $modulePath = $this->modulesPath . '/' . $moduleName;

        if (!is_dir($modulePath)) {
            return ['success' => false, 'error' => "Module not found: $moduleName"];
        }

        try {
            $docs = [
                'module_name' => $moduleName,
                'generated_at' => date('Y-m-d H:i:s'),
                'overview' => $this->extractOverview($modulePath),
                'version' => $this->extractVersion($modulePath),
                'author' => $this->extractAuthor($modulePath),
                'files' => $this->scanFiles($modulePath),
                'classes' => $this->extractClasses($modulePath),
                'functions' => $this->extractFunctions($modulePath),
                'api_endpoints' => $this->extractAPIEndpoints($modulePath),
                'database_schema' => $this->extractDatabaseSchema($modulePath),
                'dependencies' => $this->extractDependencies($modulePath),
                'configuration' => $this->extractConfiguration($modulePath),
            ];

            // Generate markdown
            $markdown = $this->generateMarkdown($docs);

            // Save to file
            $filename = $this->docsPath . "/{$moduleName}.md";
            file_put_contents($filename, $markdown);

            return [
                'success' => true,
                'filename' => $filename,
                'size' => strlen($markdown)
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate documentation for all modules
     */
    public function generateAllDocs()
    {
        $modules = array_filter(
            scandir($this->modulesPath),
            function ($item) {
                return $item[0] !== '.' && is_dir($this->modulesPath . '/' . $item);
            }
        );

        $results = [];
        foreach ($modules as $module) {
            $results[$module] = $this->generateModuleDocs($module);
        }

        // Generate index
        $this->generateIndex($results);

        return [
            'success' => true,
            'modules_documented' => count($modules),
            'results' => $results
        ];
    }

    /**
     * Extract module overview from README or bootstrap
     */
    private function extractOverview($modulePath)
    {
        $readmePath = $modulePath . '/README.md';
        if (file_exists($readmePath)) {
            $content = file_get_contents($readmePath);
            // Extract first paragraph
            preg_match('/^#{1,3}\s+(.+?)$/m', $content, $title);
            preg_match('/^([^\n#]+?)$/m', $content, $description);
            return [
                'title' => $title[1] ?? basename($modulePath),
                'description' => trim($description[1] ?? '')
            ];
        }

        // Try bootstrap.php
        $bootstrapPath = $modulePath . '/bootstrap.php';
        if (file_exists($bootstrapPath)) {
            $content = file_get_contents($bootstrapPath);
            preg_match('/@description\s+(.+)/i', $content, $match);
            return [
                'title' => basename($modulePath),
                'description' => trim($match[1] ?? 'No description available')
            ];
        }

        return ['title' => basename($modulePath), 'description' => 'No description available'];
    }

    /**
     * Extract version from bootstrap or README
     */
    private function extractVersion($modulePath)
    {
        $files = ['bootstrap.php', 'README.md'];
        foreach ($files as $file) {
            $filepath = $modulePath . '/' . $file;
            if (file_exists($filepath)) {
                $content = file_get_contents($filepath);
                if (preg_match('/@version\s+([\d.]+)/i', $content, $match)) {
                    return $match[1];
                }
                if (preg_match('/version[:\s]+([\d.]+)/i', $content, $match)) {
                    return $match[1];
                }
            }
        }
        return '1.0.0';
    }

    /**
     * Extract author from bootstrap or README
     */
    private function extractAuthor($modulePath)
    {
        $files = ['bootstrap.php', 'README.md'];
        foreach ($files as $file) {
            $filepath = $modulePath . '/' . $file;
            if (file_exists($filepath)) {
                $content = file_get_contents($filepath);
                if (preg_match('/@author\s+(.+?)(?:\n|$)/i', $content, $match)) {
                    return trim($match[1]);
                }
            }
        }
        return 'Unknown';
    }

    /**
     * Scan all files in module
     */
    private function scanFiles($modulePath)
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($modulePath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($modulePath . '/', '', $file->getPathname());
                $files[] = [
                    'path' => $relativePath,
                    'size' => $file->getSize(),
                    'extension' => $file->getExtension(),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime())
                ];
            }
        }

        return $files;
    }

    /**
     * Extract PHP classes and their methods
     */
    private function extractClasses($modulePath)
    {
        $classes = [];
        $phpFiles = $this->findPHPFiles($modulePath);

        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);

            // Extract class name
            if (preg_match('/class\s+(\w+)/i', $content, $classMatch)) {
                $className = $classMatch[1];

                // Extract methods
                preg_match_all('/(?:public|private|protected)\s+function\s+(\w+)\s*\(([^)]*)\)/i', $content, $methodMatches, PREG_SET_ORDER);

                $methods = [];
                foreach ($methodMatches as $method) {
                    // Extract docblock
                    $methodPos = strpos($content, $method[0]);
                    $beforeMethod = substr($content, 0, $methodPos);
                    if (preg_match('/\/\*\*(.+?)\*\//s', $beforeMethod, $docMatch)) {
                        $doc = end($docMatch);
                    } else {
                        $doc = '';
                    }

                    $methods[] = [
                        'name' => $method[1],
                        'parameters' => $method[2],
                        'docblock' => trim($doc ?? '')
                    ];
                }

                $classes[] = [
                    'name' => $className,
                    'file' => str_replace($modulePath . '/', '', $file),
                    'methods' => $methods
                ];
            }
        }

        return $classes;
    }

    /**
     * Extract standalone functions
     */
    private function extractFunctions($modulePath)
    {
        $functions = [];
        $phpFiles = $this->findPHPFiles($modulePath);

        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);

            // Find functions that are NOT inside classes
            preg_match_all('/^function\s+(\w+)\s*\(([^)]*)\)/m', $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $functions[] = [
                    'name' => $match[1],
                    'parameters' => $match[2],
                    'file' => str_replace($modulePath . '/', '', $file)
                ];
            }
        }

        return $functions;
    }

    /**
     * Extract API endpoints
     */
    private function extractAPIEndpoints($modulePath)
    {
        $endpoints = [];
        $apiPath = $modulePath . '/api';

        if (!is_dir($apiPath)) {
            return $endpoints;
        }

        $phpFiles = $this->findPHPFiles($apiPath);

        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            $filename = basename($file, '.php');

            // Extract HTTP methods
            $methods = [];
            if (preg_match_all('/(GET|POST|PUT|DELETE|PATCH)/i', $content, $methodMatches)) {
                $methods = array_unique($methodMatches[1]);
            }

            // Extract endpoint description from comments
            preg_match('/\/\*\*(.+?)\*\//s', $content, $docMatch);
            $description = $docMatch[1] ?? '';

            $endpoints[] = [
                'file' => $filename . '.php',
                'path' => '/api/' . $filename,
                'methods' => $methods,
                'description' => trim($description)
            ];
        }

        return $endpoints;
    }

    /**
     * Extract database schema (CREATE TABLE statements)
     */
    private function extractDatabaseSchema($modulePath)
    {
        $schema = [];
        $phpFiles = $this->findPHPFiles($modulePath);

        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);

            // Find CREATE TABLE statements
            if (preg_match_all('/CREATE TABLE(?:\s+IF NOT EXISTS)?\s+[`"]?(\w+)[`"]?\s*\((.+?)\);/is', $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $schema[] = [
                        'table' => $match[1],
                        'definition' => trim($match[2]),
                        'file' => str_replace($modulePath . '/', '', $file)
                    ];
                }
            }
        }

        return $schema;
    }

    /**
     * Extract configuration options
     */
    private function extractConfiguration($modulePath)
    {
        $config = [];
        $bootstrapPath = $modulePath . '/bootstrap.php';

        if (file_exists($bootstrapPath)) {
            $content = file_get_contents($bootstrapPath);

            // Find $_ENV references
            if (preg_match_all('/\$_ENV\[[\'"](\w+)[\'"]\]/i', $content, $matches)) {
                $config = array_unique($matches[1]);
            }
        }

        return $config;
    }

    /**
     * Extract module dependencies
     */
    private function extractDependencies($modulePath)
    {
        $deps = [];

        // Check composer.json
        $composerPath = $modulePath . '/composer.json';
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            if (isset($composer['require'])) {
                $deps = array_keys($composer['require']);
            }
        }

        return $deps;
    }

    /**
     * Find all PHP files in path
     */
    private function findPHPFiles($path)
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Generate markdown documentation
     */
    private function generateMarkdown($docs)
    {
        $md = "# {$docs['overview']['title']}\n\n";
        $md .= "> **Generated:** {$docs['generated_at']}  \n";
        $md .= "> **Version:** {$docs['version']}  \n";
        $md .= "> **Author:** {$docs['author']}  \n\n";
        $md .= "---\n\n";

        // Overview
        $md .= "## 📋 Overview\n\n";
        $md .= $docs['overview']['description'] . "\n\n";

        // Files
        $md .= "## 📁 Files\n\n";
        $md .= "Total files: " . count($docs['files']) . "\n\n";
        $md .= "| File | Size | Type | Last Modified |\n";
        $md .= "|------|------|------|---------------|\n";
        foreach (array_slice($docs['files'], 0, 20) as $file) {
            $md .= "| `{$file['path']}` | " . $this->formatBytes($file['size']) . " | {$file['extension']} | {$file['modified']} |\n";
        }
        $md .= "\n";

        // Classes
        if (!empty($docs['classes'])) {
            $md .= "## 🎯 Classes\n\n";
            foreach ($docs['classes'] as $class) {
                $md .= "### `{$class['name']}`\n\n";
                $md .= "**File:** `{$class['file']}`\n\n";
                if (!empty($class['methods'])) {
                    $md .= "**Methods:**\n\n";
                    foreach ($class['methods'] as $method) {
                        $md .= "- `{$method['name']}({$method['parameters']})`\n";
                    }
                }
                $md .= "\n";
            }
        }

        // API Endpoints
        if (!empty($docs['api_endpoints'])) {
            $md .= "## 🔌 API Endpoints\n\n";
            foreach ($docs['api_endpoints'] as $endpoint) {
                $methods = implode(', ', $endpoint['methods']);
                $md .= "### `{$endpoint['path']}`\n\n";
                $md .= "**Methods:** {$methods}  \n";
                $md .= "**File:** `{$endpoint['file']}`\n\n";
            }
        }

        // Database Schema
        if (!empty($docs['database_schema'])) {
            $md .= "## 🗄️ Database Schema\n\n";
            foreach ($docs['database_schema'] as $table) {
                $md .= "### Table: `{$table['table']}`\n\n";
                $md .= "```sql\n";
                $md .= "CREATE TABLE {$table['table']} (\n";
                $md .= "  " . str_replace("\n", "\n  ", trim($table['definition'])) . "\n";
                $md .= ");\n```\n\n";
            }
        }

        // Configuration
        if (!empty($docs['configuration'])) {
            $md .= "## ⚙️ Configuration\n\n";
            $md .= "Environment variables used:\n\n";
            foreach ($docs['configuration'] as $env) {
                $md .= "- `{$env}`\n";
            }
            $md .= "\n";
        }

        // Dependencies
        if (!empty($docs['dependencies'])) {
            $md .= "## 📦 Dependencies\n\n";
            foreach ($docs['dependencies'] as $dep) {
                $md .= "- `{$dep}`\n";
            }
            $md .= "\n";
        }

        $md .= "---\n\n";
        $md .= "*Documentation auto-generated by CIS Control Panel*\n";

        return $md;
    }

    /**
     * Generate documentation index
     */
    private function generateIndex($results)
    {
        $md = "# CIS Modules Documentation Index\n\n";
        $md .= "> **Generated:** " . date('Y-m-d H:i:s') . "\n\n";
        $md .= "---\n\n";

        $md .= "## 📚 Available Modules\n\n";
        foreach ($results as $module => $result) {
            if ($result['success']) {
                $md .= "- [{$module}](./{$module}.md)\n";
            }
        }

        file_put_contents($this->docsPath . '/INDEX.md', $md);
    }

    /**
     * Format bytes to human-readable
     */
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 1) . ' ' . $units[$pow];
    }
}
