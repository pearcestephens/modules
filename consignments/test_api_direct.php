<?php
/**
 * Direct API Test Runner
 * 
 * Run this in browser: /modules/consignments/test_api_direct.php?bot=true
 * Tests API endpoints with bot bypass enabled
 */

// Enable bot bypass
$_ENV['BOT_BYPASS_AUTH'] = '1';
$_SERVER['BOT_BYPASS_AUTH'] = '1';
$_GET['bot'] = 'true';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Direct API Testing</title>\n";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.test-result { margin: 10px 0; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff; }
.success { background: #d4edda; border-left-color: #28a745; color: #155724; }
.error { background: #f8d7da; border-left-color: #dc3545; color: #721c24; }
.warning { background: #fff3cd; border-left-color: #ffc107; color: #856404; }
.info { background: #d1ecf1; border-left-color: #17a2b8; color: #0c5460; }
pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; max-height: 300px; }
.endpoint { font-weight: bold; color: #007bff; }
.stats { display: flex; gap: 20px; margin: 20px 0; }
.stat { background: #007bff; color: white; padding: 15px; border-radius: 5px; text-align: center; flex: 1; }
</style>\n</head>\n<body>\n";

echo "<div class='container'>\n";
echo "<h1>🧪 Direct API Endpoint Testing</h1>\n";
echo "<p><strong>Started:</strong> " . date('Y-m-d H:i:s') . "</p>\n";
echo "<p><strong>Bot Bypass:</strong> " . ($_ENV['BOT_BYPASS_AUTH'] ?? 'NOT SET') . "</p>\n";

// Test data
$test_transfer_ids = [13219, 13218, 13217, 123, 100, 50, 10, 1];
$endpoints_to_test = [
    'receive_autosave.php',
    'receive_submit.php', 
    'pack_autosave.php',
    'pack_submit.php',
    'add_line.php',
    'remove_line.php',
    'update_line_qty.php',
    'pack_lock.php',
    'search_products.php'
];

$total_tests = 0;
$successful_tests = 0;
$working_transfer_ids = [];

echo "<h2>🔍 Testing Transfer IDs for Valid Data</h2>\n";

// First, find working transfer IDs
foreach ($test_transfer_ids as $transfer_id) {
    $result = testTransferExists($transfer_id);
    
    if ($result['exists']) {
        echo "<div class='test-result success'>✅ Transfer ID {$transfer_id}: {$result['details']}</div>\n";
        $working_transfer_ids[] = $transfer_id;
    } else {
        echo "<div class='test-result error'>❌ Transfer ID {$transfer_id}: Not found</div>\n";
    }
}

if (empty($working_transfer_ids)) {
    echo "<div class='test-result error'>❌ No valid transfer IDs found! Cannot test API endpoints.</div>\n";
} else {
    echo "<h2>🚀 Testing API Endpoints</h2>\n";
    
    $working_id = $working_transfer_ids[0];
    echo "<p><strong>Using Transfer ID:</strong> {$working_id}</p>\n";
    
    foreach ($endpoints_to_test as $endpoint) {
        echo "<div class='test-result info'>\n";
        echo "<div class='endpoint'>Testing: {$endpoint}</div>\n";
        
        $result = testApiEndpoint($endpoint, $working_id);
        $total_tests++;
        
        if ($result['success']) {
            $successful_tests++;
            echo "<div style='color: #28a745; font-weight: bold;'>✅ SUCCESS</div>\n";
            echo "<div>Response: " . htmlspecialchars(substr($result['response'], 0, 200)) . "...</div>\n";
        } else {
            echo "<div style='color: #dc3545; font-weight: bold;'>❌ FAILED</div>\n";
            echo "<div>Error: " . htmlspecialchars($result['error']) . "</div>\n";
            if (!empty($result['response'])) {
                echo "<details><summary>Full Response</summary>\n";
                echo "<pre>" . htmlspecialchars($result['response']) . "</pre>\n";
                echo "</details>\n";
            }
        }
        
        echo "</div>\n";
    }
}

// Summary statistics
echo "<div class='stats'>\n";
echo "<div class='stat'>\n";
echo "<div style='font-size: 2em;'>{$total_tests}</div>\n";
echo "<div>Total Tests</div>\n";
echo "</div>\n";
echo "<div class='stat'>\n";
echo "<div style='font-size: 2em;'>{$successful_tests}</div>\n";
echo "<div>Successful</div>\n";
echo "</div>\n";
echo "<div class='stat'>\n";
echo "<div style='font-size: 2em;'>" . ($total_tests - $successful_tests) . "</div>\n";
echo "<div>Failed</div>\n";
echo "</div>\n";
echo "<div class='stat'>\n";
echo "<div style='font-size: 2em;'>" . ($total_tests > 0 ? round(($successful_tests / $total_tests) * 100, 1) : 0) . "%</div>\n";
echo "<div>Success Rate</div>\n";
echo "</div>\n";
echo "</div>\n";

echo "<p><strong>Completed:</strong> " . date('Y-m-d H:i:s') . "</p>\n";
echo "</div>\n</body>\n</html>\n";

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function testTransferExists($transfer_id) {
    try {
        // Simple database connection
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $dbname = $_ENV['DB_NAME'] ?? 'jcepnzzkmj';
        $username = $_ENV['DB_USER'] ?? 'root';
        $password = $_ENV['DB_PASS'] ?? '';
        
        $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $username, $password);
        
        $stmt = $pdo->prepare("
            SELECT t.id, t.transfer_mode, t.status, 
                   COUNT(ti.id) as item_count,
                   o_from.name as from_outlet,
                   o_to.name as to_outlet
            FROM transfers t
            LEFT JOIN transfer_items ti ON t.id = ti.transfer_id
            LEFT JOIN vend_outlets o_from ON t.from_outlet_id = o_from.id
            LEFT JOIN vend_outlets o_to ON t.to_outlet_id = o_to.id
            WHERE t.id = ?
            GROUP BY t.id
        ");
        
        $stmt->execute([$transfer_id]);
        $transfer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($transfer) {
            $details = "Mode: {$transfer['transfer_mode']}, Status: {$transfer['status']}, Items: {$transfer['item_count']}, From: {$transfer['from_outlet']}, To: {$transfer['to_outlet']}";
            return ['exists' => true, 'details' => $details, 'data' => $transfer];
        }
        
        return ['exists' => false];
    } catch (Exception $e) {
        return ['exists' => false, 'error' => $e->getMessage()];
    }
}

function testApiEndpoint($endpoint, $transfer_id) {
    try {
        // Build URL
        $base_url = 'https://' . $_SERVER['HTTP_HOST'];
        $url = $base_url . '/modules/consignments/api/' . $endpoint . '?bot=true';
        
        // Build payload based on endpoint
        $payload = buildTestPayload($endpoint, $transfer_id);
        
        if (!$payload) {
            return ['success' => false, 'error' => 'Could not build test payload'];
        }
        
        // Make cURL request
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Requested-With: XMLHttpRequest',
                'User-Agent: CIS-Direct-Test/1.0'
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            return ['success' => false, 'error' => "cURL error: {$curl_error}"];
        }
        
        if ($http_code === 200) {
            $response_data = json_decode($response, true);
            if ($response_data && isset($response_data['success']) && $response_data['success']) {
                return ['success' => true, 'response' => $response];
            } else {
                return ['success' => false, 'error' => 'HTTP 200 but error response', 'response' => $response];
            }
        } else {
            return ['success' => false, 'error' => "HTTP {$http_code}", 'response' => $response];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function buildTestPayload($endpoint, $transfer_id) {
    $base_payload = [
        'transfer_id' => $transfer_id,
        'transfer_mode' => 'GENERAL',
        'timestamp' => date('Y-m-d H:i:s'),
        'bot_test' => true
    ];
    
    switch ($endpoint) {
        case 'receive_autosave.php':
        case 'receive_submit.php':
            return array_merge($base_payload, [
                'items' => [
                    [
                        'item_id' => 1,
                        'product_id' => 1,
                        'qty_requested' => 1.0,
                        'qty_received' => 1.0,
                        'weight_grams' => 100.0
                    ]
                ],
                'totals' => [
                    'total_requested' => 1.0,
                    'total_received' => 1.0,
                    'weight_grams' => 100.0
                ],
                'receiver_name' => 'Direct Test Bot',
                'delivery_notes' => 'Direct API test'
            ]);
            
        case 'pack_autosave.php':
        case 'pack_submit.php':
            return array_merge($base_payload, [
                'items' => [
                    [
                        'item_id' => 1,
                        'product_id' => 1,
                        'qty_requested' => 1.0,
                        'qty_packed' => 1.0
                    ]
                ]
            ]);
            
        case 'add_line.php':
            return array_merge($base_payload, [
                'product_id' => 1,
                'qty' => 1
            ]);
            
        case 'remove_line.php':
            return array_merge($base_payload, [
                'item_id' => 1
            ]);
            
        case 'update_line_qty.php':
            return array_merge($base_payload, [
                'item_id' => 1,
                'qty' => 2
            ]);
            
        case 'pack_lock.php':
            return array_merge($base_payload, [
                'action' => 'lock'
            ]);
            
        case 'search_products.php':
            return [
                'query' => 'test',
                'limit' => 10,
                'bot_test' => true
            ];
            
        default:
            return $base_payload;
    }
}
?>