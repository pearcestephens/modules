<?php
declare(strict_types=1);

namespace CIS\Modules\StaffAccounts;

use PDO;
use Exception;

/**
 * Staff Account Service
 * 
 * Core business logic for staff account management
 * Handles querying, balance checking, and account operations
 * 
 * @package CIS\Modules\StaffAccounts
 * @version 2.0.0
 */
class StaffAccountService
{
    /**
     * Get all active staff accounts with Vend customer balances
     * 
     * @return array<int, array<string, mixed>>
     */
    public static function getAllStaffAccounts(): array
    {
        $query = "
            SELECT 
                u.id as user_id,
                u.first_name,
                u.last_name,
                u.email,
                u.vend_customer_account as vend_customer_id,
                u.deputy_id,
                u.staff_active as active,
                COALESCE(vc.balance, '0.00') as current_balance,
                vc.updated_at as balance_updated,
                vc.loyalty_balance,
                vc.year_to_date,
                vc.customer_code
            FROM users u
            LEFT JOIN vend_customers vc ON u.vend_customer_account = vc.id
            WHERE u.vend_customer_account IS NOT NULL 
            AND u.vend_customer_account != ''
            AND u.staff_active = 1
            ORDER BY u.last_name, u.first_name
        ";
        
        try {
            // Use PDO instead of non-existent DB class
            $pdo = $GLOBALS['pdo'] ?? null;
            if (!$pdo) {
                error_log('StaffAccountService::getAllStaffAccounts - PDO not available');
                return [];
            }
            
            $stmt = $pdo->query($query);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return array_map(function($row) {
                return [
                    'user_id' => (int)$row['user_id'],
                    'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                    'email' => $row['email'],
                    'vend_customer_id' => $row['vend_customer_id'],
                    'deputy_id' => $row['deputy_id'],
                    'active' => (bool)$row['active'],
                    'current_balance' => (float)($row['current_balance'] ?? 0),
                    'balance_updated' => $row['balance_updated'],
                    'loyalty_balance' => (float)($row['loyalty_balance'] ?? 0),
                    'year_to_date' => (float)($row['year_to_date'] ?? 0),
                    'customer_code' => $row['customer_code'] ?? ''
                ];
            }, $rows);
            
        } catch (Exception $e) {
            error_log('StaffAccountService::getAllStaffAccounts error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get manual account payments (Internet Banking/POLi at Hamilton East)
     * 
     * @param string $vendCustomerId Optional filter by customer ID
     * @param int $days Look back period in days
     * @return array<string, mixed>
     */
    public static function getManualAccountPayments(string $vendCustomerId = '', int $days = 21): array
    {
        try {
            $dateFrom = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            
            $query = "
                SELECT 
                    vs.id,
                    vs.sale_date,
                    vs.total_price,
                    vs.customer_id,
                    vs.register_id,
                    vs.outlet_id,
                    vs.payments,
                    vo.name as outlet_name,
                    vc.first_name,
                    vc.last_name,
                    vc.customer_code
                FROM vend_sales vs
                LEFT JOIN vend_outlets vo ON vs.outlet_id = vo.id
                LEFT JOIN vend_customers vc ON vs.customer_id = vc.id
                WHERE vs.sale_date >= :dateFrom
                  AND vs.outlet_id = '02dcd191-ae2b-11e6-f485-8eceed6eeafb'
                  AND vs.register_id = '02dcd191-ae2b-11e6-f485-8eceed6ff0d6'
                  AND vs.payments IS NOT NULL
                  AND vs.payments != ''
                  AND vs.payments != '[]'
            ";
            
            $params = ['dateFrom' => $dateFrom];
            
            if (!empty($vendCustomerId)) {
                $query .= " AND vs.customer_id = :customerId";
                $params['customerId'] = $vendCustomerId;
            }
            
            $query .= " ORDER BY vs.sale_date DESC";
            
            // Use PDO instead of non-existent DB class
            $pdo = $GLOBALS['pdo'] ?? null;
            if (!$pdo) {
                error_log('StaffAccountService::getManualAccountPayments - PDO not available');
                return [];
            }
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $manualPayments = [];
            
            foreach ($results as $row) {
                $payments = json_decode($row['payments'], true);
                if (!$payments || !is_array($payments)) {
                    continue;
                }
                
                $hasManualPayment = false;
                $paymentDetails = [];
                
                foreach ($payments as $payment) {
                    if (isset($payment['name']) && 
                        (stripos($payment['name'], 'internet banking') !== false ||
                         stripos($payment['name'], 'bank deposit') !== false)) {
                        
                        $hasManualPayment = true;
                        $paymentDetails[] = [
                            'method' => $payment['name'] ?? 'Unknown',
                            'amount' => $payment['amount'] ?? 0,
                            'retailer_payment_type_id' => $payment['retailer_payment_type_id'] ?? null
                        ];
                    }
                }
                
                if ($hasManualPayment) {
                    $manualPayments[] = [
                        'sale_id' => $row['id'],
                        'sale_date' => $row['sale_date'],
                        'total_price' => $row['total_price'],
                        'customer_id' => $row['customer_id'],
                        'customer_name' => trim($row['first_name'] . ' ' . $row['last_name']),
                        'customer_code' => $row['customer_code'],
                        'outlet_name' => $row['outlet_name'],
                        'register_id' => $row['register_id'],
                        'payment_details' => $paymentDetails,
                        'raw_payments' => $payments
                    ];
                }
            }
            
            return [
                'success' => true,
                'count' => count($manualPayments),
                'date_range' => "{$days} days (from {$dateFrom})",
                'payments' => $manualPayments,
                'criteria' => [
                    'outlet_id' => '02dcd191-ae2b-11e6-f485-8eceed6eeafb',
                    'register_id' => '02dcd191-ae2b-11e6-f485-8eceed6ff0d6',
                    'payment_methods' => ['Internet Banking / Bank Deposit']
                ]
            ];
            
        } catch (Exception $e) {
            error_log("StaffAccountService::getManualAccountPayments error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve manual payments: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Search transactions across snapshot event logs
     * 
     * @param string $searchTerm Search filter
     * @param string $userId Optional user ID filter
     * @param int $limit Maximum results
     * @return array<int, array<string, mixed>>
     */
    public static function searchTransactions(string $searchTerm = '', string $userId = '', int $limit = 200): array
    {
        $transactions = [];
        $snapshotDir = $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/xeroAPI/_payroll_snapshots';
        $eventFiles = glob($snapshotDir . '/snapshot_*.events.jsonl');
        
        if (!$eventFiles) {
            return [];
        }
        
        sort($eventFiles, SORT_STRING);
        
        foreach (array_reverse($eventFiles) as $file) {
            if (count($transactions) >= $limit) break;
            
            $handle = fopen($file, 'r');
            if (!$handle) continue;
            
            while (($line = fgets($handle)) !== false) {
                if (count($transactions) >= $limit) break;
                
                $event = json_decode(trim($line), true);
                if (!$event) continue;
                
                // Filter by user ID if specified
                if ($userId && ($event['user_id'] ?? '') !== $userId) continue;
                
                // Search filter
                if ($searchTerm) {
                    $searchLower = strtolower($searchTerm);
                    $eventText = strtolower(json_encode($event));
                    if (strpos($eventText, $searchLower) === false) continue;
                }
                
                $transactions[] = $event;
            }
            
            fclose($handle);
        }
        
        return $transactions;
    }
}
