# 🧠 CIS Knowledge Base Master Setup Guide - PART 2
## Maintenance Scripts, Performance, and Example Code

---

## 🛠 Maintenance Scripts (Continued)

#### 3. `verify-kb.php` - Verification Tool
**Purpose:** Check KB integrity and completeness  
**Usage:**
```bash
php verify-kb.php                    # Full verification
php verify-kb.php --fix              # Auto-fix simple issues
php verify-kb.php --module=base      # Verify single module
php verify-kb.php --strict           # Fail on warnings
```

**What it checks:**
- All required directories exist
- Required documentation files present
- JSON relationship files valid
- No broken links in markdown
- File permissions correct
- Cache not stale (< 24h for full analysis)
- Cron jobs installed and working
- Git integration functional

**Output:** Pass/Fail report + fix suggestions

**Run time:** 30 seconds

---

#### 4. `cleanup-kb.php` - Cleanup & Optimization
**Purpose:** Remove old data, compress, optimize storage  
**Usage:**
```bash
php cleanup-kb.php --older-than=30   # Remove snapshots older than 30 days
php cleanup-kb.php --compress        # Compress old snapshots
php cleanup-kb.php --purge-cache     # Clear all cached data
php cleanup-kb.php --enforce-limit   # Enforce 1GB storage limit
php cleanup-kb.php --dry-run         # Show what would be deleted
```

**What it cleans:**
- Old snapshots (configurable retention)
- Stale cache entries (> 7 days unused)
- Temporary analysis files
- Orphaned relationship maps
- Compressed logs > 90 days old

**Safety:** Always makes backup before destructive operations

**Run time:** 1-2 minutes

---

#### 5. `analyze-performance.php` - Performance Analyzer
**Purpose:** Identify bottlenecks and optimization opportunities  
**Usage:**
```bash
php analyze-performance.php                      # Analyze all
php analyze-performance.php --slow-queries       # Parse slow query log
php analyze-performance.php --complexity         # Find complex functions
php analyze-performance.php --file-size          # Track file bloat
php analyze-performance.php --trends             # Show historical trends
php analyze-performance.php --report=html        # Generate HTML report
```

**What it analyzes:**
- Slow query log (queries > 300ms)
- Cyclomatic complexity (functions > 15 complexity)
- File size growth (files > 500 lines)
- Memory usage patterns
- Query count per endpoint
- N+1 query detection

**Output:**  
- JSON: `_kb/performance/benchmarks.json`
- Markdown: `_kb/performance/report.md`
- HTML: `_kb/performance/report.html` (with charts)

**Run time:** 3-8 minutes

---

#### 6. `map-relationships.php` - Relationship Mapper
**Purpose:** Build comprehensive dependency graphs  
**Usage:**
```bash
php map-relationships.php                        # Map all relationships
php map-relationships.php --includes             # Include/require chains only
php map-relationships.php --classes              # Class dependencies only
php map-relationships.php --functions            # Function call graph only
php map-relationships.php --cross-module         # Inter-module dependencies
php map-relationships.php --circular             # Detect circular dependencies
php map-relationships.php --depth=5              # Max recursion depth
php map-relationships.php --output=json          # JSON output
php map-relationships.php --output=dot           # GraphViz DOT format
```

**What it maps:**
- Include/require chains (with depth tracking)
- Class inheritance and trait usage
- Function call graphs
- Database table usage
- Cross-module dependencies
- Circular dependency detection

**Output:**
- `_kb/relationships/dependency-graph.json`
- `_kb/relationships/include-map.json`
- `_kb/relationships/class-hierarchy.json`
- `_kb/relationships/function-calls.json`
- `_kb/relationships/cross-module.json`

**Run time:** 2-5 minutes

---

#### 7. `generate-diagrams.php` - Diagram Generator
**Purpose:** Create visual documentation (Mermaid format)  
**Usage:**
```bash
php generate-diagrams.php                        # Generate all diagrams
php generate-diagrams.php --erd                  # Database ERD only
php generate-diagrams.php --module-deps          # Module dependency graph
php generate-diagrams.php --class-hierarchy      # Class inheritance tree
php generate-diagrams.php --flow                 # Complex function flows
php generate-diagrams.php --format=mermaid       # Mermaid (default)
php generate-diagrams.php --format=plantuml      # PlantUML format
```

**Diagram types:**

1. **Entity Relationship Diagram (ERD)**
   - All database tables
   - Relationships (foreign keys)
   - Cardinality
   - Output: `_kb/database/erd.mmd`

2. **Module Dependency Graph**
   - Module-to-module dependencies
   - Include depth
   - Circular warnings
   - Output: `_kb/diagrams/module-dependencies.mmd`

3. **Class Hierarchy**
   - Class inheritance tree
   - Interface implementations
   - Trait usage
   - Output: `_kb/diagrams/class-hierarchy.mmd`

4. **Function Flow Charts**
   - Complex functions (complexity > 10)
   - Decision trees
   - Call sequences
   - Output: `_kb/diagrams/flows/[function-name].mmd`

**Run time:** 2-4 minutes

---

#### 8. `detect-dead-code.php` - Dead Code Detector
**Purpose:** Find unused code for cleanup  
**Usage:**
```bash
php detect-dead-code.php                         # Detect all dead code
php detect-dead-code.php --functions             # Unused functions only
php detect-dead-code.php --classes               # Unused classes only
php detect-dead-code.php --files                 # Orphaned files only
php detect-dead-code.php --commented             # Commented-out blocks
php detect-dead-code.php --aggressive            # Include "maybe unused"
php detect-dead-code.php --exclude=test          # Exclude test files
```

**What it detects:**
- Functions never called
- Classes never instantiated
- Files never included
- Methods never invoked
- Properties never accessed
- Large commented-out blocks (> 10 lines)

**Safety levels:**
- **Conservative** (default): Only reports definitely unused code
- **Aggressive**: Includes "probably unused" (may have false positives)

**Output:**  
- Markdown report: `_kb/dead-code-report.md`
- JSON: `_kb/dead-code.json`
- Includes:
  - File path
  - Line numbers
  - Size (lines)
  - Last modified date
  - Confidence level (definite/probable)

**Run time:** 10-20 minutes

---

## 🚀 Performance Optimization

### Caching Strategy

#### File Hash Cache
**Purpose:** Detect changed files quickly without full scan  
**Location:** `_kb/cache/file-hashes.json`  
**TTL:** Updated on every refresh  
**Format:**
```json
{
  "modules/base/views/layouts/master.php": {
    "hash": "a3f5e9c2d8b1...",
    "size": 142,
    "modified": "2025-10-12T14:30:00Z",
    "changed": false
  }
}
```

#### Parsed Structure Cache
**Purpose:** Avoid re-parsing unchanged files  
**Location:** `_kb/cache/parsed-structure.json`  
**TTL:** 6 hours  
**Format:**
```json
{
  "modules/consignments/pack.php": {
    "classes": ["PackController"],
    "functions": ["handleSubmit", "validatePack"],
    "includes": ["module_bootstrap.php"],
    "parsed_at": "2025-10-12T14:30:00Z"
  }
}
```

#### Relationship Map Cache
**Purpose:** Speed up dependency lookups  
**Location:** `_kb/cache/relationships.cache`  
**TTL:** 12 hours  
**Size:** ~500KB - 2MB  
**Format:** Serialized PHP array (faster than JSON for complex structures)

### Optimization Techniques

#### 1. Incremental Analysis
- Only re-analyze changed files (using file hashes)
- Reuse cached parsed structure for unchanged files
- Update only affected relationship branches

**Speed improvement:** 10x faster than full scan

#### 2. Parallel Processing
- Use `pcntl_fork()` for parallel file parsing (if available)
- Process modules concurrently
- Combine results at the end

**Speed improvement:** 3-4x faster on multi-core systems

#### 3. Smart Indexing
- Pre-build file indexes (avoid `glob` on every query)
- Use binary search for sorted indexes
- Cache frequently accessed data

**Speed improvement:** 5x faster lookups

#### 4. Lazy Loading
- Load relationship maps only when needed
- Stream large JSON files instead of loading entirely
- Use JSON streaming parser for > 5MB files

**Memory savings:** 80% reduction for large projects

### Performance Budgets

| Operation | Target | Warning | Critical |
|-----------|--------|---------|----------|
| Quick Refresh | < 60s | 90s | 120s |
| Full Analysis | < 10m | 15m | 20m |
| Relationship Mapping | < 5m | 8m | 10m |
| Performance Analysis | < 5m | 8m | 10m |
| Diagram Generation | < 4m | 6m | 8m |
| Dead Code Detection | < 15m | 20m | 30m |

**Monitoring:** Each script logs execution time to `_kb/performance/script-timings.log`

---

## ✅ Do's and Don'ts

### ✅ DO

#### Directory Management
- ✅ **DO** keep `_kb/` outside `public_html/` for security
- ✅ **DO** use consistent naming (lowercase, hyphens)
- ✅ **DO** organize by module first, then by type
- ✅ **DO** include `.gitignore` for cache/ and snapshots/
- ✅ **DO** document directory structure in README

#### Documentation
- ✅ **DO** write markdown in present tense
- ✅ **DO** include code examples for complex concepts
- ✅ **DO** link related documents liberally
- ✅ **DO** keep files focused (one topic per file)
- ✅ **DO** use descriptive filenames (WHAT.md not DOC3.md)

#### Automation
- ✅ **DO** test cron jobs before deploying
- ✅ **DO** log all automated operations
- ✅ **DO** use `--dry-run` for destructive operations
- ✅ **DO** send alerts on critical failures
- ✅ **DO** version your scripts (`#!/usr/bin/env php`)

#### Relationships
- ✅ **DO** map dependencies before refactoring
- ✅ **DO** update relationship maps after major changes
- ✅ **DO** detect circular dependencies early
- ✅ **DO** document breaking changes in CHANGELOG
- ✅ **DO** track cross-module dependencies

#### Performance
- ✅ **DO** use caching aggressively
- ✅ **DO** profile scripts with large datasets
- ✅ **DO** set performance budgets
- ✅ **DO** monitor script execution times
- ✅ **DO** optimize for incremental updates

#### Maintenance
- ✅ **DO** snapshot before major KB updates
- ✅ **DO** test restore procedures quarterly
- ✅ **DO** rotate logs and old snapshots
- ✅ **DO** verify KB integrity weekly
- ✅ **DO** keep scripts up-to-date with PHP versions

### ❌ DON'T

#### Directory Management
- ❌ **DON'T** mix KB files with application code
- ❌ **DON'T** store KB in database (slow, hard to version)
- ❌ **DON'T** use spaces in directory names
- ❌ **DON'T** nest _kb/ directories inside other _kb/ directories
- ❌ **DON'T** commit cache/ or snapshots/ to git

#### Documentation
- ❌ **DON'T** write 1000+ line documents (split them)
- ❌ **DON'T** duplicate information across files
- ❌ **DON'T** use proprietary formats (no .docx, .pages)
- ❌ **DON'T** include sensitive data (passwords, keys)
- ❌ **DON'T** write "TODO: document this later" (do it now)

#### Automation
- ❌ **DON'T** run full refresh every hour (too slow)
- ❌ **DON'T** run heavy jobs during business hours
- ❌ **DON'T** ignore cron job failures
- ❌ **DON'T** run as root (use dedicated user)
- ❌ **DON'T** forget to log to separate files per job

#### Relationships
- ❌ **DON'T** ignore circular dependencies
- ❌ **DON'T** map relationships manually (automate it)
- ❌ **DON'T** skip relationship updates after refactoring
- ❌ **DON'T** assume relationships from memory (verify)
- ❌ **DON'T** delete relationship maps (archive them)

#### Performance
- ❌ **DON'T** parse unchanged files (use cache)
- ❌ **DON'T** load entire codebases into memory
- ❌ **DON'T** run performance analysis in production
- ❌ **DON'T** ignore slow script warnings
- ❌ **DON'T** skip profiling for large projects

#### Maintenance
- ❌ **DON'T** delete snapshots without archiving
- ❌ **DON'T** ignore verification failures
- ❌ **DON'T** run cleanup without --dry-run first
- ❌ **DON'T** disable cron jobs and forget about them
- ❌ **DON'T** let KB storage grow unbounded

---

## 🎯 Module-Specific KB Setup

### Per-Module Directory Structure

```
modules/[module-name]/
├── _kb/
│   ├── README.md                    # Module overview
│   ├── API.md                       # Module API endpoints
│   ├── COMPONENTS.md                # Component inventory
│   ├── DEPENDENCIES.md              # What this module needs
│   ├── DEPENDENTS.md                # What depends on this
│   ├── CHANGELOG.md                 # Module version history
│   ├── ARCHITECTURE.md              # Module-specific architecture
│   ├── TESTING.md                   # How to test this module
│   │
│   ├── relationships/               # Module-specific relationships
│   │   ├── internal-dependencies.json    # Within-module deps
│   │   ├── external-dependencies.json    # To other modules
│   │   └── reverse-dependencies.json     # From other modules
│   │
│   ├── examples/                    # Code examples
│   │   ├── basic-usage.php
│   │   ├── advanced-usage.php
│   │   └── integration-example.php
│   │
│   ├── diagrams/                    # Module-specific diagrams
│   │   ├── module-flow.mmd
│   │   └── component-diagram.mmd
│   │
│   ├── decisions/                   # Module-level ADRs
│   │   └── 001-why-this-approach.md
│   │
│   └── cache/                       # Module cache (gitignored)
│       ├── file-hashes.json
│       └── parsed-structure.json
```

### Auto-Generation for New Modules

When you create a new module, run:

```bash
php _kb/tools/setup-kb.php --module=new-module-name
```

This will:
1. Create the `modules/new-module-name/_kb/` structure
2. Generate template documentation files
3. Add module to global index
4. Create initial relationship maps
5. Set up module-specific cron job (optional)

### Module Linking

Global KB automatically maintains bi-directional links:

**Global → Module:**
- `_kb/modules/consignments.md` → Links to `modules/consignments/_kb/README.md`

**Module → Global:**
- `modules/consignments/_kb/DEPENDENCIES.md` → Links to `_kb/modules/[dependency].md`

### Module Isolation

Each module KB is **self-contained**:
- Can be extracted and used independently
- Contains all necessary context
- Includes own relationship maps
- Has own cache and snapshots

But also **integrated**:
- Cross-referenced in global KB
- Included in global dependency graph
- Contributes to project-wide metrics

---

## 📝 Example Code & Templates

### 1. Full `refresh-kb.php` Script

```php
<?php
/**
 * CIS Knowledge Base - Main Refresh Script
 * 
 * Updates knowledge base with latest code changes
 * 
 * Usage:
 *   php refresh-kb.php --quick     (fast update, 30-60 sec)
 *   php refresh-kb.php --full      (deep analysis, 5-15 min)
 *   php refresh-kb.php --module=X  (single module)
 * 
 * @package CIS\KB\Tools
 * @version 2.0.0
 */

declare(strict_types=1);

// Configuration
$config = [
    'project_root' => '/home/master/applications/jcepnzzkmj/public_html',
    'kb_root' => '/home/master/applications/jcepnzzkmj/public_html/_kb',
    'modules_dir' => '/home/master/applications/jcepnzzkmj/public_html/modules',
    'cache_ttl' => 21600, // 6 hours
    'snapshot_on_full' => true,
    'parallel_processing' => true,
    'max_workers' => 4,
];

// Parse command line arguments
$options = getopt('', ['quick', 'full', 'module:', 'snapshot', 'force', 'help']);

if (isset($options['help'])) {
    showHelp();
    exit(0);
}

$mode = 'quick';
if (isset($options['full'])) {
    $mode = 'full';
}

$targetModule = $options['module'] ?? null;
$forceRefresh = isset($options['force']);
$createSnapshot = isset($options['snapshot']) || ($mode === 'full' && $config['snapshot_on_full']);

// Start timing
$startTime = microtime(true);
log_message("KB Refresh started (mode: {$mode})");

// Step 1: Create snapshot if requested
if ($createSnapshot) {
    log_message("Creating snapshot...");
    createSnapshot($config['kb_root']);
}

// Step 2: Scan file system for changes
log_message("Scanning file system...");
$fileChanges = scanFilesystem($config, $forceRefresh);
log_message(sprintf("Found %d new, %d modified, %d deleted files", 
    count($fileChanges['new']),
    count($fileChanges['modified']),
    count($fileChanges['deleted'])
));

// Step 3: Update file indexes
log_message("Updating file indexes...");
updateFileIndexes($config, $fileChanges);

// Step 4: Regenerate module lists
log_message("Regenerating module lists...");
regenerateModuleLists($config, $targetModule);

// Step 5: Update cross-references
log_message("Updating cross-references...");
updateCrossReferences($config);

if ($mode === 'full') {
    // Step 6: Deep code analysis (AST parsing)
    log_message("Performing deep code analysis...");
    performCodeAnalysis($config, $fileChanges, $targetModule);
    
    // Step 7: Map relationships
    log_message("Mapping relationships...");
    mapRelationships($config, $targetModule);
    
    // Step 8: Performance profiling
    log_message("Analyzing performance...");
    analyzePerformance($config);
    
    // Step 9: Generate documentation
    log_message("Generating documentation...");
    generateDocumentation($config, $targetModule);
    
    // Step 10: Create diagrams
    log_message("Generating diagrams...");
    generateDiagrams($config);
}

// Final: Update timestamp
file_put_contents(
    $config['kb_root'] . '/last-refresh.json',
    json_encode([
        'mode' => $mode,
        'timestamp' => date('Y-m-d H:i:s'),
        'duration' => round(microtime(true) - $startTime, 2),
        'changes' => [
            'new' => count($fileChanges['new']),
            'modified' => count($fileChanges['modified']),
            'deleted' => count($fileChanges['deleted']),
        ]
    ], JSON_PRETTY_PRINT)
);

$duration = round(microtime(true) - $startTime, 2);
log_message("KB Refresh completed in {$duration}s");

exit(0);

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function showHelp(): void
{
    echo <<<HELP
CIS Knowledge Base Refresh Script

Usage:
  php refresh-kb.php [OPTIONS]

Options:
  --quick              Fast update (files only, 30-60 sec)
  --full               Deep analysis (slow, 5-15 min)
  --module=NAME        Update specific module only
  --snapshot           Create snapshot before refresh
  --force              Ignore cache, regenerate everything
  --help               Show this help message

Examples:
  php refresh-kb.php --quick
  php refresh-kb.php --full --snapshot
  php refresh-kb.php --module=consignments
  php refresh-kb.php --force --full

HELP;
}

function log_message(string $message): void
{
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[{$timestamp}] {$message}\n";
    echo $logLine;
    
    // Also log to file
    $logFile = $_SERVER['DOCUMENT_ROOT'] . '/logs/kb-refresh.log';
    file_put_contents($logFile, $logLine, FILE_APPEND);
}

function createSnapshot(string $kbRoot): void
{
    $snapshotDir = $kbRoot . '/snapshots';
    if (!is_dir($snapshotDir)) {
        mkdir($snapshotDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d_H-i');
    $snapshotFile = $snapshotDir . '/' . $timestamp . '.tar.gz';
    
    $excludes = '--exclude=cache --exclude=snapshots --exclude=*.log';
    $cmd = "tar -czf {$snapshotFile} {$excludes} -C {$kbRoot} .";
    
    exec($cmd, $output, $returnVar);
    
    if ($returnVar === 0) {
        log_message("Snapshot created: {$snapshotFile}");
    } else {
        log_message("WARNING: Snapshot creation failed");
    }
}

function scanFilesystem(array $config, bool $forceRefresh): array
{
    $hashCacheFile = $config['kb_root'] . '/cache/file-hashes.json';
    $oldHashes = [];
    
    if (!$forceRefresh && file_exists($hashCacheFile)) {
        $oldHashes = json_decode(file_get_contents($hashCacheFile), true) ?? [];
    }
    
    $newHashes = [];
    $changes = ['new' => [], 'modified' => [], 'deleted' => []];
    
    // Scan modules directory
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($config['modules_dir'], RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && preg_match('/\.(php|js|css|md)$/', $file->getFilename())) {
            $filePath = $file->getPathname();
            $relativePath = str_replace($config['project_root'] . '/', '', $filePath);
            
            $hash = md5_file($filePath);
            $newHashes[$relativePath] = [
                'hash' => $hash,
                'size' => $file->getSize(),
                'modified' => date('Y-m-d H:i:s', $file->getMTime()),
            ];
            
            if (!isset($oldHashes[$relativePath])) {
                $changes['new'][] = $relativePath;
            } elseif ($oldHashes[$relativePath]['hash'] !== $hash) {
                $changes['modified'][] = $relativePath;
            }
        }
    }
    
    // Detect deleted files
    foreach ($oldHashes as $path => $data) {
        if (!isset($newHashes[$path])) {
            $changes['deleted'][] = $path;
        }
    }
    
    // Save new hashes
    if (!is_dir(dirname($hashCacheFile))) {
        mkdir(dirname($hashCacheFile), 0755, true);
    }
    file_put_contents($hashCacheFile, json_encode($newHashes, JSON_PRETTY_PRINT));
    
    return $changes;
}

function updateFileIndexes(array $config, array $fileChanges): void
{
    $indexFile = $config['kb_root'] . '/file-index.json';
    
    $index = [];
    if (file_exists($indexFile)) {
        $index = json_decode(file_get_contents($indexFile), true) ?? [];
    }
    
    // Add new files
    foreach ($fileChanges['new'] as $file) {
        $index[$file] = [
            'added' => date('Y-m-d H:i:s'),
            'type' => getFileType($file),
            'module' => getModuleFromPath($file),
        ];
    }
    
    // Update modified files
    foreach ($fileChanges['modified'] as $file) {
        if (isset($index[$file])) {
            $index[$file]['modified'] = date('Y-m-d H:i:s');
        }
    }
    
    // Remove deleted files
    foreach ($fileChanges['deleted'] as $file) {
        unset($index[$file]);
    }
    
    file_put_contents($indexFile, json_encode($index, JSON_PRETTY_PRINT));
}

function getFileType(string $path): string
{
    if (strpos($path, '/controllers/') !== false) return 'controller';
    if (strpos($path, '/models/') !== false) return 'model';
    if (strpos($path, '/views/') !== false) return 'view';
    if (strpos($path, '/lib/') !== false) return 'library';
    if (strpos($path, '/api/') !== false) return 'api';
    if (strpos($path, '/css/') !== false) return 'stylesheet';
    if (strpos($path, '/js/') !== false) return 'javascript';
    if (preg_match('/\.md$/', $path)) return 'documentation';
    
    return 'other';
}

function getModuleFromPath(string $path): ?string
{
    if (preg_match('#modules/([^/]+)/#', $path, $matches)) {
        return $matches[1];
    }
    return null;
}

function regenerateModuleLists(array $config, ?string $targetModule): void
{
    $modulesDir = $config['modules_dir'];
    $modules = [];
    
    foreach (new DirectoryIterator($modulesDir) as $dir) {
        if ($dir->isDir() && !$dir->isDot() && $dir->getFilename()[0] !== '_') {
            $moduleName = $dir->getFilename();
            
            if ($targetModule && $moduleName !== $targetModule) {
                continue; // Skip if not target module
            }
            
            $modules[$moduleName] = [
                'name' => $moduleName,
                'path' => $dir->getPathname(),
                'files' => countFilesInDirectory($dir->getPathname()),
                'has_kb' => is_dir($dir->getPathname() . '/_kb'),
                'last_updated' => date('Y-m-d H:i:s', $dir->getMTime()),
            ];
        }
    }
    
    $outputFile = $config['kb_root'] . '/modules-index.json';
    file_put_contents($outputFile, json_encode($modules, JSON_PRETTY_PRINT));
}

function countFilesInDirectory(string $dir): int
{
    $count = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) $count++;
    }
    return $count;
}

function updateCrossReferences(array $config): void
{
    // Scan all markdown files for internal links
    // Update broken link report
    // This is a simplified version
    
    $mdFiles = glob($config['kb_root'] . '/**/*.md', GLOB_BRACE);
    $brokenLinks = [];
    
    foreach ($mdFiles as $mdFile) {
        $content = file_get_contents($mdFile);
        preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/', $content, $matches);
        
        foreach ($matches[2] as $link) {
            if (strpos($link, 'http') === 0) continue; // External link
            
            $targetFile = dirname($mdFile) . '/' . $link;
            if (!file_exists($targetFile)) {
                $brokenLinks[] = [
                    'file' => $mdFile,
                    'link' => $link,
                    'target' => $targetFile,
                ];
            }
        }
    }
    
    if (!empty($brokenLinks)) {
        $reportFile = $config['kb_root'] . '/broken-links.json';
        file_put_contents($reportFile, json_encode($brokenLinks, JSON_PRETTY_PRINT));
        log_message("WARNING: Found " . count($brokenLinks) . " broken links");
    }
}

function performCodeAnalysis(array $config, array $fileChanges, ?string $targetModule): void
{
    // Parse PHP files for structure (classes, functions, etc.)
    // This would use PHP-Parser library in production
    log_message("Code analysis: Parsing PHP files...");
    
    // Simplified version - in production use nikic/php-parser
    $changedFiles = array_merge($fileChanges['new'], $fileChanges['modified']);
    $phpFiles = array_filter($changedFiles, function($f) {
        return preg_match('/\.php$/', $f);
    });
    
    foreach ($phpFiles as $file) {
        $fullPath = $config['project_root'] . '/' . $file;
        // Here you would use PHP-Parser to extract:
        // - Classes, interfaces, traits
        // - Functions
        // - Use statements
        // - Constants
        // etc.
        
        // For now, just log
        log_message("  Analyzed: {$file}");
    }
}

function mapRelationships(array $config, ?string $targetModule): void
{
    // This calls the separate map-relationships.php script
    $scriptPath = $config['kb_root'] . '/tools/map-relationships.php';
    
    if (file_exists($scriptPath)) {
        $moduleArg = $targetModule ? " --module={$targetModule}" : '';
        $cmd = "php {$scriptPath}{$moduleArg} 2>&1";
        exec($cmd, $output, $returnVar);
        
        if ($returnVar !== 0) {
            log_message("WARNING: Relationship mapping failed");
        }
    }
}

function analyzePerformance(array $config): void
{
    // This calls the separate analyze-performance.php script
    $scriptPath = $config['kb_root'] . '/tools/analyze-performance.php';
    
    if (file_exists($scriptPath)) {
        $cmd = "php {$scriptPath} 2>&1";
        exec($cmd, $output, $returnVar);
        
        if ($returnVar !== 0) {
            log_message("WARNING: Performance analysis failed");
        }
    }
}

function generateDocumentation(array $config, ?string $targetModule): void
{
    log_message("Generating auto-documentation...");
    
    // Generate API docs, component lists, etc.
    // In production, this would use phpDocumentor or similar
    
    $modules = json_decode(file_get_contents($config['kb_root'] . '/modules-index.json'), true);
    
    foreach ($modules as $moduleName => $moduleData) {
        if ($targetModule && $moduleName !== $targetModule) {
            continue;
        }
        
        $moduleKbDir = $moduleData['path'] . '/_kb';
        if (!is_dir($moduleKbDir)) {
            mkdir($moduleKbDir, 0755, true);
        }
        
        // Generate COMPONENTS.md
        generateComponentsDoc($moduleData, $moduleKbDir);
    }
}

function generateComponentsDoc(array $moduleData, string $kbDir): void
{
    $componentsFile = $kbDir . '/COMPONENTS.md';
    
    $content = "# {$moduleData['name']} Module Components\n\n";
    $content .= "Auto-generated: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Scan module directory for components
    $modulePath = $moduleData['path'];
    
    // Controllers
    $controllers = glob($modulePath . '/controllers/*.php');
    if (!empty($controllers)) {
        $content .= "## Controllers\n\n";
        foreach ($controllers as $controller) {
            $name = basename($controller, '.php');
            $content .= "- `{$name}` - " . getFileDescription($controller) . "\n";
        }
        $content .= "\n";
    }
    
    // Similar for models, views, etc.
    
    file_put_contents($componentsFile, $content);
}

function getFileDescription(string $file): string
{
    // Extract first line of docblock or return generic description
    $content = file_get_contents($file);
    if (preg_match('#/\*\*\s*\n\s*\*\s*(.+?)\n#', $content, $matches)) {
        return trim($matches[1]);
    }
    return "No description available";
}

function generateDiagrams(array $config): void
{
    // This calls the separate generate-diagrams.php script
    $scriptPath = $config['kb_root'] . '/tools/generate-diagrams.php';
    
    if (file_exists($scriptPath)) {
        $cmd = "php {$scriptPath} 2>&1";
        exec($cmd, $output, $returnVar);
        
        if ($returnVar !== 0) {
            log_message("WARNING: Diagram generation failed");
        }
    }
}
```

---

Continuing with **Part 3** in next message...

