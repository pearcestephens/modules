<?php
/**
 * Unified Consignments API Handler
 *
 * Centralized API for ALL transfer operations:
 * - Stock Transfers
 * - Purchase Orders
 * - Supplier Returns
 * - Outlet Returns
 * - Adjustments
 *
 * Extracted from TransferManager backend.php and generalized.
 *
 * @package CIS\Consignments\API
 * @version 2.0.0
 */

declare(strict_types=1);

// Early JSON setup and error handling
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
ob_start();

// JSON error handlers
set_exception_handler(function(Throwable $e){
    http_response_code(500);
    if (ob_get_level() > 0) { @ob_clean(); }
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'SERVER_ERROR',
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ],
        'meta' => ['timestamp' => date('c')]
    ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
});

set_error_handler(function($severity, $message, $file, $line){
    http_response_code(500);
    if (ob_get_level() > 0) { @ob_clean(); }
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'PHP_ERROR',
            'message' => $message,
            'file' => basename($file),
            'line' => $line
        ],
        'meta' => ['timestamp' => date('c')]
    ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
});

// Load bootstrap
require_once __DIR__ . '/../bootstrap.php';

// Discard any bootstrap output
if (ob_get_level() > 0) { @ob_clean(); }
ob_start();

// Authentication check
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'UNAUTHORIZED',
            'message' => 'Authentication required'
        ],
        'meta' => ['timestamp' => date('c')]
    ]);
    exit;
}

// Load services
use CIS\Consignments\Services\TransferManagerService;
use CIS\Consignments\Services\ConsignmentHelpers;

// Initialize services
$transferService = new TransferManagerService($db);
$helpers = new ConsignmentHelpers($db);

// Get request data
$method = $_SERVER['REQUEST_METHOD'];
$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '{}', true) ?: [];
$action = $payload['action'] ?? $_GET['action'] ?? '';

// Response helpers
function sendSuccess(array $data = [], array $meta = []): void {
    $response = [
        'success' => true,
        'data' => $data,
        'meta' => array_merge([
            'timestamp' => date('c'),
            'execution_time' => round(microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)), 3) . 's'
        ], $meta)
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function sendError(string $code, string $message, array $details = [], int $httpCode = 400): void {
    http_response_code($httpCode);
    $response = [
        'success' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
            'details' => $details
        ],
        'meta' => ['timestamp' => date('c')]
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Route actions
try {
    switch ($action) {
        
        // ===== INITIALIZATION =====
        case 'init':
            $data = $transferService->init();
            sendSuccess($data);
            break;

        // ===== SYNC CONTROL =====
        case 'toggle_sync':
            $enabled = (bool)($payload['enabled'] ?? false);
            $transferService->setSyncEnabled($enabled);
            sendSuccess(['sync_enabled' => $enabled]);
            break;

        case 'verify_sync':
            sendSuccess(['sync_enabled' => $transferService->getSyncEnabled()]);
            break;

        // ===== TRANSFER LISTING =====
        case 'list_transfers':
            $filters = [
                'type' => $payload['type'] ?? null,
                'state' => $payload['state'] ?? null,
                'outlet' => $payload['outlet'] ?? null,
                'q' => $payload['q'] ?? null
            ];
            $page = (int)($payload['page'] ?? 1);
            $perPage = (int)($payload['perPage'] ?? 50);
            
            $result = $transferService->listTransfers($filters, $page, $perPage);
            sendSuccess($result);
            break;

        // ===== TRANSFER DETAIL =====
        case 'get_transfer_detail':
            $id = (int)($payload['id'] ?? 0);
            if (!$id) {
                sendError('INVALID_ID', 'Transfer ID required');
            }
            
            $detail = $transferService->getTransferDetail($id);
            sendSuccess($detail);
            break;

        // ===== PRODUCT SEARCH =====
        case 'search_products':
        case 'product_search':
            $query = $payload['q'] ?? '';
            $limit = (int)($payload['limit'] ?? 20);
            
            if (strlen($query) < 2) {
                sendSuccess(['products' => []]);
            }
            
            $products = $transferService->searchProducts($query, $limit);
            sendSuccess(['products' => $products]);
            break;

        // ===== CREATE TRANSFER =====
        case 'create_transfer':
            $requiredFields = ['transfer_category'];
            foreach ($requiredFields as $field) {
                if (empty($payload[$field])) {
                    sendError('MISSING_FIELD', "Required field: {$field}");
                }
            }
            
            $result = $transferService->createTransfer($payload);
            sendSuccess($result);
            break;

        // ===== ITEM MANAGEMENT =====
        case 'add_transfer_item':
            $id = (int)($payload['id'] ?? 0);
            $productId = $payload['product_id'] ?? '';
            $qty = (int)($payload['qty'] ?? 0);
            
            if (!$id || !$productId || $qty <= 0) {
                sendError('INVALID_PARAMS', 'Transfer ID, product ID, and positive quantity required');
            }
            
            $result = $transferService->addTransferItem($id, $productId, $qty);
            sendSuccess($result);
            break;

        case 'update_transfer_item':
            $id = (int)($payload['id'] ?? 0);
            $itemId = (int)($payload['item_id'] ?? 0);
            $qty = (int)($payload['qty_requested'] ?? $payload['quantity'] ?? 0);
            
            if (!$id || !$itemId) {
                sendError('INVALID_PARAMS', 'Transfer ID and item ID required');
            }
            
            $result = $transferService->updateTransferItem($id, $itemId, $qty);
            sendSuccess($result);
            break;

        case 'update_transfer_item_qty':
            $id = (int)($payload['id'] ?? 0);
            $itemId = (int)($payload['item_id'] ?? 0);
            $field = $payload['field'] ?? 'req'; // 'req', 'sent', 'rec'
            $value = (int)($payload['value'] ?? 0);
            
            if (!$id || !$itemId) {
                sendError('INVALID_PARAMS', 'Transfer ID and item ID required');
            }
            
            // Map field to column
            $columnMap = [
                'req' => 'quantity',
                'sent' => 'quantity_sent',
                'rec' => 'quantity_received'
            ];
            
            if (!isset($columnMap[$field])) {
                sendError('INVALID_FIELD', 'Field must be: req, sent, or rec');
            }
            
            $column = $columnMap[$field];
            $stmt = $db->prepare("
                UPDATE vend_consignment_line_items 
                SET {$column} = ?, updated_at = NOW()
                WHERE id = ? AND transfer_id = ?
            ");
            $stmt->execute([$value, $itemId, $id]);
            
            $helpers->logEvent($id, 'item_qty_updated', [
                'item_id' => $itemId,
                'field' => $field,
                'value' => $value
            ]);
            
            $result = $transferService->getTransferDetail($id);
            sendSuccess($result);
            break;

        case 'remove_transfer_item':
            $itemId = (int)($payload['item_id'] ?? 0);
            
            if (!$itemId) {
                sendError('INVALID_PARAMS', 'Item ID required');
            }
            
            $result = $transferService->removeTransferItem($itemId);
            sendSuccess($result);
            break;

        // ===== BULK OPERATIONS =====
        case 'add_products_to_consignment':
            $id = (int)($payload['id'] ?? 0);
            $productIds = $payload['product_ids'] ?? [];
            $quantities = $payload['quantities'] ?? [];
            
            if (!$id || empty($productIds)) {
                sendError('INVALID_PARAMS', 'Transfer ID and product IDs required');
            }
            
            foreach ($productIds as $idx => $productId) {
                $qty = (int)($quantities[$idx] ?? 1);
                if ($qty > 0) {
                    $transferService->addTransferItem($id, $productId, $qty);
                }
            }
            
            $result = $transferService->getTransferDetail($id);
            sendSuccess($result);
            break;

        // ===== STATUS CHANGES =====
        case 'mark_sent':
            $id = (int)($payload['id'] ?? 0);
            $totalBoxes = isset($payload['total_boxes']) ? (int)$payload['total_boxes'] : null;
            
            if (!$id) {
                sendError('INVALID_PARAMS', 'Transfer ID required');
            }
            
            $result = $transferService->markSent($id, $totalBoxes);
            sendSuccess($result);
            break;

        case 'mark_receiving':
            $id = (int)($payload['id'] ?? 0);
            
            if (!$id) {
                sendError('INVALID_PARAMS', 'Transfer ID required');
            }
            
            $result = $transferService->markReceiving($id);
            sendSuccess($result);
            break;

        case 'receive_all':
            $id = (int)($payload['id'] ?? 0);
            
            if (!$id) {
                sendError('INVALID_PARAMS', 'Transfer ID required');
            }
            
            $result = $transferService->receiveAll($id);
            sendSuccess($result);
            break;

        case 'cancel_transfer':
            $id = (int)($payload['id'] ?? 0);
            $reason = $payload['reason'] ?? '';
            
            if (!$id) {
                sendError('INVALID_PARAMS', 'Transfer ID required');
            }
            
            $result = $transferService->cancelTransfer($id, $reason);
            sendSuccess($result);
            break;

        // ===== REVERT OPERATIONS =====
        case 'revert_to_open':
            $id = (int)($payload['id'] ?? 0);
            if (!$id) {
                sendError('INVALID_PARAMS', 'Transfer ID required');
            }
            
            $helpers->updateStatus($id, 'OPEN', 'Reverted to open state');
            $result = $transferService->getTransferDetail($id);
            sendSuccess($result);
            break;

        case 'revert_to_sent':
            $id = (int)($payload['id'] ?? 0);
            if (!$id) {
                sendError('INVALID_PARAMS', 'Transfer ID required');
            }
            
            $helpers->updateStatus($id, 'SENT', 'Reverted to sent state');
            $result = $transferService->getTransferDetail($id);
            sendSuccess($result);
            break;

        case 'revert_to_receiving':
            $id = (int)($payload['id'] ?? 0);
            if (!$id) {
                sendError('INVALID_PARAMS', 'Transfer ID required');
            }
            
            $helpers->updateStatus($id, 'RECEIVING', 'Reverted to receiving state');
            $result = $transferService->getTransferDetail($id);
            sendSuccess($result);
            break;

        // ===== NOTES =====
        case 'add_note':
            $id = (int)($payload['id'] ?? 0);
            $noteText = $payload['note_text'] ?? '';
            
            if (!$id || !$noteText) {
                sendError('INVALID_PARAMS', 'Transfer ID and note text required');
            }
            
            $result = $transferService->addNote($id, $noteText);
            sendSuccess($result);
            break;

        // ===== RECREATE TRANSFER =====
        case 'recreate_transfer':
            $id = (int)($payload['id'] ?? 0);
            $revertStock = (bool)($payload['revert_stock'] ?? false);
            
            if (!$id) {
                sendError('INVALID_PARAMS', 'Transfer ID required');
            }
            
            // Get original transfer
            $original = $transferService->getTransferDetail($id);
            
            // Create new transfer with same data
            $newTransferData = [
                'transfer_category' => $original['transfer']['transfer_category'],
                'source_outlet_id' => $original['transfer']['source_outlet_id'],
                'destination_outlet_id' => $original['transfer']['destination_outlet_id'],
                'supplier_id' => $original['transfer']['supplier_id'],
                'name' => $original['transfer']['name'] . ' (Recreated)'
            ];
            
            $newTransfer = $transferService->createTransfer($newTransferData);
            $newId = $newTransfer['transfer']['id'];
            
            // Copy items
            foreach ($original['items'] as $item) {
                $transferService->addTransferItem(
                    $newId,
                    $item['product_id'],
                    (int)$item['quantity']
                );
            }
            
            // Copy notes
            foreach ($original['notes'] as $note) {
                $transferService->addNote($newId, '[From original] ' . $note['note_text']);
            }
            
            $result = $transferService->getTransferDetail($newId);
            sendSuccess($result, ['original_id' => $id]);
            break;

        // ===== LIGHTSPEED SYNC =====
        case 'create_consignment':
        case 'push_consignment_lines':
            $id = (int)($payload['id'] ?? 0);
            
            if (!$id) {
                sendError('INVALID_PARAMS', 'Transfer ID required');
            }
            
            if (!$transferService->getSyncEnabled()) {
                sendError('SYNC_DISABLED', 'Lightspeed sync is disabled');
            }
            
            // Use LightspeedSync service
            // Use fully qualified class name below
            $lightspeed = new \CIS\Services\Consignments\Integration\LightspeedSync($db);
            
            $syncResult = $lightspeed->syncConsignment($id);
            
            if (!$syncResult['success']) {
                sendError('SYNC_FAILED', $syncResult['error'] ?? 'Sync failed');
            }
            
            $result = $transferService->getTransferDetail($id);
            sendSuccess($result, ['sync_result' => $syncResult]);
            break;

        // ===== VEND NUMBER STORAGE =====
        case 'store_vend_numbers':
            $id = (int)($payload['id'] ?? 0);
            $vendNumber = $payload['vend_number'] ?? null;
            $vendTransferId = $payload['vend_transfer_id'] ?? null;
            
            if (!$id) {
                sendError('INVALID_PARAMS', 'Transfer ID required');
            }
            
            $stmt = $db->prepare("
                UPDATE vend_consignments 
                SET vend_consignment_number = ?,
                    vend_transfer_id = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$vendNumber, $vendTransferId, $id]);
            
            $helpers->logEvent($id, 'vend_numbers_stored', [
                'vend_number' => $vendNumber,
                'vend_transfer_id' => $vendTransferId
            ]);
            
            $result = $transferService->getTransferDetail($id);
            sendSuccess($result);
            break;

        // ===== UNKNOWN ACTION =====
        default:
            sendError('UNKNOWN_ACTION', "Unknown action: {$action}", [], 404);
    }

} catch (InvalidArgumentException $e) {
    sendError('INVALID_ARGUMENT', $e->getMessage(), [], 400);
} catch (RuntimeException $e) {
    sendError('RUNTIME_ERROR', $e->getMessage(), [], 500);
} catch (Throwable $e) {
    sendError('UNEXPECTED_ERROR', $e->getMessage(), [
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
}
