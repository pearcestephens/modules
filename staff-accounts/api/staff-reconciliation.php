<?php
/**
 * Staff Reconciliation API
 * 
 * Returns REAL staff account data from database
 * 
 * @package CIS\StaffAccounts\API
 * @version 2.0.0
 */

require_once __DIR__ . '/../bootstrap.php';

// Set JSON headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Fetch all staff accounts with REAL data - USING ACTUAL COLUMN NAMES
    $stmt = $pdo->query("
        SELECT 
            sar.user_id,
            sar.employee_name,
            sar.vend_customer_id,
            sar.vend_balance,
            sar.total_allocated,
            sar.total_payments_ytd,
            sar.last_payment_date,
            sar.last_payment_amount,
            sar.outstanding_amount,
            sar.status,
            sar.vend_balance_updated_at,
            DATEDIFF(NOW(), sar.last_payment_date) as days_since_last_payment
        FROM staff_account_reconciliation sar
        WHERE sar.employee_name IS NOT NULL
        ORDER BY sar.vend_balance ASC
    ");
    
    $staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate summary statistics using ACTUAL column names
    $total_owed = 0;
    $total_purchases = 0;
    $total_payments = 0;
    
    foreach ($staff_list as $staff) {
        $balance = floatval($staff['vend_balance']);
        if ($balance < 0) {
            $total_owed += abs($balance);
        }
        $total_purchases += floatval($staff['total_allocated']);
        $total_payments += floatval($staff['total_payments_ytd']);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $staff_list,
        'summary' => [
            'total_staff' => count($staff_list),
            'total_owed' => $total_owed,
            'total_purchases' => $total_purchases,
            'total_payments' => $total_payments
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

exit;

// OLD MOCK DATA CODE - REMOVED
if (false) {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'getReconciliation':
            echo json_encode([
                'success' => true,
                'testing_mode' => true,
                'data' => [
                    [
                        'user_id' => 1,
                        'first_name' => 'John',
                        'last_name' => 'Smith',
                        'email' => 'john.smith@vapeshed.co.nz',
                        'xero_id' => 'XE12345',
                        'vend_customer_account' => 'VC67890',
                        'xero_deductions' => '456.50',
                        'vend_purchases' => '456.50',
                        'current_balance' => '0.00',
                        'pay_period_count' => 3
                    ],
                    [
                        'user_id' => 2,
                        'first_name' => 'Jane',
                        'last_name' => 'Doe',
                        'email' => 'jane.doe@vapeshed.co.nz',
                        'xero_id' => 'XE22222',
                        'vend_customer_account' => 'VC11111',
                        'xero_deductions' => '289.20',
                        'vend_purchases' => '305.40',
                        'current_balance' => '-16.20',
                        'pay_period_count' => 2
                    ],
                    [
                        'user_id' => 3,
                        'first_name' => 'Bob',
                        'last_name' => 'Wilson',
                        'email' => 'bob.wilson@vapeshed.co.nz',
                        'xero_id' => 'XE33333',
                        'vend_customer_account' => null,
                        'xero_deductions' => '125.00',
                        'vend_purchases' => '0.00',
                        'current_balance' => '125.00',
                        'pay_period_count' => 1
                    ],
                    [
                        'user_id' => 4,
                        'first_name' => 'Sarah',
                        'last_name' => 'Johnson',
                        'email' => 'sarah.johnson@vapeshed.co.nz',
                        'xero_id' => 'XE44444',
                        'vend_customer_account' => 'VC44444',
                        'xero_deductions' => '567.80',
                        'vend_purchases' => '545.90',
                        'current_balance' => '21.90',
                        'pay_period_count' => 4
                    ],
                    [
                        'user_id' => 5,
                        'first_name' => 'Mike',
                        'last_name' => 'Brown',
                        'email' => 'mike.brown@vapeshed.co.nz',
                        'xero_id' => 'XE55555',
                        'vend_customer_account' => 'VC55555',
                        'xero_deductions' => '345.60',
                        'vend_purchases' => '389.90',
                        'current_balance' => '-44.30',
                        'pay_period_count' => 2
                    ]
                ],
                'summary' => [
                    'total_xero' => '1784.10',
                    'total_vend' => '1697.70',
                    'unallocated' => '86.40',
                    'staff_count' => 5
                ]
            ]);
            exit;
            
        case 'saveAllocations':
            echo json_encode([
                'success' => true,
                'testing_mode' => true,
                'message' => 'Allocations saved successfully',
                'allocations_saved' => 5
            ]);
            exit;
            
        case 'getStaffDetail':
            $userId = $_GET['user_id'] ?? 1;
            echo json_encode([
                'success' => true,
                'testing_mode' => true,
                'staff' => [
                    'user_id' => $userId,
                    'name' => 'John Smith',
                    'email' => 'john.smith@vapeshed.co.nz'
                ],
                'purchases' => [
                    [
                        'date' => '2025-10-20',
                        'product' => 'Lost Mary BM600',
                        'quantity' => 2,
                        'amount' => '45.90',
                        'outlet' => 'Queen Street'
                    ],
                    [
                        'date' => '2025-10-18',
                        'product' => 'Elf Bar 600',
                        'quantity' => 1,
                        'amount' => '22.50',
                        'outlet' => 'Queen Street'
                    ]
                ],
                'deductions' => [
                    [
                        'pay_date' => '2025-10-15',
                        'period' => '01-15 Oct 2025',
                        'amount' => '456.50'
                    ]
                ]
            ]);
            exit;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Unknown action',
                'testing_mode' => true
            ]);
            exit;
    }
}

// Real implementation (when not in testing mode)
require_once '../bootstrap.php';

use CIS\Modules\StaffAccounts\Lib\Db;
use CIS\Modules\StaffAccounts\Lib\Log;

try {
    $db = Db::getInstance();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'getReconciliation':
            $from = $_GET['from'] ?? date('Y-m-d', strtotime('-14 days'));
            $to = $_GET['to'] ?? date('Y-m-d');
            
            // Get all active staff with their Xero deductions and Vend purchases
            $sql = "
                SELECT 
                    u.id as user_id,
                    u.first_name,
                    u.last_name,
                    u.email,
                    u.xero_id,
                    u.vend_customer_account,
                    COALESCE(SUM(xd.amount), 0) as xero_deductions,
                    COALESCE(vp.total_purchases, 0) as vend_purchases,
                    COALESCE(u.staff_account_balance, 0) as current_balance,
                    COUNT(DISTINCT xd.pay_period) as pay_period_count
                FROM users u
                LEFT JOIN xero_payroll_deductions xd 
                    ON u.xero_id = xd.employee_id 
                    AND xd.pay_date BETWEEN ? AND ?
                LEFT JOIN (
                    SELECT 
                        customer_id,
                        SUM(total_price) as total_purchases
                    FROM vend_sales
                    WHERE sale_date BETWEEN ? AND ?
                    AND status != 'VOIDED'
                    GROUP BY customer_id
                ) vp ON u.vend_customer_account = vp.customer_id
                WHERE u.staff_active = 1
                GROUP BY u.id
                HAVING xero_deductions > 0 OR vend_purchases > 0
                ORDER BY u.last_name, u.first_name
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$from, $to, $from, $to]);
            $staffData = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Calculate summary
            $totalXero = array_sum(array_column($staffData, 'xero_deductions'));
            $totalVend = array_sum(array_column($staffData, 'vend_purchases'));
            $unallocated = $totalXero - $totalVend;
            
            echo json_encode([
                'success' => true,
                'data' => $staffData,
                'summary' => [
                    'total_xero' => number_format($totalXero, 2, '.', ''),
                    'total_vend' => number_format($totalVend, 2, '.', ''),
                    'unallocated' => number_format($unallocated, 2, '.', ''),
                    'staff_count' => count($staffData)
                ],
                'period' => [
                    'from' => $from,
                    'to' => $to
                ]
            ]);
            break;
            
        case 'saveAllocations':
            $input = json_decode(file_get_contents('php://input'), true);
            $allocations = $input['allocations'] ?? [];
            $period = $input['period'] ?? [];
            
            if (empty($allocations)) {
                throw new \Exception('No allocations provided');
            }
            
            $db->beginTransaction();
            
            try {
                $saved = 0;
                foreach ($allocations as $alloc) {
                    $userId = $alloc['user_id'] ?? 0;
                    $amount = $alloc['amount'] ?? 0;
                    
                    if ($userId > 0 && $amount != 0) {
                        // Update staff account balance
                        $sql = "UPDATE users SET staff_account_balance = staff_account_balance + ? WHERE id = ?";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([$amount, $userId]);
                        
                        // Log the allocation
                        $sql = "
                            INSERT INTO staff_account_transactions 
                            (user_id, amount, transaction_type, description, period_from, period_to, created_at)
                            VALUES (?, ?, 'allocation', 'Manual reconciliation allocation', ?, ?, NOW())
                        ";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([
                            $userId,
                            $amount,
                            $period['from'] ?? null,
                            $period['to'] ?? null
                        ]);
                        
                        $saved++;
                    }
                }
                
                $db->commit();
                
                Log::info("Saved $saved allocations", ['user_id' => $_SESSION['userID'] ?? 0]);
                
                echo json_encode([
                    'success' => true,
                    'message' => "Successfully saved $saved allocations",
                    'allocations_saved' => $saved
                ]);
                
            } catch (\Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'getStaffDetail':
            $userId = $_GET['user_id'] ?? 0;
            
            if (!$userId) {
                throw new \Exception('User ID required');
            }
            
            // Get staff info
            $sql = "SELECT id, first_name, last_name, email, xero_id, vend_customer_account FROM users WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$userId]);
            $staff = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$staff) {
                throw new \Exception('Staff member not found');
            }
            
            // Get purchase history
            $sql = "
                SELECT 
                    DATE(sale_date) as date,
                    product_name as product,
                    quantity,
                    total_price as amount,
                    outlet_name as outlet
                FROM vend_sales
                WHERE customer_id = ?
                AND status != 'VOIDED'
                ORDER BY sale_date DESC
                LIMIT 50
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute([$staff['vend_customer_account']]);
            $purchases = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Get deduction history
            $sql = "
                SELECT 
                    pay_date,
                    pay_period as period,
                    amount
                FROM xero_payroll_deductions
                WHERE employee_id = ?
                ORDER BY pay_date DESC
                LIMIT 50
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute([$staff['xero_id']]);
            $deductions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'staff' => [
                    'user_id' => $staff['id'],
                    'name' => $staff['first_name'] . ' ' . $staff['last_name'],
                    'email' => $staff['email']
                ],
                'purchases' => $purchases,
                'deductions' => $deductions
            ]);
            break;
            
        default:
            throw new \Exception('Invalid action');
    }
    
} catch (\Exception $e) {
    Log::error('Staff reconciliation API error', [
        'error' => $e->getMessage(),
        'action' => $action ?? 'unknown'
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
