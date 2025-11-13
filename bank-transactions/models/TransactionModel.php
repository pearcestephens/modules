<?php
/**
 * TransactionModel - Bank deposits model
 *
 * Manages bank_transactions_current table
 *
 * @package CIS\BankTransactions\Models
 */

declare(strict_types=1);

namespace CIS\BankTransactions\Models;

require_once __DIR__ . '/BaseModel.php';

class TransactionModel extends BaseModel
{
    protected $table = 'bank_transactions_current';
    protected $primaryKey = 'id';

    /**
     * Find unmatched transactions
     *
     * @param int $limit Number of records
     * @param int $offset Offset for pagination
     * @param array $filters Filter criteria
     * @return array Transactions
     */
    public function findUnmatched(int $limit = 50, int $offset = 0, array $filters = []): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        // Apply filters
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        } else {
            // Default to unmatched and review
            $sql .= " AND status IN ('unmatched', 'review')";
        }

        if (!empty($filters['type'])) {
            $sql .= " AND transaction_type = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND transaction_date >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND transaction_date <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['store_id'])) {
            $sql .= " AND store_id = ?";
            $params[] = $filters['store_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (transaction_reference LIKE ? OR transaction_name LIKE ? OR notes LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY transaction_date DESC, id DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->query($sql, $params);
    }

    /**
     * Find transactions needing manual review
     *
     * @param int $limit Number of records
     * @return array Transactions
     */
    public function findNeedingReview(int $limit = 100): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE status = 'review'
                ORDER BY transaction_date DESC, id DESC
                LIMIT ?";

        return $this->query($sql, [$limit]);
    }

    /**
     * Find a transaction by ID
     *
     * @param int $id Transaction ID
     * @return array|null Transaction data or null if not found
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        $result = $this->query($sql, [$id]);
        return $result[0] ?? null;
    }

    /**
     * Update transaction status
     *
     * @param int $id Transaction ID
     * @param string $status New status
     * @param array $additionalData Additional fields to update
     * @return bool Success status
     */
    public function updateStatus(int $id, string $status, array $additionalData = []): bool
    {
        $data = array_merge($additionalData, [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return $this->update($id, $data);
    }

    /**
     * Get dashboard metrics
     *
     * @param string $date Date to get metrics for (Y-m-d)
     * @return array Metrics data
     */
    public function getDashboardMetrics(string $date): array
    {
        $sql = "SELECT
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'unmatched' THEN 1 END) as unmatched,
                    COUNT(CASE WHEN status = 'matched' THEN 1 END) as matched,
                    COUNT(CASE WHEN status = 'review' THEN 1 END) as review,
                    SUM(CASE WHEN status = 'unmatched' THEN transaction_amount ELSE 0 END) as unmatched_amount,
                    SUM(CASE WHEN status = 'matched' THEN transaction_amount ELSE 0 END) as matched_amount
                FROM {$this->table}
                WHERE transaction_date = ?";

        $result = $this->query($sql, [$date]);
        return $result[0] ?? [
            'total' => 0,
            'unmatched' => 0,
            'matched' => 0,
            'review' => 0,
            'unmatched_amount' => 0,
            'matched_amount' => 0
        ];
    }

    /**
     * Get transaction type breakdown
     *
     * @param string $date Date (Y-m-d)
     * @return array Type breakdown
     */
    public function getTypeBreakdown(string $date): array
    {
        $sql = "SELECT
                    transaction_type,
                    COUNT(*) as count,
                    SUM(transaction_amount) as total_amount,
                    COUNT(CASE WHEN status = 'matched' THEN 1 END) as matched_count,
                    COUNT(CASE WHEN status = 'unmatched' THEN 1 END) as unmatched_count
                FROM {$this->table}
                WHERE transaction_date = ?
                GROUP BY transaction_type
                ORDER BY count DESC";

        return $this->query($sql, [$date]);
    }

    /**
     * Get recent matches
     *
     * @param int $limit Number of records
     * @return array Recent matches
     */
    public function getRecentMatches(int $limit = 10): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE status = 'matched'
                AND matched_at IS NOT NULL
                ORDER BY matched_at DESC
                LIMIT ?";

        return $this->query($sql, [$limit]);
    }

    /**
     * Calculate auto-match rate for date range
     *
     * @param string $dateFrom Start date
     * @param string $dateTo End date
     * @return float Auto-match rate (0-100)
     */
    public function getAutoMatchRate(string $dateFrom, string $dateTo): float
    {
        $sql = "SELECT
                    COUNT(CASE WHEN matched_by = 'AUTO' THEN 1 END) as auto_matched,
                    COUNT(CASE WHEN status = 'matched' THEN 1 END) as total_matched
                FROM {$this->table}
                WHERE transaction_date BETWEEN ? AND ?";

        $result = $this->query($sql, [$dateFrom, $dateTo]);
        $data = $result[0] ?? ['auto_matched' => 0, 'total_matched' => 0];

        if ($data['total_matched'] == 0) {
            return 0.0;
        }

        return round(($data['auto_matched'] / $data['total_matched']) * 100, 2);
    }

    /**
     * Get average reconciliation time
     *
     * @param string $date Date (Y-m-d)
     * @return int Average minutes
     */
    public function getAvgReconciliationTime(string $date): int
    {
        $sql = "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, matched_at)) as avg_minutes
                FROM {$this->table}
                WHERE transaction_date = ?
                AND status = 'matched'
                AND matched_at IS NOT NULL";

        $result = $this->query($sql, [$date]);
        return (int)($result[0]['avg_minutes'] ?? 0);
    }
}
