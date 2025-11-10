<?php
/**
 * Credit Limit Management Service
 * 
 * Manages staff credit limits via Lightspeed CreditAccount API
 * 
 * Features:
 * - Set individual credit limits per staff member
 * - Set company-wide default credit limit
 * - Override limits for specific staff
 * - Update limits in real-time
 * - Sync from Lightspeed for reporting
 * 
 * @package CIS\StaffAccounts\Lib
 * @version 1.0.0
 */

declare(strict_types=1);

namespace StaffAccounts\Lib;

use PDO;
use Exception;

class CreditLimitService
{
    private PDO $db;
    private VendAPI $vend;
    
    /**
     * Company-wide default credit limit (can be overridden per staff)
     * Stored in config table
     */
    private const DEFAULT_CREDIT_LIMIT_KEY = 'staff_default_credit_limit';
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->vend = new VendAPI();
    }
    
    // ========================================================================
    // COMPANY-WIDE DEFAULT SETTINGS
    // ========================================================================
    
    /**
     * Get company-wide default credit limit
     * 
     * @return float Default credit limit (0 = unlimited)
     */
    public function getDefaultCreditLimit(): float
    {
        $stmt = $this->db->prepare("
            SELECT config_value 
            FROM staff_account_config 
            WHERE config_key = ?
        ");
        $stmt->execute([self::DEFAULT_CREDIT_LIMIT_KEY]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (float)$result['config_value'] : 500.00; // Default $500
    }
    
    /**
     * Set company-wide default credit limit
     * 
     * This applies to ALL staff unless individually overridden
     * 
     * @param float $limit New default limit (0 = unlimited)
     * @return bool Success
     */
    public function setDefaultCreditLimit(float $limit): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO staff_account_config (config_key, config_value, updated_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                config_value = VALUES(config_value),
                updated_at = NOW()
        ");
        
        return $stmt->execute([self::DEFAULT_CREDIT_LIMIT_KEY, $limit]);
    }
    
    /**
     * Apply default credit limit to all staff who don't have individual limits set
     * 
     * @param float $limit The limit to apply
     * @return int Number of staff updated
     */
    public function applyDefaultLimitToAll(float $limit): int
    {
        // Get all staff who don't have individual limits (credit_limit = 0 or NULL)
        $stmt = $this->db->query("
            SELECT id, vend_customer_id, employee_name
            FROM staff_account_reconciliation
            WHERE (credit_limit IS NULL OR credit_limit = 0)
            AND status != 'archived'
        ");
        
        $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $updated = 0;
        
        foreach ($staff as $member) {
            try {
                $success = $this->setCreditLimit(
                    (int)$member['id'], 
                    $limit, 
                    false // Don't mark as individual override
                );
                if ($success) {
                    $updated++;
                }
            } catch (Exception $e) {
                error_log("Failed to set default limit for {$member['employee_name']}: " . $e->getMessage());
            }
        }
        
        return $updated;
    }
    
    // ========================================================================
    // INDIVIDUAL CREDIT LIMIT MANAGEMENT
    // ========================================================================
    
    /**
     * Get credit limit for a specific staff member
     * 
     * @param int $reconciliationId Staff account reconciliation ID
     * @return array ['credit_limit' => float, 'is_override' => bool, 'credit_account_id' => string]
     */
    public function getCreditLimit(int $reconciliationId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                credit_limit,
                credit_account_id,
                CASE 
                    WHEN credit_limit > 0 THEN 1 
                    ELSE 0 
                END as is_override
            FROM staff_account_reconciliation
            WHERE id = ?
        ");
        $stmt->execute([$reconciliationId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            throw new Exception("Staff member not found");
        }
        
        return [
            'credit_limit' => (float)($result['credit_limit'] ?? 0),
            'credit_account_id' => $result['credit_account_id'],
            'is_override' => (bool)$result['is_override']
        ];
    }
    
    /**
     * Set credit limit for a specific staff member
     * 
     * This updates BOTH local DB AND Lightspeed CreditAccount API
     * 
     * @param int $reconciliationId Staff account reconciliation ID
     * @param float $limit New credit limit (0 = unlimited)
     * @param bool $isIndividualOverride Whether this is a manual override (vs company default)
     * @return bool Success
     * @throws Exception If update fails
     */
    public function setCreditLimit(int $reconciliationId, float $limit, bool $isIndividualOverride = true): bool
    {
        // Get staff member details
        $stmt = $this->db->prepare("
            SELECT vend_customer_id, credit_account_id, employee_name
            FROM staff_account_reconciliation
            WHERE id = ?
        ");
        $stmt->execute([$reconciliationId]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$staff) {
            throw new Exception("Staff member not found");
        }
        
        // Update in Lightspeed CreditAccount API (if credit account exists)
        if (!empty($staff['credit_account_id'])) {
            try {
                $this->updateLightspeedCreditLimit($staff['credit_account_id'], $limit);
            } catch (Exception $e) {
                error_log("Failed to update Lightspeed credit limit for {$staff['employee_name']}: " . $e->getMessage());
                // Continue anyway - we'll sync it later
            }
        }
        
        // Update local database
        $stmt = $this->db->prepare("
            UPDATE staff_account_reconciliation
            SET 
                credit_limit = ?,
                vend_last_synced_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $success = $stmt->execute([$limit, $reconciliationId]);
        
        // Log the change
        if ($success) {
            $this->logCreditLimitChange(
                $reconciliationId,
                $limit,
                $isIndividualOverride ? 'individual_override' : 'company_default'
            );
        }
        
        return $success;
    }
    
    /**
     * Update credit limit in Lightspeed CreditAccount API
     * 
     * @param string $creditAccountId Lightspeed CreditAccount ID
     * @param float $limit New credit limit
     * @return bool Success
     * @throws Exception If API call fails
     */
    private function updateLightspeedCreditLimit(string $creditAccountId, float $limit): bool
    {
        $response = $this->vend->request(
            "credit_accounts/{$creditAccountId}",
            'PUT',
            [
                'credit_account' => [
                    'credit_limit' => number_format($limit, 2, '.', '')
                ]
            ]
        );
        
        if (!isset($response['credit_account'])) {
            throw new Exception("Lightspeed API error: Invalid response");
        }
        
        return true;
    }
    
    /**
     * Remove individual override and revert to company default
     * 
     * @param int $reconciliationId Staff account reconciliation ID
     * @return bool Success
     */
    public function revertToDefault(int $reconciliationId): bool
    {
        $defaultLimit = $this->getDefaultCreditLimit();
        return $this->setCreditLimit($reconciliationId, $defaultLimit, false);
    }
    
    // ========================================================================
    // BULK OPERATIONS
    // ========================================================================
    
    /**
     * Get all staff credit limits
     * 
     * @param string $filterType 'all' | 'overrides' | 'defaults'
     * @return array List of staff with credit limits
     */
    public function getAllCreditLimits(string $filterType = 'all'): array
    {
        $where = "WHERE sar.status != 'archived'";
        
        if ($filterType === 'overrides') {
            $where .= " AND sar.credit_limit > 0";
        } elseif ($filterType === 'defaults') {
            $where .= " AND (sar.credit_limit IS NULL OR sar.credit_limit = 0)";
        }
        
        $stmt = $this->db->query("
            SELECT 
                sar.id,
                sar.user_id,
                sar.vend_customer_id,
                sar.employee_name,
                sar.credit_limit,
                sar.credit_account_id,
                sar.vend_balance,
                sar.outstanding_amount,
                CASE 
                    WHEN sar.credit_limit > 0 THEN 'Individual Override'
                    ELSE 'Company Default'
                END as limit_type,
                sar.vend_last_synced_at
            FROM staff_account_reconciliation sar
            {$where}
            ORDER BY sar.employee_name
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Bulk update credit limits for multiple staff
     * 
     * @param array $updates [['id' => 1, 'limit' => 500.00], ...]
     * @return array ['success' => int, 'failed' => int, 'errors' => array]
     */
    public function bulkSetCreditLimits(array $updates): array
    {
        $success = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($updates as $update) {
            try {
                $result = $this->setCreditLimit(
                    (int)$update['id'], 
                    (float)$update['limit'],
                    true
                );
                
                if ($result) {
                    $success++;
                } else {
                    $failed++;
                    $errors[] = "Failed to update ID {$update['id']}";
                }
            } catch (Exception $e) {
                $failed++;
                $errors[] = "ID {$update['id']}: " . $e->getMessage();
            }
        }
        
        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors
        ];
    }
    
    // ========================================================================
    // SYNC FROM LIGHTSPEED (FOR REPORTING)
    // ========================================================================
    
    /**
     * Sync credit limits from Lightspeed for a specific staff member
     * 
     * This is READ-ONLY sync for reporting/backup purposes
     * 
     * @param int $reconciliationId Staff account reconciliation ID
     * @return bool Success
     */
    public function syncFromLightspeed(int $reconciliationId): bool
    {
        $stmt = $this->db->prepare("
            SELECT vend_customer_id, credit_account_id
            FROM staff_account_reconciliation
            WHERE id = ?
        ");
        $stmt->execute([$reconciliationId]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$staff || empty($staff['vend_customer_id'])) {
            return false;
        }
        
        try {
            // Fetch customer with CreditAccount relation
            $response = $this->vend->request(
                "customers/{$staff['vend_customer_id']}?load_relations=[\"CreditAccount\"]",
                'GET'
            );
            
            if (!isset($response['customer']['credit_account'])) {
                return false; // No credit account
            }
            
            $creditAccount = $response['customer']['credit_account'];
            
            // Update local database with synced data
            $stmt = $this->db->prepare("
                UPDATE staff_account_reconciliation
                SET 
                    credit_limit = ?,
                    credit_account_id = ?,
                    vend_last_synced_at = NOW()
                WHERE id = ?
            ");
            
            return $stmt->execute([
                (float)$creditAccount['credit_limit'],
                $creditAccount['credit_account_id'],
                $reconciliationId
            ]);
            
        } catch (Exception $e) {
            error_log("Sync from Lightspeed failed for reconciliation ID {$reconciliationId}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sync ALL staff credit limits from Lightspeed (cron job)
     * 
     * @return array ['synced' => int, 'failed' => int]
     */
    public function syncAllFromLightspeed(): array
    {
        $stmt = $this->db->query("
            SELECT id 
            FROM staff_account_reconciliation 
            WHERE status != 'archived'
        ");
        
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $synced = 0;
        $failed = 0;
        
        foreach ($ids as $id) {
            if ($this->syncFromLightspeed((int)$id)) {
                $synced++;
            } else {
                $failed++;
            }
        }
        
        return ['synced' => $synced, 'failed' => $failed];
    }
    
    // ========================================================================
    // AUDIT LOGGING
    // ========================================================================
    
    /**
     * Log credit limit changes for audit trail
     * 
     * @param int $reconciliationId Staff account reconciliation ID
     * @param float $newLimit New credit limit
     * @param string $changeType 'individual_override' | 'company_default' | 'revert' | 'bulk'
     * @return void
     */
    private function logCreditLimitChange(int $reconciliationId, float $newLimit, string $changeType): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO staff_account_audit_log 
            (reconciliation_id, action_type, old_value, new_value, change_type, changed_by, changed_at)
            SELECT 
                ?,
                'credit_limit_change',
                credit_limit,
                ?,
                ?,
                ?,
                NOW()
            FROM staff_account_reconciliation
            WHERE id = ?
        ");
        
        $changedBy = $_SESSION['userID'] ?? 'system';
        
        $stmt->execute([
            $reconciliationId,
            $newLimit,
            $changeType,
            $changedBy,
            $reconciliationId
        ]);
    }
    
    /**
     * Get credit limit change history for a staff member
     * 
     * @param int $reconciliationId Staff account reconciliation ID
     * @param int $limit Number of records to return
     * @return array History records
     */
    public function getCreditLimitHistory(int $reconciliationId, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                old_value,
                new_value,
                change_type,
                changed_by,
                changed_at
            FROM staff_account_audit_log
            WHERE reconciliation_id = ?
            AND action_type = 'credit_limit_change'
            ORDER BY changed_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$reconciliationId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ========================================================================
    // REPORTING & ANALYTICS
    // ========================================================================
    
    /**
     * Get credit limit utilization report
     * 
     * Shows how much of their credit limit each staff member is using
     * 
     * @return array Report data
     */
    public function getCreditUtilizationReport(): array
    {
        $stmt = $this->db->query("
            SELECT 
                employee_name,
                credit_limit,
                vend_balance,
                outstanding_amount,
                CASE 
                    WHEN credit_limit = 0 THEN 0
                    ELSE ROUND((vend_balance / credit_limit) * 100, 2)
                END as utilization_percent,
                CASE 
                    WHEN credit_limit = 0 THEN 'Unlimited'
                    WHEN vend_balance / credit_limit > 0.9 THEN 'High (>90%)'
                    WHEN vend_balance / credit_limit > 0.7 THEN 'Medium (70-90%)'
                    ELSE 'Low (<70%)'
                END as risk_level
            FROM staff_account_reconciliation
            WHERE status != 'archived'
            ORDER BY utilization_percent DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
