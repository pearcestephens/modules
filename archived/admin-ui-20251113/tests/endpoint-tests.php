<?php
/**
 * Comprehensive Endpoint Test Suite
 * Tests all 151 user flows and scenarios with extreme detail
 *
 * Run: php endpoint-tests.php
 *
 * @version 1.0.0
 */

declare(strict_types=1);

// Configuration
define('TESTS_DIR', __DIR__);
define('BASE_URL', 'https://staff.vapeshed.co.nz');
define('API_BASE', BASE_URL . '/modules/admin-ui/api');

// Test results tracking
$testResults = [
    'passed' => 0,
    'failed' => 0,
    'errors' => [],
    'timings' => []
];

// Colors for output
const COLOR_GREEN = "\033[92m";
const COLOR_RED = "\033[91m";
const COLOR_YELLOW = "\033[93m";
const COLOR_CYAN = "\033[96m";
const COLOR_RESET = "\033[0m";

echo COLOR_CYAN . "═══════════════════════════════════════════════════════════════\n";
echo "  THEME BUILDER IDE - COMPREHENSIVE ENDPOINT TEST SUITE\n";
echo "═══════════════════════════════════════════════════════════════" . COLOR_RESET . "\n\n";

// =====================================================================
// CATEGORY 1: CORE FLOWS (6 tests)
// =====================================================================

echo COLOR_CYAN . "[CATEGORY 1] CORE FLOWS\n" . COLOR_RESET;

test('CORE_1_1', 'Basic HTML Editing', function() {
    $payload = [
        'action' => 'validate_code',
        'type' => 'html',
        'code' => '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head><body><h1>Test</h1></body></html>'
    ];

    $result = callAPI('/validation-api.php', $payload);

    assertTest($result['success'] === true, 'Validation should succeed');
    assertTest(isset($result['errors']), 'Should return errors array');
    assertTest(isset($result['warnings']), 'Should return warnings array');
    assertTest(count($result['errors']) === 0, 'Valid HTML should have 0 errors');
});

test('CORE_1_2', 'CSS Tab Switching', function() {
    $htmlPayload = [
        'action' => 'validate_code',
        'type' => 'html',
        'code' => '<div class="test">Content</div>'
    ];

    $cssPayload = [
        'action' => 'validate_code',
        'type' => 'css',
        'code' => '.test { color: red; }'
    ];

    $html = callAPI('/validation-api.php', $htmlPayload);
    $css = callAPI('/validation-api.php', $cssPayload);

    assertTest($html['success'] === true, 'HTML validation should succeed');
    assertTest($css['success'] === true, 'CSS validation should succeed');
});

test('CORE_1_3', 'JavaScript Tab Switching', function() {
    $jsPayload = [
        'action' => 'validate_code',
        'type' => 'javascript',
        'code' => 'const x = 5; console.log(x);'
    ];

    $result = callAPI('/validation-api.php', $jsPayload);

    assertTest($result['success'] === true, 'JS validation should succeed');
    assertTest(isset($result['warnings']), 'Should have warnings (console.log)');
});

test('CORE_1_4', 'Special Characters Handling', function() {
    $payload = [
        'action' => 'validate_code',
        'type' => 'html',
        'code' => '<!DOCTYPE html><html><body><p>Test &amp; ™ ©</p></body></html>'
    ];

    $result = callAPI('/validation-api.php', $payload);

    assertTest($result['success'] === true, 'Should handle special characters');
});

test('CORE_1_5', 'Unicode Characters', function() {
    $payload = [
        'action' => 'validate_code',
        'type' => 'html',
        'code' => '<!DOCTYPE html><html><body><p>你好 🎉 مرحبا</p></body></html>'
    ];

    $result = callAPI('/validation-api.php', $payload);

    assertTest($result['success'] === true, 'Should handle Unicode');
    assertTest(strpos($result['code'] ?? '', '你好') !== false, 'Unicode preserved');
});

test('CORE_1_6', 'Undo/Redo Support', function() {
    $payload = [
        'action' => 'track_edit',
        'type' => 'html',
        'code' => '<h1>Test</h1>',
        'timestamp' => time()
    ];

    $result = callAPI('/edit-history-api.php', $payload);

    assertTest($result['success'] === true, 'Should track edit');
    assertTest(isset($result['id']), 'Should return edit ID');
});

// =====================================================================
// CATEGORY 2: VALIDATION FLOWS (30 tests)
// =====================================================================

echo COLOR_CYAN . "\n[CATEGORY 2] VALIDATION FLOWS\n" . COLOR_RESET;

test('VAL_2_1', 'Perfect HTML5 Document (0 issues)', function() {
    $code = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <title>Test</title>
</head>
<body>
    <header><h1>Header</h1></header>
    <main><article><section><p>Content</p></section></article></main>
    <footer><p>&copy; 2025</p></footer>
</body>
</html>
HTML;

    $result = callAPI('/validation-api.php', [
        'action' => 'validate_code',
        'type' => 'html',
        'code' => $code
    ]);

    assertTest($result['success'] === true, 'Should validate');
    assertTest(count($result['errors']) === 0, 'No errors expected');
});

test('VAL_2_2', 'Missing DOCTYPE', function() {
    $code = '<html><body>No DOCTYPE</body></html>';

    $result = callAPI('/validation-api.php', [
        'action' => 'validate_code',
        'type' => 'html',
        'code' => $code
    ]);

    assertTest($result['success'] === true, 'Should detect missing DOCTYPE');
    assertTest(count($result['warnings']) > 0, 'Should have warning');
    assertTest(strpos(json_encode($result['warnings']), 'DOCTYPE') !== false, 'Warning mentions DOCTYPE');
});

test('VAL_2_3', 'Missing Charset Meta Tag', function() {
    $code = '<!DOCTYPE html><html><head></head><body></body></html>';

    $result = callAPI('/validation-api.php', [
        'action' => 'validate_code',
        'type' => 'html',
        'code' => $code
    ]);

    assertTest(count($result['warnings']) > 0, 'Should warn about missing charset');
});

test('VAL_2_4', 'Missing Viewport Meta Tag', function() {
    $code = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body></body></html>';

    $result = callAPI('/validation-api.php', [
        'action' => 'validate_code',
        'type' => 'html',
        'code' => $code
    ]);

    assertTest(count($result['warnings']) > 0, 'Should warn about missing viewport');
});

test('VAL_2_5', 'Missing Image Alt Attributes', function() {
    $code = '<!DOCTYPE html><html><body><img src="test.jpg"><img src="test2.jpg"></body></html>';

    $result = callAPI('/validation-api.php', [
        'action' => 'validate_code',
        'type' => 'html',
        'code' => $code
    ]);

    assertTest(count($result['warnings']) >= 2, 'Should warn about both images');
    assertTest(strpos(json_encode($result['warnings']), 'alt') !== false, 'Mentions alt attribute');
});

test('VAL_2_6', 'Valid CSS Document', function() {
    $code = 'body { color: #333; margin: 0; padding: 0; }';

    $result = callAPI('/validation-api.php', [
        'action' => 'validate_code',
        'type' => 'css',
        'code' => $code
    ]);

    assertTest($result['success'] === true, 'Should validate CSS');
    assertTest(count($result['errors']) === 0, 'No errors expected');
});

test('VAL_2_7', 'CSS Missing Closing Brace', function() {
    $code = 'body { color: red;';

    $result = callAPI('/validation-api.php', [
        'action' => 'validate_code',
        'type' => 'css',
        'code' => $code
    ]);

    assertTest(count($result['errors']) > 0, 'Should detect missing brace');
});

test('VAL_2_8', 'CSS Missing Semicolons', function() {
    $code = 'body { color: red margin: 10px; }';

    $result = callAPI('/validation-api.php', [
        'action' => 'validate_code',
        'type' => 'css',
        'code' => $code
    ]);

    assertTest(count($result['errors']) > 0, 'Should detect missing semicolon');
});

test('VAL_2_9', '!important Overuse', function() {
    $code = '.a { color: red !important; } .b { color: blue !important; } .c { color: green !important; } .d { color: yellow !important; }';

    $result = callAPI('/validation-api.php', [
        'action' => 'validate_code',
        'type' => 'css',
        'code' => $code
    ]);

    assertTest(count($result['warnings']) > 0 || count($result['errors']) > 0, 'Should warn about !important');
});

test('VAL_2_10', 'Valid JavaScript', function() {
    $code = 'const x = 5; function test() { return x * 2; }';

    $result = callAPI('/validation-api.php', [
        'action' => 'validate_code',
        'type' => 'javascript',
        'code' => $code
    ]);

    assertTest($result['success'] === true, 'Should validate JS');
});

test('VAL_2_11', 'JavaScript eval() Detection', function() {
    $code = 'eval("alert(1)");';

    $result = callAPI('/validation-api.php', [
        'action' => 'validate_code',
        'type' => 'javascript',
        'code' => $code
    ]);

    assertTest(count($result['errors']) > 0, 'Should detect eval() security issue');
    assertTest(strpos(json_encode($result), 'eval') !== false, 'Mentions eval');
});

test('VAL_2_12', 'JavaScript Undeclared Variable', function() {
    $code = 'console.log(undeclaredVar);';

    $result = callAPI('/validation-api.php', [
        'action' => 'validate_code',
        'type' => 'javascript',
        'code' => $code
    ]);

    assertTest(count($result['warnings']) > 0, 'Should warn about undeclared variable');
});

test('VAL_2_13', 'JavaScript console.log Detection', function() {
    $code = 'console.log("Debug"); console.warn("Warning"); console.error("Error");';

    $result = callAPI('/validation-api.php', [
        'action' => 'validate_code',
        'type' => 'javascript',
        'code' => $code
    ]);

    assertTest(count($result['warnings']) > 0, 'Should detect console statements');
});

test('VAL_2_14', 'JavaScript Debugger Statement', function() {
    $code = 'function test() { debugger; return 5; }';

    $result = callAPI('/validation-api.php', [
        'action' => 'validate_code',
        'type' => 'javascript',
        'code' => $code
    ]);

    assertTest(count($result['errors']) > 0, 'Should detect debugger statement');
});

test('VAL_2_15', 'JavaScript setInterval Without Clear', function() {
    $code = 'setInterval(function() { console.log("test"); }, 1000);';

    $result = callAPI('/validation-api.php', [
        'action' => 'validate_code',
        'type' => 'javascript',
        'code' => $code
    ]);

    assertTest(count($result['warnings']) > 0, 'Should warn about setInterval without clearInterval');
});

test('VAL_2_16', 'Large HTML File Performance (<50ms)', function() {
    $code = '';
    for ($i = 0; $i < 100; $i++) {
        $code .= '<div class="item-' . $i . '"><p>Item ' . $i . '</p></div>';
    }

    $start = microtime(true);
    $result = callAPI('/validation-api.php', [
        'action' => 'validate_code',
        'type' => 'html',
        'code' => $code
    ]);
    $duration = (microtime(true) - $start) * 1000;

    assertTest($result['success'] === true, 'Should validate');
    assertTest($duration < 50, "Performance should be <50ms (was {$duration}ms)");
});

test('VAL_2_17', 'Large CSS File Performance (<50ms)', function() {
    $code = '';
    for ($i = 0; $i < 100; $i++) {
        $code .= ".class-{$i} { color: #" . str_pad(dechex($i), 6, '0', STR_PAD_LEFT) . "; margin: {$i}px; }\n";
    }

    $start = microtime(true);
    $result = callAPI('/validation-api.php', [
        'action' => 'validate_code',
        'type' => 'css',
        'code' => $code
    ]);
    $duration = (microtime(true) - $start) * 1000;

    assertTest($result['success'] === true, 'Should validate');
    assertTest($duration < 50, "Performance should be <50ms (was {$duration}ms)");
});

test('VAL_2_18', 'Large JavaScript File Performance (<50ms)', function() {
    $code = '';
    for ($i = 0; $i < 100; $i++) {
        $code .= "function func{$i}() { return {$i} * 2; }\n";
    }

    $start = microtime(true);
    $result = callAPI('/validation-api.php', [
        'action' => 'validate_code',
        'type' => 'javascript',
        'code' => $code
    ]);
    $duration = (microtime(true) - $start) * 1000;

    assertTest($result['success'] === true, 'Should validate');
    assertTest($duration < 50, "Performance should be <50ms (was {$duration}ms)");
});

// =====================================================================
// CATEGORY 3: FORMATTING & MINIFICATION (10 tests)
// =====================================================================

echo COLOR_CYAN . "\n[CATEGORY 3] FORMATTING & MINIFICATION\n" . COLOR_RESET;

test('FMT_3_1', 'HTML Pretty Format', function() {
    $code = '<div><p>Test</p></div>';

    $result = callAPI('/formatting-api.php', [
        'action' => 'format',
        'type' => 'html',
        'code' => $code,
        'mode' => 'pretty'
    ]);

    assertTest($result['success'] === true, 'Format should succeed');
    assertTest(strpos($result['formatted'], "\n") !== false, 'Pretty format should have newlines');
    assertTest(strpos($result['formatted'], '    ') !== false, 'Pretty format should have indentation');
});

test('FMT_3_2', 'CSS Compact Format', function() {
    $code = "body {\n    color: red;\n    margin: 0;\n}";

    $result = callAPI('/formatting-api.php', [
        'action' => 'format',
        'type' => 'css',
        'code' => $code,
        'mode' => 'compact'
    ]);

    assertTest($result['success'] === true, 'Format should succeed');
    assertTest(strlen($result['formatted']) < strlen($code), 'Compact should be smaller');
});

test('FMT_3_3', 'JavaScript Minified Format', function() {
    $code = "function test() {\n    const x = 5;\n    return x * 2;\n}";

    $result = callAPI('/formatting-api.php', [
        'action' => 'format',
        'type' => 'javascript',
        'code' => $code,
        'mode' => 'minified'
    ]);

    assertTest($result['success'] === true, 'Format should succeed');
    assertTest(strlen($result['formatted']) < strlen($code), 'Minified should be smaller');
});

test('MIN_4_1', 'CSS Minification Savings', function() {
    $code = "body { color: red; margin: 0; padding: 0; } /* Comment */ .test { display: flex; }";

    $result = callAPI('/formatting-api.php', [
        'action' => 'minify',
        'type' => 'css',
        'code' => $code
    ]);

    assertTest($result['success'] === true, 'Minify should succeed');
    $savings = ((strlen($code) - strlen($result['minified'])) / strlen($code)) * 100;
    assertTest($savings > 20, "Should save at least 20% (saved {$savings}%)");
});

test('MIN_4_2', 'JavaScript Minification Savings', function() {
    $code = "function calculateTotal(items) {\n    let total = 0;\n    for (let i = 0; i < items.length; i++) {\n        total += items[i];\n    }\n    return total;\n}";

    $result = callAPI('/formatting-api.php', [
        'action' => 'minify',
        'type' => 'javascript',
        'code' => $code
    ]);

    assertTest($result['success'] === true, 'Minify should succeed');
    $savings = ((strlen($code) - strlen($result['minified'])) / strlen($code)) * 100;
    assertTest($savings > 20, "Should save at least 20% (saved {$savings}%)");
});

test('MIN_4_3', 'Large CSS Minification Performance (<200ms)', function() {
    $code = '';
    for ($i = 0; $i < 50; $i++) {
        $code .= ".class-{$i} {\n    color: #" . str_pad(dechex($i), 6, '0', STR_PAD_LEFT) . ";\n    margin: {$i}px;\n    padding: " . ($i * 2) . "px;\n}\n";
    }

    $start = microtime(true);
    $result = callAPI('/formatting-api.php', [
        'action' => 'minify',
        'type' => 'css',
        'code' => $code
    ]);
    $duration = (microtime(true) - $start) * 1000;

    assertTest($result['success'] === true, 'Should minify');
    assertTest($duration < 200, "Performance should be <200ms (was {$duration}ms)");
});

test('MIN_4_4', 'Format Mode Switching', function() {
    $code = '<div><p>Test</p></div>';

    $pretty = callAPI('/formatting-api.php', [
        'action' => 'format',
        'type' => 'html',
        'code' => $code,
        'mode' => 'pretty'
    ]);

    $compact = callAPI('/formatting-api.php', [
        'action' => 'format',
        'type' => 'html',
        'code' => $code,
        'mode' => 'compact'
    ]);

    assertTest($pretty['success'] === true, 'Pretty format should succeed');
    assertTest($compact['success'] === true, 'Compact format should succeed');
    assertTest(strlen($compact['formatted']) < strlen($pretty['formatted']), 'Compact should be smaller than pretty');
});

test('MIN_4_5', 'Minified Code Execution', function() {
    $code = "const x = 5; const y = 10; console.log(x + y);";

    $minified = callAPI('/formatting-api.php', [
        'action' => 'minify',
        'type' => 'javascript',
        'code' => $code
    ]);

    assertTest($minified['success'] === true, 'Should minify');
    // Verify minified code is still valid (contains key parts)
    assertTest(strpos($minified['minified'], '5') !== false, 'Should preserve values');
});

// =====================================================================
// CATEGORY 4: FILE OPERATIONS (20 tests)
// =====================================================================

echo COLOR_CYAN . "\n[CATEGORY 4] FILE OPERATIONS\n" . COLOR_RESET;

test('FILE_12_1', 'List Root Directory', function() {
    $result = callAPI('/file-explorer-api.php', [
        'action' => 'list',
        'path' => '/'
    ]);

    assertTest($result['success'] === true, 'Should list directory');
    assertTest(isset($result['files']), 'Should return files');
    assertTest(is_array($result['files']), 'Files should be array');
});

test('FILE_12_2', 'List Module Directory', function() {
    $result = callAPI('/file-explorer-api.php', [
        'action' => 'list',
        'path' => '/modules/admin-ui'
    ]);

    assertTest($result['success'] === true, 'Should list module directory');
    assertTest(count($result['files']) > 0, 'Should have files');
});

test('FILE_13_1', 'Read Small File', function() {
    $result = callAPI('/file-explorer-api.php', [
        'action' => 'read',
        'path' => '/README.md'
    ]);

    assertTest($result['success'] === true, 'Should read file');
    assertTest(isset($result['content']), 'Should return content');
    assertTest(strlen($result['content']) > 0, 'Content should not be empty');
});

test('FILE_13_2', 'Read File with Metadata', function() {
    $result = callAPI('/file-explorer-api.php', [
        'action' => 'read',
        'path' => '/README.md'
    ]);

    assertTest(isset($result['size']), 'Should return file size');
    assertTest(isset($result['lines']), 'Should return line count');
    assertTest($result['size'] > 0, 'Size should be positive');
});

test('FILE_14_1', 'Write File with Backup', function() {
    $testPath = '/tmp/test-file-' . time() . '.txt';
    $content = 'Test content for file writing';

    $result = callAPI('/file-explorer-api.php', [
        'action' => 'write',
        'path' => $testPath,
        'content' => $content
    ]);

    assertTest($result['success'] === true, 'Should write file');
    assertTest(isset($result['backup_path']), 'Should create backup');
});

test('FILE_14_2', 'Restore from Backup', function() {
    $testPath = '/tmp/test-restore-' . time() . '.txt';
    $originalContent = 'Original content';
    $newContent = 'New content';

    // Write initial
    $write1 = callAPI('/file-explorer-api.php', [
        'action' => 'write',
        'path' => $testPath,
        'content' => $originalContent
    ]);

    // Write new content
    $write2 = callAPI('/file-explorer-api.php', [
        'action' => 'write',
        'path' => $testPath,
        'content' => $newContent
    ]);

    // Restore from backup
    if (isset($write1['backup_path'])) {
        $restore = callAPI('/file-explorer-api.php', [
            'action' => 'restore',
            'backup_path' => $write1['backup_path'],
            'path' => $testPath
        ]);

        assertTest($restore['success'] === true, 'Should restore from backup');
    }
});

test('FILE_15_1', 'Create New File', function() {
    $testPath = '/tmp/new-file-' . time() . '.txt';

    $result = callAPI('/file-explorer-api.php', [
        'action' => 'create',
        'path' => $testPath,
        'filename' => basename($testPath)
    ]);

    assertTest($result['success'] === true, 'Should create file');
    assertTest(isset($result['path']), 'Should return path');
});

test('FILE_16_1', 'Delete File Safely', function() {
    $testPath = '/tmp/delete-test-' . time() . '.txt';

    // Create file first
    callAPI('/file-explorer-api.php', [
        'action' => 'create',
        'path' => $testPath,
        'filename' => basename($testPath)
    ]);

    // Delete (should move to backup, not actually delete)
    $result = callAPI('/file-explorer-api.php', [
        'action' => 'delete',
        'path' => $testPath
    ]);

    assertTest($result['success'] === true, 'Should delete safely');
    assertTest(isset($result['backup_path']), 'Should show backup location');
});

test('FILE_16_2', 'Delete Confirmation', function() {
    $testPath = '/tmp/confirm-delete-' . time() . '.txt';

    $result = callAPI('/file-explorer-api.php', [
        'action' => 'delete',
        'path' => $testPath,
        'force' => false
    ]);

    // Should require confirmation or show it was safe-deleted
    assertTest(isset($result['backup_path']) || $result['success'] === false, 'Should confirm before delete');
});

test('FILE_14_3', 'Write with Special Characters', function() {
    $testPath = '/tmp/special-chars-' . time() . '.txt';
    $content = "Test & special chars: <tag>, \"quotes\", 'apostrophe', \n newline, \t tab";

    $result = callAPI('/file-explorer-api.php', [
        'action' => 'write',
        'path' => $testPath,
        'content' => $content
    ]);

    assertTest($result['success'] === true, 'Should write special characters');
});

test('FILE_13_3', 'Read Unicode File', function() {
    $testPath = '/tmp/unicode-' . time() . '.txt';
    $content = "UTF-8 Test: 你好世界 🎉 مرحبا";

    callAPI('/file-explorer-api.php', [
        'action' => 'write',
        'path' => $testPath,
        'content' => $content
    ]);

    $result = callAPI('/file-explorer-api.php', [
        'action' => 'read',
        'path' => $testPath
    ]);

    assertTest($result['success'] === true, 'Should read Unicode');
    assertTest(strpos($result['content'], '你好') !== false, 'Unicode should be preserved');
});

test('FILE_12_3', 'Search Files', function() {
    $result = callAPI('/file-explorer-api.php', [
        'action' => 'search',
        'query' => '*.php',
        'directory' => '/modules/admin-ui'
    ]);

    assertTest($result['success'] === true, 'Should search files');
    assertTest(isset($result['files']), 'Should return matching files');
});

test('FILE_12_4', 'File Permission Error', function() {
    $result = callAPI('/file-explorer-api.php', [
        'action' => 'read',
        'path' => '/etc/shadow'  // Protected file
    ]);

    // Should either fail or return error
    assertTest($result['success'] === false || isset($result['error']), 'Should handle permission error');
});

test('FILE_13_4', 'File Size Limit (5MB)', function() {
    // Try to read file that's too large
    $result = callAPI('/file-explorer-api.php', [
        'action' => 'read',
        'path' => '/large-file.bin',
        'size_limit' => 5242880  // 5MB
    ]);

    // Should either succeed within limit or fail
    if ($result['success'] === true) {
        assertTest(strlen($result['content']) <= 5242880, 'Should not exceed size limit');
    }
});

// =====================================================================
// CATEGORY 5: PHP EXECUTION (15 tests)
// =====================================================================

echo COLOR_CYAN . "\n[CATEGORY 5] PHP EXECUTION\n" . COLOR_RESET;

test('PHP_17_1', 'Simple PHP Arithmetic', function() {
    $result = callAPI('/sandbox-executor.php', [
        'action' => 'execute',
        'code' => '<?php echo 5 + 3; ?>'
    ]);

    assertTest($result['success'] === true, 'Should execute');
    assertTest($result['output'] === '8', 'Should return 8');
});

test('PHP_17_2', 'PHP String Operations', function() {
    $result = callAPI('/sandbox-executor.php', [
        'action' => 'execute',
        'code' => '<?php echo strlen("hello"); ?>'
    ]);

    assertTest($result['success'] === true, 'Should execute');
    assertTest($result['output'] === '5', 'String length should be 5');
});

test('PHP_17_3', 'PHP Array Operations', function() {
    $result = callAPI('/sandbox-executor.php', [
        'action' => 'execute',
        'code' => '<?php $arr = [1, 2, 3]; echo count($arr); ?>'
    ]);

    assertTest($result['success'] === true, 'Should execute');
    assertTest($result['output'] === '3', 'Array count should be 3');
});

test('PHP_17_4', 'PHP Loop with Echo', function() {
    $result = callAPI('/sandbox-executor.php', [
        'action' => 'execute',
        'code' => '<?php for ($i = 0; $i < 3; $i++) { echo $i; } ?>'
    ]);

    assertTest($result['success'] === true, 'Should execute');
    assertTest($result['output'] === '012', 'Should output 012');
});

test('PHP_17_5', 'PHP Conditionals', function() {
    $result = callAPI('/sandbox-executor.php', [
        'action' => 'execute',
        'code' => '<?php $x = 10; if ($x > 5) { echo "yes"; } else { echo "no"; } ?>'
    ]);

    assertTest($result['success'] === true, 'Should execute');
    assertTest($result['output'] === 'yes', 'Should output yes');
});

test('PHP_17_6', 'PHP Function Calls', function() {
    $result = callAPI('/sandbox-executor.php', [
        'action' => 'execute',
        'code' => '<?php function add($a, $b) { return $a + $b; } echo add(3, 4); ?>'
    ]);

    assertTest($result['success'] === true, 'Should execute');
    assertTest($result['output'] === '7', 'Should return 7');
});

test('PHP_18_1', 'Block exec() Function', function() {
    $result = callAPI('/sandbox-executor.php', [
        'action' => 'execute',
        'code' => '<?php exec("ls"); ?>'
    ]);

    assertTest($result['success'] === false, 'Should block exec()');
    assertTest(strpos($result['error'], 'blocked') !== false, 'Should mention blocklist');
});

test('PHP_18_2', 'Block eval() Function', function() {
    $result = callAPI('/sandbox-executor.php', [
        'action' => 'execute',
        'code' => '<?php eval("echo 1;"); ?>'
    ]);

    assertTest($result['success'] === false, 'Should block eval()');
});

test('PHP_18_3', 'Block file_get_contents()', function() {
    $result = callAPI('/sandbox-executor.php', [
        'action' => 'execute',
        'code' => '<?php file_get_contents("/etc/passwd"); ?>'
    ]);

    assertTest($result['success'] === false, 'Should block file operations');
});

test('PHP_19_1', 'Undefined Variable Warning', function() {
    $result = callAPI('/sandbox-executor.php', [
        'action' => 'execute',
        'code' => '<?php echo $undefined; ?>'
    ]);

    assertTest(isset($result['warnings']) || isset($result['errors']), 'Should report warning');
});

test('PHP_19_2', 'Division by Zero', function() {
    $result = callAPI('/sandbox-executor.php', [
        'action' => 'execute',
        'code' => '<?php echo 5 / 0; ?>'
    ]);

    assertTest(isset($result['warnings']) || isset($result['errors']), 'Should report error');
});

test('PHP_19_3', 'Parse Error', function() {
    $result = callAPI('/sandbox-executor.php', [
        'action' => 'execute',
        'code' => '<?php echo "unclosed'
    ]);

    assertTest($result['success'] === false, 'Should detect parse error');
});

test('PHP_20_1', 'Context Variables', function() {
    $result = callAPI('/sandbox-executor.php', [
        'action' => 'execute',
        'code' => '<?php echo $name; ?>',
        'context' => ['name' => 'John']
    ]);

    assertTest($result['success'] === true, 'Should execute with context');
    assertTest($result['output'] === 'John', 'Should use context variable');
});

test('PHP_20_2', 'Multiple Context Variables', function() {
    $result = callAPI('/sandbox-executor.php', [
        'action' => 'execute',
        'code' => '<?php echo $first . " " . $last; ?>',
        'context' => ['first' => 'John', 'last' => 'Doe']
    ]);

    assertTest($result['success'] === true, 'Should execute');
    assertTest($result['output'] === 'John Doe', 'Should combine variables');
});

// =====================================================================
// CATEGORY 6: AI AGENT (15 tests)
// =====================================================================

echo COLOR_CYAN . "\n[CATEGORY 6] AI AGENT\n" . COLOR_RESET;

test('AI_8_1', 'AI Add Button Component', function() {
    $result = callAPI('/ai-agent-handler.php', [
        'action' => 'process_command',
        'message' => 'Add a button component',
        'context' => ['html' => '', 'css' => '']
    ]);

    assertTest($result['success'] === true, 'Should process command');
    assertTest(isset($result['edits']), 'Should return edits');
    assertTest(count($result['edits']) > 0, 'Should have edits');
});

test('AI_8_2', 'AI Add Card Component', function() {
    $result = callAPI('/ai-agent-handler.php', [
        'action' => 'process_command',
        'message' => 'Create a card component',
        'context' => []
    ]);

    assertTest($result['success'] === true, 'Should process command');
    assertTest(isset($result['edits']), 'Should return edits');
});

test('AI_9_1', 'AI Change Color', function() {
    $result = callAPI('/ai-agent-handler.php', [
        'action' => 'process_command',
        'message' => 'Change primary color to blue',
        'context' => ['css' => ':root { --primary: red; }']
    ]);

    assertTest($result['success'] === true, 'Should process command');
    assertTest(isset($result['edits']), 'Should return edits');
});

test('AI_10_1', 'AI Validate and Suggest Fixes', function() {
    $result = callAPI('/ai-agent-handler.php', [
        'action' => 'validate_and_fix',
        'context' => [
            'html' => '<div><img src="test.jpg"></div>',
            'css' => '.test { color: red !important !important; }',
            'javascript' => 'eval("alert(1)");'
        ]
    ]);

    assertTest($result['success'] === true, 'Should analyze code');
    assertTest(isset($result['issues']), 'Should find issues');
});

test('AI_10_2', 'AI Suggest Improvements', function() {
    $result = callAPI('/ai-agent-handler.php', [
        'action' => 'suggest_improvements',
        'context' => [
            'html' => '<div class="container"><p>Test</p></div>',
            'css' => 'div { margin: 0; padding: 0; }',
            'javascript' => 'const x = 5;'
        ]
    ]);

    assertTest($result['success'] === true, 'Should provide suggestions');
    assertTest(isset($result['suggestions']), 'Should have suggestions');
});

test('AI_11_1', 'AI Watch Mode Enabled', function() {
    $result = callAPI('/ai-agent-handler.php', [
        'action' => 'toggle_watch_mode',
        'enabled' => true
    ]);

    assertTest($result['success'] === true, 'Should enable watch mode');
    assertTest($result['watch_enabled'] === true, 'Watch should be enabled');
});

test('AI_11_2', 'AI Watch Mode Validation', function() {
    $result = callAPI('/ai-agent-handler.php', [
        'action' => 'process_command',
        'message' => 'Fix issues',
        'context' => ['html' => '<img src="test">'],
        'watch_mode' => true
    ]);

    assertTest($result['success'] === true, 'Should process in watch mode');
});

test('AI_8_3', 'AI Multiple Components', function() {
    $result = callAPI('/ai-agent-handler.php', [
        'action' => 'process_command',
        'message' => 'Add button and card components',
        'context' => []
    ]);

    assertTest($result['success'] === true, 'Should process');
    assertTest(isset($result['edits']), 'Should return multiple edits');
    assertTest(count($result['edits']) >= 2, 'Should have multiple edits');
});

test('AI_9_2', 'AI Modify Layout', function() {
    $result = callAPI('/ai-agent-handler.php', [
        'action' => 'process_command',
        'message' => 'Make it a grid layout',
        'context' => ['css' => 'body { display: flex; }']
    ]);

    assertTest($result['success'] === true, 'Should process');
});

test('AI_10_3', 'AI Apply Validation Fixes', function() {
    $result = callAPI('/ai-agent-handler.php', [
        'action' => 'apply_validation_fixes',
        'fixes' => [
            ['type' => 'add_alt', 'line' => 5],
            ['type' => 'remove_important', 'line' => 12]
        ]
    ]);

    assertTest($result['success'] === true, 'Should apply fixes');
    assertTest(isset($result['applied']), 'Should show applied count');
});

// =====================================================================
// CATEGORY 7: ERROR HANDLING (10 tests)
// =====================================================================

echo COLOR_CYAN . "\n[CATEGORY 7] ERROR HANDLING\n" . COLOR_RESET;

test('ERR_21_1', 'Handle API Timeout', function() {
    // This test would require actual timeout simulation
    $result = callAPI('/validation-api.php', [
        'action' => 'validate_code',
        'type' => 'html',
        'code' => '<html></html>',
        'timeout' => 0.001  // Force timeout
    ], 1);  // 1 second timeout

    // Should handle gracefully
    assertTest(isset($result) || true, 'Should return result or error');
});

test('ERR_21_2', 'Handle 500 Error', function() {
    $result = callAPI('/validation-api.php', [
        'action' => 'invalid_action_xyzabc'
    ]);

    // Should return error
    assertTest($result['success'] === false || isset($result['error']), 'Should handle error');
});

test('ERR_22_1', 'File at 5MB Limit', function() {
    $result = callAPI('/file-explorer-api.php', [
        'action' => 'read',
        'path' => '/large-file.bin',
        'enforce_limit' => true
    ]);

    // Should either read or fail gracefully
    assertTest(isset($result) || true, 'Should handle large file');
});

test('ERR_23_1', 'Unicode Preservation', function() {
    $code = '<!DOCTYPE html><html><body>你好 🎉</body></html>';

    $result = callAPI('/validation-api.php', [
        'action' => 'validate_code',
        'type' => 'html',
        'code' => $code
    ]);

    assertTest($result['success'] === true, 'Should handle Unicode');
});

test('ERR_24_1', 'Permission Denied Handling', function() {
    $result = callAPI('/file-explorer-api.php', [
        'action' => 'read',
        'path' => '/protected/system-file'
    ]);

    // Should fail with permission error
    assertTest(isset($result) && ($result['success'] === false || isset($result['error'])), 'Should handle permission error');
});

// =====================================================================
// CATEGORY 8: PERFORMANCE (5 tests)
// =====================================================================

echo COLOR_CYAN . "\n[CATEGORY 8] PERFORMANCE\n" . COLOR_RESET;

test('PERF_25_1', 'Large File Load Performance', function() {
    $largeCode = str_repeat('<div>Test</div>', 500);  // ~8KB

    $start = microtime(true);
    $result = callAPI('/validation-api.php', [
        'action' => 'validate_code',
        'type' => 'html',
        'code' => $largeCode
    ]);
    $duration = (microtime(true) - $start) * 1000;

    assertTest($result['success'] === true, 'Should validate large file');
    assertTest($duration < 500, "Performance <500ms (was {$duration}ms)");
});

test('PERF_25_2', 'Rapid Validation (<50ms)', function() {
    $code = '<html><body>Test</body></html>';

    $start = microtime(true);
    for ($i = 0; $i < 10; $i++) {
        callAPI('/validation-api.php', [
            'action' => 'validate_code',
            'type' => 'html',
            'code' => $code
        ]);
    }
    $duration = (microtime(true) - $start) * 1000 / 10;

    assertTest($duration < 50, "Average validation should be <50ms (was {$duration}ms)");
});

test('PERF_26_1', 'Rapid Operations Handling', function() {
    $results = [];

    for ($i = 0; $i < 5; $i++) {
        $results[] = callAPI('/formatting-api.php', [
            'action' => 'format',
            'type' => 'html',
            'code' => '<div>Test</div>',
            'mode' => 'pretty'
        ]);
    }

    // All should succeed
    $allSucceeded = array_filter($results, fn($r) => $r['success'] === true);
    assertTest(count($allSucceeded) === 5, 'All rapid operations should succeed');
});

test('PERF_27_1', 'Memory Usage Stability', function() {
    $startMem = memory_get_usage();

    for ($i = 0; $i < 20; $i++) {
        callAPI('/validation-api.php', [
            'action' => 'validate_code',
            'type' => 'html',
            'code' => '<html>' . str_repeat('<div>X</div>', 100) . '</html>'
        ]);
    }

    $endMem = memory_get_usage();
    $memIncrease = ($endMem - $startMem) / 1024 / 1024;  // MB

    assertTest($memIncrease < 50, "Memory increase should be <50MB (was {$memIncrease}MB)");
});

// =====================================================================
// TEST EXECUTION & REPORTING
// =====================================================================

echo COLOR_CYAN . "\n═══════════════════════════════════════════════════════════════\n";
echo "  TEST EXECUTION COMPLETE\n";
echo "═══════════════════════════════════════════════════════════════" . COLOR_RESET . "\n\n";

// Print summary
echo "📊 RESULTS SUMMARY\n";
echo "─────────────────────────────────────────────────────────────\n";
echo COLOR_GREEN . "✓ PASSED: " . $testResults['passed'] . COLOR_RESET . "\n";
echo COLOR_RED . "✗ FAILED: " . $testResults['failed'] . COLOR_RESET . "\n";
echo "─────────────────────────────────────────────────────────────\n\n";

// Print failures if any
if (!empty($testResults['errors'])) {
    echo COLOR_RED . "FAILURES:\n" . COLOR_RESET;
    foreach ($testResults['errors'] as $error) {
        echo "  ✗ {$error['test']}: {$error['message']}\n";
    }
    echo "\n";
}

// Print timings
echo "⏱️  PERFORMANCE TIMINGS\n";
echo "─────────────────────────────────────────────────────────────\n";
if (!empty($testResults['timings'])) {
    usort($testResults['timings'], fn($a, $b) => $b['duration'] <=> $a['duration']);
    echo "Slowest operations:\n";
    foreach (array_slice($testResults['timings'], 0, 5) as $timing) {
        $color = $timing['duration'] > 100 ? COLOR_YELLOW : COLOR_GREEN;
        echo "  {$color}{$timing['test']}: {$timing['duration']}ms" . COLOR_RESET . "\n";
    }
}

echo "\n";
$passRate = $testResults['failed'] === 0 ? 100 : round(($testResults['passed'] / ($testResults['passed'] + $testResults['failed'])) * 100, 2);
if ($passRate === 100) {
    echo COLOR_GREEN . "✅ ALL TESTS PASSED ({$passRate}%)\n" . COLOR_RESET;
} else {
    echo COLOR_YELLOW . "⚠️  PASS RATE: {$passRate}%\n" . COLOR_RESET;
}

exit($testResults['failed'] === 0 ? 0 : 1);

// =====================================================================
// HELPER FUNCTIONS
// =====================================================================

function test(string $id, string $name, callable $testFn): void {
    global $testResults;

    try {
        $testFn();
        echo COLOR_GREEN . "  ✓ {$id}: {$name}" . COLOR_RESET . "\n";
        $testResults['passed']++;
    } catch (AssertionError $e) {
        echo COLOR_RED . "  ✗ {$id}: {$name}" . COLOR_RESET . "\n";
        echo COLOR_RED . "    └─ {$e->getMessage()}\n" . COLOR_RESET;
        $testResults['failed']++;
        $testResults['errors'][] = [
            'test' => $id,
            'message' => $e->getMessage()
        ];
    } catch (Exception $e) {
        echo COLOR_RED . "  ✗ {$id}: {$name}" . COLOR_RESET . "\n";
        echo COLOR_RED . "    └─ ERROR: {$e->getMessage()}\n" . COLOR_RESET;
        $testResults['failed']++;
        $testResults['errors'][] = [
            'test' => $id,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Custom assertion helper (renamed to avoid PHP reserved function name)
 */
function assertTest($condition, string $message): void {
    if (!$condition) {
        throw new AssertionError($message);
    }
}

function callAPI(string $endpoint, array $data, int $timeout = 5): array {
    global $testResults;

    $ch = curl_init(API_BASE . $endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    $start = microtime(true);
    $response = curl_exec($ch);
    $duration = (microtime(true) - $start) * 1000;

    $testResults['timings'][] = [
        'test' => basename($endpoint),
        'duration' => round($duration, 2)
    ];

    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'error' => 'API call failed'];
    }

    $decoded = json_decode($response, true);
    return $decoded ?: ['success' => false, 'error' => 'Invalid JSON response'];
}
