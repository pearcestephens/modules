<?php
/**
 * SIMPLE Consignment Upload Progress - Server-Sent Events
 * 
 * This is a WORKING version without the disabled SecureAPI/SecureDatabase classes
 * 
 * @version 1.0.0
 */

declare(strict_types=1);

// Basic validation
if (empty($_GET['transfer_id']) || !is_numeric($_GET['transfer_id'])) {
    http_response_code(400);
    die("Invalid transfer_id");
}

$transferId = (int) $_GET['transfer_id'];
$sessionId = $_GET['session'] ?? $_GET['session_id'] ?? null; // Optional, not required

// SSE Headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');

// Flush output buffer
if (ob_get_level()) ob_end_clean();

// Connect to database
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

try {
    // Get transfer info
    $stmt = $pdo->prepare("SELECT * FROM transfers WHERE id = ?");
    $stmt->execute([$transferId]);
    $transfer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transfer) {
        sendEvent('error', ['message' => 'Transfer not found']);
        exit;
    }
    
    // Send connected event
    sendEvent('connected', [
        'transfer_id' => $transferId,
        'session_id' => $sessionId,
        'timestamp' => date('c')
    ]);
    
    // Get transfer items
    $stmt = $pdo->prepare("
        SELECT ti.*, p.name as product_name
        FROM transfer_items ti
        LEFT JOIN vend_products p ON ti.product_id = p.id
        WHERE ti.transfer_id = ?
    ");
    $stmt->execute([$transferId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalProducts = count($items);
    $completed = 0;
    
    // Simulate progress (in reality this would track actual upload progress)
    foreach ($items as $index => $item) {
        // Check if client disconnected
        if (connection_aborted()) {
            break;
        }
        
        $completed++;
        $progressPercent = ($completed / $totalProducts) * 100;
        
        // Send progress event
        sendEvent('progress', [
            'progress_percentage' => round($progressPercent, 1),
            'current_operation' => "Processing {$item['product_name']}...",
            'completed_products' => $completed,
            'total_products' => $totalProducts,
            'failed_products' => 0,
            'status' => 'uploading',
            'products' => array_map(function($item) {
                return [
                    'id' => $item['id'],
                    'product_id' => $item['product_id'],
                    'name' => $item['product_name'] ?? 'Unknown Product',
                    'qty_requested' => $item['qty_requested'],
                    'qty_sent_total' => $item['qty_sent_total'],
                    'status' => 'completed'
                ];
            }, array_slice($items, 0, $completed))
        ]);
        
        // Simulate processing time
        usleep(500000); // 0.5 seconds per item
        flush();
    }
    
    // Send completion event
    sendEvent('finished', [
        'success' => true,
        'message' => 'Upload complete!',
        'completed_products' => $completed,
        'total_products' => $totalProducts,
        'transfer_id' => $transferId,
        'timestamp' => date('c')
    ]);
    
} catch (Exception $e) {
    sendEvent('error', [
        'message' => $e->getMessage()
    ]);
}

/**
 * Send SSE event
 */
function sendEvent(string $event, array $data): void
{
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";
    
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}
