<?php
declare(strict_types=1);

/**
 * List Purchase Orders API
 *
 * Fetches paginated list of purchase orders with filtering, sorting, and search.
 * Supports datatables format for UI integration.
 *
 * @endpoint GET /api/purchase-orders/list.php
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

    $poService = new PurchaseOrderService($pdo);

    // Parse query parameters
    $params = [
        'search' => $_GET['search'] ?? '',
        'status' => $_GET['status'] ?? null,
        'supplier_id' => isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null,
        'outlet_id' => isset($_GET['outlet_id']) ? (int)$_GET['outlet_id'] : null,
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
        'min_total' => isset($_GET['min_total']) ? (float)$_GET['min_total'] : null,
        'max_total' => isset($_GET['max_total']) ? (float)$_GET['max_total'] : null,
        'needs_approval' => isset($_GET['needs_approval']) ? filter_var($_GET['needs_approval'], FILTER_VALIDATE_BOOLEAN) : null,
        'created_by' => isset($_GET['created_by']) ? (int)$_GET['created_by'] : null,

        // Pagination
        'page' => isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1,
        'per_page' => isset($_GET['per_page']) ? min(100, max(10, (int)$_GET['per_page'])) : 25,

        // Sorting
        'sort_by' => $_GET['sort_by'] ?? 'created_at',
        'sort_dir' => strtoupper($_GET['sort_dir'] ?? 'DESC')
    ];

    // Validate sort direction
    if (!in_array($params['sort_dir'], ['ASC', 'DESC'])) {
        $params['sort_dir'] = 'DESC';
    }

    // Validate sort field
    $validSortFields = ['id', 'consignment_number', 'supplier_name', 'total_amount', 'status', 'created_at', 'due_date'];
    if (!in_array($params['sort_by'], $validSortFields)) {
        $params['sort_by'] = 'created_at';
    }

    // Get list
    $result = $poService->list($params);

    // Format for datatables if requested
    if (isset($_GET['datatables']) && $_GET['datatables'] === 'true') {
        echo json_encode([
            'draw' => isset($_GET['draw']) ? (int)$_GET['draw'] : 1,
            'recordsTotal' => $result['total'],
            'recordsFiltered' => $result['total'],
            'data' => $result['data']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'data' => $result['data'],
            'pagination' => [
                'current_page' => $result['current_page'],
                'total_pages' => $result['total_pages'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
                'has_next' => $result['has_next'],
                'has_prev' => $result['has_prev']
            ],
            'filters' => $params,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INVALID_PARAMS',
            'message' => $e->getMessage()
        ]
    ]);
} catch (Exception $e) {
    error_log("PO List API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Failed to fetch purchase orders'
        ]
    ]);
}
