<?php
declare(strict_types=1);

/**
 * Create Freight Label API
 *
 * Creates a freight label for a purchase order using selected carrier.
 * Stores label URL and tracking number in consignment_parcels table.
 *
 * @endpoint POST /api/purchase-orders/create-label.php
 * @auth Required
 * @package CIS\Consignments\API
 */

require_once __DIR__ . '/../../bootstrap.php';

use CIS\Consignments\Services\PurchaseOrderService;
use CIS\Consignments\Services\FreightIntegration;

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
    $required = ['po_id', 'carrier', 'service_level'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'MISSING_FIELD',
                    'message' => "Required field missing: {$field}"
                ]
            ]);
            exit;
        }
    }

    $poId = (int)$data['po_id'];
    $poService = new PurchaseOrderService($pdo);
    $freightService = new FreightIntegration($pdo);

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
    $canEdit = (
        $po['created_by'] === $_SESSION['userID'] ||
        hasPermission('po.edit_all')
    );

    if (!$canEdit) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => 'You do not have permission to create freight labels for this PO'
            ]
        ]);
        exit;
    }

    // Verify PO is in correct state (PACKAGED or SENT)
    if (!in_array($po['status'], ['PACKAGED', 'SENT', 'PACKING'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'INVALID_STATE',
                'message' => "Purchase order must be PACKAGED before creating freight label. Current status: {$po['status']}"
            ]
        ]);
        exit;
    }

    // Create label via FreightIntegration
    $labelData = $freightService->createTransferLabel($poId, [
        'carrier' => $data['carrier'],
        'service_level' => $data['service_level'],
        'insurance_value' => $data['insurance_value'] ?? null,
        'signature_required' => $data['signature_required'] ?? false,
        'delivery_instructions' => $data['delivery_instructions'] ?? null
    ]);

    // Update PO with freight info
    $poService->update($poId, [
        'freight_method' => $data['carrier'],
        'freight_cost' => $labelData['freight_cost'],
        'tracking_number' => $labelData['tracking_number']
    ], $_SESSION['userID']);

    // Store label in consignment_parcels
    $stmt = $pdo->prepare("
        INSERT INTO consignment_parcels (
            shipment_id,
            parcel_number,
            tracking_number,
            courier,
            weight_kg,
            label_url,
            label_format,
            status,
            created_at
        )
        SELECT
            cs.id,
            1,
            :tracking_number,
            :carrier,
            :weight,
            :label_url,
            :label_format,
            'PENDING',
            NOW()
        FROM consignment_shipments cs
        WHERE cs.transfer_id = :po_id
        LIMIT 1
        ON DUPLICATE KEY UPDATE
            tracking_number = VALUES(tracking_number),
            label_url = VALUES(label_url),
            updated_at = NOW()
    ");

    $stmt->execute([
        'po_id' => $poId,
        'tracking_number' => $labelData['tracking_number'],
        'carrier' => $data['carrier'],
        'weight' => $labelData['weight_kg'] ?? 0,
        'label_url' => $labelData['label_url'],
        'label_format' => $labelData['label_format'] ?? 'PDF'
    ]);

    // Log action
    $poService->logAction($poId, 'LABEL_CREATED', [
        'carrier' => $data['carrier'],
        'tracking_number' => $labelData['tracking_number'],
        'freight_cost' => $labelData['freight_cost']
    ], $_SESSION['userID']);

    echo json_encode([
        'success' => true,
        'data' => [
            'po_id' => $poId,
            'tracking_number' => $labelData['tracking_number'],
            'label_url' => $labelData['label_url'],
            'label_format' => $labelData['label_format'] ?? 'PDF',
            'freight_cost' => $labelData['freight_cost'],
            'carrier' => $data['carrier'],
            'service_level' => $data['service_level'],
            'estimated_delivery' => $labelData['estimated_delivery'] ?? null
        ],
        'message' => 'Freight label created successfully',
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
    error_log("Create Label API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Failed to create freight label',
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
