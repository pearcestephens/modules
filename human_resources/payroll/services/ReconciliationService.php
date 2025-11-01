<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Services;

use PDO;

/**
 * Reconciliation Service
 *
 * Detects variances between:
 * - CIS calculated payroll vs Xero final payslips
 * - Deputy timesheet hours vs Xero payslip hours
 * - Vend account balances vs actual deductions
 *
 * Provides drill-down variance reporting for payroll accuracy auditing
 *
 * @package HumanResources\Payroll\Services
 * @version 1.0.0
 */
class ReconciliationService
{
    private PDO $pdo;

    /** Variance tolerance (absolute difference considered "matched") */
    private const TOLERANCE_CENTS = 5; // $0.05
    private const TOLERANCE_HOURS = 0.1; // 6 minutes

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get reconciliation overview for a pay run
     *
     * @param int $runId Pay run ID
     * @return array Reconciliation summary with variance counts
     */
    public function getRunReconciliation(int $runId): array
    {
        $payrollVariances = $this->detectPayrollVariances($runId);
        $hoursVariances = $this->detectHoursVariances($runId);
        $deductionVariances = $this->detectDeductionVariances($runId);

        return [
            'run_id' => $runId,
            'summary' => [
                'total_employees' => $this->getEmployeeCount($runId),
                'payroll_variances' => count($payrollVariances),
                'hours_variances' => count($hoursVariances),
                'deduction_variances' => count($deductionVariances),
                'total_variances' => count($payrollVariances) + count($hoursVariances) + count($deductionVariances)
            ],
            'variances' => [
                'payroll' => $payrollVariances,
                'hours' => $hoursVariances,
                'deductions' => $deductionVariances
            ]
        ];
    }

    /**
     * Detect payroll amount variances (CIS vs Xero)
     *
     * @param int $runId Pay run ID
     * @return array Array of variance records
     */
    private function detectPayrollVariances(int $runId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                ed.id,
                ed.user_id,
                ed.employee_name,
                ed.gross_earnings AS cis_gross,
                ed.net_pay AS cis_net,
                ps.gross_earnings AS xero_gross,
                ps.total_pay AS xero_net,
                (ed.gross_earnings - ps.gross_earnings) AS gross_variance,
                (ed.net_pay - ps.total_pay) AS net_variance
            FROM payroll_employee_details ed
            LEFT JOIN xero_payslips ps ON ed.xero_payslip_id = ps.payslip_id
            WHERE ed.run_id = ?
                AND ps.payslip_id IS NOT NULL
                AND (
                    ABS(ed.gross_earnings - ps.gross_earnings) > ?
                    OR ABS(ed.net_pay - ps.total_pay) > ?
                )
            ORDER BY ABS(ed.gross_earnings - ps.gross_earnings) DESC
        ");

        $stmt->execute([$runId, self::TOLERANCE_CENTS / 100, self::TOLERANCE_CENTS / 100]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function($row) {
            return [
                'employee_id' => $row['user_id'],
                'employee_name' => $row['employee_name'],
                'cis_gross' => (float)$row['cis_gross'],
                'xero_gross' => (float)$row['xero_gross'],
                'gross_variance' => (float)$row['gross_variance'],
                'cis_net' => (float)$row['cis_net'],
                'xero_net' => (float)$row['xero_net'],
                'net_variance' => (float)$row['net_variance'],
                'severity' => $this->calculateSeverity((float)$row['gross_variance'], (float)$row['cis_gross'])
            ];
        }, $results);
    }

    /**
     * Detect hours variances (Deputy vs Xero)
     *
     * @param int $runId Pay run ID
     * @return array Array of variance records
     */
    private function detectHoursVariances(int $runId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                ed.user_id,
                ed.employee_name,
                ed.total_hours AS cis_total_hours,
                ed.ordinary_hours AS cis_ordinary,
                ed.overtime_hours AS cis_overtime,
                ed.deputy_timesheet_count,
                SUM(CASE WHEN pxl.line_category = 'earnings' AND pxl.display_name LIKE '%Ordinary%'
                    THEN pxl.number_of_units ELSE 0 END) AS xero_ordinary,
                SUM(CASE WHEN pxl.line_category = 'earnings' AND pxl.display_name LIKE '%Overtime%'
                    THEN pxl.number_of_units ELSE 0 END) AS xero_overtime,
                SUM(CASE WHEN pxl.line_category = 'earnings'
                    THEN pxl.number_of_units ELSE 0 END) AS xero_total_hours
            FROM payroll_employee_details ed
            LEFT JOIN payroll_xero_payslip_lines pxl ON ed.id = pxl.employee_detail_id
            WHERE ed.run_id = ?
            GROUP BY ed.id
            HAVING ABS(cis_total_hours - xero_total_hours) > ?
            ORDER BY ABS(cis_total_hours - xero_total_hours) DESC
        ");

        $stmt->execute([$runId, self::TOLERANCE_HOURS]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function($row) {
            return [
                'employee_id' => $row['user_id'],
                'employee_name' => $row['employee_name'],
                'cis_hours' => (float)$row['cis_total_hours'],
                'xero_hours' => (float)$row['xero_total_hours'],
                'hours_variance' => (float)($row['cis_total_hours'] - $row['xero_total_hours']),
                'breakdown' => [
                    'cis_ordinary' => (float)$row['cis_ordinary'],
                    'cis_overtime' => (float)$row['cis_overtime'],
                    'xero_ordinary' => (float)$row['xero_ordinary'],
                    'xero_overtime' => (float)$row['xero_overtime']
                ],
                'deputy_timesheets' => (int)$row['deputy_timesheet_count'],
                'severity' => $this->calculateHoursSeverity((float)($row['cis_total_hours'] - $row['xero_total_hours']))
            ];
        }, $results);
    }

    /**
     * Detect deduction variances (expected vs actual)
     *
     * @param int $runId Pay run ID
     * @return array Array of variance records
     */
    private function detectDeductionVariances(int $runId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                ed.user_id,
                ed.employee_name,
                ed.account_payment_deduction AS expected_deduction,
                ed.vend_account_balance,
                SUM(CASE WHEN pxl.line_category = 'deduction'
                    THEN pxl.calculated_amount ELSE 0 END) AS actual_deduction,
                (ed.account_payment_deduction -
                 SUM(CASE WHEN pxl.line_category = 'deduction'
                     THEN pxl.calculated_amount ELSE 0 END)) AS variance
            FROM payroll_employee_details ed
            LEFT JOIN payroll_xero_payslip_lines pxl ON ed.id = pxl.employee_detail_id
            WHERE ed.run_id = ?
                AND ed.account_payment_deduction > 0
            GROUP BY ed.id
            HAVING ABS(variance) > ?
            ORDER BY ABS(variance) DESC
        ");

        $stmt->execute([$runId, self::TOLERANCE_CENTS / 100]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function($row) {
            return [
                'employee_id' => $row['user_id'],
                'employee_name' => $row['employee_name'],
                'expected_deduction' => (float)$row['expected_deduction'],
                'actual_deduction' => (float)$row['actual_deduction'],
                'variance' => (float)$row['variance'],
                'vend_balance' => (float)$row['vend_account_balance'],
                'severity' => $this->calculateSeverity((float)$row['variance'], (float)$row['expected_deduction'])
            ];
        }, $results);
    }

    /**
     * Get detailed variance breakdown for an employee
     *
     * @param int $runId Pay run ID
     * @param int $userId User ID
     * @return array Detailed variance information
     */
    public function getEmployeeVarianceDetail(int $runId, int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM payroll_employee_details
            WHERE run_id = ? AND user_id = ?
        ");
        $stmt->execute([$runId, $userId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$employee) {
            return ['error' => 'Employee not found'];
        }

        // Get Xero payslip lines
        $stmt = $this->pdo->prepare("
            SELECT * FROM payroll_xero_payslip_lines
            WHERE employee_detail_id = ?
            ORDER BY line_category, line_order
        ");
        $stmt->execute([$employee['id']]);
        $xeroLines = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'employee' => [
                'id' => $employee['user_id'],
                'name' => $employee['employee_name'],
                'email' => $employee['employee_email']
            ],
            'cis_data' => [
                'total_hours' => (float)$employee['total_hours'],
                'gross_earnings' => (float)$employee['gross_earnings'],
                'net_pay' => (float)$employee['net_pay'],
                'deductions' => (float)$employee['total_deductions']
            ],
            'xero_lines' => array_map(function($line) {
                return [
                    'category' => $line['line_category'],
                    'description' => $line['display_name'],
                    'units' => (float)$line['number_of_units'],
                    'rate' => (float)$line['rate_per_unit'],
                    'amount' => (float)$line['calculated_amount']
                ];
            }, $xeroLines),
            'variances' => $this->calculateEmployeeVariances($employee, $xeroLines)
        ];
    }

    /**
     * Calculate variances for a single employee
     */
    private function calculateEmployeeVariances(array $employee, array $xeroLines): array
    {
        $xeroGross = array_sum(array_map(fn($l) => $l['line_category'] === 'earnings' ? (float)$l['calculated_amount'] : 0, $xeroLines));
        $xeroHours = array_sum(array_map(fn($l) => $l['line_category'] === 'earnings' ? (float)$l['number_of_units'] : 0, $xeroLines));
        $xeroDeductions = array_sum(array_map(fn($l) => $l['line_category'] === 'deduction' ? (float)$l['calculated_amount'] : 0, $xeroLines));

        return [
            'gross' => [
                'cis' => (float)$employee['gross_earnings'],
                'xero' => $xeroGross,
                'variance' => (float)$employee['gross_earnings'] - $xeroGross
            ],
            'hours' => [
                'cis' => (float)$employee['total_hours'],
                'xero' => $xeroHours,
                'variance' => (float)$employee['total_hours'] - $xeroHours
            ],
            'deductions' => [
                'cis' => (float)$employee['total_deductions'],
                'xero' => $xeroDeductions,
                'variance' => (float)$employee['total_deductions'] - $xeroDeductions
            ]
        ];
    }

    /**
     * Calculate severity level (low/medium/high/critical)
     */
    private function calculateSeverity(float $variance, float $base): string
    {
        if ($base == 0) return 'unknown';

        $percentVariance = abs($variance / $base * 100);

        if ($percentVariance < 1) return 'low';
        if ($percentVariance < 5) return 'medium';
        if ($percentVariance < 10) return 'high';
        return 'critical';
    }

    /**
     * Calculate hours variance severity
     */
    private function calculateHoursSeverity(float $variance): string
    {
        $absVariance = abs($variance);

        if ($absVariance < 0.5) return 'low'; // < 30 minutes
        if ($absVariance < 2) return 'medium'; // < 2 hours
        if ($absVariance < 4) return 'high'; // < 4 hours
        return 'critical'; // >= 4 hours
    }

    /**
     * Get employee count for run
     */
    private function getEmployeeCount(int $runId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM payroll_employee_details WHERE run_id = ?
        ");
        $stmt->execute([$runId]);
        return (int)$stmt->fetchColumn();
    }
}
