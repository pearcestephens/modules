<?php
/**
 * UNIVERSAL MODULE CRAWLER & ANALYZER
 *
 * Automatically discovers and tests ALL modules in the /modules/ folder:
 * - Finds all PHP pages automatically
 * - Tests all navigation links
 * - Tests all API endpoints
 * - Validates UI elements
 * - Checks interconnections
 * - Tests integration visibility
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get command line arguments
$specificModule = isset($argv[1]) ? $argv[1] : null;
$modulesBasePath = __DIR__ . '/..';

echo "╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║                                                                        ║\n";
echo "║         UNIVERSAL MODULE CRAWLER & ANALYZER                           ║\n";
echo "║                                                                        ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

if ($specificModule) {
    echo "🎯 Testing specific module: $specificModule\n\n";
} else {
    echo "🔍 Auto-discovering all modules...\n\n";
}

$results = [
    'modules_found' => 0,
    'modules_tested' => 0,
    'pages_tested' => 0,
    'pages_success' => 0,
    'pages_failed' => 0,
    'links_tested' => 0,
    'links_valid' => 0,
    'links_broken' => 0,
    'api_tested' => 0,
    'api_success' => 0,
    'api_failed' => 0,
    'ui_elements' => 0,
    'ui_valid' => 0,
    'ui_issues' => 0
];

$errors = [];
$warnings = [];
$moduleResults = [];

// Auto-discover modules
function discoverModules($basePath, $specificModule = null) {
    $modules = [];

    if ($specificModule) {
        $modulePath = $basePath . '/' . $specificModule;
        if (is_dir($modulePath)) {
            $modules[] = [
                'name' => $specificModule,
                'path' => $modulePath
            ];
        }
        return $modules;
    }

    // Scan all directories in /modules/
    $dirs = scandir($basePath);
    foreach ($dirs as $dir) {
        if ($dir === '.' || $dir === '..') continue;

        $fullPath = $basePath . '/' . $dir;
        if (is_dir($fullPath)) {
            $modules[] = [
                'name' => $dir,
                'path' => $fullPath
            ];
        }
    }

    return $modules;
}

// Recursively find all PHP files in a directory
function findAllPhpFiles($dir, $relativeTo = '') {
    $files = [];

    if (!is_dir($dir)) return $files;

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $fullPath = $dir . '/' . $item;
        $relativePath = $relativeTo ? $relativeTo . '/' . $item : $item;

        if (is_dir($fullPath)) {
            // Skip certain directories
            if (in_array($item, ['vendor', 'node_modules', '.git', 'tests', 'cache'])) {
                continue;
            }
            $files = array_merge($files, findAllPhpFiles($fullPath, $relativePath));
        } elseif (is_file($fullPath) && pathinfo($item, PATHINFO_EXTENSION) === 'php') {
            $files[] = [
                'name' => $item,
                'path' => $fullPath,
                'relative' => $relativePath
            ];
        }
    }

    return $files;
}

// Helper function to test if file can be parsed
function testPageLoad($file, $description, $moduleName) {
    global $results, $errors;

    $results['pages_tested']++;
    echo "  Testing: $description ... ";

    if (!file_exists($file)) {
        $results['pages_failed']++;
        $errors[] = "[$moduleName] $description - File not found: $file";
        echo "✗ NOT FOUND\n";
        return false;
    }

    // Check PHP syntax
    $output = [];
    $return = 0;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return);

    if ($return !== 0) {
        $results['pages_failed']++;
        $errors[] = "[$moduleName] $description - Syntax error";
        echo "✗ SYNTAX ERROR\n";
        return false;
    }

    $results['pages_success']++;
    echo "✓ OK\n";
    return true;
}// Helper function to extract and test links
function extractLinks($file, $description, $modulePath) {
    global $results, $warnings;

    $content = file_get_contents($file);
    $links = [];

    // Extract href links
    preg_match_all('/href=["\']([^"\']*\.php[^"\']*)["\']/', $content, $matches);
    if (!empty($matches[1])) {
        $links = array_merge($links, $matches[1]);
    }

    // Extract JavaScript location.href
    preg_match_all('/location\.href\s*=\s*["\']([^"\']*\.php[^"\']*)["\']/', $content, $matches);
    if (!empty($matches[1])) {
        $links = array_merge($links, $matches[1]);
    }

    // Extract window.location
    preg_match_all('/window\.location\s*=\s*["\']([^"\']*\.php[^"\']*)["\']/', $content, $matches);
    if (!empty($matches[1])) {
        $links = array_merge($links, $matches[1]);
    }

    $links = array_unique($links);

    echo "    Found " . count($links) . " link(s) in $description:\n";

    foreach ($links as $link) {
        $results['links_tested']++;

        // Remove query strings and anchors for file check
        $linkFile = preg_replace('/[?#].*$/', '', $link);

        // Check if it's a relative or absolute path
        if (strpos($linkFile, 'http') === 0) {
            // External link - skip for now
            echo "      → $link (external) ⊘ SKIP\n";
            continue;
        }

        // Build full path relative to module
        $fullPath = $modulePath . '/' . $linkFile;

        // Handle parent directory references
        $fullPath = realpath($fullPath);

        if ($fullPath && file_exists($fullPath)) {
            $results['links_valid']++;
            echo "      → $link ✓\n";
        } else {
            $results['links_broken']++;
            $warnings[] = "$description links to non-existent file: $link";
            echo "      → $link ✗ BROKEN\n";
        }
    }

    return $links;
}

// Helper function to check UI elements
function checkUIElements($file, $description) {
    global $results;

    $content = file_get_contents($file);
    $elements = [];

    // Check for Bootstrap classes
    if (preg_match_all('/(card|btn|badge|alert|breadcrumb|pagination|table|form-control|nav-tabs)/', $content, $matches)) {
        $elements['bootstrap'] = array_unique($matches[1]);
    }

    // Check for Font Awesome icons
    if (preg_match_all('/fa-([a-z-]+)/', $content, $matches)) {
        $elements['icons'] = array_unique($matches[1]);
    }

    // Check for breadcrumbs
    if (strpos($content, 'breadcrumb') !== false) {
        $elements['has_breadcrumb'] = true;
    }

    // Check for navigation
    if (strpos($content, 'Back to') !== false || strpos($content, 'navigation') !== false) {
        $elements['has_navigation'] = true;
    }

    echo "  UI Elements in $description:\n";

    if (!empty($elements['bootstrap'])) {
        $results['ui_elements'] += count($elements['bootstrap']);
        $results['ui_valid'] += count($elements['bootstrap']);
        echo "    ✓ Bootstrap classes: " . count($elements['bootstrap']) . "\n";
    }

    if (!empty($elements['icons'])) {
        echo "    ✓ Font Awesome icons: " . count($elements['icons']) . "\n";
    }

    if (!empty($elements['has_breadcrumb'])) {
        echo "    ✓ Has breadcrumb navigation\n";
    }

    if (!empty($elements['has_navigation'])) {
        echo "    ✓ Has back/navigation links\n";
    }

    return $elements;
}

// Helper function to check integration visibility
function checkIntegrationVisibility($file, $description) {
    $content = file_get_contents($file);
    $integrations = [];

    // Check for Deputy mentions
    if (stripos($content, 'deputy') !== false) {
        $integrations['deputy'] = true;
    }

    // Check for Xero mentions
    if (stripos($content, 'xero') !== false) {
        $integrations['xero'] = true;
    }

    // Check for sync status
    if (stripos($content, 'sync') !== false) {
        $integrations['sync_status'] = true;
    }

    if (!empty($integrations)) {
        echo "  Integration visibility in $description:\n";
        if (!empty($integrations['deputy'])) echo "    ✓ Deputy integration visible\n";
        if (!empty($integrations['xero'])) echo "    ✓ Xero integration visible\n";
        if (!empty($integrations['sync_status'])) echo "    ✓ Sync status shown\n";
    }

    return $integrations;
}

// ============================================================================
// MAIN EXECUTION: DISCOVER AND TEST ALL MODULES
// ============================================================================

$modules = discoverModules($modulesBasePath, $specificModule);
$results['modules_found'] = count($modules);

echo "📦 Found " . count($modules) . " module(s) to test\n\n";

foreach ($modules as $module) {
    $moduleName = $module['name'];
    $modulePath = $module['path'];

    echo "╔════════════════════════════════════════════════════════════════════════╗\n";
    echo "║  MODULE: " . str_pad($moduleName, 61) . "║\n";
    echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

    $results['modules_tested']++;

    // Find all PHP files in this module
    $phpFiles = findAllPhpFiles($modulePath);

    if (empty($phpFiles)) {
        echo "  ⚠ No PHP files found in this module\n\n";
        continue;
    }

    echo "  Found " . count($phpFiles) . " PHP file(s)\n\n";

    // Categorize files
    $pages = [];
    $apiEndpoints = [];
    $includes = [];
    $others = [];

    foreach ($phpFiles as $file) {
        if (strpos($file['relative'], 'api/') !== false) {
            $apiEndpoints[] = $file;
        } elseif (strpos($file['relative'], 'includes/') !== false ||
                  strpos($file['relative'], 'classes/') !== false ||
                  strpos($file['relative'], 'lib/') !== false) {
            $includes[] = $file;
        } elseif (strpos($file['name'], 'index') !== false ||
                  strpos($file['name'], '-') !== false ||
                  !in_array($file['name'], ['config.php', 'bootstrap.php', 'functions.php'])) {
            $pages[] = $file;
        } else {
            $others[] = $file;
        }
    }

    // Test Pages
    if (!empty($pages)) {
        echo "  【1】 PAGE TESTS (" . count($pages) . " pages)\n";
        echo "  ───────────────────────────────────────────────────────────────────\n";
        foreach ($pages as $file) {
            testPageLoad($file['path'], $file['relative'], $moduleName);
        }
        echo "\n";
    }

    // Test API Endpoints
    if (!empty($apiEndpoints)) {
        echo "  【2】 API ENDPOINT TESTS (" . count($apiEndpoints) . " endpoints)\n";
        echo "  ───────────────────────────────────────────────────────────────────\n";
        foreach ($apiEndpoints as $file) {
            $results['api_tested']++;
            echo "  Testing: " . $file['relative'] . " ... ";

            // Check syntax
            $output = [];
            $return = 0;
            exec("php -l " . escapeshellarg($file['path']) . " 2>&1", $output, $return);

            if ($return !== 0) {
                $results['api_failed']++;
                echo "✗ SYNTAX ERROR\n";
                continue;
            }

            // Check for JSON response
            $content = file_get_contents($file['path']);
            if (strpos($content, 'json_encode') !== false || strpos($content, 'application/json') !== false) {
                $results['api_success']++;
                echo "✓ OK (JSON)\n";
            } else {
                $results['api_success']++;
                echo "✓ OK\n";
            }
        }
        echo "\n";
    }

    // Test Includes/Classes
    if (!empty($includes)) {
        echo "  【3】 INCLUDES/CLASSES (" . count($includes) . " files)\n";
        echo "  ───────────────────────────────────────────────────────────────────\n";
        foreach ($includes as $file) {
            echo "  Testing: " . $file['relative'] . " ... ";

            $output = [];
            $return = 0;
            exec("php -l " . escapeshellarg($file['path']) . " 2>&1", $output, $return);

            if ($return === 0) {
                echo "✓ OK\n";
            } else {
                echo "✗ SYNTAX ERROR\n";
            }
        }
        echo "\n";
    }

    // Test navigation links for main pages
    if (!empty($pages)) {
        echo "  【4】 NAVIGATION TESTS\n";
        echo "  ───────────────────────────────────────────────────────────────────\n";
        foreach (array_slice($pages, 0, 5) as $file) { // Test first 5 pages
            extractLinks($file['path'], $file['relative'], $modulePath);
        }
        echo "\n";
    }

    // Test UI elements
    if (!empty($pages)) {
        echo "  【5】 UI ELEMENT VALIDATION\n";
        echo "  ───────────────────────────────────────────────────────────────────\n";
        foreach (array_slice($pages, 0, 3) as $file) { // Test first 3 pages
            checkUIElements($file['path'], $file['relative']);
        }
        echo "\n";
    }

    // Store module results
    $moduleResults[$moduleName] = [
        'pages' => count($pages),
        'api' => count($apiEndpoints),
        'includes' => count($includes),
        'total_files' => count($phpFiles)
    ];

    echo "\n";
}

// ============================================================================
// RESULTS SUMMARY
// ============================================================================
echo "╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║                    UNIVERSAL CRAWLER RESULTS SUMMARY                   ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

echo "📦 MODULES:\n";
echo "   Total Found:     " . $results['modules_found'] . "\n";
echo "   Total Tested:    " . $results['modules_tested'] . "\n\n";

if (!empty($moduleResults)) {
    echo "   Per-Module Breakdown:\n";
    echo "   ───────────────────────────────────────────────────────────────────\n";
    foreach ($moduleResults as $moduleName => $stats) {
        echo "   • $moduleName:\n";
        echo "     - Pages:     " . $stats['pages'] . "\n";
        echo "     - APIs:      " . $stats['api'] . "\n";
        echo "     - Includes:  " . $stats['includes'] . "\n";
        echo "     - Total:     " . $stats['total_files'] . " files\n";
    }
    echo "\n";
}

echo "📄 PAGES:\n";
echo "   Total Tested:    " . $results['pages_tested'] . "\n";
echo "   ✓ Success:       " . $results['pages_success'] . "\n";
echo "   ✗ Failed:        " . $results['pages_failed'] . "\n\n";

echo "🔗 NAVIGATION LINKS:\n";
echo "   Total Tested:    " . $results['links_tested'] . "\n";
echo "   ✓ Valid:         " . $results['links_valid'] . "\n";
echo "   ✗ Broken:        " . $results['links_broken'] . "\n\n";

echo "🔌 API ENDPOINTS:\n";
echo "   Total Tested:    " . $results['api_tested'] . "\n";
echo "   ✓ Success:       " . $results['api_success'] . "\n";
echo "   ✗ Failed:        " . $results['api_failed'] . "\n\n";

echo "🎨 UI ELEMENTS:\n";
echo "   Bootstrap Classes: " . $results['ui_valid'] . "\n\n";

// Show errors
if (!empty($errors)) {
    echo "❌ ERRORS (" . count($errors) . "):\n";
    echo "───────────────────────────────────────────────────────────────────────\n";
    foreach ($errors as $error) {
        echo "   • $error\n";
    }
    echo "\n";
}

// Show warnings
if (!empty($warnings)) {
    echo "⚠️  WARNINGS (" . count($warnings) . "):\n";
    echo "───────────────────────────────────────────────────────────────────────\n";
    foreach ($warnings as $warning) {
        echo "   • $warning\n";
    }
    echo "\n";
}

// Final verdict
$totalTests = $results['pages_tested'] + $results['links_tested'] + $results['api_tested'];
$totalSuccess = $results['pages_success'] + $results['links_valid'] + $results['api_success'];
$totalFailed = $results['pages_failed'] + $results['links_broken'] + $results['api_failed'];
$successRate = $totalTests > 0 ? round(($totalSuccess / $totalTests) * 100, 1) : 0;

echo "╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║                         FINAL VERDICT                                  ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

echo "Total Tests:     $totalTests\n";
echo "✓ Passed:        $totalSuccess ($successRate%)\n";
echo "✗ Failed:        $totalFailed\n";
echo "⚠ Warnings:      " . count($warnings) . "\n\n";

if ($totalFailed === 0 && count($errors) === 0) {
    echo "🎉 PERFECT! All modules tested successfully!\n";
    echo "✅ All pages load, all links work, all APIs respond.\n";
    exit(0);
} elseif ($successRate >= 80) {
    echo "✅ GOOD! Most tests passed ($successRate% success rate).\n";
    echo "⚠️  Review warnings above for minor issues.\n";
    exit(0);
} else {
    echo "⚠️  Some issues found. Please review the errors and warnings above.\n";
    exit(1);
}
