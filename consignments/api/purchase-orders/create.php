<?php
/**
 * Purchase Orders API - Create
 *
 * Creates a new purchase order with line items
 *
 * Method: POST
 * Payload:
 * {
 *   "supplier_id": "UUID",
 *   "outlet_id": "UUID",
 *   "expected_date": "YYYY-MM-DD" (optional),
 *   "supplier_reference": "string" (optional),
 *   "notes": "string" (optional),
 *   "line_items": [
 *     {
 *       "product_id": "UUID",
 *       "quantity": number,
 *       "cost": number,
 *       "notes": "string" (optional)
 *     }
 *   ],
 *   "is_draft": boolean
 * }
 *
 * @package CIS\Consignments\PurchaseOrders\API
 * @version 1.0.0
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once __DIR__ . '/../../lib/Services/PurchaseOrderService.php';
require_once __DIR__ . '/../../lib/Helpers/ValidationHelper.php';

use CIS\Consignments\Services\PurchaseOrderService;
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

    // Validate required fields
    $validation = ValidationHelper::validateRequired($data, ['supplier_id', 'outlet_id', 'line_items']);
    if (!$validation['valid']) {
        throw new InvalidArgumentException('Missing required fields: ' . implode(', ', $validation['missing']));
    }

    // Validate line items
    if (empty($data['line_items']) || !is_array($data['line_items'])) {
        throw new InvalidArgumentException('At least one line item is required');
    }

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

    // Sanitize inputs
    $poData = [
        'supplier_id' => ValidationHelper::sanitizeString($data['supplier_id']),
        'outlet_id' => ValidationHelper::sanitizeString($data['outlet_id']),
        'expected_date' => isset($data['expected_date']) ? ValidationHelper::sanitizeString($data['expected_date']) : null,
        'supplier_reference' => isset($data['supplier_reference']) ? ValidationHelper::sanitizeString($data['supplier_reference']) : null,
        'notes' => isset($data['notes']) ? ValidationHelper::sanitizeString($data['notes']) : null,
    ];

    // Validate supplier exists
    $supplierValidation = ValidationHelper::validateSupplierExists($db, $poData['supplier_id']);
    if (!$supplierValidation['valid']) {
        throw new InvalidArgumentException('Invalid supplier ID');
    }

    // Validate outlet exists
    $outletValidation = ValidationHelper::validateOutletExists($db, $poData['outlet_id']);
    if (!$outletValidation['valid']) {
        throw new InvalidArgumentException('Invalid outlet ID');
    }

    // Create PO
    $poId = $poService->create($poData, $_SESSION['user_id']);

    // Add line items
    $itemCount = 0;
    foreach ($data['line_items'] as $item) {
        $itemData = [
            'product_id' => ValidationHelper::sanitizeString($item['product_id']),
            'quantity' => ValidationHelper::sanitizeInt($item['quantity']),
            'cost' => ValidationHelper::sanitizeFloat($item['cost']),
            'notes' => isset($item['notes']) ? ValidationHelper::sanitizeString($item['notes']) : null,
        ];

        $poService->addLineItem($poId, $itemData);
        $itemCount++;
    }

    // Change state if not draft
    if (!($data['is_draft'] ?? true)) {
        $poService->changeState($poId, 'OPEN', $_SESSION['user_id'], 'Purchase order created');
    }

    // Fetch created PO
    $po = $poService->get($poId);

    echo json_encode([
        'success' => true,
        'message' => 'Purchase order created successfully',
        'data' => [
            'id' => $poId,
            'public_id' => $po->public_id,
            'state' => $po->state,
            'item_count' => $itemCount,
            'total_cost' => $po->total_cost,
        ]
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("PO Create API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
