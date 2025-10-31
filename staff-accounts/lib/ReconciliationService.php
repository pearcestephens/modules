<?php
declare(strict_types=1);

namespace CIS\Modules\StaffAccounts;

use PDO;
use Exception;

/**
 * Reconciliation Service
 * 
 * Compares Xero payroll deductions with Vend customer balances
 * Identifies outstanding amounts and discrepancies
 * 
 * @package CIS\Modules\StaffAccounts
 * @version 1.0.0
 */
class ReconciliationService
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Run full reconciliation for all staff accounts
     * 
     * @return array<string, mixed>
     */
    public function runFullReconciliation(): array
    {
        try {
            // Get all unique Vend customers from deductions
            $stmt = $this->db->prepare("
                SELECT DISTINCT vend_customer_id, employee_name
                FROM xero_payroll_deductions
                WHERE vend_customer_id IS NOT NULL
            ");
            
            $stmt->execute();
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $reconciledCount = 0;
            $errors = [];
            
            foreach ($customers as $customer) {
                try {
                    $this->reconcileCustomer($customer['vend_customer_id']);
                    $reconciledCount++;
                } catch (Exception $e) {
                    $errors[] = "{$customer['employee_name']}: " . $e->getMessage();
                }
            }
            
            return [
                'success' => true,
                'reconciled' => $reconciledCount,
                'total' => count($customers),
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Reconcile a single customer
     * 
     * @param string $vendCustomerId
     * @return array<string, mixed>
     */
    public function reconcileCustomer(string $vendCustomerId): array
    {
        try {
            // Get Xero deduction totals
            $xeroData = $this->getXeroDeductionTotals($vendCustomerId);
            
            // Get current Vend balance
            $vendBalance = $this->getVendCustomerBalance($vendCustomerId);
            
            // Calculate outstanding amount
            // Outstanding = Vend Balance - Allocated Payments
            $outstanding = $vendBalance - $xeroData['total_allocated'];
            
            // Determine status
            $status = 'balanced';
            if ($outstanding > 0.01) {
                $status = 'underpaid'; // Customer still owes money
            } elseif ($outstanding < -0.01) {
                $status = 'overpaid'; // Customer paid more than owed
            }
            
            if ($xeroData['pending_allocation'] > 0) {
                $status = 'pending';
            }
            
            // Update or insert reconciliation record
            $this->updateReconciliation(
                $vendCustomerId,
                $xeroData['employee_name'],
                $xeroData['user_id'],
                $xeroData['total_deductions'],
                $xeroData['total_allocated'],
                $xeroData['pending_allocation'],
                $vendBalance,
                $outstanding,
                $status
            );
            
            return [
                'success' => true,
                'vend_customer_id' => $vendCustomerId,
                'employee_name' => $xeroData['employee_name'],
                'total_deductions' => $xeroData['total_deductions'],
                'total_allocated' => $xeroData['total_allocated'],
                'pending_allocation' => $xeroData['pending_allocation'],
                'vend_balance' => $vendBalance,
                'outstanding' => $outstanding,
                'status' => $status
            ];
            
        } catch (Exception $e) {
            throw new Exception('Reconciliation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get Xero deduction totals for a customer
     * 
     * @param string $vendCustomerId
     * @return array
     */
    private function getXeroDeductionTotals(string $vendCustomerId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                MAX(employee_name) as employee_name,
                MAX(user_id) as user_id,
                SUM(amount) as total_deductions,
                SUM(allocated_amount) as total_allocated,
                SUM(CASE WHEN allocation_status = 'pending' THEN amount ELSE 0 END) as pending_allocation
            FROM xero_payroll_deductions
            WHERE vend_customer_id = ?
        ");
        
        $stmt->execute([$vendCustomerId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'employee_name' => $result['employee_name'] ?? 'Unknown',
            'user_id' => $result['user_id'],
            'total_deductions' => (float)($result['total_deductions'] ?? 0),
            'total_allocated' => (float)($result['total_allocated'] ?? 0),
            'pending_allocation' => (float)($result['pending_allocation'] ?? 0)
        ];
    }
    
    /**
     * Get current Vend customer balance
     * 
     * @param string $vendCustomerId
     * @return float
     */
    private function getVendCustomerBalance(string $vendCustomerId): float
    {
        try {
            global $vend;
            
            if (!isset($vend)) {
                throw new Exception('Vend API not initialized');
            }
            
            // Fetch customer from Vend API
            $response = $vend->request("customers/{$vendCustomerId}", 'GET');
            
            if (isset($response['customer']['balance'])) {
                return abs((float)$response['customer']['balance']);
            }
            
            // Fallback: Try to get from local vend_customers table
            $stmt = $this->db->prepare("
                SELECT customer_balance 
                FROM vend_customers 
                WHERE customer_id = ?
                LIMIT 1
            ");
            
            $stmt->execute([$vendCustomerId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return abs((float)$result['customer_balance']);
            }
            
            return 0.0;
            
        } catch (Exception $e) {
            // Log error but don't fail reconciliation
            error_log("Failed to get Vend balance for {$vendCustomerId}: " . $e->getMessage());
            return 0.0;
        }
    }
    
    /**
     * Update reconciliation record
     * 
     * @param string $vendCustomerId
     * @param string $employeeName
     * @param int|null $userId
     * @param float $totalDeductions
     * @param float $totalAllocated
     * @param float $pendingAllocation
     * @param float $vendBalance
     * @param float $outstanding
     * @param string $status
     * @return void
     */
    private function updateReconciliation(
        string $vendCustomerId,
        string $employeeName,
        ?int $userId,
        float $totalDeductions,
        float $totalAllocated,
        float $pendingAllocation,
        float $vendBalance,
        float $outstanding,
        string $status
    ): void {
        // Check if exists
        $stmt = $this->db->prepare("
            SELECT id FROM staff_account_reconciliation
            WHERE vend_customer_id = ?
            LIMIT 1
        ");
        
        $stmt->execute([$vendCustomerId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update
            $stmt = $this->db->prepare("
                UPDATE staff_account_reconciliation
                SET employee_name = ?,
                    user_id = ?,
                    total_xero_deductions = ?,
                    total_allocated = ?,
                    pending_allocation = ?,
                    vend_balance = ?,
                    vend_balance_updated_at = NOW(),
                    outstanding_amount = ?,
                    status = ?,
                    last_reconciled_at = NOW()
                WHERE vend_customer_id = ?
            ");
            
            $stmt->execute([
                $employeeName,
                $userId,
                $totalDeductions,
                $totalAllocated,
                $pendingAllocation,
                $vendBalance,
                $outstanding,
                $status,
                $vendCustomerId
            ]);
            
        } else {
            // Insert
            $stmt = $this->db->prepare("
                INSERT INTO staff_account_reconciliation (
                    user_id, vend_customer_id, employee_name,
                    total_xero_deductions, total_allocated, pending_allocation,
                    vend_balance, vend_balance_updated_at,
                    outstanding_amount, status, last_reconciled_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW())
            ");
            
            $stmt->execute([
                $userId,
                $vendCustomerId,
                $employeeName,
                $totalDeductions,
                $totalAllocated,
                $pendingAllocation,
                $vendBalance,
                $outstanding,
                $status
            ]);
        }
    }
    
    /**
     * Get all reconciliation records
     * 
     * @param string|null $status Filter by status
     * @return array
     */
    public function getAllReconciliations(?string $status = null): array
    {
        if ($status) {
            $stmt = $this->db->prepare("
                SELECT * FROM staff_account_reconciliation
                WHERE status = ?
                ORDER BY outstanding_amount DESC, employee_name ASC
            ");
            $stmt->execute([$status]);
        } else {
            $stmt = $this->db->query("
                SELECT * FROM staff_account_reconciliation
                ORDER BY outstanding_amount DESC, employee_name ASC
            ");
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get reconciliation for specific customer
     * 
     * @param string $vendCustomerId
     * @return array|null
     */
    public function getReconciliation(string $vendCustomerId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM staff_account_reconciliation
            WHERE vend_customer_id = ?
            LIMIT 1
        ");
        
        $stmt->execute([$vendCustomerId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }
    
    /**
     * Get outstanding balances (underpaid accounts)
     * 
     * @return array
     */
    public function getOutstandingBalances(): array
    {
        $stmt = $this->db->query("
            SELECT * FROM staff_account_reconciliation
            WHERE status = 'underpaid'
            AND outstanding_amount > 0
            ORDER BY outstanding_amount DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
