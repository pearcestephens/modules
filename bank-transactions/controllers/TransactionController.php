<?php
/**
 * TransactionController - Transaction list and detail management
 *
 * @package CIS\BankTransactions\Controllers
 */

declare(strict_types=1);

namespace CIS\BankTransactions\Controllers;

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/TransactionModel.php';
require_once __DIR__ . '/../models/OrderModel.php';
require_once __DIR__ . '/../models/PaymentModel.php';

use CIS\BankTransactions\Models\TransactionModel;
use CIS\BankTransactions\Models\OrderModel;
use CIS\BankTransactions\Models\PaymentModel;

class TransactionController extends BaseController
{
    private $transactionModel;
    private $orderModel;
    private $paymentModel;

    public function __construct()
    {
        parent::__construct();
        $this->requirePermission('bank_transactions.view');

        global $con, $vapeShedCon;
        $this->transactionModel = new TransactionModel($con);
        $this->orderModel = new OrderModel($vapeShedCon);
        $this->paymentModel = new PaymentModel($vapeShedCon);
    }

    /**
     * Display transaction list with filters
     */
    public function list(): void
    {
        $this->requirePermission('bank_transactions.view');

        // Get filters from query string
        $filters = [
            'status' => $_GET['status'] ?? '',
            'type' => $_GET['type'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
            'store_id' => $_GET['store_id'] ?? '',
            'search' => $_GET['search'] ?? ''
        ];

        // Pagination
        $page = (int)($_GET['page'] ?? 1);
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        // Get transactions
        $transactions = $this->transactionModel->findUnmatched(
            $filters['status'],
            $filters['type'],
            $filters['date_from'],
            $filters['date_to'],
            $filters['store_id'],
            $filters['search'],
            $perPage,
            $offset
        );

        // Get total count for pagination
        $totalCount = $this->transactionModel->countTransactions($filters);
        $totalPages = ceil($totalCount / $perPage);

        // Prepare pagination data
        $pagination = [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $totalCount,
            'total_pages' => $totalPages,
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $totalCount)
        ];

        // Load stores for filter dropdown
        $stores = $this->loadStores();

        // If AJAX request, return JSON
        if ($this->isAjax()) {
            $this->json([
                'transactions' => $transactions,
                'pagination' => $pagination
            ]);
            return;
        }

        // Render view
        $this->render('transaction-list', [
            'transactions' => $transactions,
            'filters' => $filters,
            'pagination' => $pagination,
            'stores' => $stores
        ]);
    }

    /**
     * Load stores from database
     *
     * @return array List of stores
     */
    private function loadStores(): array
    {
        $query = "SELECT id, name FROM vend_outlets ORDER BY name ASC";
        $result = $this->db->query($query);

        $stores = [];
        while ($row = $result->fetch_assoc()) {
            $stores[] = $row;
        }

        return $stores;
    }

    /**
     * Display transaction detail
     */
    public function detail(): void
    {
        $id = (int)($_GET['id'] ?? 0);

        if (!$id) {
            $this->abort(400, 'Transaction ID required');
        }

        $transaction = $this->transactionModel->find($id);

        if (!$transaction) {
            $this->abort(404, 'Transaction not found');
        }

        // Get match suggestions if unmatched or in review
        $suggestions = [];
        if (in_array($transaction['status'], ['unmatched', 'review'])) {
            $suggestions = $this->getMatchSuggestions($transaction);
        }

        // Get linked order details if matched
        $orderDetails = null;
        if ($transaction['order_id']) {
            $orderDetails = $this->orderModel->getOrderWithPayments($transaction['order_id']);
        }

        // Get audit trail
        $auditTrail = $this->getAuditTrail('transaction', $id);

        $this->render('views/transaction-detail.php', [
            'transaction' => $transaction,
            'suggestions' => $suggestions,
            'orderDetails' => $orderDetails,
            'auditTrail' => $auditTrail,
            'pageTitle' => 'Transaction #' . $id
        ]);
    }

    /**
     * Get match suggestions for transaction
     *
     * @param array $transaction Transaction data
     * @return array Suggestions
     */
    private function getMatchSuggestions(array $transaction): array
    {
        // Load matching engine and scorer
        require_once __DIR__ . '/../lib/MatchingEngine.php';
        require_once __DIR__ . '/../lib/ConfidenceScorer.php';

        global $vapeShedCon;
        $matchingEngine = new \CIS\BankTransactions\MatchingEngine($vapeShedCon, $this->db);
        $confidenceScorer = new \CIS\BankTransactions\ConfidenceScorer();

        // Find potential matches
        $candidates = $matchingEngine->findPotentialMatches($transaction);

        // Score each candidate
        $suggestions = [];
        foreach ($candidates as $candidate) {
            $score = $confidenceScorer->calculateScore($transaction, $candidate);

            $suggestions[] = [
                'order_id' => $candidate['order_id'],
                'customer_name' => $candidate['customer_name'],
                'amount' => $candidate['amount'],
                'date' => $candidate['date'],
                'outlet_name' => $candidate['outlet_name'],
                'confidence' => $score['total'],
                'confidence_level' => $score['confidence_level'],
                'breakdown' => $score['breakdown']
            ];
        }

        // Sort by confidence (highest first)
        usort($suggestions, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        return array_slice($suggestions, 0, 5); // Top 5 suggestions
    }

    /**
     * Get audit trail for entity
     *
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @return array Audit trail entries
     */
    private function getAuditTrail(string $entityType, int $entityId): array
    {
        $sql = "SELECT * FROM audit_trail
                WHERE entity_type = ? AND entity_id = ?
                ORDER BY created_at DESC
                LIMIT 50";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$entityType, $entityId]);

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if ($row['details']) {
                $row['details'] = json_decode($row['details'], true);
            }
            $results[] = $row;
        }

        return $results;
    }

    /**
     * Auto-match single transaction
     */
    public function autoMatch(): void
    {
        $this->validateCsrfToken();
        $this->requirePermission('bank_transactions.match');

        $validated = $this->validate([
            'transaction_id' => ['required' => true, 'type' => 'int']
        ]);

        $transactionId = $validated['transaction_id'];
        $transaction = $this->transactionModel->find($transactionId);

        if (!$transaction) {
            $this->error('Transaction not found', null, 404);
        }

        // Load matching engine and scorer
        require_once __DIR__ . '/../lib/MatchingEngine.php';
        require_once __DIR__ . '/../lib/ConfidenceScorer.php';

        global $vapeShedCon;
        $matchingEngine = new \CIS\BankTransactions\MatchingEngine($vapeShedCon, $this->db);
        $confidenceScorer = new \CIS\BankTransactions\ConfidenceScorer();

        // Find potential matches
        $candidates = $matchingEngine->findPotentialMatches($transaction);

        if (empty($candidates)) {
            $this->logAudit('transaction', $transactionId, 'auto_match_failed', [
                'reason' => 'No candidates found'
            ]);

            $this->error('No matching orders found');
        }

        // Score candidates
        $bestMatch = null;
        $bestScore = 0;

        foreach ($candidates as $candidate) {
            $score = $confidenceScorer->calculateScore($transaction, $candidate);

            if ($score['total'] > $bestScore) {
                $bestScore = $score['total'];
                $bestMatch = [
                    'candidate' => $candidate,
                    'score' => $score
                ];
            }
        }

        // Check confidence threshold
        if ($bestScore >= 200) {
            // High confidence - auto-match
            $result = $this->executeMatch(
                $transactionId,
                $bestMatch['candidate']['order_id'],
                $bestMatch['score'],
                'AUTO'
            );

            if ($result['success']) {
                $this->success($result, 'Transaction matched successfully');
            } else {
                $this->error($result['message'] ?? 'Match failed');
            }
        } elseif ($bestScore >= 150) {
            // Medium confidence - send to review
            $this->transactionModel->updateStatus($transactionId, 'review');

            $this->logAudit('transaction', $transactionId, 'sent_to_review', [
                'reason' => 'Confidence below auto-match threshold',
                'best_score' => $bestScore
            ]);

            $this->error('Confidence too low for auto-match. Sent to manual review.', [
                'action' => 'review',
                'best_score' => $bestScore,
                'threshold' => 200,
                'suggestion' => $bestMatch
            ]);
        } else {
            // Low confidence - leave unmatched
            $this->logAudit('transaction', $transactionId, 'no_match', [
                'reason' => 'All candidates below review threshold',
                'best_score' => $bestScore
            ]);

            $this->error('No confident matches found', [
                'best_score' => $bestScore,
                'threshold' => 150
            ]);
        }
    }

    /**
     * Manual match transaction to specific order
     */
    public function manualMatch(): void
    {
        $this->validateCsrfToken();
        $this->requirePermission('bank_transactions.match');

        $validated = $this->validate([
            'transaction_id' => ['required' => true, 'type' => 'int'],
            'order_id' => ['required' => true, 'type' => 'int'],
            'reason' => ['required' => false]
        ]);

        $transaction = $this->transactionModel->find($validated['transaction_id']);
        $order = $this->orderModel->find($validated['order_id']);

        if (!$transaction || !$order) {
            $this->error('Transaction or order not found', null, 404);
        }

        // Calculate confidence score
        require_once __DIR__ . '/../lib/ConfidenceScorer.php';
        $confidenceScorer = new \CIS\BankTransactions\ConfidenceScorer();
        $score = $confidenceScorer->calculateScore($transaction, $order);

        // Execute match
        $result = $this->executeMatch(
            $validated['transaction_id'],
            $validated['order_id'],
            $score,
            'MANUAL',
            $validated['reason'] ?? null
        );

        if ($result['success']) {
            $this->success($result, 'Transaction matched to order successfully');
        } else {
            $this->error($result['message'] ?? 'Match failed');
        }
    }

    /**
     * Execute match (create payment, update status, log)
     *
     * @param int $transactionId Transaction ID
     * @param int $orderId Order ID
     * @param array $score Confidence score data
     * @param string $matchType AUTO or MANUAL
     * @param string|null $reason Optional reason
     * @return array Result
     */
    private function executeMatch(
        int $transactionId,
        int $orderId,
        array $score,
        string $matchType,
        ?string $reason = null
    ): array {
        try {
            $this->db->beginTransaction();

            $transaction = $this->transactionModel->find($transactionId);

            // Check for duplicate payment
            if ($this->orderModel->hasPayment($orderId, $transaction['transaction_amount'], $transaction['transaction_date'])) {
                throw new \Exception('Payment already recorded for this order');
            }

            // Create payment record
            $paymentId = $this->paymentModel->createPayment(
                $orderId,
                $transaction['transaction_amount'],
                $transaction['transaction_date'],
                'bank_transfer',
                [
                    'transaction_id' => $transactionId,
                    'reference' => $transaction['transaction_reference'],
                    'confidence' => $score['total'],
                    'matched_by' => $matchType
                ]
            );

            if (!$paymentId) {
                throw new \Exception('Failed to create payment record');
            }

            // Update transaction status
            $this->transactionModel->update($transactionId, [
                'status' => 'matched',
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'matched_at' => date('Y-m-d H:i:s'),
                'matched_by' => $matchType,
                'matched_by_user_id' => $matchType === 'MANUAL' ? $this->currentUserId : null,
                'confidence_score' => $score['total']
            ]);

            // Log audit trail
            $this->logAudit('transaction', $transactionId, 'matched', [
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'confidence' => $score['total'],
                'match_type' => $matchType,
                'reason' => $reason,
                'breakdown' => $score['breakdown']
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'data' => [
                    'transaction_id' => $transactionId,
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'confidence' => $score['total'],
                    'match_type' => $matchType
                ]
            ];

        } catch (\Exception $e) {
            $this->db->rollback();

            $this->logAudit('transaction', $transactionId, 'match_failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
