<?php
// Quick Transfer ID Finder - Run in browser with ?bot=true
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Enable bot bypass
$_ENV['BOT_BYPASS_AUTH'] = '1';
$_GET['bot'] = 'true';

echo "<h1>🔍 Quick Transfer ID Finder</h1>";
echo "<p>Testing transfer IDs to find working ones...</p>";

try {
    // Independent module connection
    require_once __DIR__ . '/lib/Db.php';
    
    echo "<p>✅ Independent Db.php loaded successfully</p>";
    
    // Get database connection
    $db = new Db();
    $pdo = $db->getPdo();
    echo "<p>✅ Database connected successfully</p>";
    
    // Test the specific transfer IDs you mentioned
    $test_ids = [13218, 13219, 13217, 13220, 13000, 12000, 10000, 5000, 1000, 100, 50, 10, 1];
    
    echo "<h2>Transfer ID Test Results</h2>";
    echo "<table border='1' style='border-collapse:collapse; width:100%;'>";
    echo "<tr><th>Transfer ID</th><th>Status</th><th>Mode</th><th>Items</th><th>From → To</th></tr>";
    
    $found_working = [];
    
    foreach ($test_ids as $id) {
        $stmt = $pdo->prepare("
            SELECT t.id, t.transfer_mode, t.status, 
                   COUNT(ti.id) as item_count,
                   o_from.name as from_outlet,
                   o_to.name as to_outlet
            FROM transfers t
            LEFT JOIN transfer_items ti ON t.id = ti.transfer_id AND ti.deleted_by IS NULL
            LEFT JOIN vend_outlets o_from ON t.from_outlet_id = o_from.id
            LEFT JOIN vend_outlets o_to ON t.to_outlet_id = o_to.id
            WHERE t.id = ?
            GROUP BY t.id
        ");
        
        $stmt->execute([$id]);
        $transfer = $stmt->fetch();
        
        if ($transfer) {
            $color = $transfer['item_count'] > 0 ? '#d4edda' : '#fff3cd';
            echo "<tr style='background-color: {$color};'>";
            echo "<td><strong>{$id}</strong></td>";
            echo "<td>{$transfer['status']}</td>";
            echo "<td>{$transfer['transfer_mode']}</td>";
            echo "<td>{$transfer['item_count']}</td>";
            echo "<td>{$transfer['from_outlet']} → {$transfer['to_outlet']}</td>";
            echo "</tr>";
            
            if ($transfer['item_count'] > 0) {
                $found_working[] = $id;
            }
        } else {
            echo "<tr style='background-color: #f8d7da;'>";
            echo "<td>{$id}</td>";
            echo "<td colspan='4'>❌ Not found</td>";
            echo "</tr>";
        }
    }
    
    echo "</table>";
    
    if (!empty($found_working)) {
        echo "<h2>✅ Working Transfer IDs Found:</h2>";
        echo "<ul>";
        foreach ($found_working as $id) {
            echo "<li><strong>Transfer ID: {$id}</strong> - Has items and can be tested</li>";
        }
        echo "</ul>";
        
        echo "<h2>🚀 Quick API Test</h2>";
        echo "<p>Testing with Transfer ID: {$found_working[0]}</p>";
        
        // Get actual transfer items for the first working ID
        $stmt = $pdo->prepare("
            SELECT ti.id as item_id, ti.product_id, ti.qty_requested, 
                   p.name as product_name
            FROM transfer_items ti
            LEFT JOIN vend_products p ON ti.product_id = p.id
            WHERE ti.transfer_id = ? AND ti.deleted_at IS NULL
            LIMIT 3
        ");
        
        $stmt->execute([$found_working[0]]);
        $items = $stmt->fetchAll();
        
        if (!empty($items)) {
            echo "<h3>Available Items for Testing:</h3>";
            echo "<ul>";
            foreach ($items as $item) {
                echo "<li>Item ID: {$item['item_id']}, Product: {$item['product_name']}, Qty: {$item['qty_requested']}</li>";
            }
            echo "</ul>";
            
            echo "<h3>✅ Ready for API Testing!</h3>";
            echo "<p>You can now test with:</p>";
            echo "<pre>";
            echo "Transfer ID: {$found_working[0]}\n";
            echo "Item ID: {$items[0]['item_id']}\n";
            echo "Product ID: {$items[0]['product_id']}\n";
            echo "Qty Requested: {$items[0]['qty_requested']}";
            echo "</pre>";
        }
    } else {
        echo "<h2>❌ No Working Transfer IDs Found</h2>";
        echo "<p>All tested transfer IDs either don't exist or have no items.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<hr>";
echo "<p><strong>Test completed:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>