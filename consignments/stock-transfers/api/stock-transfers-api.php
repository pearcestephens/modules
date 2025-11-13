<?php
/**
 * Stock Transfers API Backend
 *
 * Handles AJAX requests for stock transfers list view
 * Actions: get_counts, get_transfers, get_transfer_detail
 *
 * @package CIS\Consignments\StockTransfers
 * @version 4.0.0
 * @created 2025-11-10
 */

declare(strict_types=1);

// JSON-only mode - set headers first
header('Content-Type: application/json; charset=utf-8');

// Error handling for JSON responses
set_exception_handler(function(Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'SERVER_ERROR',
            'message' => $e->getMessage()
        ],
        'meta' => ['timestamp' => date('c')]
    ]);
    exit;
});

// Load bootstrap
require_once __DIR__ . '/../../bootstrap.php';

// Authentication check
if (!function_exists('requireAuth')) {
    function requireAuth() {
        if (empty($_SESSION['user_id'])) {
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
    }
}

requireAuth();

// Get request data
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? '';

// Route to action handlers
switch ($action) {
    case 'get_counts':
        handleGetCounts();
        break;

    case 'get_transfers':
        handleGetTransfers($input);
        break;

    case 'get_transfer_detail':
        handleGetTransferDetail($input);
        break;

    default:
        respondError('INVALID_ACTION', 'Invalid or missing action');
}

/**
 * Get transfer counts by state
 */
function handleGetCounts() {
    try {
        $uid = $_SESSION['user_id'] ?? null;

        // Get all counts
        $countsAll = getTransferCountsByState('STOCK');

        // Get user's counts
        $countsMine = [];
        if ($uid) {
            $countsMine = getTransferCountsByState('STOCK', ['created_by' => (int)$uid]);
        }

        $counts = [
            'TOTAL' => $countsAll['TOTAL'] ?? 0,
            'OPEN' => $countsAll['OPEN'] ?? 0,
            'SENT' => $countsAll['SENT'] ?? 0,
            'RECEIVING' => $countsAll['RECEIVING'] ?? 0,
            'RECEIVED' => $countsAll['RECEIVED'] ?? 0,
            'MINE' => $countsMine['TOTAL'] ?? 0
        ];

        respondSuccess($counts);
    } catch (Throwable $e) {
        error_log('get_counts failed: ' . $e->getMessage());
        respondError('FETCH_ERROR', 'Failed to load counts');
    }
}

/**
 * Get transfers list with optional filters
 */
function handleGetTransfers(array $input) {
    try {
        $filters = $input['filters'] ?? [];
        $state = $filters['state'] ?? '';
        $scope = $filters['scope'] ?? '';
        $limit = $input['limit'] ?? 50;

        // Build options
        $opts = [];
        if ($state !== '') {
            $opts['state'] = $state;
        }
        if ($scope === 'mine') {
            $uid = $_SESSION['user_id'] ?? null;
            if ($uid) {
                $opts['created_by'] = (int)$uid;
            }
        }

        // Fetch transfers
        $transfers = getRecentTransfersEnrichedDB($limit, 'STOCK', $opts);

        respondSuccess($transfers);
    } catch (Throwable $e) {
        error_log('get_transfers failed: ' . $e->getMessage());
        respondError('FETCH_ERROR', 'Failed to load transfers');
    }
}

/**
 * Get single transfer detail
 */
function handleGetTransferDetail(array $input) {
    try {
        $id = $input['id'] ?? '';
        if (!$id) {
            respondError('INVALID_INPUT', 'Transfer ID required');
            return;
        }

        // Fetch single transfer
        $transfers = getRecentTransfersEnrichedDB(1, 'STOCK', ['id' => $id]);

        if (empty($transfers)) {
            respondError('NOT_FOUND', 'Transfer not found');
            return;
        }

        respondSuccess($transfers[0]);
    } catch (Throwable $e) {
        error_log('get_transfer_detail failed: ' . $e->getMessage());
        respondError('FETCH_ERROR', 'Failed to load transfer details');
    }
}

/**
 * Send success response
 */
function respondSuccess($data) {
    echo json_encode([
        'success' => true,
        'data' => $data,
        'meta' => [
            'timestamp' => date('c'),
            'server_time' => time()
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Send error response
 */
function respondError(string $code, string $message, int $httpCode = 400) {
    http_response_code($httpCode);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => $code,
            'message' => $message
        ],
        'meta' => ['timestamp' => date('c')]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
