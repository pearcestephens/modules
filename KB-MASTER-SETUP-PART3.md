# 🧠 CIS Knowledge Base Master Setup Guide - PART 3
## Additional Scripts and Portable Context Refresh

---

## 📝 Example Code & Templates (Continued)

### 2. `map-relationships.php` - Complete Relationship Mapper

```php
<?php
/**
 * CIS Knowledge Base - Relationship Mapper
 * 
 * Maps all code relationships:
 * - Include/require chains
 * - Class dependencies
 * - Function call graphs
 * - Database table usage
 * - Cross-module dependencies
 * 
 * Usage:
 *   php map-relationships.php
 *   php map-relationships.php --circular
 *   php map-relationships.php --output=json
 * 
 * @package CIS\KB\Tools
 * @version 2.0.0
 */

declare(strict_types=1);

$config = [
    'project_root' => '/home/master/applications/jcepnzzkmj/public_html',
    'kb_root' => '/home/master/applications/jcepnzzkmj/public_html/_kb',
    'modules_dir' => '/home/master/applications/jcepnzzkmj/public_html/modules',
    'max_depth' => 10,
];

$options = getopt('', ['circular', 'output:', 'module:', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Relationship Mapper

Usage:
  php map-relationships.php [OPTIONS]

Options:
  --circular           Only show circular dependencies
  --output=FORMAT      Output format: json, dot, text (default: json)
  --module=NAME        Map specific module only
  --help               Show this help

HELP;
    exit(0);
}

$startTime = microtime(true);
log_message("Starting relationship mapping...");

// Step 1: Map include/require chains
log_message("Mapping include/require chains...");
$includeMap = mapIncludes($config);
saveJson($config['kb_root'] . '/relationships/include-map.json', $includeMap);

// Step 2: Map class dependencies
log_message("Mapping class dependencies...");
$classMap = mapClasses($config);
saveJson($config['kb_root'] . '/relationships/class-hierarchy.json', $classMap);

// Step 3: Map function calls
log_message("Mapping function calls...");
$functionMap = mapFunctions($config);
saveJson($config['kb_root'] . '/relationships/function-calls.json', $functionMap);

// Step 4: Map database usage
log_message("Mapping database usage...");
$dbMap = mapDatabaseUsage($config);
saveJson($config['kb_root'] . '/relationships/database-usage.json', $dbMap);

// Step 5: Map cross-module dependencies
log_message("Mapping cross-module dependencies...");
$crossModuleMap = mapCrossModule($config, $includeMap, $classMap);
saveJson($config['kb_root'] . '/relationships/cross-module.json', $crossModuleMap);

// Step 6: Detect circular dependencies
log_message("Detecting circular dependencies...");
$circularDeps = detectCircularDependencies($includeMap);
if (!empty($circularDeps)) {
    saveJson($config['kb_root'] . '/relationships/circular-dependencies.json', $circularDeps);
    log_message("WARNING: Found " . count($circularDeps) . " circular dependencies");
}

// Step 7: Build full dependency graph
log_message("Building full dependency graph...");
$dependencyGraph = buildDependencyGraph($includeMap, $classMap, $functionMap);
saveJson($config['kb_root'] . '/relationships/dependency-graph.json', $dependencyGraph);

$duration = round(microtime(true) - $startTime, 2);
log_message("Relationship mapping completed in {$duration}s");

exit(0);

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function mapIncludes(array $config): array
{
    $map = [];
    $phpFiles = getPhpFiles($config['modules_dir']);
    
    foreach ($phpFiles as $file) {
        $relativePath = str_replace($config['project_root'] . '/', '', $file);
        $content = file_get_contents($file);
        
        $includes = [];
        
        // Match require, require_once, include, include_once
        preg_match_all(
            '/(require|require_once|include|include_once)\s*[\(\s]+[\'"]([^\'"]+)[\'"]/',
            $content,
            $matches
        );
        
        foreach ($matches[2] as $includedFile) {
            // Resolve relative paths
            $resolved = resolveIncludePath($file, $includedFile, $config);
            if ($resolved) {
                $includes[] = str_replace($config['project_root'] . '/', '', $resolved);
            }
        }
        
        $map[$relativePath] = [
            'file' => $relativePath,
            'includes' => array_unique($includes),
            'module' => getModuleFromPath($relativePath),
        ];
    }
    
    // Add reverse mapping (included_by)
    foreach ($map as $file => &$data) {
        $data['included_by'] = [];
        foreach ($map as $otherFile => $otherData) {
            if (in_array($file, $otherData['includes'])) {
                $data['included_by'][] = $otherFile;
            }
        }
    }
    
    return $map;
}

function mapClasses(array $config): array
{
    $map = [];
    $phpFiles = getPhpFiles($config['modules_dir']);
    
    foreach ($phpFiles as $file) {
        $relativePath = str_replace($config['project_root'] . '/', '', $file);
        $content = file_get_contents($file);
        
        // Extract namespace
        $namespace = null;
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = trim($matches[1]);
        }
        
        // Extract classes
        preg_match_all(
            '/class\s+(\w+)(?:\s+extends\s+(\w+))?(?:\s+implements\s+([^{]+))?/i',
            $content,
            $classMatches,
            PREG_SET_ORDER
        );
        
        foreach ($classMatches as $match) {
            $className = $match[1];
            $extends = $match[2] ?? null;
            $implements = isset($match[3]) ? array_map('trim', explode(',', $match[3])) : [];
            
            $fullClassName = $namespace ? $namespace . '\\' . $className : $className;
            
            // Extract use statements
            $uses = [];
            preg_match_all('/use\s+([^;]+);/', $content, $useMatches);
            foreach ($useMatches[1] as $use) {
                $uses[] = trim($use);
            }
            
            $map[$fullClassName] = [
                'class' => $fullClassName,
                'file' => $relativePath,
                'namespace' => $namespace,
                'extends' => $extends,
                'implements' => $implements,
                'uses' => $uses,
                'module' => getModuleFromPath($relativePath),
            ];
        }
    }
    
    return $map;
}

function mapFunctions(array $config): array
{
    $map = [];
    $phpFiles = getPhpFiles($config['modules_dir']);
    
    foreach ($phpFiles as $file) {
        $relativePath = str_replace($config['project_root'] . '/', '', $file);
        $content = file_get_contents($file);
        
        // Extract function definitions
        preg_match_all(
            '/function\s+(\w+)\s*\(([^)]*)\)/',
            $content,
            $functionMatches,
            PREG_SET_ORDER
        );
        
        foreach ($functionMatches as $match) {
            $functionName = $match[1];
            $params = $match[2];
            
            // Find function calls within this function
            $calls = [];
            // This is simplified - in production use PHP-Parser for accurate AST
            preg_match_all('/(\w+)\s*\(/', $content, $callMatches);
            $calls = array_unique(array_filter($callMatches[1], function($name) use ($functionName) {
                return $name !== $functionName && ctype_alpha($name[0]);
            }));
            
            $map[$functionName] = [
                'function' => $functionName,
                'file' => $relativePath,
                'params' => trim($params),
                'calls' => array_values($calls),
                'module' => getModuleFromPath($relativePath),
            ];
        }
    }
    
    return $map;
}

function mapDatabaseUsage(array $config): array
{
    $map = [];
    $phpFiles = getPhpFiles($config['modules_dir']);
    
    foreach ($phpFiles as $file) {
        $relativePath = str_replace($config['project_root'] . '/', '', $file);
        $content = file_get_contents($file);
        
        $tables = [
            'read' => [],
            'write' => [],
            'delete' => [],
        ];
        
        // Find SELECT queries
        preg_match_all('/SELECT\s+.+?\s+FROM\s+`?(\w+)`?/i', $content, $selectMatches);
        $tables['read'] = array_merge($tables['read'], $selectMatches[1]);
        
        // Find INSERT queries
        preg_match_all('/INSERT\s+INTO\s+`?(\w+)`?/i', $content, $insertMatches);
        $tables['write'] = array_merge($tables['write'], $insertMatches[1]);
        
        // Find UPDATE queries
        preg_match_all('/UPDATE\s+`?(\w+)`?/i', $content, $updateMatches);
        $tables['write'] = array_merge($tables['write'], $updateMatches[1]);
        
        // Find DELETE queries
        preg_match_all('/DELETE\s+FROM\s+`?(\w+)`?/i', $content, $deleteMatches);
        $tables['delete'] = array_merge($tables['delete'], $deleteMatches[1]);
        
        if (!empty($tables['read']) || !empty($tables['write']) || !empty($tables['delete'])) {
            $map[$relativePath] = [
                'file' => $relativePath,
                'tables' => [
                    'read' => array_unique($tables['read']),
                    'write' => array_unique($tables['write']),
                    'delete' => array_unique($tables['delete']),
                ],
                'module' => getModuleFromPath($relativePath),
            ];
        }
    }
    
    return $map;
}

function mapCrossModule(array $config, array $includeMap, array $classMap): array
{
    $crossModule = [];
    
    // From includes
    foreach ($includeMap as $file => $data) {
        $sourceModule = $data['module'];
        if (!$sourceModule) continue;
        
        foreach ($data['includes'] as $includedFile) {
            $targetModule = getModuleFromPath($includedFile);
            if ($targetModule && $targetModule !== $sourceModule) {
                $key = "{$sourceModule} -> {$targetModule}";
                if (!isset($crossModule[$key])) {
                    $crossModule[$key] = [
                        'source' => $sourceModule,
                        'target' => $targetModule,
                        'files' => [],
                        'type' => 'include',
                    ];
                }
                $crossModule[$key]['files'][] = [
                    'from' => $file,
                    'to' => $includedFile,
                ];
            }
        }
    }
    
    // From class usage
    foreach ($classMap as $className => $data) {
        $sourceModule = $data['module'];
        if (!$sourceModule) continue;
        
        foreach ($data['uses'] as $usedClass) {
            // Find which module defines this class
            $targetModule = null;
            foreach ($classMap as $otherClass => $otherData) {
                if (strpos($otherClass, $usedClass) !== false) {
                    $targetModule = $otherData['module'];
                    break;
                }
            }
            
            if ($targetModule && $targetModule !== $sourceModule) {
                $key = "{$sourceModule} -> {$targetModule}";
                if (!isset($crossModule[$key])) {
                    $crossModule[$key] = [
                        'source' => $sourceModule,
                        'target' => $targetModule,
                        'files' => [],
                        'type' => 'class_usage',
                    ];
                }
                $crossModule[$key]['files'][] = [
                    'from' => $data['file'],
                    'class' => $usedClass,
                ];
            }
        }
    }
    
    return array_values($crossModule);
}

function detectCircularDependencies(array $includeMap): array
{
    $circular = [];
    
    foreach ($includeMap as $file => $data) {
        $visited = [$file];
        $chain = findCircular($file, $includeMap, $visited);
        
        if (!empty($chain)) {
            $circular[] = [
                'start' => $file,
                'chain' => $chain,
            ];
        }
    }
    
    return $circular;
}

function findCircular(string $file, array $map, array $visited): array
{
    if (!isset($map[$file])) return [];
    
    foreach ($map[$file]['includes'] as $included) {
        if (in_array($included, $visited)) {
            // Found circular dependency
            return array_merge($visited, [$included]);
        }
        
        $newVisited = array_merge($visited, [$included]);
        $result = findCircular($included, $map, $newVisited);
        
        if (!empty($result)) {
            return $result;
        }
    }
    
    return [];
}

function buildDependencyGraph(array $includeMap, array $classMap, array $functionMap): array
{
    return [
        'summary' => [
            'total_files' => count($includeMap),
            'total_classes' => count($classMap),
            'total_functions' => count($functionMap),
            'timestamp' => date('Y-m-d H:i:s'),
        ],
        'includes' => $includeMap,
        'classes' => $classMap,
        'functions' => $functionMap,
    ];
}

function resolveIncludePath(string $fromFile, string $includePath, array $config): ?string
{
    // Handle absolute paths
    if ($includePath[0] === '/') {
        return $includePath;
    }
    
    // Handle $_SERVER['DOCUMENT_ROOT'] prefixes
    if (strpos($includePath, '$_SERVER') !== false) {
        // Extract the path part
        if (preg_match('/\$_SERVER\[[\'"]DOCUMENT_ROOT[\'"]\]\s*\.\s*[\'"]([^\'"]*)/', $includePath, $matches)) {
            return $config['project_root'] . $matches[1];
        }
        return null;
    }
    
    // Handle relative paths
    $dir = dirname($fromFile);
    $resolved = realpath($dir . '/' . $includePath);
    
    return $resolved ?: null;
}

function getPhpFiles(string $dir): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    
    return $files;
}

function getModuleFromPath(string $path): ?string
{
    if (preg_match('#modules/([^/]+)/#', $path, $matches)) {
        return $matches[1];
    }
    return null;
}

function saveJson(string $file, array $data): void
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function log_message(string $message): void
{
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] {$message}\n";
}
```

---

### 3. `analyze-performance.php` - Performance Analyzer

```php
<?php
/**
 * CIS Knowledge Base - Performance Analyzer
 * 
 * Analyzes codebase performance characteristics:
 * - Slow query log parsing
 * - Cyclomatic complexity
 * - File size growth
 * - Query count per endpoint
 * 
 * Usage:
 *   php analyze-performance.php
 *   php analyze-performance.php --slow-queries
 *   php analyze-performance.php --complexity
 * 
 * @package CIS\KB\Tools
 * @version 2.0.0
 */

declare(strict_types=1);

$config = [
    'project_root' => '/home/master/applications/jcepnzzkmj/public_html',
    'kb_root' => '/home/master/applications/jcepnzzkmj/public_html/_kb',
    'modules_dir' => '/home/master/applications/jcepnzzkmj/public_html/modules',
    'slow_query_log' => '/home/master/applications/jcepnzzkmj/public_html/logs/slow-queries.log',
    'slow_threshold' => 300, // milliseconds
    'complexity_threshold' => 15,
    'large_file_threshold' => 500, // lines
];

log_message("Starting performance analysis...");

// 1. Parse slow query log
if (file_exists($config['slow_query_log'])) {
    log_message("Parsing slow query log...");
    $slowQueries = parseSlowQueryLog($config);
    saveJson($config['kb_root'] . '/performance/slow-queries.json', $slowQueries);
    log_message("Found " . count($slowQueries) . " slow queries");
}

// 2. Calculate cyclomatic complexity
log_message("Calculating cyclomatic complexity...");
$complexityReport = calculateComplexity($config);
saveJson($config['kb_root'] . '/performance/complexity.json', $complexityReport);
$highComplexity = array_filter($complexityReport, function($item) use ($config) {
    return $item['complexity'] > $config['complexity_threshold'];
});
log_message("Found " . count($highComplexity) . " functions with high complexity");

// 3. Track file size growth
log_message("Tracking file size growth...");
$fileSizes = trackFileSizes($config);
saveJson($config['kb_root'] . '/performance/file-sizes.json', $fileSizes);
$largeFiles = array_filter($fileSizes, function($item) use ($config) {
    return $item['lines'] > $config['large_file_threshold'];
});
log_message("Found " . count($largeFiles) . " large files (>" . $config['large_file_threshold'] . " lines)");

// 4. Generate performance report
log_message("Generating performance report...");
$report = generatePerformanceReport($config, $slowQueries ?? [], $complexityReport, $fileSizes);
file_put_contents($config['kb_root'] . '/performance/report.md', $report);

log_message("Performance analysis completed");

exit(0);

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function parseSlowQueryLog(array $config): array
{
    $log = file_get_contents($config['slow_query_log']);
    $queries = [];
    
    // Parse slow query log format (MySQL/MariaDB)
    // This is simplified - adjust to your log format
    preg_match_all(
        '/# Time: (.+?)\n# User@Host: (.+?)\n# Query_time: ([\d.]+).+?\n(.+?);/s',
        $log,
        $matches,
        PREG_SET_ORDER
    );
    
    foreach ($matches as $match) {
        $queries[] = [
            'timestamp' => $match[1],
            'user' => $match[2],
            'duration' => (float)$match[3],
            'query' => trim($match[4]),
        ];
    }
    
    // Sort by duration descending
    usort($queries, function($a, $b) {
        return $b['duration'] <=> $a['duration'];
    });
    
    return array_slice($queries, 0, 100); // Top 100
}

function calculateComplexity(array $config): array
{
    $report = [];
    $phpFiles = getPhpFiles($config['modules_dir']);
    
    foreach ($phpFiles as $file) {
        $relativePath = str_replace($config['project_root'] . '/', '', $file);
        $content = file_get_contents($file);
        
        // Extract functions
        preg_match_all(
            '/function\s+(\w+)\s*\(([^)]*)\)\s*(?::\s*\S+)?\s*\{/s',
            $content,
            $matches,
            PREG_OFFSET_CAPTURE
        );
        
        foreach ($matches[0] as $i => $match) {
            $functionName = $matches[1][$i][0];
            $startPos = $match[1];
            
            // Find function end (matching closing brace)
            $braceCount = 1;
            $pos = $startPos + strlen($match[0]);
            $endPos = $pos;
            
            while ($braceCount > 0 && $pos < strlen($content)) {
                if ($content[$pos] === '{') $braceCount++;
                if ($content[$pos] === '}') $braceCount--;
                if ($braceCount === 0) $endPos = $pos;
                $pos++;
            }
            
            $functionBody = substr($content, $startPos, $endPos - $startPos + 1);
            $complexity = calculateFunctionComplexity($functionBody);
            $lines = substr_count($functionBody, "\n") + 1;
            
            $report[] = [
                'file' => $relativePath,
                'function' => $functionName,
                'complexity' => $complexity,
                'lines' => $lines,
                'module' => getModuleFromPath($relativePath),
            ];
        }
    }
    
    // Sort by complexity descending
    usort($report, function($a, $b) {
        return $b['complexity'] <=> $a['complexity'];
    });
    
    return $report;
}

function calculateFunctionComplexity(string $code): int
{
    // Simplified cyclomatic complexity calculation
    // Base complexity = 1
    $complexity = 1;
    
    // Count decision points
    $complexity += preg_match_all('/\bif\b/', $code);
    $complexity += preg_match_all('/\belse\b/', $code);
    $complexity += preg_match_all('/\belseif\b/', $code);
    $complexity += preg_match_all('/\bfor\b/', $code);
    $complexity += preg_match_all('/\bforeach\b/', $code);
    $complexity += preg_match_all('/\bwhile\b/', $code);
    $complexity += preg_match_all('/\bcase\b/', $code);
    $complexity += preg_match_all('/\bcatch\b/', $code);
    $complexity += preg_match_all('/\b\?\?/', $code); // Null coalescing
    $complexity += preg_match_all('/\?[^:]/', $code); // Ternary
    $complexity += preg_match_all('/&&|\|\|/', $code); // Logical operators
    
    return $complexity;
}

function trackFileSizes(array $config): array
{
    $sizes = [];
    $phpFiles = getPhpFiles($config['modules_dir']);
    
    foreach ($phpFiles as $file) {
        $relativePath = str_replace($config['project_root'] . '/', '', $file);
        $lines = count(file($file));
        $bytes = filesize($file);
        
        $sizes[] = [
            'file' => $relativePath,
            'lines' => $lines,
            'bytes' => $bytes,
            'kb' => round($bytes / 1024, 2),
            'module' => getModuleFromPath($relativePath),
        ];
    }
    
    // Sort by lines descending
    usort($sizes, function($a, $b) {
        return $b['lines'] <=> $a['lines'];
    });
    
    return $sizes;
}

function generatePerformanceReport(array $config, array $slowQueries, array $complexity, array $fileSizes): string
{
    $report = "# Performance Analysis Report\n\n";
    $report .= "**Generated:** " . date('Y-m-d H:i:s') . "\n\n";
    $report .= "---\n\n";
    
    // Summary
    $report .= "## Summary\n\n";
    $report .= "- **Slow Queries:** " . count($slowQueries) . "\n";
    $report .= "- **High Complexity Functions:** " . count(array_filter($complexity, function($c) use ($config) {
        return $c['complexity'] > $config['complexity_threshold'];
    })) . "\n";
    $report .= "- **Large Files:** " . count(array_filter($fileSizes, function($f) use ($config) {
        return $f['lines'] > $config['large_file_threshold'];
    })) . "\n\n";
    
    // Slow Queries
    $report .= "## Top 10 Slowest Queries\n\n";
    $report .= "| Duration (s) | Query Preview |\n";
    $report .= "|--------------|---------------|\n";
    foreach (array_slice($slowQueries, 0, 10) as $query) {
        $preview = substr($query['query'], 0, 80) . '...';
        $report .= sprintf("| %.3f | %s |\n", $query['duration'], $preview);
    }
    $report .= "\n";
    
    // High Complexity
    $report .= "## Top 10 Most Complex Functions\n\n";
    $report .= "| Complexity | Function | File | Lines |\n";
    $report .= "|------------|----------|------|-------|\n";
    foreach (array_slice($complexity, 0, 10) as $func) {
        $report .= sprintf("| %d | `%s` | %s | %d |\n",
            $func['complexity'],
            $func['function'],
            $func['file'],
            $func['lines']
        );
    }
    $report .= "\n";
    
    // Large Files
    $report .= "## Top 10 Largest Files\n\n";
    $report .= "| Lines | Size (KB) | File |\n";
    $report .= "|-------|-----------|------|\n";
    foreach (array_slice($fileSizes, 0, 10) as $file) {
        $report .= sprintf("| %d | %.2f | %s |\n",
            $file['lines'],
            $file['kb'],
            $file['file']
        );
    }
    $report .= "\n";
    
    // Recommendations
    $report .= "## Recommendations\n\n";
    $report .= "### High Priority\n\n";
    
    if (count($slowQueries) > 10) {
        $report .= "- **Optimize slow queries:** " . count($slowQueries) . " queries taking > " . ($config['slow_threshold'] / 1000) . "s\n";
        $report .= "  - Add missing indexes\n";
        $report .= "  - Refactor N+1 queries\n";
        $report .= "  - Use query result caching\n\n";
    }
    
    $veryComplex = array_filter($complexity, function($c) {
        return $c['complexity'] > 25;
    });
    if (count($veryComplex) > 0) {
        $report .= "- **Refactor complex functions:** " . count($veryComplex) . " functions with complexity > 25\n";
        $report .= "  - Break into smaller functions\n";
        $report .= "  - Extract conditional logic\n";
        $report .= "  - Simplify nested loops\n\n";
    }
    
    $veryLarge = array_filter($fileSizes, function($f) {
        return $f['lines'] > 1000;
    });
    if (count($veryLarge) > 0) {
        $report .= "- **Split large files:** " . count($veryLarge) . " files > 1000 lines\n";
        $report .= "  - Extract classes to separate files\n";
        $report .= "  - Move helpers to utility files\n";
        $report .= "  - Split into modules\n\n";
    }
    
    return $report;
}

function getPhpFiles(string $dir): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

function getModuleFromPath(string $path): ?string
{
    if (preg_match('#modules/([^/]+)/#', $path, $matches)) {
        return $matches[1];
    }
    return null;
}

function saveJson(string $file, array $data): void
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function log_message(string $message): void
{
    echo "[" . date('Y-m-d H:i:s') . "] {$message}\n";
}
```

---

Continuing with **Part 4** - The Portable Context Refresh Guide in next message...

