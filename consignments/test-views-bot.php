<?php
/**
 * Test all consignment views with bot bypass
 */

// Bot bypass - set cookie
$_COOKIE['bot_bypass'] = 'true';
$_SERVER['HTTP_USER_AGENT'] = 'CIS-TestBot/1.0';

// Load bootstrap
require_once __DIR__ . '/bootstrap.php';

// Override auth check
function requireAuth() { return true; }
function isAuthenticated() { return true; }

echo "Testing Consignments Views\n";
echo str_repeat("=", 50) . "\n\n";

$routes = [
    ['route' => '', 'name' => 'Home Dashboard'],
    ['route' => 'home', 'name' => 'Home'],
    ['route' => 'stock-transfers', 'name' => 'Stock Transfers List'],
    ['route' => 'purchase-orders', 'name' => 'Purchase Orders List'],
    ['route' => 'transfer-manager', 'name' => 'Transfer Manager'],
    ['route' => 'freight', 'name' => 'Freight'],
    ['route' => 'control-panel', 'name' => 'Control Panel'],
    ['route' => 'receiving', 'name' => 'Receiving'],
    ['route' => 'dashboard', 'name' => 'Dashboard'],
    ['route' => 'queue-status', 'name' => 'Queue Status'],
];

foreach ($routes as $test) {
    echo "Testing: {$test['name']} (?route={$test['route']})\n";
    
    ob_start();
    $_GET['route'] = $test['route'];
    
    try {
        include __DIR__ . '/index.php';
        $output = ob_get_clean();
        
        if (!empty($output)) {
            echo "  ✓ View rendered successfully\n";
        } else {
            echo "  ✗ No output generated\n";
        }
    } catch (Exception $e) {
        ob_end_clean();
        echo "  ✗ Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "Test complete!\n";
