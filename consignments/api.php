<?php
/**
 * Consignments JSON API
 *
 * Modern REST-like API for AJAX consignment operations.
 * All requests must be POST with JSON payload.
 *
 * Actions:
 * - recent: Get recent consignments
 * - get: Get single consignment with items
 * - create: Create new consignment (requires CSRF)
 * - add_item: Add item to consignment (requires CSRF)
 * - status: Update consignment status (requires CSRF)
 * - search: Search consignments by ref_code/outlet
 * - stats: Get consignment statistics
 *
 * Request format:
 * {
 *   "action": "recent",
 *   "data": { ...action-specific params... }
 * }
 *
 * Response format (success):
 * {
 *   "ok": true,
 *   "data": { ...results... },
 *   "time": "2025-10-31T10:30:00+00:00"
 * }
 *
 * Response format (error):
 * {
 *   "ok": false,
 *   "error": "Error message",
 *   "meta": { ...additional context... },
 *   "time": "2025-10-31T10:30:00+00:00"
 * }
 *
 * @package CIS\Consignments\API
 * @version 1.0.0
 * @created 2025-10-31
 */

declare(strict_types=1);

// Bootstrap module
require_once __DIR__ . '/bootstrap.php';

// Load service layer
require_once __DIR__ . '/ConsignmentService.php';

// Load security helpers (if outside workspace, use global functions)
if (file_exists(__DIR__ . '/../../assets/functions/security.php')) {
    require_once __DIR__ . '/../../assets/functions/security.php';
}

// Ensure JSON helpers exist (fallback implementation)
if (!function_exists('json_ok')) {
    function json_ok(array $data = [], int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'data' => $data,
            'time' => date(DATE_ATOM)
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('json_fail')) {
    function json_fail(string $msg, int $code = 400, array $meta = []): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => $msg,
            'meta' => $meta,
            'time' => date(DATE_ATOM)
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('csrf_require')) {
    function csrf_require(string $tokenFromForm): void {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $valid = isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$tokenFromForm);
        if (!$valid) {
            error_log('[CSRF_FAIL] Consignments API - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            json_fail('Invalid CSRF token', 403);
        }
    }
}

// Set headers
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Only POST allowed
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_fail('POST required', 405);
}

// Parse JSON payload
$rawBody = file_get_contents('php://input') ?: '{}';
$payload = json_decode($rawBody, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    json_fail('Invalid JSON: ' . json_last_error_msg(), 400);
}

$action = (string)($payload['action'] ?? '');
$data = (array)($payload['data'] ?? []);

// Validate action
if ($action === '') {
    json_fail('action required', 400, [
        'allowed' => ['recent', 'get', 'create', 'add_item', 'status', 'search', 'stats']
    ]);
}

try {
    // Create service instance
    $svc = ConsignmentService::make();

    // Route action
    switch ($action) {

        // ====================================================================
        // READ OPERATIONS (no CSRF required)
        // ====================================================================

        case 'recent':
            $limit = (int)($data['limit'] ?? 50);
            $rows = $svc->recent($limit);
            json_ok(['rows' => $rows, 'count' => count($rows)]);
            break;

        case 'get':
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                json_fail('id required', 400);
            }

            $consignment = $svc->get($id);
            if (!$consignment) {
                json_fail('Consignment not found', 404, ['id' => $id]);
            }

            $items = $svc->items($id);

            json_ok([
                'consignment' => $consignment,
                'items' => $items
            ]);
            break;

        case 'search':
            $refCode = (string)($data['ref_code'] ?? '');
            $outletId = isset($data['outlet_id']) ? (int)$data['outlet_id'] : null;
            $limit = (int)($data['limit'] ?? 50);

            $rows = $svc->search($refCode, $outletId, $limit);

            json_ok(['rows' => $rows, 'count' => count($rows)]);
            break;

        case 'stats':
            $outletId = isset($data['outlet_id']) ? (int)$data['outlet_id'] : null;
            $stats = $svc->stats($outletId);
            json_ok($stats);
            break;

        // ====================================================================
        // WRITE OPERATIONS (CSRF required)
        // ====================================================================

        case 'create':
            csrf_require((string)($data['csrf'] ?? ''));

            // Validate required fields
            $required = ['ref_code', 'origin_outlet_id', 'dest_outlet_id', 'created_by'];
            $missing = [];
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    $missing[] = $field;
                }
            }

            if (!empty($missing)) {
                json_fail('Missing required fields', 400, ['missing' => $missing]);
            }

            $created = $svc->create($data);

            json_ok($created, 201);
            break;

        case 'add_item':
            csrf_require((string)($data['csrf'] ?? ''));

            $consignmentId = (int)($data['consignment_id'] ?? 0);
            if ($consignmentId <= 0) {
                json_fail('consignment_id required', 400);
            }

            // Validate item has required fields
            $required = ['product_id', 'sku', 'qty'];
            $missing = [];
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    $missing[] = $field;
                }
            }

            if (!empty($missing)) {
                json_fail('Missing required item fields', 400, ['missing' => $missing]);
            }

            $result = $svc->addItem($consignmentId, $data);

            json_ok($result, 201);
            break;

        case 'status':
            csrf_require((string)($data['csrf'] ?? ''));

            $id = (int)($data['id'] ?? 0);
            $status = (string)($data['status'] ?? '');

            if ($id <= 0) {
                json_fail('id required', 400);
            }

            if ($status === '') {
                json_fail('status required', 400, [
                    'allowed' => ['draft', 'sent', 'receiving', 'received', 'completed', 'cancelled']
                ]);
            }

            // Use updateStatus which validates the status value
            try {
                $updated = $svc->updateStatus($id, $status);
                json_ok(['updated' => $updated, 'id' => $id, 'status' => $status]);
            } catch (\InvalidArgumentException $e) {
                // Status validation or illegal transition
                json_fail($e->getMessage(), 422, [
                    'field' => 'status',
                    'value' => $status
                ]);
            }
            break;

        case 'update_item_qty':
            csrf_require((string)($data['csrf'] ?? ''));

            $itemId = (int)($data['item_id'] ?? 0);
            $packedQty = (int)($data['packed_qty'] ?? 0);

            if ($itemId <= 0) {
                json_fail('item_id required', 400);
            }

            try {
                $updated = $svc->updateItemPackedQty($itemId, $packedQty);
                json_ok(['updated' => $updated, 'item_id' => $itemId, 'packed_qty' => $packedQty]);
            } catch (\InvalidArgumentException $e) {
                json_fail($e->getMessage(), 422, [
                    'field' => 'packed_qty',
                    'value' => $packedQty
                ]);
            }
            break;

        // ====================================================================
        // UNKNOWN ACTION
        // ====================================================================

        default:
            json_fail('Unknown action: ' . $action, 400, [
                'allowed' => ['recent', 'get', 'create', 'add_item', 'status', 'search', 'stats', 'update_item_qty']
            ]);
    }

} catch (RuntimeException $e) {
    // Expected exceptions (RW connection, validation, etc.)
    error_log('[ConsignmentsAPI] RuntimeException: ' . $e->getMessage());
    json_fail($e->getMessage(), 500);

} catch (PDOException $e) {
    // Database errors
    error_log('[ConsignmentsAPI] PDOException: ' . $e->getMessage());
    json_fail('Database error', 500, [
        'code' => $e->getCode(),
        'debug' => defined('ENVIRONMENT') && ENVIRONMENT === 'development' ? $e->getMessage() : null
    ]);

} catch (Throwable $e) {
    // Catch-all for unexpected errors
    error_log('[ConsignmentsAPI] Throwable: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    json_fail('Server error', 500, [
        'type' => get_class($e),
        'debug' => defined('ENVIRONMENT') && ENVIRONMENT === 'development' ? $e->getMessage() : null
    ]);
}
