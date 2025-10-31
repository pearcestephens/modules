<?php
/**
 * Dashboard Metrics API Endpoint
 *
 * Returns dashboard metrics for a specific date
 *
 * Method: GET
 * Parameters:
 *   - date: Date in Y-m-d format (optional, default today)
 *
 * Response: JSON
 *   {
 *     "success": true,
 *     "data": {
 *       "metrics": {
 *         "total": 150,
 *         "unmatched": 45,
 *         "unmatched_amount": 12500.50,
 *         "matched": 100,
 *         "matched_amount": 45600.75,
 *         "review": 5
 *       },
 *       "type_breakdown": [...]
 *     }
 *   }
 */

declare(strict_types=1);

// Include application bootstrap
require_once __DIR__ . '/../bootstrap.php';

// Load required files
require_once __DIR__ . '/../controllers/BaseController.php';
require_once __DIR__ . '/../models/TransactionModel.php';
require_once __DIR__ . '/../lib/APIHelper.php';

use CIS\BankTransactions\API\APIHelper;

// CORS headers for AJAX
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Require authentication (supports bot bypass)
$userId = APIHelper::requireAuth();

// Require permission (supports bot bypass)
APIHelper::requirePermission('bank_transactions.view');

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    APIHelper::error('METHOD_NOT_ALLOWED', 'Only GET requests are allowed', 405);
}

try {
    // Get date parameter
    $date = $_GET['date'] ?? date('Y-m-d');

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'INVALID_DATE',
                'message' => 'Date must be in Y-m-d format'
            ]
        ]);
        exit;
    }

    // Validate date is real
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'INVALID_DATE',
                'message' => 'Invalid date provided'
            ]
        ]);
        exit;
    }

    // Initialize model
    $transactionModel = new \CIS\BankTransactions\Models\TransactionModel();

    // Get metrics - handle query failures gracefully
    try {
        $metrics = $transactionModel->getDashboardMetrics($date) ?? ['total' => 0, 'unmatched' => 0, 'unmatched_amount' => 0, 'matched' => 0];
        $typeBreakdown = $transactionModel->getTypeBreakdown($date) ?? [];
    } catch (\Exception $e) {
        $metrics = ['total' => 0, 'unmatched' => 0, 'unmatched_amount' => 0, 'matched' => 0];
        $typeBreakdown = [];
    }

    // Return success response using APIHelper
    APIHelper::success([
        'metrics' => $metrics,
        'type_breakdown' => $typeBreakdown,
        'date' => $date,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    // Log error
    error_log("Dashboard Metrics API Error: " . $e->getMessage());

    // Return error response using APIHelper
    APIHelper::error('INTERNAL_ERROR', 'An error occurred while fetching dashboard metrics', 500);
}
