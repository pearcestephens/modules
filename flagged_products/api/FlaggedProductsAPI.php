<?php
/**
 * Flagged Products API Module
 *
 * RESTful API for flagged products operations
 * Handles AJAX requests from frontend
 *
 * @package CIS\Modules\FlaggedProducts
 * @version 1.0.0
 */

// Bootstrap the module
require_once __DIR__ . '/../bootstrap.php';

use CIS\FlaggedProducts\Model\FlaggedProductModel;

// Set JSON header
header('Content-Type: application/json');

// Get database connection
$db = db();

// Initialize model
$model = new FlaggedProductModel($db);

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? 'list';

try {
    switch ($method) {
        case 'GET':
            handleGet($model, $action);
            break;

        case 'POST':
            handlePost($model, $action);
            break;

        default:
            response(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    error_log("Flagged Products API Error: " . $e->getMessage());
    response(['success' => false, 'message' => 'Internal server error'], 500);
}

/**
 * Handle GET requests
 */
function handleGet($model, $action) {
    switch ($action) {
        case 'list':
            $outletId = $_GET['outlet_id'] ?? 0;
            if (!$outletId) {
                response(['success' => false, 'message' => 'outlet_id required'], 400);
            }
            $products = $model->getByOutlet($outletId);
            response(['success' => true, 'products' => $products]);
            break;

        case 'stats':
            $outletId = $_GET['outlet_id'] ?? 0;
            if (!$outletId) {
                response(['success' => false, 'message' => 'outlet_id required'], 400);
            }

            $stats = [
                'pending_count' => $model->getPendingCount($outletId),
                'accuracy' => $model->getAccuracyStats($outletId, 30)
            ];

            response(['success' => true, 'stats' => $stats]);
            break;

        case 'history':
            $outletId = $_GET['outlet_id'] ?? 0;
            if (!$outletId) {
                response(['success' => false, 'message' => 'outlet_id required'], 400);
            }
            $history = $model->getLast30Days($outletId);
            response(['success' => true, 'history' => $history]);
            break;

        case 'commonly_inaccurate':
            $outletId = $_GET['outlet_id'] ?? 0;
            $limit = $_GET['limit'] ?? 10;
            if (!$outletId) {
                response(['success' => false, 'message' => 'outlet_id required'], 400);
            }
            $products = $model->getCommonlyInaccurate($outletId, $limit);
            response(['success' => true, 'products' => $products]);
            break;

        case 'export':
            exportToCSV($model);
            break;

        default:
            response(['success' => false, 'message' => 'Invalid action'], 400);
    }
}

/**
 * Handle POST requests
 */
function handlePost($model, $action) {
    // Get JSON body
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        response(['success' => false, 'message' => 'Invalid JSON'], 400);
    }

    switch ($action) {
        case 'create':
            $required = ['product_id', 'outlet_id', 'reason', 'qty_before'];
            foreach ($required as $field) {
                if (!isset($input[$field])) {
                    response(['success' => false, 'message' => "{$field} is required"], 400);
                }
            }

            // Check if already exists
            if ($model->exists($input['product_id'], $input['outlet_id'], $input['reason'])) {
                response(['success' => false, 'message' => 'Product already flagged with this reason'], 409);
            }

            $id = $model->create($input);
            response(['success' => true, 'id' => $id, 'message' => 'Product flagged successfully']);
            break;

        case 'complete':
            $required = ['product_id', 'outlet_id', 'staff_id', 'qty_before', 'qty_after'];
            foreach ($required as $field) {
                if (!isset($input[$field])) {
                    response(['success' => false, 'message' => "{$field} is required"], 400);
                }
            }

            $result = $model->markComplete(
                $input['product_id'],
                $input['outlet_id'],
                $input['staff_id'],
                $input['qty_after'],
                $input['qty_before']
            );

            if ($result) {
                response(['success' => true, 'message' => 'Product marked as complete']);
            } else {
                response(['success' => false, 'message' => 'Failed to complete product'], 500);
            }
            break;

        case 'delete':
            $productId = $input['product_id'] ?? 0;
            $outletId = $input['outlet_id'] ?? 0;

            if (!$productId || !$outletId) {
                response(['success' => false, 'message' => 'product_id and outlet_id required'], 400);
            }

            $result = $model->delete($outletId);

            if ($result) {
                response(['success' => true, 'message' => 'Product flag deleted']);
            } else {
                response(['success' => false, 'message' => 'Failed to delete'], 500);
            }
            break;

        case 'bulk_complete':
            $outletId = $input['outlet_id'] ?? 0;
            $staffId = $input['staff_id'] ?? 0;

            if (!$outletId || !$staffId) {
                response(['success' => false, 'message' => 'outlet_id and staff_id required'], 400);
            }

            // Get all pending products
            $products = $model->getByOutlet($outletId);
            $count = 0;

            foreach ($products as $product) {
                if ($model->markComplete(
                    $product['product_id'],
                    $outletId,
                    $staffId,
                    $product['current_stock'],
                    $product['qty_before']
                )) {
                    $count++;
                }
            }

            response(['success' => true, 'count' => $count, 'message' => "Completed {$count} products"]);
            break;

        case 'delete_all':
            $outletId = $input['outlet_id'] ?? 0;

            if (!$outletId) {
                response(['success' => false, 'message' => 'outlet_id required'], 400);
            }

            $result = $model->delete($outletId);

            if ($result) {
                response(['success' => true, 'message' => 'All flagged products deleted']);
            } else {
                response(['success' => false, 'message' => 'Failed to delete'], 500);
            }
            break;

        default:
            response(['success' => false, 'message' => 'Invalid action'], 400);
    }
}

/**
 * Export flagged products to CSV
 */
function exportToCSV($model) {
    $outletId = $_GET['outlet_id'] ?? 0;

    if (!$outletId) {
        response(['success' => false, 'message' => 'outlet_id required'], 400);
    }

    $products = $model->getByOutlet($outletId);

    if (empty($products)) {
        response(['success' => false, 'message' => 'No products to export'], 404);
    }

    // Set CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="flagged-products-outlet-' . $outletId . '-' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // CSV headers
    fputcsv($output, [
        'SKU',
        'Product Name',
        'Reason',
        'Qty Before',
        'Current Stock',
        'Flagged Date',
        'Staff ID',
        'Dummy Product',
        'Active'
    ]);

    // CSV data
    foreach ($products as $product) {
        fputcsv($output, [
            $product['sku'],
            $product['product_name'],
            $product['reason'],
            $product['qty_before'],
            $product['current_stock'],
            $product['flagged_datetime'],
            $product['staff_id'],
            $product['dummy_product'] ? 'Yes' : 'No',
            $product['active'] ? 'Yes' : 'No'
        ]);
    }

    fclose($output);
    exit;
}

/**
 * Send JSON response
 */
function response($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

/**
 * Get database connection helper
 */
function db() {
    // Use existing database connection from CIS
    global $mysqli;

    if (!isset($mysqli)) {
        require_once __DIR__ . '/../../../db.php';
    }

    return $mysqli;
}
