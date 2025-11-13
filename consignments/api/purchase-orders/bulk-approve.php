<?php
/**
 * Bulk Approve/Reject Purchase Orders API
 *
 * Processes approval decisions for multiple purchase orders at once.
 * All POs must pass validation, otherwise entire batch fails (atomic).
 *
 * @endpoint POST /api/purchase-orders/bulk-approve.php
 * @authentication Required (session)
 * @package CIS\Consignments\API
 * @since 1.0.0
 */

declare(strict_types=1);

// Bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/bootstrap.php';

use Consignments\Lib\Services\ApprovalService;
use Consignments\Lib\Services\PurchaseOrderService;

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized. Please log in.'
    ]);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

$db = getDB();
$approvalService = new ApprovalService($db);
$poService = new PurchaseOrderService($db);
$currentUserId = $_SESSION['user_id'];

// Parse request
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON payload.'
    ]);
    exit;
}

// Validate payload
$poIds = $input['po_ids'] ?? null;
$action = $input['action'] ?? null;
$comments = $input['comments'] ?? '';

if (!is_array($poIds) || empty($poIds)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'po_ids must be a non-empty array.'
    ]);
    exit;
}

if (!in_array($action, ['APPROVED', 'REJECTED', 'REQUEST_CHANGES'], true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'action must be APPROVED, REJECTED, or REQUEST_CHANGES.'
    ]);
    exit;
}

// Sanitize
$poIds = array_map('intval', $poIds);
$comments = trim($comments);

try {
    $db->beginTransaction();

    $results = [
        'processed' => 0,
        'failed' => 0,
        'errors' => []
    ];

    foreach ($poIds as $poId) {
        try {
            // Get PO
            $po = $poService->get($poId);
            if (!$po) {
                throw new Exception("Purchase order #{$poId} not found.");
            }

            // Check state
            if ($po['state'] !== 'PENDING_APPROVAL') {
                throw new Exception("Purchase order {$po['public_id']} is not pending approval.");
            }

            // Process approval
            $result = $approvalService->processApproval(
                'purchase_order',
                $poId,
                $currentUserId,
                $action,
                $comments
            );

            // Update PO state based on result
            if ($result['all_approved'] && $action === 'APPROVED') {
                $poService->changeState($poId, 'APPROVED');
            } elseif ($action === 'REJECTED' || $action === 'REQUEST_CHANGES') {
                // Send back to DRAFT with reason
                $reason = $comments ?: ($action === 'REJECTED' ? 'Rejected by approver' : 'Changes requested');
                $poService->changeState($poId, 'DRAFT');

                // Log the reason in notes
                $currentNotes = $po['notes'] ?? '';
                $newNotes = $currentNotes . "\n\n[" . date('Y-m-d H:i:s') . "] " .
                           ($action === 'REJECTED' ? 'REJECTED' : 'CHANGES REQUESTED') . ": " . $reason;

                $stmt = $db->prepare("
                    UPDATE vend_consignments
                    SET notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$newNotes, $poId]);
            }

            $results['processed']++;

        } catch (Exception $e) {
            $results['failed']++;
            $results['errors'][] = [
                'po_id' => $poId,
                'error' => $e->getMessage()
            ];
        }
    }

    // If any failed, rollback entire transaction
    if ($results['failed'] > 0) {
        $db->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Bulk approval failed. All changes rolled back.',
            'data' => $results
        ]);
        exit;
    }

    // All succeeded, commit
    $db->commit();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => "{$results['processed']} purchase orders {$action} successfully.",
        'data' => $results
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log("Bulk approve error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error.',
        'message' => $e->getMessage()
    ]);
}
