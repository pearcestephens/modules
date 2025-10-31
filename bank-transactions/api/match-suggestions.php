<?php
/**
 * Match Suggestions API Endpoint
 *
 * Returns AI-powered match suggestions with confidence breakdown
 */

declare(strict_types=1);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../models/TransactionModel.php';
require_once __DIR__ . '/../models/OrderModel.php';
require_once __DIR__ . '/../lib/MatchingEngine.php';
require_once __DIR__ . '/../lib/ConfidenceScorer.php';
require_once __DIR__ . '/../lib/APIHelper.php';

use CIS\BankTransactions\API\APIHelper;

// Require authentication (supports bot bypass)
$userId = APIHelper::requireAuth();

// Require permission (supports bot bypass)
APIHelper::requirePermission('bank_transactions.view');

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    APIHelper::error('METHOD_NOT_ALLOWED', 'Only GET requests allowed', 405);
}

try {
    // Validate transaction_id parameter
    $transactionId = $_GET['transaction_id'] ?? null;

    if (!$transactionId || !is_numeric($transactionId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'INVALID_PARAMETER',
                'message' => 'Valid transaction_id required'
            ]
        ]);
        exit;
    }

    $transactionId = (int)$transactionId;

    // Load models
    $transactionModel = new \CIS\BankTransactions\Models\TransactionModel();
    $orderModel = new \CIS\BankTransactions\Models\OrderModel();
    $matchingEngine = new \CIS\BankTransactions\Lib\MatchingEngine();
    $confidenceScorer = new \CIS\BankTransactions\Lib\ConfidenceScorer();

    // Load transaction
    try {
        $transaction = $transactionModel->findById($transactionId);
    } catch (\Exception $e) {
        $transaction = null;
    }

    if (!$transaction) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'NOT_FOUND',
                'message' => 'Transaction not found'
            ]
        ]);
        exit;
    }

    // Find candidates
    try {
        $candidates = $matchingEngine->findCandidates($transaction);
    } catch (\Exception $e) {
        $candidates = [];
    }

    if (empty($candidates)) {
        echo json_encode([
            'success' => true,
            'data' => [
                'transaction_id' => $transactionId,
                'suggestions' => [],
                'message' => 'No matching candidates found'
            ]
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // Score all candidates and prepare detailed breakdown
    $suggestions = [];

    foreach ($candidates as $candidate) {
        // Calculate confidence score
        $confidence = $confidenceScorer->calculateConfidence($transaction, $candidate);

        // Get confidence breakdown (individual factors)
        $breakdown = $confidenceScorer->getConfidenceBreakdown($transaction, $candidate);

        // Load full order details
        $orderDetails = $orderModel->findById($candidate['id']);

        $suggestions[] = [
            'order_id' => $candidate['id'],
            'confidence' => $confidence,
            'status' => $confidence >= 200 ? 'auto' : ($confidence >= 150 ? 'review' : 'low'),
            'breakdown' => $breakdown,
            'order_details' => [
                'total_amount' => $orderDetails['total_price'] ?? 0,
                'customer_name' => $orderDetails['customer_first_name'] . ' ' . $orderDetails['customer_last_name'],
                'outlet_name' => $orderDetails['outlet_name'] ?? '',
                'order_date' => $orderDetails['order_date'] ?? '',
                'order_time' => $orderDetails['order_time'] ?? '',
                'invoice_number' => $orderDetails['invoice_number'] ?? '',
                'status' => $orderDetails['status'] ?? ''
            ],
            'match_factors' => [
                'amount_match' => abs($transaction['transaction_amount'] - ($orderDetails['total_price'] ?? 0)) < 0.01,
                'date_proximity' => abs(strtotime($transaction['transaction_date']) - strtotime($orderDetails['order_date'] ?? '')) / 86400,
                'name_similarity' => $breakdown['customer_name_match'] ?? 0,
                'reference_match' => $breakdown['reference_match'] ?? 0
            ]
        ];
    }

    // Sort by confidence descending
    usort($suggestions, function($a, $b) {
        return $b['confidence'] <=> $a['confidence'];
    });

    // Return top 5 suggestions
    $topSuggestions = array_slice($suggestions, 0, 5);

    echo json_encode([
        'success' => true,
        'data' => [
            'transaction_id' => $transactionId,
            'transaction' => [
                'amount' => $transaction['transaction_amount'],
                'date' => $transaction['transaction_date'],
                'reference' => $transaction['transaction_reference'],
                'name' => $transaction['transaction_name'],
                'type' => $transaction['transaction_type']
            ],
            'suggestions' => $topSuggestions,
            'total_candidates' => count($candidates),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log("Match Suggestions Error: " . $e->getMessage());
    error_log($e->getTraceAsString());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Failed to retrieve suggestions'
        ]
    ]);
}
