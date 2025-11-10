<?php
declare(strict_types=1);

/**
 * Freight Quote API
 *
 * Gets freight rate quotes from multiple carriers for a purchase order.
 * Integrates with FreightIntegration service to compare rates.
 *
 * @endpoint POST /api/purchase-orders/freight-quote.php
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
    if (!isset($data['po_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'MISSING_PO_ID',
                'message' => 'Purchase order ID is required'
            ]
        ]);
        exit;
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
                'message' => 'You do not have permission to get freight quotes for this PO'
            ]
        ]);
        exit;
    }

    // Calculate metrics for freight quote
    $metrics = $freightService->calculateTransferMetrics($poId);

    // Get rate quotes from multiple carriers
    $carriers = $data['carriers'] ?? ['nzpost', 'nzcourier', 'gss', 'starshipit'];
    $quotes = $freightService->getTransferRates($poId, [
        'carriers' => $carriers,
        'service_level' => $data['service_level'] ?? 'standard'
    ]);

    // Get container suggestions for optimal packing
    $containers = $freightService->suggestTransferContainers($poId);

    // Calculate estimated delivery dates
    foreach ($quotes as &$quote) {
        $quote['estimated_delivery_days'] = $quote['transit_days'] ?? 3;
        $quote['estimated_delivery_date'] = date('Y-m-d', strtotime("+{$quote['estimated_delivery_days']} days"));
    }

    // Sort by price (cheapest first)
    usort($quotes, fn($a, $b) => $a['total_price'] <=> $b['total_price']);

    echo json_encode([
        'success' => true,
        'data' => [
            'po_id' => $poId,
            'metrics' => $metrics,
            'quotes' => $quotes,
            'container_suggestions' => $containers,
            'quoted_at' => date('Y-m-d H:i:s'),
            'currency' => 'NZD'
        ],
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
    error_log("Freight Quote API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Failed to get freight quotes',
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
