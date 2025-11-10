<?php
/**
 * Bulk Auto-Match API Endpoint
 *
 * Auto-matches selected transaction IDs
 *
 * Method: POST
 * Parameters:
 *   - csrf_token: CSRF token (required)
 *   - transaction_ids: Array of transaction IDs (required)
 *
 * Response: JSON
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../controllers/BaseController.php';
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../models/TransactionModel.php';
require_once __DIR__ . '/../models/PaymentModel.php';
require_once __DIR__ . '/../lib/MatchingEngine.php';
require_once __DIR__ . '/../lib/ConfidenceScorer.php';
require_once __DIR__ . '/../lib/APIHelper.php';

use CIS\BankTransactions\API\APIHelper;

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Require authentication (supports bot bypass)
$userId = APIHelper::requireAuth();

// Require permission (supports bot bypass)
APIHelper::requirePermission('bank_transactions.match');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    APIHelper::error('METHOD_NOT_ALLOWED', 'Only POST requests are allowed', 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!APIHelper::validateCSRF($input['csrf_token'] ?? null)) {
        APIHelper::error('INVALID_CSRF_TOKEN', 'Invalid CSRF token', 403);
    }

    if (!isset($input['transaction_ids']) || !is_array($input['transaction_ids']) || empty($input['transaction_ids'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => ['code' => 'INVALID_INPUT', 'message' => 'Valid transaction_ids array is required']]);
        exit;
    }

    global $con;
    $transactionModel = new \CIS\BankTransactions\Models\TransactionModel();
    $paymentModel = new \CIS\BankTransactions\Models\PaymentModel();
    $matchingEngine = new \CIS\BankTransactions\Lib\MatchingEngine($con);
    $confidenceScorer = new \CIS\BankTransactions\Lib\ConfidenceScorer();

    $matched = 0;
    $review = 0;
    $failed = 0;
    $AUTO_MATCH_THRESHOLD = 200;
    $REVIEW_THRESHOLD = 150;

    foreach ($input['transaction_ids'] as $transactionId) {
        if (!is_numeric($transactionId)) {
            $failed++;
            continue;
        }

        try {
            $transaction = $transactionModel->findById((int)$transactionId);
            if (!$transaction || $transaction['status'] === 'matched') {
                $failed++;
                continue;
            }

            $candidates = $matchingEngine->findCandidates($transaction);
            if (empty($candidates)) {
                $failed++;
                continue;
            }

            $bestMatch = null;
            $highestConfidence = 0;
            foreach ($candidates as $candidate) {
                $confidence = $confidenceScorer->calculateConfidence($transaction, $candidate);
                if ($confidence > $highestConfidence) {
                    $highestConfidence = $confidence;
                    $bestMatch = $candidate;
                }
            }

            if ($highestConfidence >= $AUTO_MATCH_THRESHOLD) {
                $con->begin_transaction();
                try {
                    $paymentId = $paymentModel->create([
                        'order_id' => $bestMatch['id'],
                        'amount' => $transaction['transaction_amount'],
                        'payment_date' => $transaction['transaction_date'],
                        'payment_type' => 'bank_transfer',
                        'reference' => $transaction['transaction_reference'],
                        'created_by' => $_SESSION['userID']
                    ]);

                    $transactionModel->updateStatus($transaction['id'], 'matched', $bestMatch['id'], $paymentId, $highestConfidence, 'AUTO');
                    $con->commit();
                    $matched++;
                } catch (Exception $e) {
                    $con->rollback();
                    $failed++;
                }
            } elseif ($highestConfidence >= $REVIEW_THRESHOLD) {
                $transactionModel->updateStatus($transaction['id'], 'review', null, null, $highestConfidence, 'SYSTEM');
                $review++;
            } else {
                $failed++;
            }
        } catch (Exception $e) {
            $failed++;
            error_log("Bulk auto-match error for transaction $transactionId: " . $e->getMessage());
        }
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'matched' => $matched,
            'review' => $review,
            'failed' => $failed,
            'total_processed' => count($input['transaction_ids'])
        ]
    ]);

} catch (Exception $e) {
    error_log("Bulk Auto-Match API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => 'An error occurred']]);
}
