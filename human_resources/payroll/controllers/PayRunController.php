<?php
/**
 * Pay Run Controller
 * Manages pay run creation, viewing, processing, and approval
 *
 * Follows same logic structure as payroll-process.php
 *
 * @package HumanResources\Payroll\Controllers
 * @version 1.0.0
 */

declare(strict_types=1);

namespace HumanResources\Payroll\Controllers;

use PayrollModule\Lib\PayrollLogger;

class PayRunController extends BaseController
{
    private \PDO $db;

    public function __construct()
    {
        parent::__construct();

        // Get database connection from global function
        $this->db = getPayrollDb();
    }

    /**
     * List all pay runs - VIEW
     * GET /payruns
     */
    public function index(): void
    {
        try {
            $currentPage = max(1, (int)($_GET['page'] ?? 1));
            $limit = 20;
            $offset = ($currentPage - 1) * $limit;

            // Get pay runs grouped by period
            $query = "
                SELECT
                    period_start,
                    period_end,
                    COUNT(*) as employee_count,
                    SUM(gross_pay) as total_gross,
                    SUM(net_pay) as total_net,
                    SUM(total_deductions) as total_deductions,
                    SUM(total_bonuses) as total_bonuses,
                    GROUP_CONCAT(DISTINCT status) as statuses,
                    MIN(created_at) as created_at,
                    MAX(updated_at) as updated_at
                FROM payroll_payslips
                GROUP BY period_start, period_end
                ORDER BY period_end DESC, period_start DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute([$limit, $offset]);
            $rawPayRuns = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Process pay runs to add derived fields
            $payRuns = [];
            foreach ($rawPayRuns as $run) {
                // Parse statuses (comma-separated from GROUP_CONCAT)
                $statusList = !empty($run['statuses']) ? explode(',', $run['statuses']) : [];

                // Determine primary status (most common or most advanced)
                $statusPriority = ['cancelled', 'paid', 'exported', 'approved', 'reviewed', 'calculated', 'draft'];
                $primaryStatus = 'draft';
                foreach ($statusPriority as $status) {
                    if (in_array($status, $statusList)) {
                        $primaryStatus = $status;
                        break;
                    }
                }

                // Add derived fields
                $run['status'] = $primaryStatus;
                $run['period_key'] = $run['period_start'] . '_' . $run['period_end'];
                $run['period_label'] = 'Week ' . date('W', strtotime($run['period_start']));

                $payRuns[] = $run;
            }

            // Get total count for pagination
            $countQuery = "
                SELECT COUNT(DISTINCT CONCAT(period_start, '_', period_end)) as total
                FROM payroll_payslips
            ";
            $totalRecords = (int)$this->db->query($countQuery)->fetchColumn();
            $totalPages = max(1, (int)ceil($totalRecords / $limit));

            // Get statistics for status cards
            $statsQuery = "
                SELECT
                    status,
                    COUNT(DISTINCT CONCAT(period_start, '_', period_end)) as count
                FROM payroll_payslips
                GROUP BY status
            ";
            $statsResult = $this->db->query($statsQuery)->fetchAll(\PDO::FETCH_ASSOC);

            $stats = [
                'draft' => 0,
                'pending' => 0,
                'approved' => 0,
                'paid' => 0
            ];

            foreach ($statsResult as $row) {
                $status = strtolower($row['status']);
                if (isset($stats[$status])) {
                    $stats[$status] = (int)$row['count'];
                }
            }

            // Render the pay runs list view
            $pageTitle = 'Pay Runs';
            require_once __DIR__ . '/../views/payruns.php';

        } catch (\Throwable $e) {
            $this->logger->error('Failed to load pay runs view', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Set empty defaults and render
            $payRuns = [];
            $stats = ['draft' => 0, 'pending' => 0, 'approved' => 0, 'paid' => 0];
            $currentPage = 1;
            $totalPages = 1;
            $pageTitle = 'Pay Runs';
            require_once __DIR__ . '/../views/payruns.php';
        }
    }

    /**
     * Get pay runs list data - API
     * GET /api/payruns/list
     */
    public function list(): void
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
            $offset = ($page - 1) * $limit;

            // Get pay runs grouped by period
            $query = "
                SELECT
                    period_start,
                    period_end,
                    COUNT(*) as employee_count,
                    SUM(gross_pay) as total_gross,
                    SUM(net_pay) as total_net,
                    SUM(total_deductions) as total_deductions,
                    SUM(total_bonuses) as total_bonuses,
                    GROUP_CONCAT(DISTINCT status) as statuses,
                    MIN(created_at) as created_at,
                    MAX(updated_at) as updated_at
                FROM payroll_payslips
                GROUP BY period_start, period_end
                ORDER BY period_end DESC, period_start DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute([$limit, $offset]);
            $payRuns = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Get total count
            $countQuery = "
                SELECT COUNT(DISTINCT CONCAT(period_start, '_', period_end)) as total
                FROM payroll_payslips
            ";
            $total = (int)$this->db->query($countQuery)->fetchColumn();

            $this->json([
                'success' => true,
                'data' => [
                    'pay_runs' => $payRuns,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'pages' => (int)ceil($total / $limit)
                    ]
                ]
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('Failed to list pay runs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->json([
                'success' => false,
                'error' => 'Failed to load pay runs'
            ], 500);
        }
    }

    /**
     * Get specific pay run details
     * GET /payruns/:period_start/:period_end
     */
    public function show(): void
    {
        try {
            $periodStart = $_GET['period_start'] ?? null;
            $periodEnd = $_GET['period_end'] ?? null;

            if (!$periodStart || !$periodEnd) {
                $this->json([
                    'success' => false,
                    'error' => 'period_start and period_end required'
                ], 400);
                return;
            }

            // Get all payslips for this period
            $query = "
                SELECT
                    ps.*,
                    CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                    u.email as employee_email
                FROM payroll_payslips ps
                LEFT JOIN users u ON ps.staff_id = u.id
                WHERE ps.period_start = ? AND ps.period_end = ?
                ORDER BY u.last_name, u.first_name
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute([$periodStart, $periodEnd]);
            $payslips = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($payslips)) {
                $this->json([
                    'success' => false,
                    'error' => 'Pay run not found'
                ], 404);
                return;
            }

            // Calculate summary
            $summary = [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'employee_count' => count($payslips),
                'total_gross' => array_sum(array_column($payslips, 'gross_pay')),
                'total_net' => array_sum(array_column($payslips, 'net_pay')),
                'total_bonuses' => array_sum(array_column($payslips, 'total_bonuses')),
                'total_deductions' => array_sum(array_column($payslips, 'total_deductions')),
                'statuses' => array_unique(array_column($payslips, 'status'))
            ];

            $this->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'payslips' => $payslips
                ]
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('Failed to load pay run', [
                'period_start' => $_GET['period_start'] ?? null,
                'period_end' => $_GET['period_end'] ?? null,
                'error' => $e->getMessage()
            ]);

            $this->json([
                'success' => false,
                'error' => 'Failed to load pay run'
            ], 500);
        }
    }

    /**
     * View pay run details - VIEW
     * GET /payrun/:periodKey
     * Period key format: 2025-01-13_2025-01-19
     */
    public function view(string $periodKey): void
    {
        // Parse period key (format: YYYY-MM-DD_YYYY-MM-DD)
        $parts = explode('_', $periodKey);
        if (count($parts) !== 2) {
            http_response_code(400);
            echo "Invalid period key format";
            exit;
        }

        list($periodStart, $periodEnd) = $parts;

        // Get pay run data
        $query = "
            SELECT
                ps.*,
                CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                u.email as employee_email,
                u.outlet_id
            FROM payroll_payslips ps
            LEFT JOIN users u ON ps.staff_id = u.id
            WHERE ps.period_start = ? AND ps.period_end = ?
            ORDER BY u.last_name, u.first_name
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$periodStart, $periodEnd]);
        $payslips = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($payslips)) {
            http_response_code(404);
            require_once __DIR__ . '/../views/errors/404.php';
            exit;
        }

        // Calculate summary
        $summary = [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'period_key' => $periodKey,
            'employee_count' => count($payslips),
            'total_gross' => array_sum(array_column($payslips, 'gross_pay')),
            'total_net' => array_sum(array_column($payslips, 'net_pay')),
            'total_bonuses' => array_sum(array_column($payslips, 'total_bonuses')),
            'total_deductions' => array_sum(array_column($payslips, 'total_deductions')),
            'statuses' => array_unique(array_column($payslips, 'status'))
        ];

        // Render the pay run detail view
        $pageTitle = "Pay Run: " . date('M j', strtotime($periodStart)) . " - " . date('M j, Y', strtotime($periodEnd));
        require_once __DIR__ . '/../views/payrun-detail.php';
    }

    /**
     * Get latest/current pay run
     * GET /payruns/current
     */
    public function current(): void
    {
        try {
            $query = "
                SELECT
                    period_start,
                    period_end,
                    COUNT(*) as employee_count,
                    SUM(gross_pay) as total_gross,
                    SUM(net_pay) as total_net,
                    GROUP_CONCAT(DISTINCT status) as statuses,
                    MAX(created_at) as created_at
                FROM payroll_payslips
                GROUP BY period_start, period_end
                ORDER BY period_end DESC, created_at DESC
                LIMIT 1
            ";

            $payRun = $this->db->query($query)->fetch(\PDO::FETCH_ASSOC);

            if (!$payRun) {
                $this->json([
                    'success' => false,
                    'error' => 'No pay runs found'
                ], 404);
                return;
            }

            $this->json([
                'success' => true,
                'data' => $payRun
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('Failed to load current pay run', [
                'error' => $e->getMessage()
            ]);

            $this->json([
                'success' => false,
                'error' => 'Failed to load current pay run'
            ], 500);
        }
    }

    /**
     * Create new pay run from Deputy timesheets
     * POST /payruns/create
     * Following payroll-process.php logic
     */
    public function create(): void
    {
        try {
            $data = $this->getJsonInput();

            $periodStart = $data['period_start'] ?? null;
            $periodEnd = $data['period_end'] ?? null;
            $autoApprove = (bool)($data['auto_approve'] ?? false);

            if (!$periodStart || !$periodEnd) {
                $this->json([
                    'success' => false,
                    'error' => 'period_start and period_end required'
                ], 400);
                return;
            }

            // Check if pay run already exists
            $checkQuery = "
                SELECT COUNT(*) FROM payroll_payslips
                WHERE period_start = ? AND period_end = ?
            ";
            $stmt = $this->db->prepare($checkQuery);
            $stmt->execute([$periodStart, $periodEnd]);
            $exists = (int)$stmt->fetchColumn();

            if ($exists > 0) {
                $this->json([
                    'success' => false,
                    'error' => 'Pay run already exists for this period'
                ], 409);
                return;
            }

            // Start transaction
            $this->db->beginTransaction();

            try {
                // Step 1: Fetch Deputy timesheets (following payroll-process.php)
                $this->logger->info('Fetching Deputy timesheets', [
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd
                ]);

                $timesheets = $this->deputyService->getTimesheetsForPeriod($periodStart, $periodEnd);

                if (empty($timesheets)) {
                    throw new \Exception('No timesheets found for this period');
                }

                // Step 2: Fetch Vend account payments
                $this->logger->info('Fetching Vend account payments');
                $vendPayments = $this->vendService->getAccountPaymentsForPeriod($periodStart, $periodEnd);

                // Step 3: Process each employee
                $processedCount = 0;
                $errors = [];

                foreach ($timesheets as $staffId => $employeeTimesheets) {
                    try {
                        // Calculate payslip
                        $payslip = $this->calculatePayslip(
                            $staffId,
                            $employeeTimesheets,
                            $vendPayments[$staffId] ?? [],
                            $periodStart,
                            $periodEnd
                        );

                        // Insert payslip
                        $this->insertPayslip($payslip, $autoApprove ? 'approved' : 'calculated');

                        $processedCount++;

                    } catch (\Throwable $e) {
                        $errors[] = [
                            'staff_id' => $staffId,
                            'error' => $e->getMessage()
                        ];
                        $this->logger->error('Failed to process employee payslip', [
                            'staff_id' => $staffId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // Commit transaction
                $this->db->commit();

                $this->logger->info('Pay run created successfully', [
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'processed' => $processedCount,
                    'errors' => count($errors)
                ]);

                $this->json([
                    'success' => true,
                    'data' => [
                        'period_start' => $periodStart,
                        'period_end' => $periodEnd,
                        'processed' => $processedCount,
                        'errors' => $errors
                    ]
                ]);

            } catch (\Throwable $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (\Throwable $e) {
            $this->logger->error('Failed to create pay run', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate payslip for employee
     * Following payroll-process.php calculation logic
     */
    private function calculatePayslip(
        int $staffId,
        array $timesheets,
        array $vendPayments,
        string $periodStart,
        string $periodEnd
    ): array {
        // Initialize payslip
        $payslip = [
            'staff_id' => $staffId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'ordinary_hours' => 0,
            'ordinary_pay' => 0,
            'overtime_hours' => 0,
            'overtime_pay' => 0,
            'night_shift_hours' => 0,
            'night_shift_pay' => 0,
            'public_holiday_hours' => 0,
            'public_holiday_pay' => 0,
            'alternative_holidays_entitled' => 0,
            'vape_drops_bonus' => 0,
            'google_reviews_bonus' => 0,
            'monthly_bonus' => 0,
            'commission' => 0,
            'acting_position_pay' => 0,
            'gamification_bonus' => 0,
            'total_bonuses' => 0,
            'leave_deduction' => 0,
            'advances_deduction' => 0,
            'student_loan_deduction' => 0,
            'kiwisaver_deduction' => 0,
            'other_deductions' => 0,
            'total_deductions' => 0,
            'gross_pay' => 0,
            'net_pay' => 0,
            'timesheets_json' => json_encode($timesheets),
            'amendments_applied' => 0,
            'calculation_notes' => ''
        ];

        // Calculate hours and pay from timesheets
        foreach ($timesheets as $timesheet) {
            $hours = (float)($timesheet['total_hours'] ?? 0);
            $rate = (float)($timesheet['hourly_rate'] ?? 0);
            $type = $timesheet['timesheet_type'] ?? 'ordinary';

            switch ($type) {
                case 'ordinary':
                    $payslip['ordinary_hours'] += $hours;
                    $payslip['ordinary_pay'] += $hours * $rate;
                    break;
                case 'overtime':
                    $payslip['overtime_hours'] += $hours;
                    $payslip['overtime_pay'] += $hours * $rate * 1.5; // Time and a half
                    break;
                case 'night_shift':
                    $payslip['night_shift_hours'] += $hours;
                    $payslip['night_shift_pay'] += $hours * $rate * 1.2; // Night rate
                    break;
                case 'public_holiday':
                    $payslip['public_holiday_hours'] += $hours;
                    $payslip['public_holiday_pay'] += $hours * $rate * 2.0; // Double time
                    $payslip['alternative_holidays_entitled']++;
                    break;
            }
        }

        // Calculate bonuses (fetch from payroll_bonuses or other tables)
        // This would integrate with BonusController logic
        $bonuses = $this->getBonusesForEmployee($staffId, $periodStart, $periodEnd);
        $payslip['vape_drops_bonus'] = $bonuses['vape_drops'] ?? 0;
        $payslip['google_reviews_bonus'] = $bonuses['google_reviews'] ?? 0;
        $payslip['monthly_bonus'] = $bonuses['monthly'] ?? 0;

        $payslip['total_bonuses'] =
            $payslip['vape_drops_bonus'] +
            $payslip['google_reviews_bonus'] +
            $payslip['monthly_bonus'] +
            $payslip['commission'] +
            $payslip['acting_position_pay'] +
            $payslip['gamification_bonus'];

        // Calculate deductions from Vend payments
        foreach ($vendPayments as $payment) {
            $payslip['other_deductions'] += (float)($payment['amount'] ?? 0);
        }

        // Calculate tax deductions (simplified - should use proper tax tables)
        $grossBeforeTax =
            $payslip['ordinary_pay'] +
            $payslip['overtime_pay'] +
            $payslip['night_shift_pay'] +
            $payslip['public_holiday_pay'] +
            $payslip['total_bonuses'];

        // Apply tax brackets (NZ 2024/2025 - simplified)
        $paye = $this->calculatePAYE($grossBeforeTax);

        // KiwiSaver (3% default)
        $payslip['kiwisaver_deduction'] = $grossBeforeTax * 0.03;

        $payslip['total_deductions'] =
            $paye +
            $payslip['leave_deduction'] +
            $payslip['advances_deduction'] +
            $payslip['student_loan_deduction'] +
            $payslip['kiwisaver_deduction'] +
            $payslip['other_deductions'];

        $payslip['gross_pay'] = $grossBeforeTax;
        $payslip['net_pay'] = $grossBeforeTax - $payslip['total_deductions'];

        return $payslip;
    }

    /**
     * Calculate PAYE (simplified NZ tax calculation)
     */
    private function calculatePAYE(float $grossPay): float
    {
        // NZ PAYE 2024/2025 (annual, converted to weekly/fortnightly)
        // This is simplified - proper implementation should use IRD tax tables
        if ($grossPay <= 14000) {
            return $grossPay * 0.105;
        } elseif ($grossPay <= 48000) {
            return 1470 + (($grossPay - 14000) * 0.175);
        } elseif ($grossPay <= 70000) {
            return 7420 + (($grossPay - 48000) * 0.30);
        } elseif ($grossPay <= 180000) {
            return 14020 + (($grossPay - 70000) * 0.33);
        } else {
            return 50320 + (($grossPay - 180000) * 0.39);
        }
    }

    /**
     * Get bonuses for employee in period
     */
    private function getBonusesForEmployee(int $staffId, string $periodStart, string $periodEnd): array
    {
        // Query bonuses from database
        // This would integrate with the Bonus system
        $query = "
            SELECT
                SUM(CASE WHEN bonus_type = 'vape_drop' THEN amount ELSE 0 END) as vape_drops,
                SUM(CASE WHEN bonus_type = 'google_review' THEN amount ELSE 0 END) as google_reviews,
                SUM(CASE WHEN bonus_type = 'monthly' THEN amount ELSE 0 END) as monthly
            FROM payroll_bonuses
            WHERE staff_id = ?
            AND bonus_date BETWEEN ? AND ?
            AND status = 'approved'
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$staffId, $periodStart, $periodEnd]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: ['vape_drops' => 0, 'google_reviews' => 0, 'monthly' => 0];
    }

    /**
     * Insert payslip into database
     */
    private function insertPayslip(array $payslip, string $status = 'calculated'): void
    {
        $payslip['status'] = $status;
        $payslip['created_at'] = date('Y-m-d H:i:s');
        $payslip['updated_at'] = date('Y-m-d H:i:s');

        $columns = array_keys($payslip);
        $placeholders = array_fill(0, count($columns), '?');

        $query = sprintf(
            "INSERT INTO payroll_payslips (%s) VALUES (%s)",
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->db->prepare($query);
        $stmt->execute(array_values($payslip));
    }

    /**
     * Approve pay run
     * POST /payruns/:period_start/:period_end/approve
     */
    public function approve(): void
    {
        try {
            $periodStart = $_POST['period_start'] ?? null;
            $periodEnd = $_POST['period_end'] ?? null;

            if (!$periodStart || !$periodEnd) {
                $this->json([
                    'success' => false,
                    'error' => 'period_start and period_end required'
                ], 400);
                return;
            }

            $userId = $this->getCurrentUserId();

            $query = "
                UPDATE payroll_payslips
                SET status = 'approved',
                    approved_by = ?,
                    approved_at = NOW(),
                    updated_at = NOW()
                WHERE period_start = ?
                AND period_end = ?
                AND status IN ('calculated', 'reviewed')
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute([$userId, $periodStart, $periodEnd]);
            $affected = $stmt->rowCount();

            $this->logger->info('Pay run approved', [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'approved_by' => $userId,
                'payslips' => $affected
            ]);

            $this->json([
                'success' => true,
                'data' => [
                    'approved_count' => $affected
                ]
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('Failed to approve pay run', [
                'error' => $e->getMessage()
            ]);

            $this->json([
                'success' => false,
                'error' => 'Failed to approve pay run'
            ], 500);
        }
    }

    /**
     * Export pay run to Xero
     * POST /payruns/:period_start/:period_end/export
     */
    public function export(): void
    {
        try {
            $periodStart = $_POST['period_start'] ?? null;
            $periodEnd = $_POST['period_end'] ?? null;

            if (!$periodStart || !$periodEnd) {
                $this->json([
                    'success' => false,
                    'error' => 'period_start and period_end required'
                ], 400);
                return;
            }

            // Get all approved payslips
            $query = "
                SELECT * FROM payroll_payslips
                WHERE period_start = ?
                AND period_end = ?
                AND status = 'approved'
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute([$periodStart, $periodEnd]);
            $payslips = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($payslips)) {
                $this->json([
                    'success' => false,
                    'error' => 'No approved payslips found for export'
                ], 404);
                return;
            }

            // Export to Xero (this would integrate with Xero API)
            // For now, just mark as exported
            $updateQuery = "
                UPDATE payroll_payslips
                SET status = 'exported',
                    exported_at = NOW(),
                    updated_at = NOW()
                WHERE period_start = ?
                AND period_end = ?
                AND status = 'approved'
            ";

            $stmt = $this->db->prepare($updateQuery);
            $stmt->execute([$periodStart, $periodEnd]);

            $this->logger->info('Pay run exported', [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'payslips' => count($payslips)
            ]);

            $this->json([
                'success' => true,
                'data' => [
                    'exported_count' => count($payslips)
                ]
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('Failed to export pay run', [
                'error' => $e->getMessage()
            ]);

            $this->json([
                'success' => false,
                'error' => 'Failed to export pay run'
            ], 500);
        }
    }
}
