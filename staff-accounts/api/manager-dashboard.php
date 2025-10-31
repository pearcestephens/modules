<?php
/**
 * Manager Dashboard API
 * 
 * Provides data for manager dashboard:
 * - Executive summary stats
 * - Staff list with filtering
 * - Action items
 * - Chart data
 * - Export functions
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/csrf.php';

header('Content-Type: application/json');

// CSRF Protection for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();
}

// Simple user authentication check - just verify logged in (use CIS standard userID)
if (!isset($_SESSION['userID'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied - Login required']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    
    /**
     * Get complete dashboard data
     */
    case 'getDashboardData':
        // Get all staff with balances (NO ROLE CHECKS - ONLY LOGIN REQUIRED)
        $query = "SELECT 
                    sar.user_id,
                    sar.employee_name as name,
                    sar.vend_customer_id,
                    COALESCE(sar.vend_balance, 0) as balance,
                    sar.total_allocated,
                    sar.total_payments_ytd,
                    sar.outstanding_amount,
                    sar.last_reconciled_at as last_purchase_date,
                    sar.last_payment_date,
                    sar.last_payment_amount,
                    sar.status,
                    (SELECT COUNT(*) FROM staff_payment_transactions spt WHERE spt.user_id = sar.user_id AND spt.transaction_type = 'payment_approved') as credit_card_payments
                  FROM staff_account_reconciliation sar
                  WHERE sar.employee_name IS NOT NULL
                  ORDER BY balance ASC";
        
        $result = $pdo->query($query);
        $staffData = [];
        $totalDebt = 0;
        $highRiskCount = 0;
        
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $balance = floatval($row['balance']);
            
            // Calculate risk (critical balance < -$500)
            if ($balance < -500) {
                $highRiskCount++;
            }
            
            // Total debt (negative balances only)
            if ($balance < 0) {
                $totalDebt += abs($balance);
            }
            
            $staffData[] = $row;
        }
        
        // Get payments this month
        $query2 = "SELECT 
                     COUNT(*) as payment_count,
                     SUM(amount) as total_amount
                   FROM vend_payment_allocations
                   WHERE MONTH(created_at) = MONTH(CURDATE())
                   AND YEAR(created_at) = YEAR(CURDATE())";
        
        $result2 = $pdo->query($query2);
        $paymentData = $result2->fetch(PDO::FETCH_ASSOC);
        
        // Get action items
        $actionItems = getActionItems($pdo);
        
        // Get chart data
        $chartData = getChartData($pdo);
        
        echo json_encode([
            'success' => true,
            'summary' => [
                'totalDebt' => $totalDebt,
                'staffCount' => count($staffData),
                'highRiskCount' => $highRiskCount,
                'paymentsThisMonth' => $paymentData['total_amount'] ?? 0,
                'paymentCount' => $paymentData['payment_count'] ?? 0,
                'avgBalance' => count($staffData) > 0 ? $totalDebt / count($staffData) : 0
            ],
            'staffData' => $staffData,
            'actionItems' => $actionItems,
            'chartData' => $chartData
        ]);
        break;
    
    /**
     * Send payment reminder to specific staff
     */
    case 'sendReminder':
        $userId = intval($_POST['userId'] ?? 0);
        
        if ($userId === 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
            exit;
        }
        
        // Get staff info - USING ACTUAL COLUMN NAMES
        $query = "SELECT 
                    sar.employee_name,
                    sar.vend_balance,
                    sar.user_id
                  FROM staff_account_reconciliation sar
                  WHERE sar.user_id = ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$staff) {
            echo json_encode(['success' => false, 'error' => 'Staff member not found']);
            exit;
        }
        
        // Send email
        $to = "manager@company.com";  // Replace with actual manager email
        $subject = "Staff Account Balance Reminder";
        $message = "Hi {$staff['employee_name']},\n\n";
        $message .= "This is a friendly reminder about your staff account balance.\n\n";
        $message .= "Current Balance: $" . number_format($staff['vend_balance'], 2) . "\n\n";
        $message .= "You can view your statement and make a payment at:\n";
        $message .= "https://staff.vapeshed.co.nz/staff-accounts/\n\n";
        $message .= "Thank you,\nThe Vape Shed Team";
        
        $headers = "From: accounts@vapeshed.co.nz\r\n";
        $headers .= "Reply-To: accounts@vapeshed.co.nz\r\n";
        
        $sent = mail($to, $subject, $message, $headers);
        
        // Log reminder
        $query2 = "INSERT INTO staff_reminder_log (user_id, sent_at, sent_by) VALUES (?, NOW(), ?)";
        $stmt2 = $pdo->prepare($query2);
        $stmt2->execute([$userId, $_SESSION['userID']]);
        
        echo json_encode([
            'success' => $sent,
            'message' => $sent ? 'Reminder sent successfully' : 'Failed to send reminder'
        ]);
        break;
    
    /**
     * Send bulk reminders to high-risk accounts
     */
    case 'sendBulkReminders':
        $query = "SELECT 
                    u.id, u.first_name, u.email,
                    sab.current_balance, sab.credit_limit
                  FROM users u
                  LEFT JOIN staff_account_reconciliation sab ON u.id = sab.user_id
                  WHERE u.is_active = 1
                  AND (sab.current_balance > 500 
                       OR (sab.credit_limit > 0 AND sab.current_balance / sab.credit_limit > 0.8))";
        
        $result = $pdo->query($query);
        $count = 0;
        
        while ($staff = $result->fetch(PDO::FETCH_ASSOC)) {
            $to = $staff['email'];
            $subject = "Important: Staff Account Balance Notice";
            $message = "Hi {$staff['first_name']},\n\n";
            $message .= "Your staff account balance requires attention.\n\n";
            $message .= "Current Balance: $" . number_format($staff['current_balance'], 2) . "\n";
            $message .= "Credit Limit: $" . number_format($staff['credit_limit'], 2) . "\n\n";
            $message .= "Please review your account and consider making a payment:\n";
            $message .= "https://staff.vapeshed.co.nz/staff-accounts/\n\n";
            $message .= "You can pay instantly with a credit card or set up a payment plan.\n\n";
            $message .= "Thank you,\nThe Vape Shed Team";
            
            $headers = "From: accounts@vapeshed.co.nz\r\n";
            
            if (mail($to, $subject, $message, $headers)) {
                $count++;
                
                // Log reminder
                $query2 = "INSERT INTO staff_reminder_log (user_id, sent_at, sent_by) VALUES (?, NOW(), ?)";
                $stmt2 = $pdo->prepare($query2);
                $stmt2->execute([$staff['id'], $_SESSION['userID']]);
            }
        }
        
        echo json_encode([
            'success' => true,
            'count' => $count,
            'message' => "Reminders sent to {$count} staff members"
        ]);
        break;
    
    /**
     * Export CSV report
     */
    case 'exportCSV':
        $query = "SELECT 
                    CONCAT(u.first_name, ' ', u.last_name) as Name,
                    u.email as Email,
                    u.department as Department,
                    o.name as Store,
                    sab.current_balance as Balance,
                    sab.credit_limit as 'Credit Limit',
                    ROUND((sab.current_balance / sab.credit_limit * 100), 2) as 'Utilization %',
                    sab.last_purchase_date as 'Last Purchase',
                    sab.last_payment_date as 'Last Payment'
                  FROM users u
                  LEFT JOIN staff_account_reconciliation sab ON u.id = sab.user_id
                  LEFT JOIN vend_outlets o ON u.outlet_id = o.outlet_id
                  WHERE u.is_active = 1
                  ORDER BY sab.current_balance DESC";
        
        $result = $pdo->query($query);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="staff-accounts-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, ['Name', 'Email', 'Department', 'Store', 'Balance', 'Credit Limit', 'Utilization %', 'Last Purchase', 'Last Payment']);
        
        // Data
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    
    /**
     * Generate PDF report
     */
    case 'generatePDF':
        // Simple HTML-to-PDF approach
        $query = "SELECT 
                    u.id,
                    CONCAT(u.first_name, ' ', u.last_name) as name,
                    u.department,
                    o.name as outlet_name,
                    sab.current_balance,
                    sab.credit_limit,
                    sab.last_purchase_date
                  FROM users u
                  LEFT JOIN staff_account_reconciliation sab ON u.id = sab.user_id
                  LEFT JOIN vend_outlets o ON u.outlet_id = o.outlet_id
                  WHERE u.is_active = 1 AND sab.current_balance > 0
                  ORDER BY sab.current_balance DESC";
        
        $result = $pdo->query($query);
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="staff-accounts-report-' . date('Y-m-d') . '.pdf"');
        
        // For now, output HTML (in production, use a PDF library like TCPDF or mPDF)
        echo "<!DOCTYPE html><html><head><title>Staff Accounts Report</title></head><body>";
        echo "<h1>Staff Accounts Report - " . date('d/m/Y') . "</h1>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Name</th><th>Department</th><th>Store</th><th>Balance</th><th>Credit Limit</th><th>Last Purchase</th></tr>";
        
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>{$row['name']}</td>";
            echo "<td>{$row['department']}</td>";
            echo "<td>{$row['outlet_name']}</td>";
            echo "<td>$" . number_format($row['current_balance'], 2) . "</td>";
            echo "<td>$" . number_format($row['credit_limit'], 2) . "</td>";
            echo "<td>{$row['last_purchase_date']}</td>";
            echo "</tr>";
        }
        
        echo "</table></body></html>";
        exit;
    
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

/**
 * Get action items for dashboard
 */
function getActionItems($pdo) {
    $items = [];
    
    // High balance accounts
    $query = "SELECT 
                CONCAT(u.first_name, ' ', u.last_name) as name,
                sab.current_balance
              FROM users u
              LEFT JOIN staff_account_reconciliation sab ON u.id = sab.user_id
              WHERE sab.current_balance > 500
              ORDER BY sab.current_balance DESC
              LIMIT 5";
    
    $result = $pdo->query($query);
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $items[] = [
            'title' => 'High Balance Alert',
            'description' => "{$row['name']} has a balance of $" . number_format($row['current_balance'], 2),
            'severity' => 'high',
            'timestamp' => 'Just now'
        ];
    }
    
    // Failed payments
    $query2 = "SELECT 
                 CONCAT(u.first_name, ' ', u.last_name) as name,
                 spt.amount,
                 spt.created_at
               FROM staff_payment_transactions spt
               JOIN users u ON spt.user_id = u.id
               WHERE spt.transaction_type = 'payment_failed'
               AND DATE(spt.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAYS)
               ORDER BY spt.created_at DESC
               LIMIT 3";
    
    $result2 = $pdo->query($query2);
    while ($row = $result2->fetch(PDO::FETCH_ASSOC)) {
        $items[] = [
            'title' => 'Failed Payment',
            'description' => "{$row['name']} - Payment of $" . number_format($row['amount'], 2) . " failed",
            'severity' => 'medium',
            'timestamp' => date('d/m/Y H:i', strtotime($row['created_at']))
        ];
    }
    
    return $items;
}

/**
 * Get chart data
 */
function getChartData($pdo) {
    // Balance trend (last 30 days)
    $trendLabels = [];
    $trendData = [];
    
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $trendLabels[] = date('M d', strtotime($date));
        
        $query = "SELECT SUM(current_balance) as total 
                  FROM staff_account_reconciliation 
                  WHERE DATE(last_updated) <= ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $trendData[] = floatval($result['total'] ?? 0);
    }
    
    // Top 10 staff by balance
    $query2 = "SELECT 
                 CONCAT(u.first_name, ' ', SUBSTRING(u.last_name, 1, 1), '.') as name,
                 sab.current_balance
               FROM users u
               JOIN staff_account_reconciliation sab ON u.id = sab.user_id
               WHERE sab.current_balance > 0
               ORDER BY sab.current_balance DESC
               LIMIT 10";
    
    $result2 = $pdo->query($query2);
    $top10Labels = [];
    $top10Data = [];
    
    while ($row = $result2->fetch(PDO::FETCH_ASSOC)) {
        $top10Labels[] = $row['name'];
        $top10Data[] = floatval($row['current_balance']);
    }
    
    // Department totals
    $query3 = "SELECT 
                 u.department,
                 SUM(sab.current_balance) as total
               FROM users u
               JOIN staff_account_reconciliation sab ON u.id = sab.user_id
               WHERE u.department IS NOT NULL
               GROUP BY u.department
               ORDER BY total DESC";
    
    $result3 = $pdo->query($query3);
    $deptLabels = [];
    $deptData = [];
    
    while ($row = $result3->fetch(PDO::FETCH_ASSOC)) {
        $deptLabels[] = ucfirst($row['department']);
        $deptData[] = floatval($row['total']);
    }
    
    return [
        'balanceTrend' => [
            'labels' => $trendLabels,
            'data' => $trendData
        ],
        'top10' => [
            'labels' => $top10Labels,
            'data' => $top10Data
        ],
        'departments' => [
            'labels' => $deptLabels,
            'data' => $deptData
        ]
    ];
}
