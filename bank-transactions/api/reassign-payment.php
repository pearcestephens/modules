<?php
/**
 * Reassign Payment API Endpoint
 *
 * Reassigns a payment from one order to another with audit trail
 */

declare(strict_types=1);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../models/TransactionModel.php';
require_once __DIR__ . '/../models/OrderModel.php';
require_once __DIR__ . '/../models/PaymentModel.php';
require_once __DIR__ . '/../lib/APIHelper.php';

use CIS\BankTransactions\API\APIHelper;

// Require authentication (supports bot bypass)
$userId = APIHelper::requireAuth();

// Require permission (supports bot bypass)
APIHelper::requirePermission('bank_transactions.match');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    APIHelper::error('METHOD_NOT_ALLOWED', 'Only POST requests allowed', 405);
}

try {
    // Parse JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'INVALID_JSON',
                'message' => 'Invalid JSON input'
            ]
        ]);
        exit;
    }

    // Validate CSRF token
    if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'CSRF_VALIDATION_FAILED',
                'message' => 'Invalid CSRF token'
            ]
        ]);
        exit;
    }

    // Validate required parameters
    $transactionId = $input['transaction_id'] ?? null;
    $oldOrderId = $input['old_order_id'] ?? null;
    $newOrderId = $input['new_order_id'] ?? null;
    $reason = $input['reason'] ?? '';

    if (!$transactionId || !is_numeric($transactionId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'INVALID_TRANSACTION_ID',
                'message' => 'Valid transaction_id required'
            ]
        ]);
        exit;
    }

    if (!$oldOrderId || !is_numeric($oldOrderId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'INVALID_OLD_ORDER_ID',
                'message' => 'Valid old_order_id required'
            ]
        ]);
        exit;
    }

    if (!$newOrderId || !is_numeric($newOrderId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'INVALID_NEW_ORDER_ID',
                'message' => 'Valid new_order_id required'
            ]
        ]);
        exit;
    }

    if (trim($reason) === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'REASON_REQUIRED',
                'message' => 'Reason for reassignment required'
            ]
        ]);
        exit;
    }

    // Load models
    $transactionModel = new \CIS\BankTransactions\Models\TransactionModel();
    $orderModel = new \CIS\BankTransactions\Models\OrderModel();
    $paymentModel = new \CIS\BankTransactions\Models\PaymentModel();

    // Load transaction
    $transaction = $transactionModel->findById((int)$transactionId);
    if (!$transaction) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'TRANSACTION_NOT_FOUND',
                'message' => 'Transaction not found'
            ]
        ]);
        exit;
    }

    // Verify transaction is matched to old_order_id
    if ($transaction['order_id'] != $oldOrderId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'ORDER_MISMATCH',
                'message' => 'Transaction is not matched to the specified old order'
            ]
        ]);
        exit;
    }

    // Verify old order exists
    $oldOrder = $orderModel->findById((int)$oldOrderId);
    if (!$oldOrder) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'OLD_ORDER_NOT_FOUND',
                'message' => 'Old order not found'
            ]
        ]);
        exit;
    }

    // Verify new order exists
    $newOrder = $orderModel->findById((int)$newOrderId);
    if (!$newOrder) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'NEW_ORDER_NOT_FOUND',
                'message' => 'New order not found'
            ]
        ]);
        exit;
    }

    // Begin database transaction
    $con->begin_transaction();

    try {
        // Void old payment
        if ($transaction['payment_id']) {
            $oldPayment = $paymentModel->findById((int)$transaction['payment_id']);
            if ($oldPayment) {
                $paymentModel->void((int)$transaction['payment_id'], $_SESSION['userID'], 'Reassigned to order ' . $newOrderId);
            }
        }

        // Create new payment for new order
        $newPaymentData = [
            'order_id' => (int)$newOrderId,
            'amount' => $transaction['transaction_amount'],
            'payment_date' => $transaction['transaction_date'],
            'payment_type' => $transaction['transaction_type'],
            'reference' => $transaction['transaction_reference'],
            'notes' => 'Reassigned from order ' . $oldOrderId . '. Reason: ' . $reason,
            'created_by' => $_SESSION['userID']
        ];

        $newPaymentId = $paymentModel->create($newPaymentData);

        // Update transaction
        $updateData = [
            'order_id' => (int)$newOrderId,
            'payment_id' => $newPaymentId,
            'matched_by' => 'USER',
            'matched_at' => date('Y-m-d H:i:s')
        ];

        $transactionModel->update((int)$transactionId, $updateData);

        // Log to audit trail
        $auditData = [
            'transaction_id' => (int)$transactionId,
            'action' => 'reassign',
            'old_order_id' => (int)$oldOrderId,
            'new_order_id' => (int)$newOrderId,
            'old_payment_id' => $transaction['payment_id'],
            'new_payment_id' => $newPaymentId,
            'reason' => $reason,
            'performed_by' => $_SESSION['userID'],
            'performed_at' => date('Y-m-d H:i:s')
        ];

        $auditAction = 'reassign';
        $auditDetails = json_encode($auditData);
        $userId = (int)$_SESSION['userID'];

        $auditStmt = $con->prepare("INSERT INTO audit_trail (transaction_id, action, details, user_id, created_at) VALUES (?, ?, ?, ?, NOW())");
        $auditStmt->bind_param('issi', $transactionId, $auditAction, $auditDetails, $userId);
        $auditStmt->execute();

        // Commit transaction
        $con->commit();

        echo json_encode([
            'success' => true,
            'data' => [
                'transaction_id' => (int)$transactionId,
                'old_order_id' => (int)$oldOrderId,
                'new_order_id' => (int)$newOrderId,
                'old_payment_id' => $transaction['payment_id'],
                'new_payment_id' => $newPaymentId,
                'message' => 'Payment successfully reassigned'
            ]
        ], JSON_PRETTY_PRINT);

    } catch (Exception $e) {
        $con->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Reassign Payment Error: " . $e->getMessage());
    error_log($e->getTraceAsString());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Failed to reassign payment'
        ]
    ]);
}
