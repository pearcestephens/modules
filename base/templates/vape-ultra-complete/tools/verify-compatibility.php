#!/usr/bin/env php
<?php
/**
 * VapeUltra Compatibility Verification Tool
 *
 * Scans all modules to verify VapeUltra compatibility and readiness
 *
 * USAGE:
 *   php verify-compatibility.php
 *   php verify-compatibility.php --module=consignments
 *   php verify-compatibility.php --report=json
 *   php verify-compatibility.php --fix-issues
 */

declare(strict_types=1);

class VapeUltraCompatibilityVerifier
{
    private string $modulesPath;
    private array $results = [];
    private bool $fixIssues = false;
    private string $reportFormat = 'text';

    // Critical compatibility checks
    private array $checks = [
        'bootstrap_exists' => 'Bootstrap file exists',
        'views_directory' => 'Views directory exists',
        'base_bootstrap_loaded' => 'Loads base/bootstrap.php',
        'no_conflicting_templates' => 'No conflicting template systems',
        'proper_namespacing' => 'Uses proper namespacing',
        'csrf_compatible' => 'CSRF token compatible',
        'session_compatible' => 'Session management compatible',
        'asset_paths_correct' => 'Asset paths are correct',
        'renderer_loadable' => 'Renderer class can load'
    ];

    public function __construct(string $modulesPath)
    {
        $this->modulesPath = rtrim($modulesPath, '/');
    }

    public function setFixIssues(bool $fix): void
    {
        $this->fixIssues = $fix;
    }

    public function setReportFormat(string $format): void
    {
        $this->reportFormat = $format;
    }

    /**
     * Run compatibility check on all modules or specific module
     */
    public function verify(?string $specificModule = null): void
    {
        echo "🔍 VapeUltra Compatibility Verification\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        if ($specificModule) {
            $this->verifyModule($specificModule);
        } else {
            $this->verifyAllModules();
        }

        $this->generateReport();
    }

    /**
     * Verify all modules
     */
    private function verifyAllModules(): void
    {
        $modules = $this->findAllModules();

        echo "📦 Found " . count($modules) . " modules to verify\n\n";

        foreach ($modules as $module) {
            $this->verifyModule($module);
        }
    }

    /**
     * Verify single module
     */
    private function verifyModule(string $module): array
    {
        $modulePath = "{$this->modulesPath}/{$module}";

        if (!is_dir($modulePath)) {
            echo "❌ Module not found: {$module}\n\n";
            return [];
        }

        echo "🔎 Verifying: {$module}\n";

        $results = [];

        // Run all checks
        foreach ($this->checks as $checkKey => $checkName) {
            $methodName = 'check_' . $checkKey;

            if (method_exists($this, $methodName)) {
                $result = $this->$methodName($module, $modulePath);
                $results[$checkKey] = $result;

                $icon = $result['passed'] ? '✅' : '❌';
                echo "  {$icon} {$checkName}";

                if (!$result['passed'] && isset($result['message'])) {
                    echo ": {$result['message']}";
                }

                if ($this->fixIssues && !$result['passed'] && isset($result['fix'])) {
                    echo " → Fixing...";
                    $fixed = $result['fix']();
                    if ($fixed) {
                        echo " ✅ Fixed!";
                        $results[$checkKey]['passed'] = true;
                    } else {
                        echo " ⚠️ Could not auto-fix";
                    }
                }

                echo "\n";
            }
        }

        // Calculate compatibility score
        $passed = count(array_filter($results, fn($r) => $r['passed']));
        $total = count($results);
        $score = (int) round(($passed / $total) * 100);

        $results['score'] = $score;
        $results['status'] = $this->getStatusForScore($score);

        echo "  📊 Compatibility Score: {$score}% ({$passed}/{$total} checks passed)\n";
        echo "  🎯 Status: {$results['status']}\n";
        echo "\n";

        $this->results[$module] = $results;

        return $results;
    }

    /**
     * Check if bootstrap.php exists
     */
    private function check_bootstrap_exists(string $module, string $modulePath): array
    {
        $bootstrapPath = "{$modulePath}/bootstrap.php";
        $exists = file_exists($bootstrapPath);

        return [
            'passed' => $exists,
            'message' => $exists ? null : 'bootstrap.php not found',
            'fix' => function() use ($module, $modulePath) {
                return $this->createBootstrap($module, $modulePath);
            }
        ];
    }

    /**
     * Check if views directory exists
     */
    private function check_views_directory(string $module, string $modulePath): array
    {
        $viewsPath = "{$modulePath}/views";
        $exists = is_dir($viewsPath);

        return [
            'passed' => $exists,
            'message' => $exists ? null : 'views/ directory not found',
            'fix' => function() use ($viewsPath) {
                return mkdir($viewsPath, 0755, true);
            }
        ];
    }

    /**
     * Check if base/bootstrap.php is loaded
     */
    private function check_base_bootstrap_loaded(string $module, string $modulePath): array
    {
        $bootstrapPath = "{$modulePath}/bootstrap.php";

        if (!file_exists($bootstrapPath)) {
            return ['passed' => false, 'message' => 'bootstrap.php not found'];
        }

        $content = file_get_contents($bootstrapPath);
        $loaded = strpos($content, "base/bootstrap.php") !== false;

        return [
            'passed' => $loaded,
            'message' => $loaded ? null : 'Does not load base/bootstrap.php',
            'fix' => function() use ($bootstrapPath) {
                return $this->addBaseBootstrapRequire($bootstrapPath);
            }
        ];
    }

    /**
     * Check for conflicting template systems
     */
    private function check_no_conflicting_templates(string $module, string $modulePath): array
    {
        $viewsPath = "{$modulePath}/views";

        if (!is_dir($viewsPath)) {
            return ['passed' => true, 'message' => 'No views directory'];
        }

        $conflicts = [];
        $files = glob("{$viewsPath}/*.php");

        foreach ($files as $file) {
            $content = file_get_contents($file);

            // Check for old template patterns
            if (preg_match('/include.*header\.php|require.*footer\.php|getTemplate\(|loadTemplate\(/i', $content)) {
                $conflicts[] = basename($file);
            }
        }

        return [
            'passed' => empty($conflicts),
            'message' => empty($conflicts) ? null : 'Found old template includes: ' . implode(', ', array_slice($conflicts, 0, 3))
        ];
    }

    /**
     * Check proper namespacing
     */
    private function check_proper_namespacing(string $module, string $modulePath): array
    {
        $composerPath = "{$modulePath}/composer.json";

        if (!file_exists($composerPath)) {
            return [
                'passed' => false,
                'message' => 'composer.json not found',
                'fix' => function() use ($module, $modulePath) {
                    return $this->createComposerJson($module, $modulePath);
                }
            ];
        }

        $composer = json_decode(file_get_contents($composerPath), true);
        $hasAutoload = isset($composer['autoload']['psr-4']);

        return [
            'passed' => $hasAutoload,
            'message' => $hasAutoload ? null : 'PSR-4 autoload not configured'
        ];
    }

    /**
     * Check CSRF token compatibility
     */
    private function check_csrf_compatible(string $module, string $modulePath): array
    {
        // Check if forms use proper CSRF tokens
        $viewsPath = "{$modulePath}/views";

        if (!is_dir($viewsPath)) {
            return ['passed' => true, 'message' => 'No views to check'];
        }

        $forms = 0;
        $csrfTokens = 0;
        $files = glob("{$viewsPath}/*.php");

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $forms += substr_count($content, '<form');
            $csrfTokens += substr_count($content, 'csrf') + substr_count($content, 'CSRF');
        }

        // If there are forms but no CSRF tokens, it's a problem
        if ($forms > 0 && $csrfTokens === 0) {
            return [
                'passed' => false,
                'message' => "Found {$forms} forms without CSRF protection"
            ];
        }

        return ['passed' => true];
    }

    /**
     * Check session compatibility
     */
    private function check_session_compatible(string $module, string $modulePath): array
    {
        $bootstrapPath = "{$modulePath}/bootstrap.php";

        if (!file_exists($bootstrapPath)) {
            return ['passed' => false, 'message' => 'bootstrap.php not found'];
        }

        $content = file_get_contents($bootstrapPath);

        // Check for manual session_start() calls (should use base module)
        $manualSession = preg_match('/session_start\s*\(/i', $content);

        if ($manualSession) {
            return [
                'passed' => false,
                'message' => 'Uses manual session_start() instead of base module'
            ];
        }

        return ['passed' => true];
    }

    /**
     * Check asset paths
     */
    private function check_asset_paths_correct(string $module, string $modulePath): array
    {
        $viewsPath = "{$modulePath}/views";

        if (!is_dir($viewsPath)) {
            return ['passed' => true, 'message' => 'No views to check'];
        }

        $incorrectPaths = [];
        $files = glob("{$viewsPath}/*.php");

        foreach ($files as $file) {
            $content = file_get_contents($file);

            // Check for hardcoded asset paths
            if (preg_match_all('/["\']\/assets\//i', $content, $matches)) {
                $incorrectPaths[] = basename($file);
            }
        }

        return [
            'passed' => empty($incorrectPaths),
            'message' => empty($incorrectPaths) ? null : 'Hardcoded asset paths found'
        ];
    }

    /**
     * Check if Renderer class can be loaded
     */
    private function check_renderer_loadable(string $module, string $modulePath): array
    {
        $bootstrapPath = "{$modulePath}/bootstrap.php";

        if (!file_exists($bootstrapPath)) {
            return ['passed' => false, 'message' => 'bootstrap.php not found'];
        }

        // Simple check: does bootstrap load base?
        $content = file_get_contents($bootstrapPath);
        $loadsBase = strpos($content, 'base/bootstrap.php') !== false;

        return [
            'passed' => $loadsBase,
            'message' => $loadsBase ? null : 'May not be able to load Renderer class'
        ];
    }

    /**
     * Get status label for score
     */
    private function getStatusForScore(float|int $score): string
    {
        $score = (int) round($score);
        if ($score >= 90) return '🟢 READY';
        if ($score >= 70) return '🟡 NEEDS WORK';
        if ($score >= 50) return '🟠 MAJOR ISSUES';
        return '🔴 NOT COMPATIBLE';
    }

    /**
     * Find all modules
     */
    private function findAllModules(): array
    {
        $modules = [];
        $dirs = glob($this->modulesPath . '/*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            $module = basename($dir);

            // Skip special directories
            if (in_array($module, ['base', 'vendor', 'tests', '_kb', '_logs', '_scripts', '_tests', 'archived', 'MODULES_RECYCLE_BIN'])) {
                continue;
            }

            $modules[] = $module;
        }

        sort($modules);
        return $modules;
    }

    /**
     * Generate report
     */
    private function generateReport(): void
    {
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "📊 VERIFICATION SUMMARY\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        if ($this->reportFormat === 'json') {
            echo json_encode($this->results, JSON_PRETTY_PRINT);
            return;
        }

        // Group by status
        $ready = [];
        $needsWork = [];
        $majorIssues = [];
        $notCompatible = [];

        foreach ($this->results as $module => $result) {
            $score = $result['score'];

            if ($score >= 90) $ready[] = $module;
            elseif ($score >= 70) $needsWork[] = $module;
            elseif ($score >= 50) $majorIssues[] = $module;
            else $notCompatible[] = $module;
        }

        echo "🟢 READY (" . count($ready) . " modules):\n";
        foreach ($ready as $module) {
            echo "  • {$module} ({$this->results[$module]['score']}%)\n";
        }
        echo "\n";

        echo "🟡 NEEDS WORK (" . count($needsWork) . " modules):\n";
        foreach ($needsWork as $module) {
            echo "  • {$module} ({$this->results[$module]['score']}%)\n";
        }
        echo "\n";

        echo "🟠 MAJOR ISSUES (" . count($majorIssues) . " modules):\n";
        foreach ($majorIssues as $module) {
            echo "  • {$module} ({$this->results[$module]['score']}%)\n";
        }
        echo "\n";

        echo "🔴 NOT COMPATIBLE (" . count($notCompatible) . " modules):\n";
        foreach ($notCompatible as $module) {
            echo "  • {$module} ({$this->results[$module]['score']}%)\n";
        }
        echo "\n";

        // Overall stats
        $totalModules = count($this->results);
        $avgScore = round(array_sum(array_column($this->results, 'score')) / $totalModules);

        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "📈 OVERALL STATISTICS\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "Total Modules Verified: {$totalModules}\n";
        echo "Average Compatibility: {$avgScore}%\n";
        echo "Ready for VapeUltra: " . count($ready) . " (" . round(count($ready)/$totalModules*100) . "%)\n";
        echo "\n";

        if ($this->fixIssues) {
            echo "✅ Auto-fix was enabled. Re-run verification to confirm fixes.\n";
        } else {
            echo "💡 Tip: Run with --fix-issues to automatically fix common issues.\n";
        }
    }

    /**
     * Create bootstrap.php for module
     */
    private function createBootstrap(string $module, string $modulePath): bool
    {
        $content = <<<'PHP'
<?php
/**
 * Bootstrap: MODULE_NAME
 * Initializes the module and loads required dependencies
 */

declare(strict_types=1);

// Load base framework
require_once __DIR__ . '/../base/bootstrap.php';

// Module is now ready to use base services:
// - Database::pdo()
// - Logger::info()
// - Session management
// - CSRF protection
// - And more...

PHP;

        $content = str_replace('MODULE_NAME', $module, $content);
        $path = "{$modulePath}/bootstrap.php";

        return file_put_contents($path, $content) !== false;
    }

    /**
     * Add base/bootstrap.php require to existing bootstrap
     */
    private function addBaseBootstrapRequire(string $bootstrapPath): bool
    {
        $content = file_get_contents($bootstrapPath);

        // Add at the top after <?php
        $requireLine = "\nrequire_once __DIR__ . '/../base/bootstrap.php';\n";
        $content = preg_replace('/<\?php\s*/', "<?php{$requireLine}", $content, 1);

        return file_put_contents($bootstrapPath, $content) !== false;
    }

    /**
     * Create composer.json for module
     */
    private function createComposerJson(string $module, string $modulePath): bool
    {
        $moduleName = str_replace(['_', '-'], ' ', $module);
        $moduleName = ucwords($moduleName);
        $moduleName = str_replace(' ', '', $moduleName);

        $composer = [
            'name' => "cis/{$module}",
            'description' => "{$moduleName} module for CIS",
            'type' => 'library',
            'autoload' => [
                'psr-4' => [
                    "CIS\\Modules\\{$moduleName}\\" => "./"
                ]
            ],
            'require' => [
                'php' => '>=8.0'
            ]
        ];

        $path = "{$modulePath}/composer.json";
        return file_put_contents($path, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
    }
}

// ============================================
// CLI EXECUTION
// ============================================

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Parse command line arguments
$options = getopt('', ['module:', 'report:', 'fix-issues', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
VapeUltra Compatibility Verification Tool

USAGE:
  php verify-compatibility.php [OPTIONS]

OPTIONS:
  --module=<name>     Verify specific module only
  --report=<format>   Report format: text (default) or json
  --fix-issues        Automatically fix common issues
  --help              Show this help message

EXAMPLES:
  php verify-compatibility.php
  php verify-compatibility.php --module=consignments
  php verify-compatibility.php --report=json
  php verify-compatibility.php --fix-issues

HELP;
    exit(0);
}

// Determine modules path
$modulesPath = __DIR__ . '/../../../..';

if (!is_dir($modulesPath)) {
    die("❌ Modules path not found: {$modulesPath}\n");
}

// Create verifier
$verifier = new VapeUltraCompatibilityVerifier($modulesPath);

// Configure
if (isset($options['fix-issues'])) {
    $verifier->setFixIssues(true);
}

if (isset($options['report'])) {
    $verifier->setReportFormat($options['report']);
}

// Run verification
$specificModule = $options['module'] ?? null;
$verifier->verify($specificModule);
