<?php
declare(strict_types=1);

/**
 * Autosave Purchase Order API
 *
 * Saves draft PO data without validation (for autosave functionality).
 * Creates new draft if ID not provided, updates existing draft if provided.
 *
 * @endpoint POST /api/purchase-orders/autosave.php
 * @auth Required
 * @package CIS\Consignments\API
 */

require_once __DIR__ . '/../../bootstrap.php';

use CIS\Consignments\Services\PurchaseOrderService;

header('Content-Type: application/json');

try {
    // Authentication check
    if (!isset($_SESSION['user_id'])) {
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

    $poService = new PurchaseOrderService($pdo);

    // Get or create draft
    $poId = isset($data['id']) ? (int)$data['id'] : null;

    if ($poId) {
        // Update existing draft
        $po = $poService->get($poId);

        // Verify ownership or permissions
        if ($po['created_by'] !== $_SESSION['user_id'] && !hasPermission('po.edit_all')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to edit this purchase order'
                ]
            ]);
            exit;
        }

        // Verify it's still a draft
        if ($po['status'] !== 'DRAFT') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'NOT_DRAFT',
                    'message' => 'Only draft purchase orders can be autosaved'
                ]
            ]);
            exit;
        }

        // Merge data (don't overwrite missing fields)
        $updateData = array_filter([
            'supplier_id' => $data['supplier_id'] ?? null,
            'outlet_id' => $data['outlet_id'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'freight_method' => $data['freight_method'] ?? null,
            'freight_cost' => isset($data['freight_cost']) ? (float)$data['freight_cost'] : null,
        ], fn($v) => $v !== null);

        $poService->update($poId, $updateData, $_SESSION['user_id'], $skipValidation = true);

        // Update line items if provided
        if (isset($data['items']) && is_array($data['items'])) {
            $poService->updateLineItems($poId, $data['items'], $_SESSION['user_id']);
        }

    } else {
        // Create new draft
        $createData = [
            'supplier_id' => $data['supplier_id'] ?? null,
            'outlet_id' => $data['outlet_id'] ?? null,
            'status' => 'DRAFT',
            'due_date' => $data['due_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'freight_method' => $data['freight_method'] ?? null,
            'freight_cost' => isset($data['freight_cost']) ? (float)$data['freight_cost'] : null,
        ];

        $poId = $poService->create($createData, $_SESSION['user_id'], $skipValidation = true);

        // Add line items if provided
        if (isset($data['items']) && is_array($data['items'])) {
            $poService->addLineItems($poId, $data['items'], $_SESSION['user_id']);
        }
    }

    // Fetch updated PO
    $po = $poService->get($poId);

    echo json_encode([
        'success' => true,
        'data' => $po,
        'message' => 'Draft saved',
        'autosaved_at' => date('Y-m-d H:i:s'),
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
    error_log("PO Autosave API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Failed to save draft'
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
