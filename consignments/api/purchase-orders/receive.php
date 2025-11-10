<?php
declare(strict_types=1);

/**
 * Receive Purchase Order Items API
 *
 * Records receipt of items for a purchase order.
 * Supports partial receiving and box-by-box tracking.
 *
 * @endpoint POST /api/purchase-orders/receive.php
 * @auth Required
 * @package CIS\Consignments\API
 */

require_once __DIR__ . '/../../bootstrap.php';

use CIS\Consignments\Services\PurchaseOrderService;
use CIS\Consignments\Services\ReceivingService;
use CIS\Consignments\Services\TransferReviewService;
use CIS\Consignments\Lib\PurchaseOrderLogger;

header('Content-Type: application/json');

try {
    // Authentication check
    if (!isset($_SESSION['userID'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'Authentication required'
            ]
        ]);
        exit;
    }

    // Only POST allowed
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'METHOD_NOT_ALLOWED',
                'message' => 'Only POST requests allowed'
            ]
        ]);
        exit;
    }

    // Parse JSON body
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'INVALID_JSON',
                'message' => 'Invalid JSON payload'
            ]
        ]);
        exit;
    }

    // Validate required fields
    if (!isset($data['po_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'MISSING_PO_ID',
                'message' => 'Purchase order ID is required'
            ]
        ]);
        exit;
    }

    if (!isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'MISSING_ITEMS',
                'message' => 'At least one item must be specified for receiving'
            ]
        ]);
        exit;
    }

    $poId = (int)$data['po_id'];
    $poService = new PurchaseOrderService($pdo);
    $receivingService = new ReceivingService($pdo);

    // Fetch PO
    $po = $poService->get($poId);

    if (!$po) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'NOT_FOUND',
                'message' => 'Purchase order not found'
            ]
        ]);
        exit;
    }

    // Check permissions
    $canReceive = (
        $po['outlet_id'] === $_SESSION['outlet_id'] ||
        hasPermission('po.receive')
    );

    if (!$canReceive) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => 'You do not have permission to receive items for this purchase order'
            ]
        ]);
        exit;
    }

    // Verify PO is in correct state
    if (!in_array($po['status'], ['SENT', 'RECEIVING', 'PARTIAL'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'INVALID_STATE',
                'message' => "Cannot receive items for PO in status: {$po['status']}"
            ]
        ]);
        exit;
    }

    // Start receiving session
    $receiptId = $receivingService->startReceivingSession($poId, [
        'received_by' => $_SESSION['userID'],
        'outlet_id' => $_SESSION['outlet_id'],
        'notes' => $data['notes'] ?? null,
        'parcel_id' => $data['parcel_id'] ?? null
    ]);

    $receivedItems = [];
    $errors = [];

    // Process each item
    foreach ($data['items'] as $item) {
        try {
            $result = $receivingService->receiveItem($receiptId, [
                'product_id' => $item['product_id'],
                'qty_received' => $item['qty_received'],
                'qty_damaged' => $item['qty_damaged'] ?? 0,
                'notes' => $item['notes'] ?? null,
                'scanned_barcode' => $item['scanned_barcode'] ?? null
            ]);

            $receivedItems[] = $result;

        } catch (Exception $e) {
            $errors[] = [
                'product_id' => $item['product_id'],
                'error' => $e->getMessage()
            ];
        }
    }

    // Complete receiving session
    $receivingService->completeReceivingSession($receiptId);

    // Update PO status
    $receiptSummary = $receivingService->getReceiptSummary($receiptId);

    if ($receiptSummary['fully_received']) {
        $poService->updateStatus($poId, 'RECEIVED', $_SESSION['userID']);
    } else {
        $poService->updateStatus($poId, 'PARTIAL', $_SESSION['userID']);
    }

    // Log action
    $poService->logAction($poId, 'ITEMS_RECEIVED', [
        'receipt_id' => $receiptId,
        'items_count' => count($receivedItems),
        'errors_count' => count($errors)
    ], $_SESSION['userID']);

    // Trigger transfer/purchase order review (non-blocking)
    try {
        // Best-effort: attempt to run background job via CLI if available
        $transferId = $poId;
        $php = PHP_BINARY;
        // CLI helper lives in modules/consignments/cli
        $script = __DIR__ . '/../../cli/generate_transfer_review.php';

        // Ensure logger initialized and record scheduling attempt
        try {
            PurchaseOrderLogger::init();
            PurchaseOrderLogger::reviewScheduled($poId, (int)($_SESSION['userID'] ?? 0));
        } catch (\Throwable $t) {
            // non-fatal
            error_log('Failed to record review scheduling: ' . $t->getMessage());
        }

        if (is_file($script) && function_exists('exec')) {
            // Fire-and-forget background process (safe escaping)
            $cmd = $php . ' ' . escapeshellarg($script) . ' ' . escapeshellarg((string)$transferId) . ' > /dev/null 2>&1 &';
            @exec($cmd);
        } else {
            // Fallback: run inline but guarded so it doesn't break the response
            try {
                $reviewService = new TransferReviewService($pdo);
                $review = $reviewService->generateReview($transferId);
                // Log that review was generated
                PurchaseOrderLogger::init();
                PurchaseOrderLogger::poReceivingCompleted(
                    $poId,
                    (int)($_SESSION['userID'] ?? 0),
                    count($receivedItems),
                    count($receivedItems),
                    (float)($review['metrics']['avg_time_per_item_seconds'] ?? 0.0),
                    [],
                    ['review_id' => $review['metrics']['transfer_id'] ?? null]
                );
            } catch (\Exception $e) {
                error_log("Transfer review generation failed (inline): " . $e->getMessage());
            }
        }
    } catch (\Throwable $t) {
        // Ensure any logging/async attempts never break the API
        error_log("Receive API: failed to schedule transfer review: " . $t->getMessage());
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'po_id' => $poId,
            'receipt_id' => $receiptId,
            'received_items' => $receivedItems,
            'summary' => $receiptSummary,
            'errors' => $errors,
            'new_status' => $receiptSummary['fully_received'] ? 'RECEIVED' : 'PARTIAL'
        ],
        'message' => count($errors) > 0
            ? sprintf('Received %d items with %d errors', count($receivedItems), count($errors))
            : sprintf('Successfully received %d items', count($receivedItems)),
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INVALID_DATA',
            'message' => $e->getMessage()
        ]
    ]);
} catch (Exception $e) {
    error_log("Receive Items API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Failed to receive items',
            'debug' => ENVIRONMENT === 'development' ? $e->getMessage() : null
        ]
    ]);
}

/**
 * Helper function to check permissions
 */
function hasPermission(string $permission): bool
{
    if (!isset($_SESSION['permissions'])) {
        return false;
    }
    return in_array($permission, $_SESSION['permissions']) || in_array('*', $_SESSION['permissions']);
}
