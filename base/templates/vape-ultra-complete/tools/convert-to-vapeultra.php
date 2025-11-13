#!/usr/bin/env php
<?php
/**
 * VapeUltra Theme Conversion Tool
 *
 * Converts existing module views from old theme to VapeUltra master.php
 *
 * USAGE:
 *   php convert-to-vapeultra.php [module] [view-file]
 *
 * EXAMPLES:
 *   php convert-to-vapeultra.php consignments ai-insights.php
 *   php convert-to-vapeultra.php sales dashboard.php
 *   php convert-to-vapeultra.php --scan  (scan all modules)
 */

class VapeUltraConverter
{
    private $modulesPath;
    private $backupSuffix = '.VAPEULTRA_BACKUP';
    private $dryRun = false;

    public function __construct($modulesPath)
    {
        $this->modulesPath = rtrim($modulesPath, '/');
    }

    public function setDryRun($dryRun)
    {
        $this->dryRun = $dryRun;
    }

    /**
     * Convert a single view file
     */
    public function convertFile($module, $viewFile)
    {
        $filePath = "{$this->modulesPath}/{$module}/views/{$viewFile}";

        if (!file_exists($filePath)) {
            echo "❌ File not found: {$filePath}\n";
            return false;
        }

        echo "🔍 Analyzing: {$module}/views/{$viewFile}\n";

        // Read current file
        $content = file_get_contents($filePath);

        // Check if already converted
        if (strpos($content, 'VapeUltra Theme') !== false || strpos($content, '$renderer->render(\'master\'') !== false) {
            echo "✅ Already converted to VapeUltra\n\n";
            return true;
        }

        // Extract components
        $pageTitle = $this->extractPageTitle($content);
        $breadcrumbs = $this->extractBreadcrumbs($content);
        $pageContent = $this->extractPageContent($content);

        // Generate new file
        $newContent = $this->generateVapeUltraFile($module, $viewFile, $pageTitle, $breadcrumbs, $pageContent);

        if ($this->dryRun) {
            echo "📋 DRY RUN - Would convert to:\n";
            echo "─────────────────────────────────────────\n";
            echo substr($newContent, 0, 500) . "...\n";
            echo "─────────────────────────────────────────\n\n";
            return true;
        }

        // Backup original file
        $backupPath = $filePath . $this->backupSuffix;
        copy($filePath, $backupPath);
        echo "💾 Backed up to: {$backupPath}\n";

        // Write new file
        file_put_contents($filePath, $newContent);
        echo "✅ Converted successfully!\n\n";

        return true;
    }

    /**
     * Scan and convert all modules
     */
    public function scanAndConvert()
    {
        echo "🔍 Scanning modules directory...\n\n";

        $modules = $this->findModulesWithViews();

        echo "📁 Found " . count($modules) . " modules with views:\n";
        foreach ($modules as $module => $viewFiles) {
            echo "  • {$module}: " . count($viewFiles) . " view files\n";
        }
        echo "\n";

        $converted = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($modules as $module => $viewFiles) {
            echo "📦 Processing module: {$module}\n";

            foreach ($viewFiles as $viewFile) {
                try {
                    if ($this->convertFile($module, $viewFile)) {
                        $converted++;
                    } else {
                        $skipped++;
                    }
                } catch (Exception $e) {
                    echo "❌ Error: {$e->getMessage()}\n";
                    $errors++;
                }
            }

            echo "\n";
        }

        echo "═══════════════════════════════════════\n";
        echo "📊 Conversion Summary:\n";
        echo "  ✅ Converted: {$converted}\n";
        echo "  ⏭️  Skipped: {$skipped}\n";
        echo "  ❌ Errors: {$errors}\n";
        echo "═══════════════════════════════════════\n";
    }

    /**
     * Find all modules with views
     */
    private function findModulesWithViews()
    {
        $modules = [];

        $dirs = glob($this->modulesPath . '/*/views', GLOB_ONLYDIR);

        foreach ($dirs as $viewsDir) {
            $module = basename(dirname($viewsDir));

            // Get all PHP files in views directory
            $viewFiles = glob($viewsDir . '/*.php');

            // Filter out backup files
            $viewFiles = array_filter($viewFiles, function($file) {
                return !preg_match('/\.(BACKUP|backup|old|bak)/', $file);
            });

            if (!empty($viewFiles)) {
                $modules[$module] = array_map('basename', $viewFiles);
            }
        }

        return $modules;
    }

    /**
     * Extract page title
     */
    private function extractPageTitle($content)
    {
        if (preg_match('/\$pageTitle\s*=\s*[\'"](.+?)[\'"];/s', $content, $matches)) {
            return $matches[1];
        }

        // Try to extract from H1
        if (preg_match('/<h1[^>]*>(.+?)<\/h1>/s', $content, $matches)) {
            return strip_tags($matches[1]);
        }

        return 'Untitled Page';
    }

    /**
     * Extract breadcrumbs
     */
    private function extractBreadcrumbs($content)
    {
        if (preg_match('/\$breadcrumbs\s*=\s*(\[.*?\]);/s', $content, $matches)) {
            // Attempt to parse breadcrumbs array
            $breadcrumbsStr = $matches[1];
            return $breadcrumbsStr;
        }

        return "[\n    ['label' => 'Home', 'url' => '/', 'icon' => 'bi bi-house'],\n    ['label' => 'Module', 'active' => true]\n]";
    }

    /**
     * Extract page content
     */
    private function extractPageContent($content)
    {
        // Find content between ob_start() and ob_get_clean()
        if (preg_match('/ob_start\(\);?\s*\?>(.*?)<\?php\s*\$content\s*=/s', $content, $matches)) {
            return trim($matches[1]);
        }

        return '<!-- Content extraction failed - manual review required -->';
    }

    /**
     * Generate VapeUltra file
     */
    private function generateVapeUltraFile($module, $viewFile, $pageTitle, $breadcrumbs, $pageContent)
    {
        $moduleName = ucwords(str_replace('-', ' ', $module));
        $pageName = ucwords(str_replace(['-', '.php'], [' ', ''], $viewFile));

        return <<<PHP
<?php
/**
 * {$pageName} - VapeUltra Theme
 * Module: {$moduleName}
 * Page: {$pageName}
 */
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';

// Start output buffering to capture page content
ob_start();
?>

{$pageContent}

<?php
// Capture the output
\$pageContent = ob_get_clean();

// Define breadcrumb navigation
\$breadcrumb = {$breadcrumbs};

// Define sub-navigation (customize per module)
\$subnav = [
    ['label' => 'Dashboard', 'url' => '/modules/{$module}/', 'icon' => 'bi bi-speedometer2', 'active' => true],
    // Add more navigation items here
];

// Render with VapeUltra master template
\$renderer->render('master', [
    'title' => '{$pageTitle} - CIS 2.0',
    'content' => \$pageContent,

    // Navigation
    'showBreadcrumb' => true,
    'breadcrumb' => \$breadcrumb,
    'showSubnav' => true,
    'subnav' => \$subnav,
    'subnavStyle' => 'horizontal',
    'subnavAlign' => 'left',

    // Layout visibility
    'showHeader' => true,
    'showSidebar' => true,
    'showSidebarRight' => false,
    'showFooter' => true
]);
?>
PHP;
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// CLI EXECUTION
// ═══════════════════════════════════════════════════════════════════════════

// Parse arguments
$options = getopt('h', ['help', 'scan', 'dry-run', 'module:', 'file:']);

if (isset($options['h']) || isset($options['help'])) {
    echo <<<HELP
VapeUltra Theme Conversion Tool
════════════════════════════════════════════════════════════════

USAGE:
  php convert-to-vapeultra.php [OPTIONS]

OPTIONS:
  --scan              Scan and convert all modules
  --module MODULE     Convert specific module
  --file FILE         Convert specific file (requires --module)
  --dry-run           Show what would be converted without making changes
  -h, --help          Show this help message

EXAMPLES:
  # Scan and convert all modules
  php convert-to-vapeultra.php --scan

  # Dry run (preview changes)
  php convert-to-vapeultra.php --scan --dry-run

  # Convert specific file
  php convert-to-vapeultra.php --module consignments --file ai-insights.php

  # Dry run for specific file
  php convert-to-vapeultra.php --module consignments --file ai-insights.php --dry-run

HELP;
    exit(0);
}

// Get modules path
$modulesPath = dirname(dirname(__DIR__));

// Create converter
$converter = new VapeUltraConverter($modulesPath);

// Check for dry-run
if (isset($options['dry-run'])) {
    $converter->setDryRun(true);
    echo "🔍 DRY RUN MODE - No files will be modified\n\n";
}

// Execute conversion
if (isset($options['scan'])) {
    $converter->scanAndConvert();
} elseif (isset($options['module']) && isset($options['file'])) {
    $converter->convertFile($options['module'], $options['file']);
} else {
    echo "❌ Error: Invalid arguments. Use --help for usage information.\n";
    exit(1);
}

echo "\n✅ Done!\n";

PHP
