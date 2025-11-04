<?php
declare(strict_types=1);

namespace PayrollModule\Services;

use PDO;

/**
 * Vend Allocation Service
 *
 * Allocates pending Vend account deductions to payroll runs.
 * Implements FIFO allocation with idempotency and rate limiting.
 *
 * Critical for processing the 248 pending deductions.
 *
 * @package PayrollModule\Services
 * @version 1.0.0
 */
class VendAllocationService
{
    private PDO $pdo;
    private const RATE_LIMIT_PER_MINUTE = 100;
    private const MAX_RETRIES = 4;
    private const INITIAL_BACKOFF_MS = 500;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Allocate pending Vend deductions to a pay run
     *
     * @param int $payRunId Pay run ID
     * @param array $options Allocation options
     * @return array Allocation result with summary
     */
    public function allocateToPayRun(int $payRunId, array $options = []): array
    {
        $dryRun = $options['dry_run'] ?? false;
        $staffIdFilter = $options['staff_id'] ?? null;
        $maxAmount = $options['max_amount'] ?? null;

        try {
            $this->pdo->beginTransaction();

            // Get pay run details
            $payRun = $this->getPayRun($payRunId);
            if (!$payRun) {
                throw new \Exception("Pay run not found: $payRunId");
            }

            // Get pending deductions (FIFO order)
            $pending = $this->getPendingDeductions($staffIdFilter);

            $allocated = [];
            $failed = [];
            $totalAmount = 0;

            foreach ($pending as $deduction) {
                // Check rate limit
                if (!$this->checkRateLimit($deduction['staff_id'])) {
                    $failed[] = [
                        'deduction_id' => $deduction['id'],
                        'reason' => 'Rate limit exceeded'
                    ];
                    continue;
                }

                // Check max amount
                if ($maxAmount && ($totalAmount + $deduction['amount']) > $maxAmount) {
                    break;
                }

                // Generate idempotency key
                $idempotencyKey = $this->generateIdempotencyKey(
                    $payRunId,
                    $deduction['staff_id'],
                    $deduction['amount'],
                    $payRun['payslip_number'] ?? null
                );

                // Check if already allocated
                if ($this->isAlreadyAllocated($idempotencyKey)) {
                    continue;
                }

                // Allocate (with retry logic)
                if (!$dryRun) {
                    $success = $this->allocateWithRetry($deduction, $payRunId, $idempotencyKey);

                    if ($success) {
                        $allocated[] = $deduction;
                        $totalAmount += $deduction['amount'];
                        $this->recordRateLimit($deduction['staff_id']);
                    } else {
                        $failed[] = [
                            'deduction_id' => $deduction['id'],
                            'reason' => 'Allocation failed after retries'
                        ];
                    }
                } else {
                    // Dry run - just collect what would be allocated
                    $allocated[] = $deduction;
                    $totalAmount += $deduction['amount'];
                }
            }

            if (!$dryRun) {
                $this->pdo->commit();
            } else {
                $this->pdo->rollBack();
            }

            return [
                'success' => true,
                'pay_run_id' => $payRunId,
                'dry_run' => $dryRun,
                'summary' => [
                    'allocated_count' => count($allocated),
                    'failed_count' => count($failed),
                    'total_amount' => $totalAmount,
                    'pending_remaining' => count($pending) - count($allocated)
                ],
                'allocated' => $allocated,
                'failed' => $failed
            ];

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logError('Allocation failed', [
                'pay_run_id' => $payRunId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'pay_run_id' => $payRunId
            ];
        }
    }

    /**
     * Get pending Vend deductions (FIFO order)
     */
    private function getPendingDeductions(?int $staffIdFilter = null): array
    {
        $sql = "SELECT
                    id,
                    staff_id,
                    amount,
                    transaction_date,
                    description,
                    created_at
                FROM payroll_vend_deductions
                WHERE status = 'pending'
                AND allocated_payrun_id IS NULL";

        $params = [];

        if ($staffIdFilter) {
            $sql .= " AND staff_id = ?";
            $params[] = $staffIdFilter;
        }

        $sql .= " ORDER BY transaction_date ASC, created_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get pay run details
     */
    private function getPayRun(int $payRunId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM payroll_pay_runs WHERE id = ? LIMIT 1
        ");
        $stmt->execute([$payRunId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Generate idempotency key
     */
    private function generateIdempotencyKey(
        int $payRunId,
        int $staffId,
        float $amount,
        ?string $payslipNumber
    ): string {
        $amountCents = (int)round($amount * 100);
        $data = "payroll|{$payRunId}|{$staffId}|{$amountCents}|{$payslipNumber}";

        return hash('sha256', $data);
    }

    /**
     * Check if deduction already allocated using idempotency key
     */
    private function isAlreadyAllocated(string $idempotencyKey): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM payroll_allocations
            WHERE idempotency_key = ?
        ");
        $stmt->execute([$idempotencyKey]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Allocate with exponential backoff retry
     */
    private function allocateWithRetry(
        array $deduction,
        int $payRunId,
        string $idempotencyKey
    ): bool {
        $attempt = 0;
        $backoffMs = self::INITIAL_BACKOFF_MS;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $this->insertAllocation($deduction, $payRunId, $idempotencyKey);
                return true;

            } catch (\Throwable $e) {
                $attempt++;

                if ($attempt >= self::MAX_RETRIES) {
                    $this->sendToDeadLetterQueue($deduction, $payRunId, $e->getMessage());
                    return false;
                }

                // Exponential backoff
                usleep($backoffMs * 1000);
                $backoffMs *= 2;
            }
        }

        return false;
    }

    /**
     * Insert allocation record
     */
    private function insertAllocation(
        array $deduction,
        int $payRunId,
        string $idempotencyKey
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO payroll_allocations (
                pay_run_id,
                staff_id,
                deduction_id,
                amount,
                idempotency_key,
                allocated_at,
                created_at
            ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $payRunId,
            $deduction['staff_id'],
            $deduction['id'],
            $deduction['amount'],
            $idempotencyKey
        ]);

        // Update deduction status
        $updateStmt = $this->pdo->prepare("
            UPDATE payroll_vend_deductions
            SET status = 'allocated',
                allocated_payrun_id = ?,
                allocated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$payRunId, $deduction['id']]);
    }

    /**
     * Check rate limit (100 allocations per minute per staff)
     */
    private function checkRateLimit(int $staffId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM payroll_rate_limits
            WHERE staff_id = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $stmt->execute([$staffId]);

        $count = (int)$stmt->fetchColumn();

        return $count < self::RATE_LIMIT_PER_MINUTE;
    }

    /**
     * Record rate limit entry
     */
    private function recordRateLimit(int $staffId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO payroll_rate_limits (staff_id, created_at)
            VALUES (?, NOW())
        ");
        $stmt->execute([$staffId]);
    }

    /**
     * Send failed allocation to dead letter queue
     */
    private function sendToDeadLetterQueue(
        array $deduction,
        int $payRunId,
        string $error
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO payroll_dead_letter_queue (
                entity_type,
                entity_id,
                pay_run_id,
                error_message,
                payload,
                created_at
            ) VALUES ('vend_deduction', ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $deduction['id'],
            $payRunId,
            $error,
            json_encode($deduction)
        ]);
    }

    /**
     * Generate reconciliation report
     */
    public function generateReconciliationReport(int $payRunId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                a.*,
                d.description,
                d.transaction_date,
                u.full_name as staff_name
            FROM payroll_allocations a
            JOIN payroll_vend_deductions d ON a.deduction_id = d.id
            JOIN users u ON a.staff_id = u.id
            WHERE a.pay_run_id = ?
            ORDER BY u.full_name, a.allocated_at
        ");
        $stmt->execute([$payRunId]);
        $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by staff
        $byStaff = [];
        $totalAmount = 0;

        foreach ($allocations as $alloc) {
            $staffId = $alloc['staff_id'];

            if (!isset($byStaff[$staffId])) {
                $byStaff[$staffId] = [
                    'staff_id' => $staffId,
                    'staff_name' => $alloc['staff_name'],
                    'allocations' => [],
                    'total_amount' => 0,
                    'count' => 0
                ];
            }

            $byStaff[$staffId]['allocations'][] = $alloc;
            $byStaff[$staffId]['total_amount'] += $alloc['amount'];
            $byStaff[$staffId]['count']++;
            $totalAmount += $alloc['amount'];
        }

        return [
            'pay_run_id' => $payRunId,
            'total_allocated' => count($allocations),
            'total_amount' => $totalAmount,
            'by_staff' => array_values($byStaff),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Dry run allocation (preview without committing)
     */
    public function dryRun(int $payRunId, array $options = []): array
    {
        $options['dry_run'] = true;
        return $this->allocateToPayRun($payRunId, $options);
    }

    /**
     * Get allocation statistics
     */
    public function getStats(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                COUNT(*) as total_pending,
                SUM(amount) as total_amount,
                MIN(transaction_date) as oldest_transaction,
                COUNT(DISTINCT staff_id) as affected_staff
            FROM payroll_vend_deductions
            WHERE status = 'pending'
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Log error
     */
    private function logError(string $message, array $context = []): void
    {
        error_log(sprintf(
            'VendAllocationService Error: %s | Context: %s',
            $message,
            json_encode($context)
        ));
    }
}
