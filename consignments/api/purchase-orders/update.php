<?php
/**
 * Purchase Orders API - Update
 *
 * Updates an existing purchase order (DRAFT or OPEN only)
 *
 * Method: POST
 * Payload: Same as create.php but with po_id
 *
 * @package CIS\Consignments\PurchaseOrders\API
 * @version 1.0.0
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once __DIR__ . '/../../lib/Services/PurchaseOrderService.php';
require_once __DIR__ . '/../../lib/Helpers/ValidationHelper.php';

use CIS\Services\Consignments\Core\PurchaseOrderService;
use CIS\Consignments\Helpers\ValidationHelper;

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

    // Initialize service
    $db = get_db();
    $poService = new PurchaseOrderService($db);

    // Validate PO ID
    $poId = ValidationHelper::sanitizeInt($data['po_id'] ?? 0);
    if (!$poId) {
        throw new InvalidArgumentException('Invalid or missing PO ID');
    }

    // Check PO exists and is editable
    $po = $poService->get($poId);
    if (!$po) {
        throw new InvalidArgumentException('Purchase order not found');
    }

    if (!in_array($po->state, ['DRAFT', 'OPEN'])) {
        throw new InvalidArgumentException('Purchase order cannot be edited in ' . $po->state . ' state');
    }

    // Build update data
    $updateData = [];

    if (isset($data['outlet_id'])) {
        $updateData['outlet_id'] = ValidationHelper::sanitizeString($data['outlet_id']);
        $outletValidation = ValidationHelper::validateOutletExists($db, $updateData['outlet_id']);
        if (!$outletValidation['valid']) {
            throw new InvalidArgumentException('Invalid outlet ID');
        }
    }

    if (isset($data['expected_date'])) {
        $updateData['expected_date'] = ValidationHelper::sanitizeString($data['expected_date']);
    }

    if (isset($data['supplier_reference'])) {
        $updateData['supplier_reference'] = ValidationHelper::sanitizeString($data['supplier_reference']);
    }

    if (isset($data['notes'])) {
        $updateData['notes'] = ValidationHelper::sanitizeString($data['notes']);
    }

    // Update PO header
    if (!empty($updateData)) {
        $poService->update($poId, $updateData);
    }

    // Handle line items if provided
    if (isset($data['line_items']) && is_array($data['line_items'])) {
        // Validate line items
        $lineItemValidation = ValidationHelper::batchValidateLineItems($data['line_items']);
        if (!$lineItemValidation['all_valid']) {
            $errors = [];
            foreach ($lineItemValidation['items'] as $index => $item) {
                if (!$item['valid']) {
                    $errors[] = "Item " . ($index + 1) . ": " . implode(', ', $item['errors']);
                }
            }
            throw new InvalidArgumentException('Line item validation failed: ' . implode('; ', $errors));
        }

        // Get existing line items
        $existingItems = $poService->getLineItems($poId);
        $existingIds = array_column(array_map(function($item) { return (array)$item; }, $existingItems), 'id');

        $processedIds = [];

        // Process each line item
        foreach ($data['line_items'] as $item) {
            $itemData = [
                'product_id' => ValidationHelper::sanitizeString($item['product_id']),
                'quantity' => ValidationHelper::sanitizeInt($item['quantity']),
                'cost' => ValidationHelper::sanitizeFloat($item['cost']),
                'notes' => isset($item['notes']) ? ValidationHelper::sanitizeString($item['notes']) : null,
            ];

            if (isset($item['id']) && $item['id']) {
                // Update existing item
                $itemId = ValidationHelper::sanitizeInt($item['id']);
                $poService->updateLineItem($itemId, $itemData);
                $processedIds[] = $itemId;
            } else {
                // Add new item
                $newItemId = $poService->addLineItem($poId, $itemData);
                $processedIds[] = $newItemId;
            }
        }

        // Delete items not in the update (removed items)
        foreach ($existingIds as $existingId) {
            if (!in_array($existingId, $processedIds)) {
                $poService->deleteLineItem($existingId);
            }
        }
    }

    // Change state if needed
    if (isset($data['is_draft']) && !$data['is_draft'] && $po->state === 'DRAFT') {
        $poService->changeState($poId, 'OPEN', $_SESSION['user_id'], 'Purchase order updated');
    }

    // Fetch updated PO
    $updatedPo = $poService->get($poId);

    echo json_encode([
        'success' => true,
        'message' => 'Purchase order updated successfully',
        'data' => [
            'id' => $poId,
            'public_id' => $updatedPo->public_id,
            'state' => $updatedPo->state,
            'total_cost' => $updatedPo->total_cost,
        ]
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("PO Update API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
