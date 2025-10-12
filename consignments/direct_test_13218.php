<?php
/**
 * Direct API Test for Transfer 13218
 * Tests receive endpoints with BOT_BYPASS_AUTH
 */

// Enable BOT_BYPASS_AUTH
$_ENV['BOT_BYPASS_AUTH'] = '1';
$_SERVER['BOT_BYPASS_AUTH'] = '1';
$_GET['bot'] = 'true';

echo "<h1>🧪 Direct API Test for Transfer 13218</h1>";
echo "<p><strong>BOT_BYPASS_AUTH:</strong> " . ($_ENV['BOT_BYPASS_AUTH'] ?? 'NOT SET') . "</p>";

// Test data for transfer 13218
$test_data = [
    'transfer_id' => 13218,
    'transfer_mode' => 'GENERAL',
    'items' => [
        [
            'item_id' => 1,
            'product_id' => 1,
            'qty_requested' => 10,
            'qty_received' => 8,
            'weight_grams' => 100
        ]
    ],
    'totals' => [
        'total_requested' => 10,
        'total_received' => 8,
        'weight_grams' => 800
    ],
    'receiver_name' => 'Test Bot Direct',
    'delivery_notes' => 'Direct PHP test for 13218',
    'timestamp' => date('Y-m-d H:i:s')
];

echo "<h2>📋 Test Data:</h2>";
echo "<pre>" . htmlspecialchars(json_encode($test_data, JSON_PRETTY_PRINT)) . "</pre>";

// Simulate AJAX request environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Put JSON data in php://input simulation
$json_data = json_encode($test_data);
file_put_contents('php://memory', $json_data);

echo "<h2>🚀 Calling receive_autosave.php directly...</h2>";

try {
    // Capture output from the API
    ob_start();
    
    // Set up the JSON input for the API
    $GLOBALS['HTTP_RAW_POST_DATA'] = $json_data;
    $_POST = $test_data; // Fallback
    
    // Include the API file
    include __DIR__ . '/api/receive_autosave.php';
    
    $output = ob_get_clean();
    
    echo "<h3>✅ API Response:</h3>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
} catch (Exception $e) {
    echo "<h3>❌ Exception:</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
} catch (Error $e) {
    echo "<h3>❌ Fatal Error:</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<h2>📊 Environment Check:</h2>";
echo "<ul>";
echo "<li><strong>REQUEST_METHOD:</strong> " . ($_SERVER['REQUEST_METHOD'] ?? 'NOT SET') . "</li>";
echo "<li><strong>HTTP_X_REQUESTED_WITH:</strong> " . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'NOT SET') . "</li>";
echo "<li><strong>BOT_BYPASS_AUTH:</strong> " . ($_ENV['BOT_BYPASS_AUTH'] ?? 'NOT SET') . "</li>";
echo "<li><strong>Session Started:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'YES' : 'NO') . "</li>";
echo "<li><strong>Current Directory:</strong> " . getcwd() . "</li>";
echo "</ul>";

// Test database connection
echo "<h2>🗄️ Database Connection Test:</h2>";
try {
    if (class_exists('Database')) {
        $pdo = Database::getConnection();
        echo "<p>✅ Database connection successful</p>";
        
        // Check if transfer 13218 exists
        $stmt = $pdo->prepare("SELECT id, transfer_mode, status FROM transfers WHERE id = ?");
        $stmt->execute([13218]);
        $transfer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($transfer) {
            echo "<p>✅ Transfer 13218 found:</p>";
            echo "<pre>" . htmlspecialchars(json_encode($transfer, JSON_PRETTY_PRINT)) . "</pre>";
            
            // Check transfer items
            $stmt = $pdo->prepare("SELECT COUNT(*) as item_count FROM transfer_items WHERE transfer_id = ?");
            $stmt->execute([13218]);
            $item_count = $stmt->fetch(PDO::FETCH_ASSOC)['item_count'];
            echo "<p>📦 Transfer items: {$item_count}</p>";
            
        } else {
            echo "<p>❌ Transfer 13218 NOT FOUND in database</p>";
        }
        
    } else {
        echo "<p>❌ Database class not available</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

?>