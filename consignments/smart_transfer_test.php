<?php
/**
 * Smart Transfer Testing Script
 * 
 * Uses actual database schema to find valid transfer IDs and test endpoints
 * 
 * @package CIS\Consignments\Testing
 * @version 2.0.0
 * @created 2025-10-12
 */

// Set BOT_BYPASS_AUTH for authentication bypass
$_ENV['BOT_BYPASS_AUTH'] = '1';
$_SERVER['BOT_BYPASS_AUTH'] = '1';
$_GET['bot'] = 'true';

require_once __DIR__ . '/lib/Db.php'; // Independent database connection

// Initialize database
$db = new Db();

// Configure test environment
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300); // 5 minutes

echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Smart Transfer Testing with Real Database</title>\n";
echo "<style>
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 20px; background: #f8f9fa; }
.container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.test-section { border: 1px solid #ddd; margin: 20px 0; padding: 15px; border-radius: 5px; }
.success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
.error { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
.warning { background-color: #fff3cd; border-color: #ffeaa7; color: #856404; }
.info { background-color: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 12px; }
.endpoint-test { margin: 10px 0; padding: 10px; border-left: 4px solid #007bff; background: #f8f9fa; }
.db-info { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0; }
.transfer-card { display: inline-block; margin: 5px; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 4px; min-width: 200px; }
h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
h2 { color: #34495e; }
.metric { display: inline-block; margin: 0 20px 10px 0; }
.metric strong { color: #2980b9; }
</style>\n</head>\n<body>\n<div class='container'>\n";

echo "<h1>🧠 Smart Transfer Testing with Real Database Schema</h1>\n";
echo "<p><strong>Started:</strong> " . date('Y-m-d H:i:s') . "</p>\n";
echo "<p><strong>Database:</strong> jcepnzzkmj ✅</p>\n";
echo "<p><strong>BOT_BYPASS_AUTH:</strong> " . ($_ENV['BOT_BYPASS_AUTH'] ?? 'NOT SET') . "</p>\n";

$results = [];
$total_tests = 0;
$passed_tests = 0;

// ============================================================================
// DATABASE DISCOVERY
// ============================================================================

echo "<div class='test-section info'>\n";
echo "<h2>🔍 Database Discovery</h2>\n";

try {
    $pdo = getDatabase();
    
    // Get transfer counts by status
    echo "<div class='db-info'>\n";
    echo "<h3>Transfer Statistics</h3>\n";
    
    $stmt = $pdo->query("
        SELECT 
            status,
            transfer_mode,
            COUNT(*) as count,
            MIN(id) as min_id,
            MAX(id) as max_id
        FROM transfers 
        GROUP BY status, transfer_mode 
        ORDER BY count DESC
    ");
    $transfer_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($transfer_stats)) {
        echo "<div class='error'>❌ No transfers found in database!</div>\n";
        echo "</div>\n</div>\n</div>\n</body>\n</html>\n";
        exit;
    }
    
    echo "<table border='1' cellpadding='5' cellspacing='0' style='width:100%; border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Status</th><th>Mode</th><th>Count</th><th>ID Range</th></tr>\n";
    
    $total_transfers = 0;
    foreach ($transfer_stats as $stat) {
        echo "<tr>\n";
        echo "<td><strong>{$stat['status']}</strong></td>\n";
        echo "<td>{$stat['transfer_mode']}</td>\n";
        echo "<td>{$stat['count']}</td>\n";
        echo "<td>{$stat['min_id']} - {$stat['max_id']}</td>\n";
        echo "</tr>\n";
        $total_transfers += $stat['count'];
    }
    echo "</table>\n";
    
    echo "<div class='metric'><strong>Total Transfers:</strong> {$total_transfers}</div>\n";
    echo "</div>\n";
    
    // Find the best transfer IDs to test with
    echo "<h3>🎯 Finding Optimal Test Transfer IDs</h3>\n";
    
    // Get transfers with items in different statuses
    $stmt = $pdo->query("
        SELECT 
            t.id,
            t.status,
            t.transfer_mode,
            t.created_at,
            t.from_outlet_id,
            t.to_outlet_id,
            o_from.name as from_outlet,
            o_to.name as to_outlet,
            COUNT(ti.id) as items_count,
            GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') as sample_products
        FROM transfers t
        LEFT JOIN transfer_items ti ON t.id = ti.transfer_id
        LEFT JOIN vend_products p ON ti.product_id = p.id
        LEFT JOIN vend_outlets o_from ON t.from_outlet_id = o_from.id
        LEFT JOIN vend_outlets o_to ON t.to_outlet_id = o_to.id
        WHERE t.status IN ('PACKED', 'IN_TRANSIT', 'PARTIAL_RECEIVED', 'PENDING')
        GROUP BY t.id
        HAVING items_count > 0
        ORDER BY 
            CASE t.status 
                WHEN 'PACKED' THEN 1 
                WHEN 'IN_TRANSIT' THEN 2 
                WHEN 'PARTIAL_RECEIVED' THEN 3 
                ELSE 4 
            END,
            items_count DESC,
            t.id DESC
        LIMIT 10
    ");
    $test_transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($test_transfers)) {
        echo "<div class='warning'>⚠️ No transfers with items found. Looking for any transfers...</div>\n";
        
        // Fallback: get any transfers
        $stmt = $pdo->query("
            SELECT 
                t.id,
                t.status,
                t.transfer_mode,
                t.created_at,
                o_from.name as from_outlet,
                o_to.name as to_outlet
            FROM transfers t
            LEFT JOIN vend_outlets o_from ON t.from_outlet_id = o_from.id
            LEFT JOIN vend_outlets o_to ON t.to_outlet_id = o_to.id
            ORDER BY t.id DESC
            LIMIT 5
        ");
        $test_transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if (!empty($test_transfers)) {
        echo "<div style='display: flex; flex-wrap: wrap;'>\n";
        foreach ($test_transfers as $transfer) {
            echo "<div class='transfer-card'>\n";
            echo "<strong>ID: {$transfer['id']}</strong><br>\n";
            echo "Status: <span style='color: #e67e22;'>{$transfer['status']}</span><br>\n";
            echo "Mode: {$transfer['transfer_mode']}<br>\n";
            if (isset($transfer['items_count'])) {
                echo "Items: {$transfer['items_count']}<br>\n";
            }
            echo "From: {$transfer['from_outlet']}<br>\n";
            echo "To: {$transfer['to_outlet']}<br>\n";
            echo "<small>{$transfer['created_at']}</small>\n";
            echo "</div>\n";
        }
        echo "</div>\n";
    }
    
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Database Error: " . htmlspecialchars($e->getMessage()) . "</div>\n";
    echo "</div>\n</div>\n</body>\n</html>\n";
    exit;
}

// ============================================================================
// ENDPOINT TESTING
// ============================================================================

$endpoints = [
    'receive_autosave' => [
        'path' => '/modules/consignments/api/receive_autosave.php',
        'method' => 'POST',
        'type' => 'receive',
        'description' => 'Auto-save receive progress'
    ],
    'receive_submit' => [
        'path' => '/modules/consignments/api/receive_submit.php',
        'method' => 'POST',
        'type' => 'receive',
        'description' => 'Submit completed receive'
    ],
    'add_line' => [
        'path' => '/modules/consignments/api/add_line.php',
        'method' => 'POST',
        'type' => 'pack',
        'description' => 'Add product line to transfer'
    ],
    'remove_line' => [
        'path' => '/modules/consignments/api/remove_line.php',
        'method' => 'POST',
        'type' => 'pack',
        'description' => 'Remove product line from transfer'
    ],
    'update_line_qty' => [
        'path' => '/modules/consignments/api/update_line_qty.php',
        'method' => 'POST',
        'type' => 'pack',
        'description' => 'Update line quantity'
    ],
    'pack_lock' => [
        'path' => '/modules/consignments/api/pack_lock.php',
        'method' => 'POST',
        'type' => 'pack',
        'description' => 'Lock/unlock transfer for packing'
    ],
    'search_products' => [
        'path' => '/modules/consignments/api/search_products.php',
        'method' => 'POST',
        'type' => 'utility',
        'description' => 'Search for products'
    ]
];

foreach ($test_transfers as $transfer) {
    echo "<div class='test-section info'>\n";
    echo "<h2>🎯 Testing Transfer ID: {$transfer['id']}</h2>\n";
    
    echo "<div class='info'>\n";
    echo "<strong>Transfer Details:</strong><br>\n";
    echo "ID: {$transfer['id']}<br>\n";
    echo "Status: {$transfer['status']}<br>\n";
    echo "Mode: {$transfer['transfer_mode']}<br>\n";
    echo "From: {$transfer['from_outlet']}<br>\n";
    echo "To: {$transfer['to_outlet']}<br>\n";
    echo "</div>\n";
    
    // Get actual transfer items for this transfer
    $transfer_items = getTransferItemsForTesting($transfer['id']);
    
    echo "<p><strong>Items found:</strong> " . count($transfer_items) . "</p>\n";
    
    if (empty($transfer_items)) {
        echo "<div class='warning'>⚠️ No transfer items found. Creating dummy item for testing...</div>\n";
        $transfer_items = [[
            'id' => 1,
            'product_id' => 1,
            'qty_requested' => 1,
            'product_name' => 'Test Product'
        ]];
    }
    
    // Test each endpoint
    foreach ($endpoints as $endpoint_name => $endpoint_config) {
        echo "<div class='endpoint-test'>\n";
        echo "<h4>🔧 {$endpoint_config['description']} ({$endpoint_name})</h4>\n";
        
        $result = testEndpointWithRealData($endpoint_name, $endpoint_config, $transfer, $transfer_items);
        
        $total_tests++;
        
        if ($result['success']) {
            $passed_tests++;
            echo "<div class='success'>✅ SUCCESS: {$result['message']}</div>\n";
        } else {
            echo "<div class='error'>❌ FAILED: {$result['message']}</div>\n";
        }
        
        if (!empty($result['response'])) {
            echo "<details><summary>📋 Response Details</summary>\n";
            echo "<pre>" . htmlspecialchars(json_encode($result['response'], JSON_PRETTY_PRINT)) . "</pre>\n";
            echo "</details>\n";
        }
        
        if (!empty($result['error'])) {
            echo "<details><summary>⚠️ Error Details</summary>\n";
            echo "<pre>" . htmlspecialchars($result['error']) . "</pre>\n";
            echo "</details>\n";
        }
        
        echo "</div>\n";
        
        $results[] = [
            'transfer_id' => $transfer['id'],
            'endpoint' => $endpoint_name,
            'success' => $result['success'],
            'message' => $result['message'],
            'response' => $result['response'] ?? null,
            'error' => $result['error'] ?? null
        ];
        
        // Small delay between tests
        usleep(200000); // 200ms
    }
    
    echo "</div>\n";
    
    // Test only the first 3 transfers to avoid timeout
    if (count($results) >= 18) { // 3 transfers × 6 endpoints
        echo "<div class='warning'>⚠️ Stopping after 3 transfers to avoid timeout. Results so far:</div>\n";
        break;
    }
}

// ============================================================================
// SUMMARY
// ============================================================================

echo "<div class='test-section'>\n";
echo "<h2>📊 Test Summary</h2>\n";

$success_rate = $total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 2) : 0;

echo "<div class='metric'><strong>Total Tests:</strong> {$total_tests}</div>\n";
echo "<div class='metric'><strong>Passed:</strong> {$passed_tests}</div>\n";
echo "<div class='metric'><strong>Failed:</strong> " . ($total_tests - $passed_tests) . "</div>\n";
echo "<div class='metric'><strong>Success Rate:</strong> {$success_rate}%</div>\n";

// Group results by endpoint
$endpoint_results = [];
foreach ($results as $result) {
    if (!isset($endpoint_results[$result['endpoint']])) {
        $endpoint_results[$result['endpoint']] = ['total' => 0, 'passed' => 0];
    }
    $endpoint_results[$result['endpoint']]['total']++;
    if ($result['success']) {
        $endpoint_results[$result['endpoint']]['passed']++;
    }
}

echo "<h3>📈 Endpoint Performance</h3>\n";
echo "<table border='1' cellpadding='5' cellspacing='0' style='width:100%; border-collapse: collapse;'>\n";
echo "<tr><th>Endpoint</th><th>Passed</th><th>Total</th><th>Success Rate</th></tr>\n";

foreach ($endpoint_results as $endpoint => $stats) {
    $rate = round(($stats['passed'] / $stats['total']) * 100, 1);
    $class = $rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'error');
    
    echo "<tr class='{$class}'>\n";
    echo "<td><strong>{$endpoint}</strong></td>\n";
    echo "<td>{$stats['passed']}</td>\n";
    echo "<td>{$stats['total']}</td>\n";
    echo "<td>{$rate}%</td>\n";
    echo "</tr>\n";
}

echo "</table>\n";
echo "</div>\n";

echo "<p><strong>Completed:</strong> " . date('Y-m-d H:i:s') . "</p>\n";
echo "</div>\n</body>\n</html>\n";

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function getDatabase() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $dbname = 'jcepnzzkmj'; // Use the actual database name
            $username = $_ENV['DB_USER'] ?? 'root';
            $password = $_ENV['DB_PASS'] ?? '';
            
            $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
            
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 30
            ]);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

function getTransferItemsForTesting($transfer_id) {
    try {
        $pdo = getDatabase();
        $stmt = $pdo->prepare("
            SELECT 
                ti.id,
                ti.product_id,
                ti.qty_requested,
                ti.qty_received,
                ti.qty_sent_total,
                p.name AS product_name,
                p.sku,
                p.avg_weight_grams
            FROM transfer_items ti
            LEFT JOIN vend_products p ON ti.product_id = p.id
            WHERE ti.transfer_id = ?
            ORDER BY ti.id
            LIMIT 10
        ");
        $stmt->execute([$transfer_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function testEndpointWithRealData($endpoint_name, $config, $transfer, $transfer_items) {
    $base_url = 'https://staff.vapeshed.co.nz';
    $url = $base_url . $config['path'] . '?bot=true';
    
    // Check if endpoint file exists by making a HEAD request
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 404) {
        return [
            'success' => false,
            'message' => "Endpoint file does not exist: {$config['path']}"
        ];
    }
    
    // Build test payload based on endpoint type and real data
    $payload = buildRealTestPayload($endpoint_name, $config, $transfer, $transfer_items);
    
    if (!$payload) {
        return [
            'success' => false,
            'message' => "Could not build test payload for {$endpoint_name}"
        ];
    }
    
    // Make the actual request
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Requested-With: XMLHttpRequest',
            'User-Agent: CIS-Smart-Test-Bot/2.0'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_VERBOSE => false
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        return [
            'success' => false,
            'message' => "cURL error: {$curl_error}",
            'error' => $curl_error
        ];
    }
    
    // Parse JSON response
    $response_data = json_decode($response, true);
    
    if ($http_code === 200) {
        if ($response_data && isset($response_data['success']) && $response_data['success']) {
            return [
                'success' => true,
                'message' => "HTTP 200 - API success",
                'response' => $response_data
            ];
        } else {
            $error_msg = $response_data['message'] ?? $response_data['error'] ?? 'Unknown API error';
            return [
                'success' => false,
                'message' => "HTTP 200 but API error: {$error_msg}",
                'response' => $response_data,
                'error' => $error_msg
            ];
        }
    } else {
        $error_msg = $response_data['message'] ?? "HTTP {$http_code} response";
        return [
            'success' => false,
            'message' => $error_msg,
            'response' => $response_data,
            'error' => "Non-200 response code"
        ];
    }
}

function buildRealTestPayload($endpoint_name, $config, $transfer, $transfer_items) {
    $base_payload = [
        'transfer_id' => (int)$transfer['id'],
        'transfer_mode' => $transfer['transfer_mode'],
        'timestamp' => date('Y-m-d H:i:s'),
        'bot_test' => true
    ];
    
    switch ($endpoint_name) {
        case 'receive_autosave':
        case 'receive_submit':
            // Build receive payload with real transfer items
            $items = [];
            $total_requested = 0;
            $total_received = 0;
            
            foreach ($transfer_items as $item) {
                $qty_requested = (float)($item['qty_requested'] ?? 1);
                $qty_received = min($qty_requested, $qty_requested * 0.9); // Simulate 90% received
                
                $items[] = [
                    'item_id' => (int)$item['id'],
                    'product_id' => (int)$item['product_id'],
                    'qty_requested' => $qty_requested,
                    'qty_received' => $qty_received,
                    'weight_grams' => (float)($item['avg_weight_grams'] ?? 100)
                ];
                
                $total_requested += $qty_requested;
                $total_received += $qty_received;
            }
            
            $base_payload['items'] = $items;
            $base_payload['totals'] = [
                'total_requested' => $total_requested,
                'total_received' => $total_received,
                'weight_grams' => $total_received * 100
            ];
            $base_payload['receiver_name'] = 'Smart Test Bot';
            $base_payload['delivery_notes'] = 'Automated smart test with real data';
            break;
            
        case 'add_line':
            $base_payload['product_id'] = $transfer_items[0]['product_id'] ?? 1;
            $base_payload['qty'] = 1;
            break;
            
        case 'remove_line':
            $base_payload['item_id'] = $transfer_items[0]['id'] ?? 1;
            break;
            
        case 'update_line_qty':
            $base_payload['item_id'] = $transfer_items[0]['id'] ?? 1;
            $base_payload['qty'] = 2;
            break;
            
        case 'pack_lock':
            $base_payload['action'] = 'lock';
            break;
            
        case 'search_products':
            $base_payload['query'] = 'vape';
            $base_payload['limit'] = 10;
            unset($base_payload['transfer_id']); // Not needed for search
            break;
    }
    
    return $base_payload;
}
?>