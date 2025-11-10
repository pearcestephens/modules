<?php
declare(strict_types=1);

/**
 * Get Purchase Order API
 *
 * Fetches a single purchase order with all details including:
 * - Line items with product details
 * - Freight information
 * - Approval history
 * - Receiving history
 * - Audit trail
 *
 * @endpoint GET /api/purchase-orders/get.php?id=123
 * @auth Required
 * @package CIS\Consignments\API
 */

require_once __DIR__ . '/../../bootstrap.php';

use CIS\Consignments\Services\PurchaseOrderService;

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

    // Validate ID parameter
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'MISSING_ID',
                'message' => 'Purchase order ID is required'
            ]
        ]);
        exit;
    }

    $poId = (int)$_GET['id'];
    if ($poId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'INVALID_ID',
                'message' => 'Invalid purchase order ID'
            ]
        ]);
        exit;
    }

    $poService = new PurchaseOrderService($pdo);

    // Parse include flags
    $includeLineItems = !isset($_GET['include_items']) || filter_var($_GET['include_items'], FILTER_VALIDATE_BOOLEAN);
    $includeFreight = !isset($_GET['include_freight']) || filter_var($_GET['include_freight'], FILTER_VALIDATE_BOOLEAN);
    $includeApprovals = !isset($_GET['include_approvals']) || filter_var($_GET['include_approvals'], FILTER_VALIDATE_BOOLEAN);
    $includeReceiving = isset($_GET['include_receiving']) && filter_var($_GET['include_receiving'], FILTER_VALIDATE_BOOLEAN);
    $includeAudit = isset($_GET['include_audit']) && filter_var($_GET['include_audit'], FILTER_VALIDATE_BOOLEAN);

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
    $canView = (
        $po['created_by'] === $_SESSION['userID'] ||
        $po['outlet_id'] === $_SESSION['outlet_id'] ||
        hasPermission('po.view_all')
    );

    if (!$canView) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => 'You do not have permission to view this purchase order'
            ]
        ]);
        exit;
    }

    // Build response
    $response = [
        'success' => true,
        'data' => $po,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // Add optional data
    if ($includeReceiving && in_array($po['status'], ['RECEIVING', 'PARTIAL', 'RECEIVED'])) {
        $response['receiving_history'] = $poService->getReceivingHistory($poId);
    }

    if ($includeAudit) {
        $response['audit_trail'] = $poService->getAuditTrail($poId);
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log("PO Get API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Failed to fetch purchase order'
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
