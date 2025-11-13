<?php
/**
 * Purchase Orders API - Submit for Approval
 *
 * Submits a DRAFT purchase order to approval workflow
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
require_once __DIR__ . '/../../lib/Services/ApprovalService.php';

use CIS\Services\Consignments\Core\PurchaseOrderService;
use CIS\Services\Consignments\Support\ApprovalService;

// Check authentication
if (!isset($_SESSION['user_id'])) {
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
    $approvalService = new ApprovalService($db);

    // Check PO exists
    $po = $poService->get($poId);
    if (!$po) {
        throw new InvalidArgumentException('Purchase order not found');
    }

    // Must be DRAFT
    if ($po->state !== 'DRAFT') {
        throw new InvalidArgumentException('Only DRAFT purchase orders can be submitted');
    }

    // Must have line items
    $lineItems = $poService->getLineItems($poId);
    if (empty($lineItems)) {
        throw new InvalidArgumentException('Cannot submit empty purchase order');
    }

    // Check if approval required
    $approvalRequired = $approvalService->isApprovalRequired($poId);

    if ($approvalRequired) {
        // Submit for approval
        $result = $approvalService->submitForApproval($poId, $_SESSION['user_id']);

        // Change state to PENDING_APPROVAL
        $poService->changeState(
            $poId,
            'PENDING_APPROVAL',
            $_SESSION['user_id'],
            'Submitted for approval'
        );

        echo json_encode([
            'success' => true,
            'message' => 'Purchase order submitted for approval',
            'data' => [
                'state' => 'PENDING_APPROVAL',
                'approval_tier' => $result['tier'],
                'required_approvers' => count($result['approvers']),
            ]
        ]);

    } else {
        // No approval needed, move directly to APPROVED
        $poService->changeState(
            $poId,
            'APPROVED',
            $_SESSION['user_id'],
            'Auto-approved (below threshold)'
        );

        echo json_encode([
            'success' => true,
            'message' => 'Purchase order approved automatically (no approval required)',
            'data' => [
                'state' => 'APPROVED',
                'auto_approved' => true,
            ]
        ]);
    }

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("PO Submit API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
