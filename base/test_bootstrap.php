<?php
/**
 * Bootstrap System Test
 * 
 * Tests all bootstrap functionality without requiring authentication
 */

// Suppress auth for testing
define('SKIP_AUTH', true);

require_once __DIR__ . '/bootstrap.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Bootstrap Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .test { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #ccc; }
        .test.pass { border-color: #28a745; }
        .test.fail { border-color: #dc3545; }
        .test h3 { margin: 0 0 10px 0; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; }
        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        .info { background: #e9ecef; padding: 10px; border-radius: 3px; margin: 5px 0; font-family: monospace; font-size: 13px; }
        h1 { color: #333; }
        .summary { background: #007bff; color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="summary">
        <h1>🧪 CIS Bootstrap System Test</h1>
        <p>Testing all bootstrap functionality and helper functions</p>
    </div>

    <?php
    $tests = [];
    $passed = 0;
    $failed = 0;

    // Test 1: Config loaded
    $test = ['name' => 'Config Singleton Loaded', 'pass' => false, 'info' => ''];
    try {
        if (isset($config) && $config instanceof Services\Config) {
            $test['pass'] = true;
            $test['info'] = 'Config class: ' . get_class($config);
        }
    } catch (Exception $e) {
        $test['info'] = 'Error: ' . $e->getMessage();
    }
    $tests[] = $test;

    // Test 2: Database loaded
    $test = ['name' => 'Database Singleton Loaded', 'pass' => false, 'info' => ''];
    try {
        if (isset($db) && $db instanceof PDO) {
            $test['pass'] = true;
            $test['info'] = 'PDO connection established';
        }
    } catch (Exception $e) {
        $test['info'] = 'Error: ' . $e->getMessage();
    }
    $tests[] = $test;

    // Test 3: Session started
    $test = ['name' => 'Session Management', 'pass' => false, 'info' => ''];
    if (session_status() === PHP_SESSION_ACTIVE) {
        $test['pass'] = true;
        $test['info'] = 'Session ID: ' . substr(session_id(), 0, 16) . '...';
    } else {
        $test['info'] = 'Session not started';
    }
    $tests[] = $test;

    // Test 4: ThemeManager loaded
    $test = ['name' => 'ThemeManager Loaded', 'pass' => false, 'info' => ''];
    try {
        if (class_exists('CIS\Base\ThemeManager')) {
            $test['pass'] = true;
            $test['info'] = 'Active theme: ' . theme();
        }
    } catch (Exception $e) {
        $test['info'] = 'Error: ' . $e->getMessage();
    }
    $tests[] = $test;

    // Test 5: Helper functions exist
    $helperFunctions = [
        'isAuthenticated', 'getCurrentUser', 'requireAuth', 'getUserId', 'getUserRole',
        'hasPermission', 'requirePermission', 'hasAnyPermission', 'hasAllPermissions',
        'render', 'component', 'themeAsset', 'theme',
        'e', 'asset', 'moduleUrl', 'redirect', 'jsonResponse', 'flash', 'getFlash', 'dd'
    ];
    
    $test = ['name' => 'Helper Functions Available', 'pass' => true, 'info' => ''];
    $missing = [];
    foreach ($helperFunctions as $func) {
        if (!function_exists($func)) {
            $test['pass'] = false;
            $missing[] = $func;
        }
    }
    if ($test['pass']) {
        $test['info'] = count($helperFunctions) . ' helper functions available';
    } else {
        $test['info'] = 'Missing: ' . implode(', ', $missing);
    }
    $tests[] = $test;

    // Test 6: Theme system
    $test = ['name' => 'Theme System Functional', 'pass' => false, 'info' => ''];
    try {
        $themes = \CIS\Base\ThemeManager::getAvailable();
        if (count($themes) >= 3) {
            $test['pass'] = true;
            $test['info'] = 'Themes: ' . implode(', ', array_keys($themes));
        } else {
            $test['info'] = 'Only ' . count($themes) . ' themes found';
        }
    } catch (Exception $e) {
        $test['info'] = 'Error: ' . $e->getMessage();
    }
    $tests[] = $test;

    // Test 7: e() escaping
    $test = ['name' => 'HTML Escape Function', 'pass' => false, 'info' => ''];
    $testString = '<script>alert("xss")</script>';
    $escaped = e($testString);
    if (strpos($escaped, '<script>') === false && strpos($escaped, '&lt;') !== false) {
        $test['pass'] = true;
        $test['info'] = 'Properly escapes HTML entities';
    } else {
        $test['info'] = 'Escaping failed';
    }
    $tests[] = $test;

    // Test 8: asset() URL generation
    $test = ['name' => 'Asset URL Helper', 'pass' => false, 'info' => ''];
    $assetUrl = asset('css/style.css');
    if ($assetUrl === '/assets/css/style.css') {
        $test['pass'] = true;
        $test['info'] = 'Generates correct asset URLs';
    } else {
        $test['info'] = 'Got: ' . $assetUrl;
    }
    $tests[] = $test;

    // Test 9: moduleUrl() generation
    $test = ['name' => 'Module URL Helper', 'pass' => false, 'info' => ''];
    $moduleUrl = moduleUrl('transfers', 'index.php');
    if ($moduleUrl === '/modules/transfers/index.php') {
        $test['pass'] = true;
        $test['info'] = 'Generates correct module URLs';
    } else {
        $test['info'] = 'Got: ' . $moduleUrl;
    }
    $tests[] = $test;

    // Test 10: Flash messages
    $test = ['name' => 'Flash Message System', 'pass' => false, 'info' => ''];
    flash('test', 'Test message', 'success');
    $flashData = getFlash('test');
    if ($flashData && $flashData['message'] === 'Test message' && $flashData['type'] === 'success') {
        $test['pass'] = true;
        $test['info'] = 'Flash messages work correctly';
    } else {
        $test['info'] = 'Flash message retrieval failed';
    }
    $tests[] = $test;

    // Display results
    foreach ($tests as $test) {
        if ($test['pass']) {
            $passed++;
            echo '<div class="test pass">';
            echo '<h3>✅ ' . htmlspecialchars($test['name']) . ' <span class="badge badge-success">PASS</span></h3>';
        } else {
            $failed++;
            echo '<div class="test fail">';
            echo '<h3>❌ ' . htmlspecialchars($test['name']) . ' <span class="badge badge-danger">FAIL</span></h3>';
        }
        if ($test['info']) {
            echo '<div class="info">' . htmlspecialchars($test['info']) . '</div>';
        }
        echo '</div>';
    }

    // Summary
    $total = $passed + $failed;
    $percentage = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
    $status = $failed === 0 ? 'ALL TESTS PASSED' : ($passed > $failed ? 'MOSTLY PASSING' : 'NEEDS ATTENTION');
    $color = $failed === 0 ? '#28a745' : ($passed > $failed ? '#ffc107' : '#dc3545');
    ?>

    <div style="background: <?= $color ?>; color: white; padding: 20px; border-radius: 5px; margin-top: 20px;">
        <h2 style="margin: 0 0 10px 0;">📊 Test Summary: <?= $status ?></h2>
        <p style="font-size: 18px; margin: 5px 0;">
            <strong><?= $passed ?></strong> passed | 
            <strong><?= $failed ?></strong> failed | 
            <strong><?= $percentage ?>%</strong> success rate
        </p>
    </div>

    <div style="margin-top: 20px; padding: 15px; background: white; border-radius: 5px;">
        <h3>System Information</h3>
        <table style="width: 100%; border-collapse: collapse;">
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 8px;"><strong>PHP Version:</strong></td>
                <td style="padding: 8px;"><?= PHP_VERSION ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 8px;"><strong>Bootstrap Path:</strong></td>
                <td style="padding: 8px; font-family: monospace; font-size: 12px;"><?= __DIR__ . '/bootstrap.php' ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 8px;"><strong>Active Theme:</strong></td>
                <td style="padding: 8px;"><?= theme() ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 8px;"><strong>Available Themes:</strong></td>
                <td style="padding: 8px;"><?= implode(', ', array_keys(\CIS\Base\ThemeManager::getAvailable())) ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 8px;"><strong>Database Connected:</strong></td>
                <td style="padding: 8px;"><?= isset($db) ? 'Yes' : 'No' ?></td>
            </tr>
            <tr>
                <td style="padding: 8px;"><strong>Test Date:</strong></td>
                <td style="padding: 8px;"><?= date('Y-m-d H:i:s') ?></td>
            </tr>
        </table>
    </div>

</body>
</html>
