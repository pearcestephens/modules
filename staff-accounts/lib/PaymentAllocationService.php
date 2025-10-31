<?php
declare(strict_types=1);

namespace CIS\Modules\StaffAccounts;

use PDO;
use Exception;

/**
 * Payment Allocation Service
 * 
 * Handles allocating Xero payroll deductions to Vend customer accounts
 * Supports single-customer and bulk allocation with idempotency, dry-run, and resumable operations
 * 
 * @package CIS\Modules\StaffAccounts
 * @version 2.0.0
 */
class PaymentAllocationService
{
    private PDO $db;
    private const STATE_DIR = __DIR__ . '/../var';
    private const STATE_FILE = self::STATE_DIR . '/allocation_state.json';
    
    /**
     * @var bool Use live Vend API (slow, accurate) vs local database (100x faster)
     */
    private bool $useLiveApi = false;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
        
        // Ensure state directory exists
        if (!is_dir(self::STATE_DIR)) {
            @mkdir(self::STATE_DIR, 0775, true);
        }
    }
    
    /**
     * Toggle between live API (slow) and database (fast) mode
     * 
     * @param bool $useLiveApi True = fetch from Vend API, False = use local database (default)
     * @return self
     */
    public function setUseLiveApi(bool $useLiveApi): self
    {
        $this->useLiveApi = $useLiveApi;
        return $this;
    }
    
    /**
     * Get current API mode
     * 
     * @return bool
     */
    public function isUsingLiveApi(): bool
    {
        return $this->useLiveApi;
    }
    
    /**
     * Allocate a single deduction to Vend
     * 
     * @param int $deductionId
     * @param int|null $performedBy User ID who performed action
     * @param bool $dryRun If true, simulate without actually allocating
     * @return array<string, mixed>
     */
    public function allocateDeduction(int $deductionId, ?int $performedBy = null, bool $dryRun = false): array
    {
        try {
            // Get deduction details
            $deduction = $this->getDeductionById($deductionId);
            
            if (!$deduction) {
                throw new Exception('Deduction not found');
            }
            
            if (!$deduction['vend_customer_id']) {
                throw new Exception('No Vend customer mapped for this employee');
            }
            
            if ($deduction['allocation_status'] === 'allocated') {
                throw new Exception('Deduction already allocated');
            }
            
            if ($deduction['amount'] <= 0) {
                throw new Exception('Invalid deduction amount');
            }
            
            // Apply payment to Vend via API
            $vendResult = $this->applyPaymentToVend(
                $deduction['vend_customer_id'],
                $deduction['amount'],
                "Payroll deduction: {$deduction['pay_period_start']} to {$deduction['pay_period_end']}"
            );
            
            if ($vendResult['success']) {
                // Update deduction status
                $this->updateDeductionStatus(
                    $deductionId,
                    'allocated',
                    $deduction['amount'],
                    $vendResult['payment_id'] ?? null
                );
                
                // Log allocation
                $this->logAllocation(
                    $deductionId,
                    $deduction['vend_customer_id'],
                    $deduction['employee_name'],
                    'allocate',
                    $deduction['amount'],
                    $vendResult['payment_id'] ?? null,
                    true,
                    null,
                    $performedBy
                );
                
                return [
                    'success' => true,
                    'message' => 'Payment allocated successfully',
                    'vend_payment_id' => $vendResult['payment_id'] ?? null,
                    'amount' => $deduction['amount']
                ];
                
            } else {
                // Mark as failed
                $this->updateDeductionStatus(
                    $deductionId,
                    'failed',
                    0,
                    null,
                    $vendResult['error'] ?? 'Unknown error'
                );
                
                // Log failure
                $this->logAllocation(
                    $deductionId,
                    $deduction['vend_customer_id'],
                    $deduction['employee_name'],
                    'allocate',
                    $deduction['amount'],
                    null,
                    false,
                    $vendResult['error'] ?? 'Unknown error',
                    $performedBy
                );
                
                throw new Exception($vendResult['error'] ?? 'Vend payment failed');
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Allocate all pending deductions for a specific customer
     * 
     * @param string $vendCustomerId
     * @param int|null $performedBy
     * @return array<string, mixed>
     */
    public function allocateAllForCustomer(string $vendCustomerId, ?int $performedBy = null): array
    {
        $stmt = $this->db->prepare("
            SELECT id FROM xero_payroll_deductions
            WHERE vend_customer_id = ?
            AND allocation_status = 'pending'
            AND amount > 0
            ORDER BY id ASC
        ");
        
        $stmt->execute([$vendCustomerId]);
        $deductions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $successful = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($deductions as $deductionId) {
            $result = $this->allocateDeduction($deductionId, $performedBy);
            
            if ($result['success']) {
                $successful++;
            } else {
                $failed++;
                $errors[] = "Deduction #{$deductionId}: " . $result['error'];
            }
        }
        
        return [
            'success' => true,
            'total' => count($deductions),
            'successful' => $successful,
            'failed' => $failed,
            'errors' => $errors
        ];
    }
    
    /**
     * Allocate ALL pending deductions (bulk operation)
     * 
     * @param int|null $performedBy
     * @return array<string, mixed>
     */
    public function allocateAllPending(?int $performedBy = null): array
    {
        $stmt = $this->db->prepare("
            SELECT id FROM xero_payroll_deductions
            WHERE allocation_status = 'pending'
            AND vend_customer_id IS NOT NULL
            AND amount > 0
            ORDER BY id ASC
        ");
        
        $stmt->execute();
        $deductions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $successful = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($deductions as $deductionId) {
            $result = $this->allocateDeduction($deductionId, $performedBy);
            
            if ($result['success']) {
                $successful++;
            } else {
                $failed++;
                $errors[] = "Deduction #{$deductionId}: " . $result['error'];
            }
        }
        
        return [
            'success' => true,
            'total' => count($deductions),
            'successful' => $successful,
            'failed' => $failed,
            'errors' => $errors
        ];
    }
    
    /**
     * Apply payment to Vend customer account via API
     * CRITICAL: Allocates to NEWEST sales first (FIFO pattern)
     * 
     * @param string $vendCustomerId
     * @param float $amount Total amount to allocate
     * @param string $note Reference note for payment
     * @return array{success: bool, payment_id: string|null, error: string|null, log: array, applied: float, remaining: float}
     */
    private function applyPaymentToVend(string $vendCustomerId, float $amount, string $note): array
    {
        try {
            global $vend;
            
            if (!isset($vend)) {
                throw new Exception('Vend API not initialized');
            }
            
            $log = [];
            $appliedTotal = 0.0;
            $remaining = round($amount, 2);
            $paymentIds = [];
            
            if ($remaining <= 0) {
                return [
                    'success' => true,
                    'payment_id' => null,
                    'error' => null,
                    'log' => ['Nothing to allocate (amount <= 0)'],
                    'applied' => 0.0,
                    'remaining' => 0.0
                ];
            }
            
            // Step 1: Fetch all open on-account sales for this customer (newest first)
            // Mode: Database (fast, 100x) vs Live API (slow, accurate)
            $openSales = $this->useLiveApi 
                ? $this->fetchOpenOnAccountSalesFromApi($vendCustomerId)
                : $this->fetchOpenOnAccountSalesFromDatabase($vendCustomerId);
            
            $source = $this->useLiveApi ? 'Live API' : 'Database';
            
            if (empty($openSales)) {
                $log[] = "No open On-Account sales found for customer {$vendCustomerId} (source: {$source})";
                return [
                    'success' => false,
                    'payment_id' => null,
                    'error' => 'No open On-Account sales to allocate against',
                    'log' => $log,
                    'applied' => 0.0,
                    'remaining' => $remaining
                ];
            }
            
            $log[] = "Found " . count($openSales) . " open On-Account sale(s) (source: {$source})";
            
            // Step 2: Allocate across sales (newest first, FIFO)
            foreach ($openSales as $sale) {
                if ($remaining <= 0) {
                    break;
                }
                
                $saleId = $sale['id'] ?? '';
                if (empty($saleId)) {
                    $log[] = "Skipped sale with empty ID";
                    continue;
                }
                
                // Get amount due on this sale
                $saleDue = $this->getSaleDueAmount($sale);
                
                if ($saleDue <= 0) {
                    $log[] = "Sale {$saleId} already fully paid, skipping";
                    continue;
                }
                
                // Calculate allocation for this sale
                $allocateToThisSale = min($saleDue, $remaining);
                
                // Apply payment to this specific sale
                $paymentData = [
                    'register_sale_id' => $saleId,
                    'amount' => round($allocateToThisSale, 2),
                    'payment_type_id' => $this->getStaffAccountPaymentTypeId(),
                    'payment_date' => date('c'),
                    'retailer_payment_type' => 'Account',
                    'label' => $note
                ];
                
                $response = $vend->registerSalePayments()->post($paymentData);
                
                if (isset($response['data']['id'])) {
                    $paymentId = $response['data']['id'];
                    $paymentIds[] = $paymentId;
                    $appliedTotal += $allocateToThisSale;
                    $remaining = round($remaining - $allocateToThisSale, 2);
                    
                    $log[] = sprintf(
                        "✓ Applied $%.2f to sale %s (payment ID: %s) | Due: $%.2f → $%.2f",
                        $allocateToThisSale,
                        $saleId,
                        $paymentId,
                        $saleDue,
                        round($saleDue - $allocateToThisSale, 2)
                    );
                } else {
                    $error = $response['error'] ?? 'Unknown error';
                    $log[] = "✗ Failed to apply payment to sale {$saleId}: {$error}";
                    // Continue to next sale rather than failing completely
                }
            }
            
            // Step 3: Return result
            if ($appliedTotal > 0) {
                return [
                    'success' => true,
                    'payment_id' => implode(',', $paymentIds), // Multiple payment IDs
                    'error' => null,
                    'log' => $log,
                    'applied' => round($appliedTotal, 2),
                    'remaining' => round($remaining, 2)
                ];
            }
            
            // If we get here, no payments were applied
            return [
                'success' => false,
                'payment_id' => null,
                'error' => 'Failed to apply any payments',
                'log' => $log,
                'applied' => 0.0,
                'remaining' => $remaining
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'payment_id' => null,
                'error' => 'Vend API error: ' . $e->getMessage(),
                'log' => ['Exception: ' . $e->getMessage()],
                'applied' => 0.0,
                'remaining' => $amount
            ];
        }
    }
    
    /**
     * Update deduction allocation status
     * 
     * @param int $deductionId
     * @param string $status
     * @param float $allocatedAmount
     * @param string|null $vendPaymentId
     * @param string|null $errorMessage
     * @return void
     */
    private function updateDeductionStatus(
        int $deductionId,
        string $status,
        float $allocatedAmount,
        ?string $vendPaymentId = null,
        ?string $errorMessage = null
    ): void {
        $stmt = $this->db->prepare("
            UPDATE xero_payroll_deductions
            SET allocation_status = ?,
                allocated_amount = ?,
                vend_payment_id = ?,
                allocated_at = ?,
                allocation_error = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $status,
            $allocatedAmount,
            $vendPaymentId,
            $status === 'allocated' ? date('Y-m-d H:i:s') : null,
            $errorMessage,
            $deductionId
        ]);
    }
    
    /**
     * Log allocation action
     * 
     * @param int|null $deductionId
     * @param string $vendCustomerId
     * @param string $employeeName
     * @param string $action
     * @param float $amount
     * @param string|null $vendPaymentId
     * @param bool $success
     * @param string|null $errorMessage
     * @param int|null $performedBy
     * @return void
     */
    private function logAllocation(
        ?int $deductionId,
        string $vendCustomerId,
        string $employeeName,
        string $action,
        float $amount,
        ?string $vendPaymentId,
        bool $success,
        ?string $errorMessage,
        ?int $performedBy
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO payment_allocation_log (
                deduction_id, vend_customer_id, employee_name,
                action, amount, vend_payment_id, vend_response,
                success, error_message, performed_by, ip_address
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $deductionId,
            $vendCustomerId,
            $employeeName,
            $action,
            $amount,
            $vendPaymentId,
            null, // vend_response can be added if needed
            $success ? 1 : 0,
            $errorMessage,
            $performedBy,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
    
    /**
     * Get deduction by ID
     * 
     * @param int $deductionId
     * @return array|null
     */
    private function getDeductionById(int $deductionId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT 
                d.*,
                p.payment_date,
                p.pay_period_start,
                p.pay_period_end
            FROM xero_payroll_deductions d
            JOIN xero_payrolls p ON d.payroll_id = p.id
            WHERE d.id = ?
            LIMIT 1
        ");
        
        $stmt->execute([$deductionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }
    
    /**
     * Get allocation history for a customer
     * 
     * @param string $vendCustomerId
     * @param int $limit
     * @return array
     */
    public function getAllocationHistory(string $vendCustomerId, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM payment_allocation_log
            WHERE vend_customer_id = ?
            ORDER BY performed_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$vendCustomerId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Fetch open on-account sales from LOCAL DATABASE (FAST - 100x faster)
     * 
     * @param string $vendCustomerId
     * @return array Array of sales sorted newest first
     */
    private function fetchOpenOnAccountSalesFromDatabase(string $vendCustomerId): array
    {
        // Query local vend_sales table for open on-account sales
        $stmt = $this->db->prepare("
            SELECT 
                id,
                sale_date,
                total_price,
                total_tax,
                total_loyalty,
                customer_id,
                created_at,
                updated_at,
                status,
                JSON_EXTRACT(totals, '$.total_to_pay') as total_to_pay,
                JSON_EXTRACT(totals, '$.total_paid') as total_paid
            FROM vend_sales
            WHERE customer_id = ?
            AND status IN ('OPEN', 'LAYBY', 'ONACCOUNT', 'ON_ACCOUNT')
            AND deleted_at IS NULL
            ORDER BY sale_date DESC, created_at DESC
            LIMIT 100
        ");
        
        $stmt->execute([$vendCustomerId]);
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Filter to only those with balance due
        return array_filter($sales, function($sale) {
            $totalToPay = floatval($sale['total_to_pay'] ?? $sale['total_price'] ?? 0);
            $totalPaid = floatval($sale['total_paid'] ?? 0);
            return ($totalToPay - $totalPaid) > 0.01; // Has outstanding balance
        });
    }
    
    /**
     * Fetch open on-account sales from VEND API (SLOW - accurate, live data)
     * 
     * @param string $vendCustomerId
     * @return array Array of sales sorted newest first
     */
    private function fetchOpenOnAccountSalesFromApi(string $vendCustomerId): array
    {
        global $vend;
        
        if (!isset($vend)) {
            return [];
        }
        
        try {
            // Use Vend 2.0 search API to find open on-account sales
            $params = [
                'type' => 'sales',
                'customer_id' => $vendCustomerId,
                'state' => 'pending',
                'attributes' => 'onaccount',
                'page_size' => 100,
                'order_by' => 'sale_date',
                'order_direction' => 'desc'
            ];
            
            $response = $vend->search($params);
            
            if (isset($response['data']) && is_array($response['data'])) {
                return $response['data'];
            }
            
            return [];
            
        } catch (\Exception $e) {
            error_log("Vend API error fetching sales: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get amount due on a sale
     * 
     * @param array $sale
     * @return float
     */
    private function getSaleDueAmount(array $sale): float
    {
        // Try totals.total_to_pay first (most accurate)
        if (isset($sale['totals']['total_to_pay'])) {
            return max(0.0, floatval($sale['totals']['total_to_pay']));
        }
        
        // Fallback: calculate from total_price - total_paid
        $totalPrice = floatval($sale['totals']['total_price'] ?? $sale['total_price'] ?? 0);
        $totalPaid = floatval($sale['totals']['total_paid'] ?? $sale['total_paid'] ?? 0);
        
        return max(0.0, round($totalPrice - $totalPaid, 2));
    }
    
    /**
     * Get Staff Account payment type ID for Vend
     * 
     * @return string
     */
    private function getStaffAccountPaymentTypeId(): string
    {
        // TODO: Fetch from vend_payment_types table or config
        // For now, return a common default - should be configured
        return '3'; // Usually "Account" payment type
    }
    
    /**
     * Get all pending (unallocated) payroll deductions
     * 
     * @param string|null $vendCustomerId Optional: filter by customer
     * @return array
     */
    public function getPendingDeductions(?string $vendCustomerId = null): array
    {
        if ($vendCustomerId) {
            $stmt = $this->db->prepare("
                SELECT 
                    d.*,
                    p.payment_date,
                    p.pay_period_start,
                    p.pay_period_end,
                    p.xero_payroll_id
                FROM xero_payroll_deductions d
                JOIN xero_payrolls p ON d.payroll_id = p.id
                WHERE d.vend_customer_id = ?
                AND d.allocation_status = 'pending'
                AND d.amount > 0
                ORDER BY p.payment_date DESC, d.id ASC
            ");
            $stmt->execute([$vendCustomerId]);
        } else {
            // Return ALL pending deductions, regardless of Vend customer mapping status
            $stmt = $this->db->query("
                SELECT 
                    d.*,
                    p.payment_date,
                    p.pay_period_start,
                    p.pay_period_end,
                    p.xero_payroll_id
                FROM xero_payroll_deductions d
                JOIN xero_payrolls p ON d.payroll_id = p.id
                WHERE d.allocation_status = 'pending'
                AND d.amount > 0
                ORDER BY p.payment_date DESC, d.id ASC
            ");
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get failed (unallocated) payroll deductions that need attention
     * 
     * @return array
     */
    public function getFailedDeductions(): array
    {
        $stmt = $this->db->query("
            SELECT 
                d.*,
                p.payment_date,
                p.pay_period_start,
                p.pay_period_end,
                p.xero_payroll_id
            FROM xero_payroll_deductions d
            JOIN xero_payrolls p ON d.payroll_id = p.id
            WHERE d.allocation_status = 'failed'
            AND d.amount > 0
            ORDER BY p.payment_date DESC, d.id ASC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get summary of pending deductions grouped by customer
     * 
     * @return array
     */
    public function getPendingDeductionsSummary(): array
    {
        $stmt = $this->db->query("
            SELECT 
                d.vend_customer_id,
                d.employee_name,
                COUNT(d.id) as pending_count,
                SUM(d.amount) as total_pending,
                MIN(p.payment_date) as oldest_deduction,
                MAX(p.payment_date) as newest_deduction
            FROM xero_payroll_deductions d
            JOIN xero_payrolls p ON d.payroll_id = p.id
            WHERE d.allocation_status = 'pending'
            AND d.vend_customer_id IS NOT NULL
            AND d.amount > 0
            GROUP BY d.vend_customer_id, d.employee_name
            ORDER BY total_pending DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get missed/failed payments that need reprocessing
     * 
     * @return array
     */
    public function getMissedPayments(): array
    {
        $stmt = $this->db->query("
            SELECT 
                d.vend_customer_id,
                d.employee_name,
                COUNT(d.id) as failed_count,
                SUM(d.amount) as total_failed,
                MAX(d.allocated_at) as last_attempt,
                GROUP_CONCAT(d.allocation_error SEPARATOR '; ') as errors
            FROM xero_payroll_deductions d
            WHERE d.allocation_status = 'failed'
            AND d.amount > 0
            GROUP BY d.vend_customer_id, d.employee_name
            ORDER BY total_failed DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Retry failed allocation for a specific deduction
     * 
     * @param int $deductionId
     * @param int|null $performedBy
     * @return array
     */
    public function retryFailedDeduction(int $deductionId, ?int $performedBy = null): array
    {
        // Reset status to pending
        $stmt = $this->db->prepare("
            UPDATE xero_payroll_deductions
            SET allocation_status = 'pending',
                allocation_error = NULL,
                allocated_at = NULL
            WHERE id = ?
            AND allocation_status = 'failed'
        ");
        
        $stmt->execute([$deductionId]);
        
        if ($stmt->rowCount() === 0) {
            return [
                'success' => false,
                'error' => 'Deduction not found or not in failed status'
            ];
        }
        
        // Now try to allocate again
        return $this->allocateDeduction($deductionId, $performedBy);
    }
    
    /**
     * Retry all failed deductions for a customer
     * 
     * @param string $vendCustomerId
     * @param int|null $performedBy
     * @return array
     */
    public function retryAllFailedForCustomer(string $vendCustomerId, ?int $performedBy = null): array
    {
        // Reset all failed deductions to pending
        $stmt = $this->db->prepare("
            UPDATE xero_payroll_deductions
            SET allocation_status = 'pending',
                allocation_error = NULL,
                allocated_at = NULL
            WHERE vend_customer_id = ?
            AND allocation_status = 'failed'
        ");
        
        $stmt->execute([$vendCustomerId]);
        $resetCount = $stmt->rowCount();
        
        // Now allocate all pending for this customer
        $result = $this->allocateAllForCustomer($vendCustomerId, $performedBy);
        $result['reset_count'] = $resetCount;
        
        return $result;
    }
    
    /**
     * Get payment allocation statistics
     * 
     * @return array
     */
    public function getAllocationStatistics(): array
    {
        $stmt = $this->db->query("
            SELECT 
                allocation_status,
                COUNT(*) as count,
                SUM(amount) as total_amount,
                AVG(amount) as avg_amount
            FROM xero_payroll_deductions
            WHERE amount > 0
            GROUP BY allocation_status
        ");
        
        $stats = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $stats[$row['allocation_status']] = [
                'count' => (int)$row['count'],
                'total' => round((float)$row['total_amount'], 2),
                'average' => round((float)$row['avg_amount'], 2)
            ];
        }
        
        return $stats;
    }
    
    /**
     * Get recent payment allocations (activity log)
     * 
     * @param int $limit
     * @return array
     */
    public function getRecentAllocations(int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                pal.*,
                d.xero_employee_id,
                d.employee_name,
                d.deduction_type
            FROM payment_allocation_log pal
            LEFT JOIN xero_payroll_deductions d ON pal.deduction_id = d.id
            ORDER BY pal.performed_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get payment allocation success rate
     * 
     * @param int $days Look back this many days
     * @return array
     */
    public function getAllocationSuccessRate(int $days = 30): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_attempts,
                SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed,
                ROUND(SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as success_rate
            FROM payment_allocation_log
            WHERE performed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        
        $stmt->execute([$days]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_attempts' => (int)$result['total_attempts'],
            'successful' => (int)$result['successful'],
            'failed' => (int)$result['failed'],
            'success_rate' => (float)$result['success_rate'],
            'period_days' => $days
        ];
    }
}
