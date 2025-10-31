<?php
/**
 * Auto-Match All Transactions API Endpoint
 *
 * Auto-matches all unmatched transactions for a specific date
 *
 * Method: POST
 * Parameters:
 *   - csrf_token: CSRF token (required)
 *   - date: Date in Y-m-d format (optional, default today)
 *
 * Response: JSON
 *   {
 *     "success": true,
 *     "data": {
 *       "matched": 10,
 *       "review": 5,
 *       "failed": 2,
 *       "total_processed": 17
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

    // Get date parameter
    $date = $input['date'] ?? date('Y-m-d');

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

    // Initialize models
    global $con;
    $transactionModel = new \CIS\BankTransactions\Models\TransactionModel();
    $orderModel = new \CIS\BankTransactions\Models\OrderModel();
    $paymentModel = new \CIS\BankTransactions\Models\PaymentModel();
    $matchingEngine = new \CIS\BankTransactions\Lib\MatchingEngine($con);
    $confidenceScorer = new \CIS\BankTransactions\Lib\ConfidenceScorer();

    // Get all unmatched transactions for date
    $transactions = $transactionModel->findUnmatched('unmatched', '', $date, $date, '', '', 1000, 0);

    // Counters
    $matched = 0;
    $review = 0;
    $failed = 0;
    $AUTO_MATCH_THRESHOLD = 200;
    $REVIEW_THRESHOLD = 150;

    // Process each transaction
    foreach ($transactions as $transaction) {
        try {
            // Find candidates
            $candidates = $matchingEngine->findCandidates($transaction);

            if (empty($candidates)) {
                $failed++;
                continue;
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

            // Determine action
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
                        'created_by' => $_SESSION['user_id']
                    ]);

                    // Update transaction status
                    $transactionModel->updateStatus(
                        $transaction['id'],
                        'matched',
                        $bestMatch['id'],
                        $paymentId,
                        $highestConfidence,
                        'AUTO'
                    );

                    $con->commit();
                    $matched++;

                } catch (Exception $e) {
                    $con->rollback();
                    $failed++;
                    error_log("Auto-match failed for transaction {$transaction['id']}: " . $e->getMessage());
                }

            } elseif ($highestConfidence >= $REVIEW_THRESHOLD) {
                // Send to review queue
                $transactionModel->updateStatus(
                    $transaction['id'],
                    'review',
                    null,
                    null,
                    $highestConfidence,
                    'SYSTEM'
                );
                $review++;

            } else {
                // Confidence too low
                $failed++;
            }

        } catch (Exception $e) {
            $failed++;
            error_log("Error processing transaction {$transaction['id']}: " . $e->getMessage());
        }
    }

    // Return results
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'matched' => $matched,
            'review' => $review,
            'failed' => $failed,
            'total_processed' => count($transactions),
            'date' => $date
        ]
    ]);

} catch (Exception $e) {
    // Log error
    error_log("Auto-Match All API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'An error occurred while auto-matching transactions'
        ]
    ]);
}
