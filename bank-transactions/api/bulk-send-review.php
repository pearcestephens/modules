<?php
/**
 * Bulk Send to Review API Endpoint
 *
 * Sends selected transactions to review queue
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../models/TransactionModel.php';
require_once __DIR__ . '/../lib/APIHelper.php';

use CIS\BankTransactions\API\APIHelper;

header('Content-Type: application/json');

// Require authentication (supports bot bypass)
$userId = APIHelper::requireAuth();

// Require permission (supports bot bypass)
APIHelper::requirePermission('bank_transactions.match');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    APIHelper::error('METHOD_NOT_ALLOWED', 'Only POST allowed', 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!APIHelper::validateCSRF($input['csrf_token'] ?? null)) {
        APIHelper::error('INVALID_CSRF_TOKEN', 'Invalid CSRF token', 403);
    }

    if (!isset($input['transaction_ids']) || !is_array($input['transaction_ids'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => ['code' => 'INVALID_INPUT', 'message' => 'Valid transaction_ids required']]);
        exit;
    }

    $transactionModel = new \CIS\BankTransactions\Models\TransactionModel();
    $updated = 0;

    foreach ($input['transaction_ids'] as $transactionId) {
        if (!is_numeric($transactionId)) continue;

        try {
            $transaction = $transactionModel->find((int)$transactionId);
            if ($transaction && $transaction['status'] !== 'matched') {
                $transactionModel->updateStatus((int)$transactionId, 'review', ['matched_by' => 'MANUAL']);
                $updated++;
            }
        } catch (Exception $e) {
            error_log("Error sending transaction $transactionId to review: " . $e->getMessage());
        }
    }

    echo json_encode(['success' => true, 'data' => ['updated' => $updated]]);

} catch (Exception $e) {
    error_log("Bulk Send to Review Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => 'An error occurred']]);
}
