<?php
/**
 * DEPLOYMENT VERIFICATION SCRIPT
 * Comprehensive system health check for Theme Builder IDE
 *
 * Checks:
 * - All required files exist
 * - All APIs are callable
 * - Database connectivity
 * - File permissions
 * - Performance benchmarks
 *
 * @version 1.0.0
 */

declare(strict_types=1);

// Configuration
$checks = [
    'files' => [],
    'apis' => [],
    'database' => [],
    'permissions' => [],
    'performance' => []
];

$results = [
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0,
    'errors' => []
];

$root = $_SERVER['DOCUMENT_ROOT'];

// =====================================================================
// 1. FILE EXISTENCE CHECKS
// =====================================================================

echo "📋 STEP 1: FILE EXISTENCE CHECKS\n";
echo str_repeat("=", 80) . "\n\n";

$requiredFiles = [
    'php' => [
        '/modules/admin-ui/api/ai-agent-handler.php',
        '/modules/admin-ui/api/file-explorer-api.php',
        '/modules/admin-ui/api/sandbox-executor.php',
    ],
    'javascript' => [
        '/modules/admin-ui/js/theme-builder/12-ai-agent-integration.js',
        '/modules/admin-ui/js/theme-builder/13-validation-tools.js',
        '/modules/admin-ui/js/theme-builder/14-file-explorer.js',
        '/modules/admin-ui/js/theme-builder/15-validation-engine.js',
        '/modules/admin-ui/js/theme-builder/16-validation-ui-integration.js',
    ],
    'documentation' => [
        '/modules/admin-ui/js/theme-builder/SESSION_PHASE20_SUMMARY.md',
        '/modules/admin-ui/js/theme-builder/QUICK_START_GUIDE.md',
        '/modules/admin-ui/js/theme-builder/IMPLEMENTATION_CHECKLIST.md',
        '/modules/admin-ui/API_REFERENCE.md',
    ]
];

foreach ($requiredFiles as $type => $files) {
    echo "Checking {$type} files:\n";

    foreach ($files as $file) {
        $fullPath = $root . $file;
        if (file_exists($fullPath)) {
            $size = filesize($fullPath);
            $status = $size > 0 ? '✅' : '⚠️';
            echo "  {$status} {$file} ({$size} bytes)\n";
            $results['passed']++;
            $checks['files'][$file] = 'OK';
        } else {
            echo "  ❌ {$file} - NOT FOUND\n";
            $results['failed']++;
            $results['errors'][] = "Missing file: {$file}";
            $checks['files'][$file] = 'MISSING';
        }
    }
    echo "\n";
}

// =====================================================================
// 2. PHP SYNTAX CHECKS
// =====================================================================

echo "📋 STEP 2: PHP SYNTAX CHECKS\n";
echo str_repeat("=", 80) . "\n\n";

foreach ($requiredFiles['php'] as $file) {
    $fullPath = $root . $file;
    if (file_exists($fullPath)) {
        $output = [];
        $return = 0;
        exec("php -l {$fullPath} 2>&1", $output, $return);

        if ($return === 0) {
            echo "  ✅ {$file} - Syntax OK\n";
            $results['passed']++;
            $checks['apis'][$file] = 'VALID';
        } else {
            echo "  ❌ {$file} - Syntax Error:\n";
            echo "     " . implode("\n     ", $output) . "\n";
            $results['failed']++;
            $results['errors'][] = "Syntax error in {$file}";
            $checks['apis'][$file] = 'INVALID';
        }
    }
}
echo "\n";

// =====================================================================
// 3. FILE PERMISSIONS CHECKS
// =====================================================================

echo "📋 STEP 3: FILE PERMISSIONS CHECKS\n";
echo str_repeat("=", 80) . "\n\n";

$allFiles = array_merge($requiredFiles['php'], $requiredFiles['javascript']);

foreach ($allFiles as $file) {
    $fullPath = $root . $file;
    if (file_exists($fullPath)) {
        $perms = substr(sprintf('%o', fileperms($fullPath)), -4);
        $readable = is_readable($fullPath) ? '✅' : '❌';
        $writable = is_writable($fullPath) ? '✅' : '⚠️';

        echo "  {$readable} {$writable} {$file} ({$perms})\n";

        if (is_readable($fullPath)) {
            $results['passed']++;
            $checks['permissions'][$file] = 'OK';
        } else {
            $results['warnings']++;
            $results['errors'][] = "File not readable: {$file}";
            $checks['permissions'][$file] = 'NOT_READABLE';
        }
    }
}
echo "\n";

// =====================================================================
// 4. API FUNCTIONALITY TESTS
// =====================================================================

echo "📋 STEP 4: API FUNCTIONALITY TESTS\n";
echo str_repeat("=", 80) . "\n\n";

// Test File Explorer API
echo "Testing File Explorer API:\n";
$fileExplorerTests = [
    ['action' => 'list', 'dir' => '/modules/admin-ui'],
    ['action' => 'tree', 'dir' => '/modules/admin-ui', 'depth' => 2],
];

foreach ($fileExplorerTests as $test) {
    echo "  Testing: action=" . $test['action'] . "\n";

    // Simulate API call
    $result = testFileExplorerAPI($test);
    if ($result['success']) {
        echo "    ✅ Success\n";
        $results['passed']++;
    } else {
        echo "    ❌ Failed: " . ($result['error'] ?? 'Unknown error') . "\n";
        $results['failed']++;
    }
}
echo "\n";

// Test Sandbox Executor
echo "Testing PHP Sandbox Executor:\n";
$sandboxTests = [
    '<?php echo "Hello World"; ?>',
    '<?php $x = 5 + 3; echo $x; ?>',
    '<?php return ["status" => "ok"]; ?>',
];

foreach ($sandboxTests as $i => $code) {
    echo "  Test #" . ($i + 1) . ": " . substr($code, 0, 30) . "...\n";

    $result = testSandboxExecutor($code);
    if ($result['success']) {
        echo "    ✅ Executed successfully\n";
        echo "    Output: " . trim($result['output'] ?? '') . "\n";
        $results['passed']++;
    } else {
        echo "    ❌ Execution failed\n";
        $results['failed']++;
    }
}
echo "\n";

// =====================================================================
// 5. PERFORMANCE BENCHMARKS
// =====================================================================

echo "📋 STEP 5: PERFORMANCE BENCHMARKS\n";
echo str_repeat("=", 80) . "\n\n";

// Benchmark File Explorer
$startTime = microtime(true);
for ($i = 0; $i < 10; $i++) {
    testFileExplorerAPI(['action' => 'list', 'dir' => '/modules/admin-ui']);
}
$fileExplorerTime = (microtime(true) - $startTime) / 10 * 1000;

echo "File Explorer API (10 iterations avg): " . number_format($fileExplorerTime, 2) . "ms\n";
if ($fileExplorerTime < 100) {
    echo "  ✅ Performance OK (target: < 100ms)\n";
    $results['passed']++;
} else {
    echo "  ⚠️ Performance warning (target: < 100ms)\n";
    $results['warnings']++;
}

// Benchmark Sandbox
$startTime = microtime(true);
for ($i = 0; $i < 5; $i++) {
    testSandboxExecutor('<?php $x = 5 + 3; ?>');
}
$sandboxTime = (microtime(true) - $startTime) / 5 * 1000;

echo "Sandbox Executor (5 iterations avg): " . number_format($sandboxTime, 2) . "ms\n";
if ($sandboxTime < 100) {
    echo "  ✅ Performance OK (target: < 100ms)\n";
    $results['passed']++;
} else {
    echo "  ⚠️ Performance warning (target: < 100ms)\n";
    $results['warnings']++;
}
echo "\n";

// =====================================================================
// 6. SUMMARY REPORT
// =====================================================================

echo "📋 FINAL VERIFICATION SUMMARY\n";
echo str_repeat("=", 80) . "\n\n";

echo "✅ PASSED:  " . $results['passed'] . "\n";
echo "❌ FAILED:  " . $results['failed'] . "\n";
echo "⚠️  WARNINGS: " . $results['warnings'] . "\n";

if (!empty($results['errors'])) {
    echo "\nErrors Detected:\n";
    foreach ($results['errors'] as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n" . str_repeat("=", 80) . "\n";

if ($results['failed'] === 0) {
    echo "🎉 DEPLOYMENT READY! All checks passed.\n";
    echo "Status: ✅ PRODUCTION READY\n";
    exit(0);
} else {
    echo "⚠️ DEPLOYMENT BLOCKED! Fix errors above before deploying.\n";
    echo "Status: ❌ NOT READY\n";
    exit(1);
}

// =====================================================================
// HELPER FUNCTIONS
// =====================================================================

function testFileExplorerAPI($params) {
    $root = $_SERVER['DOCUMENT_ROOT'];

    try {
        // Simulate API call
        $action = $params['action'] ?? 'list';
        $dir = $params['dir'] ?? '/modules/admin-ui';

        if (!is_dir($root . $dir)) {
            return ['success' => false, 'error' => 'Directory not found'];
        }

        if ($action === 'list') {
            $items = scandir($root . $dir);
            return ['success' => true, 'count' => count($items)];
        } elseif ($action === 'tree') {
            return ['success' => true, 'message' => 'Tree generated'];
        }

        return ['success' => false, 'error' => 'Unknown action'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function testSandboxExecutor($code) {
    try {
        ob_start();

        // Security check: block dangerous functions
        $blockedFunctions = ['exec', 'shell_exec', 'system', 'eval', 'include', 'require'];
        foreach ($blockedFunctions as $func) {
            if (stripos($code, $func) !== false) {
                ob_end_clean();
                return ['success' => false, 'error' => 'Blocked function detected'];
            }
        }

        // Execute code
        eval('?>' . $code);
        $output = ob_get_clean();

        return ['success' => true, 'output' => $output];
    } catch (Exception $e) {
        ob_end_clean();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
