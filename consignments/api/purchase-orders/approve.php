<?php
/**
 * Purchase Orders API - Approve/Reject
 *
 * Processes approval decision for a purchase order
 *
 * Method: POST
 * Payload:
 * {
 *   "po_id": number,
 *   "action": "APPROVED" | "REJECTED" | "REQUEST_CHANGES",
 *   "comments": "string" (optional)
 * }
 *
 * @package CIS\Consignments\PurchaseOrders\API
 * @version 1.0.0
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once __DIR__ . '/../../lib/Services/PurchaseOrderService.php';
require_once __DIR__ . '/../../lib/Services/ApprovalService.php';

use CIS\Consignments\Services\PurchaseOrderService;
use CIS\Consignments\Services\ApprovalService;

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

    $action = strtoupper($data['action'] ?? '');
    if (!in_array($action, ['APPROVED', 'REJECTED', 'REQUEST_CHANGES'])) {
        throw new InvalidArgumentException('Invalid action');
    }

    $comments = isset($data['comments']) ? trim($data['comments']) : null;

    // Initialize services
    $db = get_db();
    $poService = new PurchaseOrderService($db);
    $approvalService = new ApprovalService($db);

    // Check PO exists
    $po = $poService->get($poId);
    if (!$po) {
        throw new InvalidArgumentException('Purchase order not found');
    }

    // Must be PENDING_APPROVAL
    if ($po->state !== 'PENDING_APPROVAL') {
        throw new InvalidArgumentException('Purchase order is not pending approval');
    }

    // Process approval
    $result = $approvalService->processApproval(
        $poId,
        $_SESSION['userID'],
        $action,
        $comments
    );

    // Update PO state based on approval result
    if ($result['all_approved']) {
        // All approvals complete → APPROVED
        $poService->changeState(
            $poId,
            'APPROVED',
            $_SESSION['userID'],
            'Approved by ' . $result['approver_name']
        );

        $message = 'Purchase order approved successfully';
        $newState = 'APPROVED';

    } elseif ($action === 'REJECTED') {
        // Rejected → back to DRAFT
        $poService->changeState(
            $poId,
            'DRAFT',
            $_SESSION['userID'],
            'Rejected by ' . $result['approver_name'] . ($comments ? ': ' . $comments : '')
        );

        $message = 'Purchase order rejected';
        $newState = 'DRAFT';

    } elseif ($action === 'REQUEST_CHANGES') {
        // Changes requested → back to DRAFT
        $poService->changeState(
            $poId,
            'DRAFT',
            $_SESSION['userID'],
            'Changes requested by ' . $result['approver_name'] . ($comments ? ': ' . $comments : '')
        );

        $message = 'Changes requested';
        $newState = 'DRAFT';

    } else {
        // Partial approval → stays PENDING_APPROVAL
        $message = 'Approval recorded, waiting for remaining approvals';
        $newState = 'PENDING_APPROVAL';
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => [
            'state' => $newState,
            'all_approved' => $result['all_approved'] ?? false,
            'pending_count' => $result['pending_count'] ?? 0,
        ]
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("PO Approve API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
