<?php
/**
 * Purchase Orders API - Delete
 *
 * Soft deletes a purchase order (DRAFT only)
 *
 * Method: DELETE
 * Payload: { "id": number }
 *
 * @package CIS\Consignments\PurchaseOrders\API
 * @version 1.0.0
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once __DIR__ . '/../../lib/Services/PurchaseOrderService.php';

use CIS\Consignments\Services\PurchaseOrderService;

// Check authentication
if (!isset($_SESSION['userID'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only DELETE allowed
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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

    $poId = (int)($data['id'] ?? 0);
    if (!$poId) {
        throw new InvalidArgumentException('Invalid or missing PO ID');
    }

    // Initialize service
    $db = get_db();
    $poService = new PurchaseOrderService($db);

    // Check PO exists
    $po = $poService->get($poId);
    if (!$po) {
        throw new InvalidArgumentException('Purchase order not found');
    }

    // Can only delete DRAFT
    if ($po->state !== 'DRAFT') {
        throw new InvalidArgumentException('Only DRAFT purchase orders can be deleted');
    }

    // Delete PO
    $success = $poService->delete($poId, $_SESSION['userID']);

    if (!$success) {
        throw new RuntimeException('Failed to delete purchase order');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Purchase order deleted successfully'
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("PO Delete API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
