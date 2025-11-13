<?php
/**
 * Bank Transactions - Transaction Service Library
 *
 * High-level service for transaction operations
 *
 * @package CIS\BankTransactions\Lib
 * @author Pearce Stephens
 */

declare(strict_types=1);

namespace CIS\BankTransactions\Lib;

class TransactionService {

    private $con;
    private $transactionModel;
    private $matchingEngine;
    private $paymentProcessor;

    public function __construct($connection = null) {
        global $con;
        $this->con = $connection ?? $con;

        require_once __DIR__ . '/../models/TransactionModel.php';
        require_once __DIR__ . '/MatchingEngine.php';
        require_once __DIR__ . '/PaymentProcessor.php';

        $this->transactionModel = new \CIS\BankTransactions\Models\TransactionModel($this->con);
        $this->matchingEngine = new MatchingEngine($this->con);
        $this->paymentProcessor = new PaymentProcessor($this->con);
    }

    /**
     * Import bank transactions
     */
    public function importTransactions(array $transactions): array {
        $imported = [];
        $errors = [];

        $this->con->beginTransaction();

        try {
            foreach ($transactions as $idx => $txn) {
                try {
                    // Validate transaction data
                    if (!isset($txn['amount'], $txn['date'], $txn['reference'])) {
                        $errors[] = "Transaction $idx: Missing required fields";
                        continue;
                    }

                    // Check for duplicates
                    $existing = $this->findDuplicate($txn);
                    if ($existing) {
                        $errors[] = "Transaction $idx: Duplicate found (ID: {$existing['id']})";
                        continue;
                    }

                    // Create transaction record
                    $data = [
                        'transaction_amount' => (float)$txn['amount'],
                        'transaction_date' => $txn['date'],
                        'transaction_type' => $txn['type'] ?? 'debit',
                        'transaction_reference' => $txn['reference'],
                        'description' => $txn['description'] ?? '',
                        'status' => 'unmatched',
                        'created_at' => date('Y-m-d H:i:s'),
                    ];

                    $txnId = $this->transactionModel->create($data);
                    $imported[] = $txnId;

                } catch (Exception $e) {
                    $errors[] = "Transaction $idx: " . $e->getMessage();
                }
            }

            $this->con->commit();

        } catch (Exception $e) {
            $this->con->rollBack();
            throw $e;
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
            'total' => count($transactions),
            'success_count' => count($imported),
            'error_count' => count($errors),
        ];
    }

    /**
     * Find duplicate transaction
     */
    private function findDuplicate(array $txn): ?array {
        $stmt = $this->con->prepare(
            "SELECT * FROM bank_transactions
             WHERE transaction_amount = :amount
             AND transaction_date = DATE(:date)
             AND transaction_reference = :reference
             LIMIT 1"
        );

        $stmt->execute([
            'amount' => $txn['amount'],
            'date' => $txn['date'],
            'reference' => $txn['reference']
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Auto-match transactions
     */
    public function autoMatchTransactions(array $transactionIds = [], int $threshold = 85): array {
        $results = [
            'matched' => 0,
            'unmatched' => 0,
            'errors' => [],
        ];

        $transactions = empty($transactionIds)
            ? $this->transactionModel->getUnmatched()
            : $this->transactionModel->getByIds($transactionIds);

        foreach ($transactions as $txn) {
            try {
                $suggestions = $this->matchingEngine->getSuggestions($txn['id'], 1);

                if (!empty($suggestions)) {
                    $topSuggestion = $suggestions[0];

                    if ($topSuggestion['confidence'] >= $threshold) {
                        // Create payment and update transaction
                        $paymentData = [
                            'order_id' => $topSuggestion['order_id'],
                            'amount' => $txn['transaction_amount'],
                            'payment_date' => $txn['transaction_date'],
                            'payment_type' => $txn['transaction_type'],
                            'reference' => $txn['transaction_reference'],
                            'notes' => 'Auto-matched',
                            'created_by' => 0,
                        ];

                        $paymentId = $this->paymentProcessor->createPayment($paymentData);

                        $updateData = [
                            'order_id' => $topSuggestion['order_id'],
                            'payment_id' => $paymentId,
                            'matched_by' => 'SYSTEM',
                            'matched_at' => date('Y-m-d H:i:s'),
                            'status' => 'matched',
                            'confidence' => (int)$topSuggestion['confidence'],
                        ];

                        $this->transactionModel->update($txn['id'], $updateData);
                        $results['matched']++;
                    } else {
                        $results['unmatched']++;
                    }
                } else {
                    $results['unmatched']++;
                }

            } catch (Exception $e) {
                $results['errors'][] = "Transaction {$txn['id']}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Get transactions requiring review
     */
    public function getTransactionsForReview(int $limit = 100): array {
        return $this->transactionModel->getByStatus('pending_review', $limit);
    }

    /**
     * Mark transactions for manual review
     */
    public function markForReview(array $transactionIds, string $reason = ''): int {
        $count = 0;
        foreach ($transactionIds as $txnId) {
            $updateData = [
                'status' => 'pending_review',
                'review_reason' => $reason,
                'review_requested_at' => date('Y-m-d H:i:s'),
            ];
            if ($this->transactionModel->update($txnId, $updateData)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get transaction statistics
     */
    public function getStatistics(): array {
        $stmt = $this->con->query(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'matched' THEN 1 ELSE 0 END) as matched,
                SUM(CASE WHEN status = 'unmatched' THEN 1 ELSE 0 END) as unmatched,
                SUM(CASE WHEN status = 'pending_review' THEN 1 ELSE 0 END) as pending,
                SUM(transaction_amount) as total_amount,
                AVG(transaction_amount) as avg_amount
             FROM bank_transactions"
        );

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Export transactions
     */
    public function exportTransactions(array $filters = []): array {
        $query = "SELECT * FROM bank_transactions WHERE 1=1";
        $params = [];
        $types = '';

        if (isset($filters['status'])) {
            $query .= " AND status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (isset($filters['date_from'])) {
            $query .= " AND transaction_date >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }

        if (isset($filters['date_to'])) {
            $query .= " AND transaction_date <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }

        $stmt = $this->con->prepare($query);

        if (!empty($params)) {
            $stmt->execute($params);
        } else {
            $stmt->execute();
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
