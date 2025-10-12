<?php
/**
 * Quick Transfer ID Checker
 * 
 * Quickly checks specific transfer IDs mentioned by user
 * 
 * @package CIS\Consignments\Testing
 * @version 1.0.0
 */

// Set BOT_BYPASS_AUTH
$_ENV['BOT_BYPASS_AUTH'] = '1';
$_SERVER['BOT_BYPASS_AUTH'] = '1';
$_GET['bot'] = 'true';

header('Content-Type: application/json');

try {
    // Database connection
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname = 'jcepnzzkmj';
    $username = $_ENV['DB_USER'] ?? 'root';
    $password = $_ENV['DB_PASS'] ?? '';
    
    $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Test specific transfer IDs
    $test_ids = [13218, 13219, 13217, 13220, 13200, 13100, 12000, 10000, 5000, 1000, 100, 50, 10, 1];
    
    $results = [];
    
    foreach ($test_ids as $transfer_id) {
        // Check if transfer exists
        $stmt = $pdo->prepare("
            SELECT 
                t.id,
                t.status,
                t.transfer_mode,
                t.from_outlet_id,
                t.to_outlet_id,
                t.created_at,
                o_from.name AS from_outlet,
                o_to.name AS to_outlet,
                COUNT(ti.id) AS items_count
            FROM transfers t
            LEFT JOIN vend_outlets o_from ON t.from_outlet_id = o_from.id  
            LEFT JOIN vend_outlets o_to ON t.to_outlet_id = o_to.id
            LEFT JOIN transfer_items ti ON t.id = ti.transfer_id
            WHERE t.id = ?
            GROUP BY t.id
        ");
        
        $stmt->execute([$transfer_id]);
        $transfer = $stmt->fetch();
        
        if ($transfer) {
            // Get some transfer items
            $stmt = $pdo->prepare("
                SELECT ti.id, ti.product_id, ti.qty_requested, p.name as product_name
                FROM transfer_items ti
                LEFT JOIN vend_products p ON ti.product_id = p.id
                WHERE ti.transfer_id = ?
                LIMIT 3
            ");
            $stmt->execute([$transfer_id]);
            $items = $stmt->fetchAll();
            
            $results[] = [
                'transfer_id' => $transfer_id,
                'exists' => true,
                'details' => $transfer,
                'items' => $items,
                'testable' => $transfer['items_count'] > 0 && in_array($transfer['status'], ['PACKED', 'IN_TRANSIT', 'PARTIAL_RECEIVED'])
            ];
            
            // If we found a good one, also test the API
            if ($transfer['items_count'] > 0 && count($results) <= 3) {
                $api_result = testReceiveAutosave($transfer_id, $transfer['transfer_mode'], $items);
                $results[count($results) - 1]['api_test'] = $api_result;
            }
        } else {
            $results[] = [
                'transfer_id' => $transfer_id,
                'exists' => false,
                'testable' => false
            ];
        }
        
        // Stop after finding 5 existing transfers
        $existing_count = array_reduce($results, function($count, $r) { return $count + ($r['exists'] ? 1 : 0); }, 0);
        if ($existing_count >= 5) break;
    }
    
    echo json_encode([
        'success' => true,
        'database' => 'jcepnzzkmj',
        'bot_bypass' => $_ENV['BOT_BYPASS_AUTH'] ?? 'not set',
        'timestamp' => date('Y-m-d H:i:s'),
        'results' => $results
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}

function testReceiveAutosave($transfer_id, $transfer_mode, $items) {
    if (empty($items)) {
        return ['success' => false, 'message' => 'No items to test with'];
    }
    
    $payload = [
        'transfer_id' => $transfer_id,
        'transfer_mode' => $transfer_mode,
        'items' => [
            [
                'item_id' => (int)$items[0]['id'],
                'product_id' => (int)$items[0]['product_id'],
                'qty_requested' => (float)($items[0]['qty_requested'] ?? 1),
                'qty_received' => (float)($items[0]['qty_requested'] ?? 1) * 0.8,
                'weight_grams' => 100.0
            ]
        ],
        'totals' => [
            'total_requested' => (float)($items[0]['qty_requested'] ?? 1),
            'total_received' => (float)($items[0]['qty_requested'] ?? 1) * 0.8,
            'weight_grams' => 80.0
        ],
        'receiver_name' => 'Quick Test',
        'delivery_notes' => 'API verification test'
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://staff.vapeshed.co.nz/modules/consignments/api/receive_autosave.php?bot=true',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Requested-With: XMLHttpRequest'
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        return ['success' => false, 'message' => "cURL error: $curl_error"];
    }
    
    $response_data = json_decode($response, true);
    
    return [
        'success' => $http_code === 200 && isset($response_data['success']) && $response_data['success'],
        'http_code' => $http_code,
        'response' => $response_data,
        'message' => $response_data['message'] ?? "HTTP $http_code"
    ];
}
?>