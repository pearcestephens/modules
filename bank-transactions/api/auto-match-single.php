<?php
/**
 * Auto-Match Single Transaction API Endpoint
 *
 * Auto-matches a single transaction using AI confidence scoring
 *
 * Method: POST
 * Parameters:
 *   - csrf_token: CSRF token (required)
 *   - transaction_id: Transaction ID (required)
 *
 * Response: JSON
 *   {
 *     "success": true,
 *     "data": {
 *       "status": "matched|review|unmatched",
 *       "confidence": 245,
 *       "order_id": 12345,
 *       "message": "Transaction matched successfully"
 *     }
 *   }
 */

declare(strict_types=1);

// Include application bootstrap
require_once __DIR__ . '/../bootstrap.php';

// Load required files
require_once __DIR__ . '/../controllers/BaseController.php';
require_once __DIR__ . '/../models/TransactionModel.php';
require_once __DIR__ . '/../models/OrderModel.php';
require_once __DIR__ . '/../models/PaymentModel.php';
require_once __DIR__ . '/../lib/MatchingEngine.php';
require_once __DIR__ . '/../lib/ConfidenceScorer.php';
require_once __DIR__ . '/../lib/APIHelper.php';

use CIS\BankTransactions\API\APIHelper;

// CORS headers for AJAX
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Require authentication (supports bot bypass)
$userId = APIHelper::requireAuth();

// Require permission (supports bot bypass)
APIHelper::requirePermission('bank_transactions.match');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    APIHelper::error('METHOD_NOT_ALLOWED', 'Only POST requests are allowed', 405);
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate CSRF token (supports bot bypass)
    if (!APIHelper::validateCSRF($input['csrf_token'] ?? null)) {
        APIHelper::error('INVALID_CSRF_TOKEN', 'Invalid CSRF token', 403);
    }

    // Validate transaction_id
    if (!isset($input['transaction_id']) || !is_numeric($input['transaction_id'])) {
        APIHelper::error('INVALID_INPUT', 'Valid transaction_id is required', 400);
    }

    $transactionId = (int)$input['transaction_id'];

    // Initialize models
    global $con;
    $transactionModel = new \CIS\BankTransactions\Models\TransactionModel();
    $orderModel = new \CIS\BankTransactions\Models\OrderModel();
    $paymentModel = new \CIS\BankTransactions\Models\PaymentModel();

    // Find transaction
    $transaction = $transactionModel->findById($transactionId);
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

    // Check if already matched
    if ($transaction['status'] === 'matched') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'ALREADY_MATCHED',
                'message' => 'Transaction is already matched'
            ]
        ]);
        exit;
    }

    // Initialize matching engine
    $matchingEngine = new \CIS\BankTransactions\Lib\MatchingEngine($con);
    $confidenceScorer = new \CIS\BankTransactions\Lib\ConfidenceScorer();

    // Find candidates
    $candidates = $matchingEngine->findCandidates($transaction);

    if (empty($candidates)) {
        // No matches found
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => [
                'status' => 'unmatched',
                'confidence' => 0,
                'order_id' => null,
                'message' => 'No suitable matches found'
            ]
        ]);
        exit;
    }

    // Score candidates
    $bestMatch = null;
    $highestConfidence = 0;

    foreach ($candidates as $candidate) {
        $confidence = $confidenceScorer->calculateConfidence($transaction, $candidate);
        if ($confidence > $highestConfidence) {
            $highestConfidence = $confidence;
            $bestMatch = $candidate;
        }
    }

    // Determine action based on confidence threshold
    $AUTO_MATCH_THRESHOLD = 200;
    $REVIEW_THRESHOLD = 150;

    if ($highestConfidence >= $AUTO_MATCH_THRESHOLD) {
        // Auto-match
        $con->begin_transaction();

        try {
            // Create payment
            $paymentId = $paymentModel->create([
                'order_id' => $bestMatch['id'],
                'amount' => $transaction['transaction_amount'],
                'payment_date' => $transaction['transaction_date'],
                'payment_type' => 'bank_transfer',
                'reference' => $transaction['transaction_reference'],
                'created_by' => $_SESSION['userID']
            ]);

            // Update transaction status
            $transactionModel->updateStatus(
                $transactionId,
                'matched',
                $bestMatch['id'],
                $paymentId,
                $highestConfidence,
                'AUTO'
            );

            $con->commit();

            // Return success
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => [
                    'status' => 'matched',
                    'confidence' => $highestConfidence,
                    'order_id' => $bestMatch['id'],
                    'payment_id' => $paymentId,
                    'message' => 'Transaction matched successfully'
                ]
            ]);

        } catch (Exception $e) {
            $con->rollback();
            throw $e;
        }

    } elseif ($highestConfidence >= $REVIEW_THRESHOLD) {
        // Send to review queue
        $transactionModel->updateStatus(
            $transactionId,
            'review',
            null,
            null,
            $highestConfidence,
            'SYSTEM'
        );

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => [
                'status' => 'review',
                'confidence' => $highestConfidence,
                'order_id' => $bestMatch['id'],
                'message' => 'Transaction sent to review queue'
            ]
        ]);

    } else {
        // Confidence too low, leave unmatched
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => [
                'status' => 'unmatched',
                'confidence' => $highestConfidence,
                'order_id' => null,
                'message' => 'Confidence too low for matching'
            ]
        ]);
    }

} catch (Exception $e) {
    // Log error
    error_log("Auto-Match Single API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'An error occurred while matching the transaction'
        ]
    ]);
}
