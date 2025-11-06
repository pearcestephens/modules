<?php
/**
 * API: Sync from Deputy
 *
 * Syncs employees, timesheets, or other data from Deputy into CIS
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../includes/DeputyIntegration.php';

if (!isset($_SESSION['userID'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$deputy = new DeputyIntegration($pdo);
$type = $_GET['type'] ?? '';

try {
    switch ($type) {
        case 'employees':
            // Get all employees from Deputy
            $employees = $deputy->getAllEmployees();

            if (!$employees || !isset($employees['data'])) {
                throw new Exception('Failed to fetch employees from Deputy');
            }

            $synced = 0;
            $updated = 0;

            foreach ($employees['data'] as $emp) {
                // Check if staff exists by Deputy ID
                $stmt = $pdo->prepare("SELECT id FROM staff WHERE deputy_id = ?");
                $stmt->execute([$emp['Id']]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    // Update existing staff
                    $stmt = $pdo->prepare("
                        UPDATE staff SET
                            first_name = ?,
                            last_name = ?,
                            email = ?,
                            active = ?
                        WHERE deputy_id = ?
                    ");
                    $stmt->execute([
                        $emp['FirstName'] ?? '',
                        $emp['LastName'] ?? '',
                        $emp['Email'] ?? '',
                        $emp['Active'] ?? 1,
                        $emp['Id']
                    ]);
                    $updated++;
                } else {
                    // Insert new staff
                    $stmt = $pdo->prepare("
                        INSERT INTO staff (deputy_id, first_name, last_name, email, active, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $emp['Id'],
                        $emp['FirstName'] ?? '',
                        $emp['LastName'] ?? '',
                        $emp['Email'] ?? '',
                        $emp['Active'] ?? 1
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

        case 'timesheets':
            // Get timesheets from last 7 days
            $startDate = date('Y-m-d', strtotime('-7 days'));
            $endDate = date('Y-m-d');

            $synced = 0;

            // Get all staff with Deputy IDs
            $stmt = $pdo->query("SELECT id, deputy_id FROM staff WHERE deputy_id IS NOT NULL AND deputy_id != ''");
            $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($staff as $member) {
                try {
                    $timesheets = $deputy->getTimesheets($member['deputy_id'], $startDate, $endDate);

                    if ($timesheets && isset($timesheets['data'])) {
                        foreach ($timesheets['data'] as $ts) {
                            // Check if timesheet already exists
                            $stmt = $pdo->prepare("
                                SELECT id FROM payroll_timesheet_amendments
                                WHERE staff_id = ? AND timesheet_date = ?
                            ");
                            $stmt->execute([
                                $member['id'],
                                $ts['Date'] ?? date('Y-m-d')
                            ]);

                            if (!$stmt->fetch()) {
                                // Insert new timesheet
                                $stmt = $pdo->prepare("
                                    INSERT INTO payroll_timesheet_amendments
                                    (staff_id, timesheet_date, original_hours, new_hours, reason, status, created_at)
                                    VALUES (?, ?, ?, ?, ?, 'approved', NOW())
                                ");
                                $stmt->execute([
                                    $member['id'],
                                    $ts['Date'] ?? date('Y-m-d'),
                                    $ts['TotalHours'] ?? 0,
                                    $ts['TotalHours'] ?? 0,
                                    'Synced from Deputy'
                                ]);
                                $synced++;
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Continue with next staff member
                    continue;
                }
            }

            echo json_encode([
                'success' => true,
                'synced' => $synced,
                'staff_count' => count($staff)
            ]);
            break;

        default:
            throw new Exception('Invalid sync type. Use: employees or timesheets');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
