<?php
/**
 * Purchase Orders API - Send to Supplier
 *
 * Sends approved PO to supplier via email and changes state to SENT
 *
 * Method: POST
 * Payload: { "po_id": number }
 *
 * @package CIS\Consignments\PurchaseOrders\API
 * @version 1.0.0
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once __DIR__ . '/../../lib/Services/PurchaseOrderService.php';
require_once __DIR__ . '/../../lib/Services/SupplierService.php';

use CIS\Consignments\Services\PurchaseOrderService;
use CIS\Consignments\Services\SupplierService;

// Check authentication
if (!isset($_SESSION['userID'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Parse JSON payload
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new InvalidArgumentException('Invalid JSON payload');
    }

    $poId = (int)($data['po_id'] ?? 0);
    if (!$poId) {
        throw new InvalidArgumentException('Invalid or missing PO ID');
    }

    // Initialize services
    $db = get_db();
    $poService = new PurchaseOrderService($db);
    $supplierService = new SupplierService($db);

    // Check PO exists
    $po = $poService->get($poId);
    if (!$po) {
        throw new InvalidArgumentException('Purchase order not found');
    }

    // Must be APPROVED
    if ($po->state !== 'APPROVED') {
        throw new InvalidArgumentException('Only APPROVED purchase orders can be sent');
    }

    // Send email to supplier
    $emailSent = $supplierService->sendPONotification($poId, 'sent');

    if (!$emailSent) {
        throw new RuntimeException('Failed to send email to supplier');
    }

    // Change state to SENT
    $poService->changeState(
        $poId,
        'SENT',
        $_SESSION['userID'],
        'Purchase order sent to supplier'
    );

    echo json_encode([
        'success' => true,
        'message' => 'Purchase order sent to supplier successfully',
        'data' => [
            'state' => 'SENT',
            'sent_at' => date('Y-m-d H:i:s'),
        ]
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("PO Send API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
