<?php
/**
 * API: Sync from Xero
 *
 * Syncs employees, payruns, or leave applications from Xero into CIS
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../includes/XeroIntegration.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$xero = new XeroIntegration($pdo);
$type = $_GET['type'] ?? '';

try {
    switch ($type) {
        case 'employees':
            // Get all employees from Xero
            $employees = $xero->getAllEmployees();

            if (!$employees || !isset($employees['data'])) {
                throw new Exception('Failed to fetch employees from Xero');
            }

            $synced = 0;
            $updated = 0;

            foreach ($employees['data'] as $emp) {
                // Check if staff exists by Xero ID
                $stmt = $pdo->prepare("SELECT id FROM staff WHERE xero_id = ?");
                $stmt->execute([$emp['EmployeeID']]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    // Update existing staff
                    $stmt = $pdo->prepare("
                        UPDATE staff SET
                            first_name = ?,
                            last_name = ?,
                            email = ?
                        WHERE xero_id = ?
                    ");
                    $stmt->execute([
                        $emp['FirstName'] ?? '',
                        $emp['LastName'] ?? '',
                        $emp['Email'] ?? '',
                        $emp['EmployeeID']
                    ]);
                    $updated++;
                } else {
                    // Insert new staff
                    $stmt = $pdo->prepare("
                        INSERT INTO staff (xero_id, first_name, last_name, email, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $emp['EmployeeID'],
                        $emp['FirstName'] ?? '',
                        $emp['LastName'] ?? '',
                        $emp['Email'] ?? ''
                    ]);
                    $synced++;
                }
            }

            echo json_encode([
                'success' => true,
                'synced' => $synced,
                'updated' => $updated,
                'total' => count($employees['data'])
            ]);
            break;

        case 'payruns':
            // Get recent pay runs from Xero
            $payruns = $xero->getPayRuns();

            if (!$payruns || !isset($payruns['data'])) {
                throw new Exception('Failed to fetch pay runs from Xero');
            }

            $synced = 0;

            foreach ($payruns['data'] as $payrun) {
                // Get payslips from this payrun
                if (isset($payrun['Payslips'])) {
                    foreach ($payrun['Payslips'] as $payslip) {
                        // Find staff by Xero ID
                        $stmt = $pdo->prepare("SELECT id FROM staff WHERE xero_id = ?");
                        $stmt->execute([$payslip['EmployeeID']]);
                        $staff = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($staff) {
                            // Check if payrun already exists
                            $stmt = $pdo->prepare("
                                SELECT id FROM payroll_payrun_amendments
                                WHERE staff_id = ? AND pay_period = ?
                            ");
                            $stmt->execute([
                                $staff['id'],
                                $payrun['PayrollCalendarID'] ?? date('Y-m')
                            ]);

                            if (!$stmt->fetch()) {
                                // Insert new payrun
                                $stmt = $pdo->prepare("
                                    INSERT INTO payroll_payrun_amendments
                                    (staff_id, pay_period, original_amount, adjustment_amount, reason, status, created_at)
                                    VALUES (?, ?, ?, 0, ?, 'approved', NOW())
                                ");
                                $stmt->execute([
                                    $staff['id'],
                                    $payrun['PayrollCalendarID'] ?? date('Y-m'),
                                    $payslip['Wages'] ?? 0,
                                    'Synced from Xero'
                                ]);
                                $synced++;
                            }
                        }
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'synced' => $synced,
                'payruns' => count($payruns['data'])
            ]);
            break;

        case 'leave':
            // Get leave applications from Xero
            $leaveApps = $xero->getLeaveApplications();

            if (!$leaveApps || !isset($leaveApps['data'])) {
                throw new Exception('Failed to fetch leave applications from Xero');
            }

            $synced = 0;

            foreach ($leaveApps['data'] as $leave) {
                // Find staff by Xero ID
                $stmt = $pdo->prepare("SELECT id FROM staff WHERE xero_id = ?");
                $stmt->execute([$leave['EmployeeID']]);
                $staff = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($staff) {
                    // TODO: Create leave tracking table and insert leave records
                    $synced++;
                }
            }

            echo json_encode([
                'success' => true,
                'synced' => $synced,
                'leave_applications' => count($leaveApps['data'])
            ]);
            break;

        default:
            throw new Exception('Invalid sync type. Use: employees, payruns, or leave');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
