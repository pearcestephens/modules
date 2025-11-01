<?php
declare(strict_types=1);

/**
 * Payroll Snapshot Manager
 *
 * Handles complete state capture of payroll runs including:
 * - User objects (CIS processed data)
 * - Deputy timesheets
 * - Vend account balances
 * - Xero payslips and responses
 * - Public holiday data
 * - Bonus calculations
 * - Amendments
 *
 * Philosophy:
 * - Snapshot EVERYTHING before any Xero push
 * - Every button click = new revision
 * - Immutable snapshots (never modify, only append)
 * - Full diff capability between any two states
 * - Amendment tracking with approval workflow
 *
 * @package CIS\HumanResources\Payroll
 * @version 1.0.0
 * @created 2025-10-29
 */

class PayrollSnapshotManager
{
    private PDO $pdo;
    private string $xeroTenantId;
    private ?int $currentUserId;

    public function __construct(PDO $pdo, string $xeroTenantId, ?int $currentUserId = null)
    {
        $this->pdo = $pdo;
        $this->xeroTenantId = $xeroTenantId;
        $this->currentUserId = $currentUserId;
    }

    /**
     * Start a new pay run
     *
     * Called when "Load Payroll" button is clicked
     *
     * @param string $periodStart Format: Y-m-d
     * @param string $periodEnd Format: Y-m-d
     * @param string $paymentDate Format: Y-m-d
     * @param string|null $notes Optional notes about this run
     * @return array ['run_id' => int, 'run_uuid' => string]
     */
    public function startPayRun(
        string $periodStart,
        string $periodEnd,
        string $paymentDate,
        ?string $notes = null
    ): array {
        // Get next run number
        $stmt = $this->pdo->query("SELECT IFNULL(MAX(run_number), 0) + 1 AS next_number FROM payroll_runs");
        $nextNumber = (int) $stmt->fetch(PDO::FETCH_COLUMN);

        $runUuid = $this->generateUuid();

        $stmt = $this->pdo->prepare("
            INSERT INTO payroll_runs (
                run_uuid, run_number,
                period_start, period_end, payment_date,
                started_at, status, xero_tenant_id,
                created_by_user_id, notes
            ) VALUES (?, ?, ?, ?, ?, NOW(), 'draft', ?, ?, ?)
        ");

        $stmt->execute([
            $runUuid,
            $nextNumber,
            $periodStart,
            $periodEnd,
            $paymentDate,
            $this->xeroTenantId,
            $this->currentUserId,
            $notes
        ]);

        $runId = (int) $this->pdo->lastInsertId();

        payroll_log('info', "Started pay run #{$nextNumber}", [
            'run_id' => $runId,
            'run_uuid' => $runUuid,
            'period' => "$periodStart to $periodEnd"
        ]);

        return [
            'run_id' => $runId,
            'run_uuid' => $runUuid,
            'run_number' => $nextNumber
        ];
    }

    /**
     * Create a new revision (every button click)
     *
     * @param int $runId
     * @param string $actionType One of: load_payroll, calculate_bonuses, etc.
     * @param string|null $description Human-readable description
     * @param int $employeesAffected
     * @param float $totalPayDelta
     * @return int Revision ID
     */
    public function createRevision(
        int $runId,
        string $actionType,
        ?string $description = null,
        int $employeesAffected = 0,
        float $totalPayDelta = 0.0
    ): int {
        // Get next revision number for this run
        $stmt = $this->pdo->prepare("
            SELECT IFNULL(MAX(revision_number), 0) + 1
            FROM payroll_run_revisions
            WHERE run_id = ?
        ");
        $stmt->execute([$runId]);
        $revisionNumber = (int) $stmt->fetch(PDO::FETCH_COLUMN);

        $stmt = $this->pdo->prepare("
            INSERT INTO payroll_run_revisions (
                run_id, revision_number, action_type, action_description,
                performed_by_user_id, performed_at,
                employees_affected, total_pay_delta,
                ip_address, user_agent
            ) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)
        ");

        $stmt->execute([
            $runId,
            $revisionNumber,
            $actionType,
            $description,
            $this->currentUserId,
            $employeesAffected,
            $totalPayDelta,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);

        $revisionId = (int) $this->pdo->lastInsertId();

        payroll_log('info', "Created revision #{$revisionNumber}: {$actionType}", [
            'run_id' => $runId,
            'revision_id' => $revisionId,
            'action' => $actionType
        ]);

        return $revisionId;
    }

    /**
     * 🌟 THE BIG ONE: Capture Complete Snapshot
     *
     * Stores EVERYTHING about the current payroll state
     *
     * @param int $runId
     * @param int|null $revisionId
     * @param array $userObjects Array of complete $userObject from payroll-process.php
     * @param array|null $deputyTimesheets Raw Deputy API responses
     * @param array|null $vendBalances Vend account balance data
     * @param array|null $xeroPayslips Xero payslip responses (after push) - SDK objects will be converted
     * @param array|null $xeroEmployees Xero employee details
     * @param array|null $publicHolidays Public holiday data
     * @param array|null $bonusCalculations Detailed bonus breakdowns
     * @param array|null $amendments Any manual adjustments
     * @param string $snapshotType One of: pre_load, pre_push, post_push, amendment, manual
     * @return int Snapshot ID
     */
    public function captureSnapshot(
        int $runId,
        ?int $revisionId,
        array $userObjects,
        ?array $deputyTimesheets = null,
        ?array $vendBalances = null,
        ?array $xeroPayslips = null,
        ?array $xeroEmployees = null,
        ?array $publicHolidays = null,
        ?array $bonusCalculations = null,
        ?array $amendments = null,
        string $snapshotType = 'manual'
    ): int {
        // Convert Xero SDK objects to plain arrays for storage
        if ($xeroPayslips !== null) {
            $xeroPayslips = $this->convertXeroPayslipsToArray($xeroPayslips);
        }
        // Prepare JSON blobs
        $userObjectsJson = json_encode($userObjects, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $deputyTimesheetsJson = $deputyTimesheets ? json_encode($deputyTimesheets, JSON_UNESCAPED_UNICODE) : null;
        $vendBalancesJson = $vendBalances ? json_encode($vendBalances, JSON_UNESCAPED_UNICODE) : null;
        $xeroPayslipsJson = $xeroPayslips ? json_encode($xeroPayslips, JSON_UNESCAPED_UNICODE) : null;
        $xeroEmployeesJson = $xeroEmployees ? json_encode($xeroEmployees, JSON_UNESCAPED_UNICODE) : null;
        $publicHolidaysJson = $publicHolidays ? json_encode($publicHolidays, JSON_UNESCAPED_UNICODE) : null;
        $bonusCalculationsJson = $bonusCalculations ? json_encode($bonusCalculations, JSON_UNESCAPED_UNICODE) : null;
        $amendmentsJson = $amendments ? json_encode($amendments, JSON_UNESCAPED_UNICODE) : null;

        // Get current config
        $configSnapshot = $this->captureConfigSnapshot();
        $configJson = json_encode($configSnapshot, JSON_UNESCAPED_UNICODE);

        // Calculate hash for integrity
        $dataHash = hash('sha256', implode('|', [
            $userObjectsJson,
            $deputyTimesheetsJson ?? '',
            $vendBalancesJson ?? '',
            $xeroPayslipsJson ?? '',
            $xeroEmployeesJson ?? '',
            $publicHolidaysJson ?? '',
            $bonusCalculationsJson ?? '',
            $amendmentsJson ?? '',
            $configJson
        ]));

        // Calculate size
        $totalSize = strlen($userObjectsJson)
                   + strlen($deputyTimesheetsJson ?? '')
                   + strlen($vendBalancesJson ?? '')
                   + strlen($xeroPayslipsJson ?? '')
                   + strlen($xeroEmployeesJson ?? '')
                   + strlen($publicHolidaysJson ?? '')
                   + strlen($bonusCalculationsJson ?? '')
                   + strlen($amendmentsJson ?? '')
                   + strlen($configJson);

        $stmt = $this->pdo->prepare("
            INSERT INTO payroll_snapshots (
                run_id, revision_id, snapshot_at, snapshot_type,
                user_objects_json, deputy_timesheets_json, vend_account_balances_json,
                xero_payslips_json, xero_employees_json, xero_leave_json,
                public_holidays_json, bonus_calculations_json, amendments_json,
                config_snapshot_json, data_hash,
                employee_count, total_size_bytes
            ) VALUES (
                ?, ?, NOW(), ?,
                ?, ?, ?,
                ?, ?, NULL,
                ?, ?, ?,
                ?, ?,
                ?, ?
            )
        ");

        $stmt->execute([
            $runId,
            $revisionId,
            $snapshotType,
            $userObjectsJson,
            $deputyTimesheetsJson,
            $vendBalancesJson,
            $xeroPayslipsJson,
            $xeroEmployeesJson,
            $publicHolidaysJson,
            $bonusCalculationsJson,
            $amendmentsJson,
            $configJson,
            $dataHash,
            count($userObjects),
            $totalSize
        ]);

        $snapshotId = (int) $this->pdo->lastInsertId();

        // Update revision with snapshot reference
        if ($revisionId) {
            $this->pdo->prepare("UPDATE payroll_run_revisions SET snapshot_id = ? WHERE id = ?")
                ->execute([$snapshotId, $revisionId]);
        }

        payroll_log('info', "Captured {$snapshotType} snapshot", [
            'run_id' => $runId,
            'snapshot_id' => $snapshotId,
            'employees' => count($userObjects),
            'size_mb' => round($totalSize / 1024 / 1024, 2),
            'hash' => substr($dataHash, 0, 12)
        ]);

        // Also store normalized employee details for fast querying
        $this->storeEmployeeDetails($runId, $snapshotId, $userObjects);

        return $snapshotId;
    }

    /**
     * Store normalized employee details
     *
     * Extracts key fields from userObjects for fast SQL queries
     *
     * @param int $runId
     * @param int $snapshotId
     * @param array $userObjects
     */
    private function storeEmployeeDetails(int $runId, int $snapshotId, array $userObjects): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO payroll_employee_details (
                run_id, snapshot_id, user_id, xero_employee_id, xero_payslip_id,
                deputy_employee_id, vend_customer_id,
                employee_name, employee_email,
                total_hours, ordinary_hours, overtime_hours, leave_hours, public_holiday_hours,
                base_pay, overtime_pay, commission, monthly_bonus,
                google_review_bonus, vape_drops_bonus, other_bonuses,
                leave_pay, public_holiday_pay, gross_earnings,
                account_payment_deduction, other_deductions, total_deductions,
                net_pay, hourly_rate, salary_annual,
                vend_account_balance, deputy_timesheet_count,
                deputy_first_punch, deputy_last_punch,
                public_holiday_worked, public_holiday_preference,
                alternative_holiday_created, alternative_holiday_hours,
                processing_status, skip_reason, error_message,
                full_user_object_json
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?,
                ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?,
                ?, ?,
                ?, ?,
                ?, ?, ?,
                ?
            )
            ON DUPLICATE KEY UPDATE
                total_hours = VALUES(total_hours),
                gross_earnings = VALUES(gross_earnings),
                net_pay = VALUES(net_pay),
                processing_status = VALUES(processing_status),
                full_user_object_json = VALUES(full_user_object_json)
        ");

        foreach ($userObjects as $u) {
            $stmt->execute([
                $runId,
                $snapshotId,
                $u->userID ?? null,
                $u->xeroID ?? null,
                $u->payslipID ?? null,
                $u->deputyEmployeeId ?? null,
                $u->vendCustomerId ?? null,
                $u->name ?? 'Unknown',
                $u->email ?? null,
                $u->totalHours ?? 0,
                $u->ordinaryHours ?? 0,
                $u->overtimeHours ?? 0,
                $u->leaveHours ?? 0,
                $u->publicHolidayHours ?? 0,
                $u->basePay ?? 0,
                $u->overtimePay ?? 0,
                $u->commission ?? 0,
                $u->monthlyBonus ?? 0,
                $u->googleReviewBonus ?? 0,
                $u->vapeDropsBonus ?? 0,
                $u->otherBonuses ?? 0,
                $u->leavePay ?? 0,
                $u->publicHolidayPay ?? 0,
                $u->grossEarnings ?? 0,
                $u->accountPaymentDeduction ?? 0,
                $u->otherDeductions ?? 0,
                $u->totalDeductions ?? 0,
                $u->netPay ?? 0,
                $u->hourlyRate ?? null,
                $u->salaryAnnual ?? null,
                $u->vendAccountBalance ?? null,
                isset($u->deputyTimesheets) ? count($u->deputyTimesheets) : 0,
                $u->deputyFirstPunch ?? null,
                $u->deputyLastPunch ?? null,
                !empty($u->publicHolidayInfo),
                $u->publicHolidayPreference ?? null,
                $u->alternativeHolidayCreated ?? false,
                $u->alternativeHolidayHours ?? null,
                $u->processingStatus ?? 'pending',
                $u->skipReason ?? null,
                $u->errorMessage ?? null,
                json_encode($u, JSON_UNESCAPED_UNICODE)
            ]);

            $employeeDetailId = (int) $this->pdo->lastInsertId();

            // Store earning lines
            if (!empty($u->earningLines)) {
                $this->storeEarningLines($employeeDetailId, $u->earningLines);
            }

            // Store deduction lines
            if (!empty($u->deductionLines)) {
                $this->storeDeductionLines($employeeDetailId, $u->deductionLines);
            }

            // Store public holiday details
            if (!empty($u->publicHolidayInfo)) {
                $this->storePublicHolidayDetails($employeeDetailId, $u->publicHolidayInfo);
            }
        }

        // 🆕 Also store detailed Xero payslip lines if available
        if ($xeroPayslips !== null && !empty($xeroPayslips)) {
            $this->storeXeroPayslipLines($runId, $snapshotId, $xeroPayslips, $userObjects);
        }
    }

    /**
     * Store individual earning line items
     */
    private function storeEarningLines(int $employeeDetailId, array $earningLines): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO payroll_earnings_lines (
                employee_detail_id, earning_type, earning_rate_id, earning_rate_name,
                units, rate_per_unit, fixed_amount, total_amount,
                is_leave, is_overtime, is_bonus, is_public_holiday,
                source_type, source_reference, description, calculation_notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($earningLines as $line) {
            $stmt->execute([
                $employeeDetailId,
                $line['type'] ?? 'Unknown',
                $line['rateId'] ?? null,
                $line['rateName'] ?? null,
                $line['units'] ?? null,
                $line['ratePerUnit'] ?? null,
                $line['fixedAmount'] ?? null,
                $line['total'] ?? 0,
                $line['isLeave'] ?? false,
                $line['isOvertime'] ?? false,
                $line['isBonus'] ?? false,
                $line['isPublicHoliday'] ?? false,
                $line['source'] ?? 'calculated',
                $line['sourceRef'] ?? null,
                $line['description'] ?? null,
                $line['notes'] ?? null
            ]);
        }
    }

    /**
     * Store individual deduction line items
     */
    private function storeDeductionLines(int $employeeDetailId, array $deductionLines): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO payroll_deduction_lines (
                employee_detail_id, deduction_type, deduction_code, deduction_name,
                amount, vend_customer_id, vend_payment_id,
                allocation_status, allocated_at, allocation_error,
                source_type, source_reference, description, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($deductionLines as $line) {
            $stmt->execute([
                $employeeDetailId,
                $line['type'] ?? 'Unknown',
                $line['code'] ?? null,
                $line['name'] ?? null,
                $line['amount'] ?? 0,
                $line['vendCustomerId'] ?? null,
                $line['vendPaymentId'] ?? null,
                $line['allocationStatus'] ?? 'pending',
                $line['allocatedAt'] ?? null,
                $line['allocationError'] ?? null,
                $line['source'] ?? 'automatic',
                $line['sourceRef'] ?? null,
                $line['description'] ?? null,
                $line['notes'] ?? null
            ]);
        }
    }

    /**
     * Store public holiday details
     */
    private function storePublicHolidayDetails(int $employeeDetailId, array $publicHolidayInfo): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO payroll_public_holidays (
                employee_detail_id, holiday_date, holiday_name,
                hours_worked, worked, preference,
                earnings_zeroed, alternative_holiday_created,
                leave_hours_granted, xero_leave_id,
                ordinary_pay_removed, public_holiday_rate_applied,
                total_pay_impact, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($publicHolidayInfo as $holiday) {
            $stmt->execute([
                $employeeDetailId,
                $holiday['date'] ?? null,
                $holiday['name'] ?? 'Unknown Holiday',
                $holiday['hoursWorked'] ?? null,
                !empty($holiday['hoursWorked']),
                $holiday['preference'] ?? 'day_in_lieu',
                $holiday['earningsZeroed'] ?? false,
                $holiday['alternativeHolidayCreated'] ?? false,
                $holiday['leaveHoursGranted'] ?? null,
                $holiday['xeroLeaveId'] ?? null,
                $holiday['ordinaryPayRemoved'] ?? null,
                $holiday['publicHolidayRateApplied'] ?? false,
                $holiday['totalPayImpact'] ?? null,
                $holiday['notes'] ?? null
            ]);
        }
    }

    /**
     * Calculate diff between two snapshots
     *
     * @param int $fromSnapshotId
     * @param int $toSnapshotId
     * @return array Structured diff
     */
    public function calculateDiff(int $fromSnapshotId, int $toSnapshotId): array
    {
        // Check if diff already exists
        $stmt = $this->pdo->prepare("
            SELECT changes_json
            FROM payroll_snapshot_diffs
            WHERE from_snapshot_id = ? AND to_snapshot_id = ?
        ");
        $stmt->execute([$fromSnapshotId, $toSnapshotId]);

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return json_decode($row['changes_json'], true);
        }

        // Load both snapshots
        $from = $this->loadSnapshot($fromSnapshotId);
        $to = $this->loadSnapshot($toSnapshotId);

        // Calculate diff
        $diff = $this->computeDiff($from, $to);

        // Store for future use
        $this->storeDiff($fromSnapshotId, $toSnapshotId, $diff);

        return $diff;
    }

    /**
     * Load snapshot data
     */
    private function loadSnapshot(int $snapshotId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT user_objects_json, deputy_timesheets_json, vend_account_balances_json,
                   xero_payslips_json, public_holidays_json, bonus_calculations_json
            FROM payroll_snapshots
            WHERE id = ?
        ");
        $stmt->execute([$snapshotId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception("Snapshot {$snapshotId} not found");
        }

        return [
            'user_objects' => json_decode($row['user_objects_json'], true),
            'deputy_timesheets' => $row['deputy_timesheets_json'] ? json_decode($row['deputy_timesheets_json'], true) : [],
            'vend_balances' => $row['vend_account_balances_json'] ? json_decode($row['vend_account_balances_json'], true) : [],
            'xero_payslips' => $row['xero_payslips_json'] ? json_decode($row['xero_payslips_json'], true) : [],
            'public_holidays' => $row['public_holidays_json'] ? json_decode($row['public_holidays_json'], true) : [],
            'bonuses' => $row['bonus_calculations_json'] ? json_decode($row['bonus_calculations_json'], true) : []
        ];
    }

    /**
     * Compute diff between two states
     */
    private function computeDiff(array $from, array $to): array
    {
        $diff = [
            'employees_changed' => [],
            'total_pay_delta' => 0,
            'additions' => [],
            'modifications' => [],
            'deletions' => []
        ];

        // Index employees by ID
        $fromEmployees = [];
        foreach ($from['user_objects'] as $emp) {
            $fromEmployees[$emp['userID'] ?? 0] = $emp;
        }

        $toEmployees = [];
        foreach ($to['user_objects'] as $emp) {
            $toEmployees[$emp['userID'] ?? 0] = $emp;
        }

        // Find additions
        foreach ($toEmployees as $id => $emp) {
            if (!isset($fromEmployees[$id])) {
                $diff['additions'][] = [
                    'user_id' => $id,
                    'name' => $emp['name'] ?? 'Unknown',
                    'gross_earnings' => $emp['grossEarnings'] ?? 0
                ];
                $diff['total_pay_delta'] += $emp['grossEarnings'] ?? 0;
            }
        }

        // Find modifications
        foreach ($toEmployees as $id => $toEmp) {
            if (isset($fromEmployees[$id])) {
                $fromEmp = $fromEmployees[$id];
                $changes = [];

                // Compare key fields
                $fieldsToCompare = [
                    'totalHours', 'grossEarnings', 'netPay', 'commission',
                    'monthlyBonus', 'googleReviewBonus', 'vapeDropsBonus',
                    'accountPaymentDeduction'
                ];

                foreach ($fieldsToCompare as $field) {
                    $fromVal = $fromEmp[$field] ?? 0;
                    $toVal = $toEmp[$field] ?? 0;

                    if (abs($fromVal - $toVal) > 0.01) {
                        $changes[$field] = [
                            'from' => $fromVal,
                            'to' => $toVal,
                            'delta' => $toVal - $fromVal
                        ];

                        if ($field === 'grossEarnings') {
                            $diff['total_pay_delta'] += $toVal - $fromVal;
                        }
                    }
                }

                if (!empty($changes)) {
                    $diff['modifications'][] = [
                        'user_id' => $id,
                        'name' => $toEmp['name'] ?? 'Unknown',
                        'changes' => $changes
                    ];
                    $diff['employees_changed'][] = $id;
                }
            }
        }

        // Find deletions
        foreach ($fromEmployees as $id => $emp) {
            if (!isset($toEmployees[$id])) {
                $diff['deletions'][] = [
                    'user_id' => $id,
                    'name' => $emp['name'] ?? 'Unknown',
                    'gross_earnings' => $emp['grossEarnings'] ?? 0
                ];
                $diff['total_pay_delta'] -= $emp['grossEarnings'] ?? 0;
            }
        }

        $diff['employees_changed'] = array_unique($diff['employees_changed']);
        $diff['summary'] = [
            'additions_count' => count($diff['additions']),
            'modifications_count' => count($diff['modifications']),
            'deletions_count' => count($diff['deletions']),
            'employees_affected' => count($diff['employees_changed']),
            'total_pay_delta' => round($diff['total_pay_delta'], 2)
        ];

        return $diff;
    }

    /**
     * Store computed diff
     */
    private function storeDiff(int $fromSnapshotId, int $toSnapshotId, array $diff): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO payroll_snapshot_diffs (
                from_snapshot_id, to_snapshot_id,
                employees_changed, total_pay_delta,
                changes_json,
                additions_count, modifications_count, deletions_count
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $fromSnapshotId,
            $toSnapshotId,
            count($diff['employees_changed']),
            $diff['total_pay_delta'],
            json_encode($diff, JSON_UNESCAPED_UNICODE),
            $diff['summary']['additions_count'],
            $diff['summary']['modifications_count'],
            $diff['summary']['deletions_count']
        ]);
    }

    /**
     * Create amendment record
     *
     * @param int $runId
     * @param int|null $employeeDetailId
     * @param string $amendmentType
     * @param string $fieldName
     * @param float $oldValue
     * @param float $newValue
     * @param string $reason
     * @return int Amendment ID
     */
    public function createAmendment(
        int $runId,
        ?int $employeeDetailId,
        string $amendmentType,
        string $fieldName,
        float $oldValue,
        float $newValue,
        string $reason
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO payroll_amendments (
                run_id, employee_detail_id, amendment_type,
                field_name, old_value, new_value, delta,
                reason, requested_by_user_id, approval_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");

        $delta = $newValue - $oldValue;

        $stmt->execute([
            $runId,
            $employeeDetailId,
            $amendmentType,
            $fieldName,
            $oldValue,
            $newValue,
            $delta,
            $reason,
            $this->currentUserId
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Capture current payroll configuration
     */
    private function captureConfigSnapshot(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'xero_tenant_id' => $this->xeroTenantId,
            'snapshot_time' => date('Y-m-d H:i:s'),
            'constants' => [
                'PAYROLL_DRY_RUN' => defined('PAYROLL_DRY_RUN') ? PAYROLL_DRY_RUN : false,
                'ALTERNATIVE_HOLIDAY_LEAVE_TYPE_ID' => defined('ALTERNATIVE_HOLIDAY_LEAVE_TYPE_ID') ? ALTERNATIVE_HOLIDAY_LEAVE_TYPE_ID : null,
                'ACCOUNT_PAYMENT_DEDUCTION_TYPE_ID' => defined('ACCOUNT_PAYMENT_DEDUCTION_TYPE_ID') ? ACCOUNT_PAYMENT_DEDUCTION_TYPE_ID : null
            ]
        ];
    }

    /**
     * Generate UUID v4
     */
    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Update run status
     */
    public function updateRunStatus(int $runId, string $status): void
    {
        $this->pdo->prepare("UPDATE payroll_runs SET status = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$status, $runId]);

        if ($status === 'posted' || $status === 'completed') {
            $this->pdo->prepare("UPDATE payroll_runs SET completed_at = NOW(), completed_by_user_id = ? WHERE id = ?")
                ->execute([$this->currentUserId, $runId]);
        }
    }

    /**
     * Get latest snapshot for a run
     */
    public function getLatestSnapshot(int $runId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM payroll_snapshots
            WHERE run_id = ?
            ORDER BY snapshot_at DESC
            LIMIT 1
        ");
        $stmt->execute([$runId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * 🆕 Convert Xero SDK PaySlip objects to plain arrays
     *
     * Extracts EVERY field from Xero payslips including:
     * - Basic info (payslip ID, employee ID, dates, status)
     * - Earnings lines (ordinary, overtime, bonuses, leave)
     * - Deduction lines (account payments, etc.)
     * - Tax lines (PAYE, student loan, KiwiSaver)
     * - Superannuation (employer contributions)
     * - Leave accruals (annual leave, sick leave)
     * - Reimbursements
     * - Statutory deductions
     * - Gross/net calculations
     *
     * @param array $payslips Array of \XeroAPI\XeroPHP\Models\PayrollNz\PaySlip objects
     * @return array Complete payslip data as plain arrays
     */
    private function convertXeroPayslipsToArray(array $payslips): array
    {
        $result = [];

        foreach ($payslips as $ps) {
            // Skip if not a Xero PaySlip object
            if (!is_object($ps)) {
                $result[] = $ps; // Already an array
                continue;
            }

            $payslipData = [
                // ============================================================
                // BASIC IDENTIFICATION
                // ============================================================
                'payslip_id' => method_exists($ps, 'getPaySlipId') ? $ps->getPaySlipId() : null,
                'employee_id' => method_exists($ps, 'getEmployeeId') ? $ps->getEmployeeId() : null,
                'pay_run_id' => method_exists($ps, 'getPayRunId') ? $ps->getPayRunId() : null,

                // ============================================================
                // DATES & PERIOD
                // ============================================================
                'period_start_date' => method_exists($ps, 'getPeriodStartDate') ?
                    ($ps->getPeriodStartDate() ? $ps->getPeriodStartDate()->format('Y-m-d') : null) : null,
                'period_end_date' => method_exists($ps, 'getPeriodEndDate') ?
                    ($ps->getPeriodEndDate() ? $ps->getPeriodEndDate()->format('Y-m-d') : null) : null,
                'payment_date' => method_exists($ps, 'getPaymentDate') ?
                    ($ps->getPaymentDate() ? $ps->getPaymentDate()->format('Y-m-d') : null) : null,

                // ============================================================
                // STATUS
                // ============================================================
                'last_edited' => method_exists($ps, 'getLastEdited') ?
                    ($ps->getLastEdited() ? $ps->getLastEdited()->format('Y-m-d H:i:s') : null) : null,

                // ============================================================
                // 💰 EARNINGS LINES (THE IMPORTANT STUFF!)
                // ============================================================
                'earnings_lines' => $this->convertEarningsLines(
                    method_exists($ps, 'getEarningsLines') ? $ps->getEarningsLines() : []
                ),

                // ============================================================
                // 💸 DEDUCTION LINES
                // ============================================================
                'deduction_lines' => $this->convertDeductionLines(
                    method_exists($ps, 'getDeductionLines') ? $ps->getDeductionLines() : []
                ),

                // ============================================================
                // 🏖️ LEAVE EARNINGS (Annual leave, sick leave)
                // ============================================================
                'leave_earnings_lines' => $this->convertLeaveEarningsLines(
                    method_exists($ps, 'getLeaveEarningsLines') ? $ps->getLeaveEarningsLines() : []
                ),

                // ============================================================
                // 🏦 REIMBURSEMENTS
                // ============================================================
                'reimbursement_lines' => $this->convertReimbursementLines(
                    method_exists($ps, 'getReimbursementLines') ? $ps->getReimbursementLines() : []
                ),

                // ============================================================
                // 🧾 TAX LINES (PAYE, student loan, etc.)
                // ============================================================
                'employee_tax_lines' => $this->convertTaxLines(
                    method_exists($ps, 'getEmployeeTaxLines') ? $ps->getEmployeeTaxLines() : []
                ),
                'employer_tax_lines' => $this->convertTaxLines(
                    method_exists($ps, 'getEmployerTaxLines') ? $ps->getEmployerTaxLines() : []
                ),

                // ============================================================
                // 🎯 SUPERANNUATION (KiwiSaver)
                // ============================================================
                'superannuation_lines' => $this->convertSuperannuationLines(
                    method_exists($ps, 'getSuperannuationLines') ? $ps->getSuperannuationLines() : []
                ),

                // ============================================================
                // 📊 LEAVE ACCRUALS
                // ============================================================
                'leave_accrual_lines' => $this->convertLeaveAccrualLines(
                    method_exists($ps, 'getLeaveAccrualLines') ? $ps->getLeaveAccrualLines() : []
                ),

                // ============================================================
                // ⚖️ STATUTORY DEDUCTIONS
                // ============================================================
                'statutory_deduction_lines' => $this->convertStatutoryDeductionLines(
                    method_exists($ps, 'getStatutoryDeductionLines') ? $ps->getStatutoryDeductionLines() : []
                ),

                // ============================================================
                // 💵 TOTALS & CALCULATIONS
                // ============================================================
                'tax_settings' => method_exists($ps, 'getTaxSettings') ?
                    $this->convertTaxSettings($ps->getTaxSettings()) : null,
                'gross_earnings_history' => method_exists($ps, 'getGrossEarningsHistory') ?
                    $this->convertGrossEarningsHistory($ps->getGrossEarningsHistory()) : null,

                // Store raw object for debugging (truncated if too large)
                '_raw_object_class' => get_class($ps),
                '_captured_at' => date('Y-m-d H:i:s')
            ];

            $result[] = $payslipData;
        }

        return $result;
    }

    /**
     * Convert earnings lines (ordinary hours, overtime, bonuses, etc.)
     */
    private function convertEarningsLines($lines): array
    {
        if (empty($lines)) return [];

        $result = [];
        foreach ($lines as $line) {
            if (!is_object($line)) {
                $result[] = $line;
                continue;
            }

            $result[] = [
                'earnings_rate_id' => method_exists($line, 'getEarningsRateId') ? $line->getEarningsRateId() : null,
                'display_name' => method_exists($line, 'getDisplayName') ? $line->getDisplayName() : null,
                'rate_per_unit' => method_exists($line, 'getRatePerUnit') ? $line->getRatePerUnit() : null,
                'number_of_units' => method_exists($line, 'getNumberOfUnits') ? $line->getNumberOfUnits() : null,
                'fixed_amount' => method_exists($line, 'getFixedAmount') ? $line->getFixedAmount() : null,
                'amount' => method_exists($line, 'getAmount') ? $line->getAmount() : null,
                'is_linked_to_timesheet' => method_exists($line, 'getIsLinkedToTimesheet') ? $line->getIsLinkedToTimesheet() : null,
                'is_average_daily_pay_rate' => method_exists($line, 'getIsAverageDailyPayRate') ? $line->getIsAverageDailyPayRate() : null,
            ];
        }
        return $result;
    }

    /**
     * Convert deduction lines (account payments, etc.)
     */
    private function convertDeductionLines($lines): array
    {
        if (empty($lines)) return [];

        $result = [];
        foreach ($lines as $line) {
            if (!is_object($line)) {
                $result[] = $line;
                continue;
            }

            $result[] = [
                'deduction_type_id' => method_exists($line, 'getDeductionTypeId') ? $line->getDeductionTypeId() : null,
                'display_name' => method_exists($line, 'getDisplayName') ? $line->getDisplayName() : null,
                'amount' => method_exists($line, 'getAmount') ? $line->getAmount() : null,
                'percentage' => method_exists($line, 'getPercentage') ? $line->getPercentage() : null,
            ];
        }
        return $result;
    }

    /**
     * Convert leave earnings lines
     */
    private function convertLeaveEarningsLines($lines): array
    {
        if (empty($lines)) return [];

        $result = [];
        foreach ($lines as $line) {
            if (!is_object($line)) {
                $result[] = $line;
                continue;
            }

            $result[] = [
                'earnings_rate_id' => method_exists($line, 'getEarningsRateId') ? $line->getEarningsRateId() : null,
                'display_name' => method_exists($line, 'getDisplayName') ? $line->getDisplayName() : null,
                'rate_per_unit' => method_exists($line, 'getRatePerUnit') ? $line->getRatePerUnit() : null,
                'number_of_units' => method_exists($line, 'getNumberOfUnits') ? $line->getNumberOfUnits() : null,
                'fixed_amount' => method_exists($line, 'getFixedAmount') ? $line->getFixedAmount() : null,
                'amount' => method_exists($line, 'getAmount') ? $line->getAmount() : null,
            ];
        }
        return $result;
    }

    /**
     * Convert reimbursement lines
     */
    private function convertReimbursementLines($lines): array
    {
        if (empty($lines)) return [];

        $result = [];
        foreach ($lines as $line) {
            if (!is_object($line)) {
                $result[] = $line;
                continue;
            }

            $result[] = [
                'reimbursement_type_id' => method_exists($line, 'getReimbursementTypeId') ? $line->getReimbursementTypeId() : null,
                'description' => method_exists($line, 'getDescription') ? $line->getDescription() : null,
                'amount' => method_exists($line, 'getAmount') ? $line->getAmount() : null,
            ];
        }
        return $result;
    }

    /**
     * Convert tax lines (employee and employer)
     */
    private function convertTaxLines($lines): array
    {
        if (empty($lines)) return [];

        $result = [];
        foreach ($lines as $line) {
            if (!is_object($line)) {
                $result[] = $line;
                continue;
            }

            $result[] = [
                'tax_type_id' => method_exists($line, 'getTaxTypeId') ? $line->getTaxTypeId() : null,
                'description' => method_exists($line, 'getDescription') ? $line->getDescription() : null,
                'amount' => method_exists($line, 'getAmount') ? $line->getAmount() : null,
                'global_tax_type_id' => method_exists($line, 'getGlobalTaxTypeId') ? $line->getGlobalTaxTypeId() : null,
            ];
        }
        return $result;
    }

    /**
     * Convert superannuation lines (KiwiSaver)
     */
    private function convertSuperannuationLines($lines): array
    {
        if (empty($lines)) return [];

        $result = [];
        foreach ($lines as $line) {
            if (!is_object($line)) {
                $result[] = $line;
                continue;
            }

            $result[] = [
                'superannuation_type_id' => method_exists($line, 'getSuperannuationTypeId') ? $line->getSuperannuationTypeId() : null,
                'display_name' => method_exists($line, 'getDisplayName') ? $line->getDisplayName() : null,
                'amount' => method_exists($line, 'getAmount') ? $line->getAmount() : null,
                'percentage' => method_exists($line, 'getPercentage') ? $line->getPercentage() : null,
                'employee_contribution' => method_exists($line, 'getEmployeeContribution') ? $line->getEmployeeContribution() : null,
                'employer_contribution' => method_exists($line, 'getEmployerContribution') ? $line->getEmployerContribution() : null,
            ];
        }
        return $result;
    }

    /**
     * Convert leave accrual lines
     */
    private function convertLeaveAccrualLines($lines): array
    {
        if (empty($lines)) return [];

        $result = [];
        foreach ($lines as $line) {
            if (!is_object($line)) {
                $result[] = $line;
                continue;
            }

            $result[] = [
                'leave_type_id' => method_exists($line, 'getLeaveTypeId') ? $line->getLeaveTypeId() : null,
                'number_of_units' => method_exists($line, 'getNumberOfUnits') ? $line->getNumberOfUnits() : null,
                'auto_calculate' => method_exists($line, 'getAutoCalculate') ? $line->getAutoCalculate() : null,
            ];
        }
        return $result;
    }

    /**
     * Convert statutory deduction lines
     */
    private function convertStatutoryDeductionLines($lines): array
    {
        if (empty($lines)) return [];

        $result = [];
        foreach ($lines as $line) {
            if (!is_object($line)) {
                $result[] = $line;
                continue;
            }

            $result[] = [
                'statutory_deduction_type_id' => method_exists($line, 'getStatutoryDeductionTypeId') ? $line->getStatutoryDeductionTypeId() : null,
                'display_name' => method_exists($line, 'getDisplayName') ? $line->getDisplayName() : null,
                'amount' => method_exists($line, 'getAmount') ? $line->getAmount() : null,
            ];
        }
        return $result;
    }

    /**
     * Convert tax settings
     */
    private function convertTaxSettings($settings)
    {
        if (!is_object($settings)) return $settings;

        return [
            'tax_code' => method_exists($settings, 'getTaxCode') ? $settings->getTaxCode() : null,
            'special_tax_rate' => method_exists($settings, 'getSpecialTaxRate') ? $settings->getSpecialTaxRate() : null,
            'lump_sum_tax_code' => method_exists($settings, 'getLumpSumTaxCode') ? $settings->getLumpSumTaxCode() : null,
            'lump_sum_amount' => method_exists($settings, 'getLumpSumAmount') ? $settings->getLumpSumAmount() : null,
        ];
    }

    /**
     * Convert gross earnings history
     */
    private function convertGrossEarningsHistory($history)
    {
        if (!is_object($history)) return $history;

        return [
            'day_pay_gross_earnings' => method_exists($history, 'getDayPayGrossEarnings') ? $history->getDayPayGrossEarnings() : null,
            'week_pay_gross_earnings' => method_exists($history, 'getWeekPayGrossEarnings') ? $history->getWeekPayGrossEarnings() : null,
        ];
    }

    /**
     * 🆕 Store individual Xero payslip line items into dedicated table
     *
     * This stores EVERY line item from EVERY payslip:
     * - Earnings (ordinary, overtime, bonuses)
     * - Deductions (account payments, etc.)
     * - Leave earnings (annual leave, sick leave)
     * - Reimbursements
     * - Tax (employee and employer)
     * - Superannuation (KiwiSaver)
     * - Leave accruals
     * - Statutory deductions
     *
     * @param int $runId
     * @param int $snapshotId
     * @param array $xeroPayslips Converted payslip data (arrays, not SDK objects)
     * @param array $userObjects For linking employee_detail_id
     */
    private function storeXeroPayslipLines(int $runId, int $snapshotId, array $xeroPayslips, array $userObjects): void
    {
        // Build a map of employee IDs to their employee_detail_id
        $employeeDetailMap = [];
        $stmt = $this->pdo->prepare("
            SELECT id, xero_employee_id
            FROM payroll_employee_details
            WHERE run_id = ? AND snapshot_id = ?
        ");
        $stmt->execute([$runId, $snapshotId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $employeeDetailMap[$row['xero_employee_id']] = $row['id'];
        }

        $insertStmt = $this->pdo->prepare("
            INSERT INTO payroll_xero_payslip_lines (
                run_id, snapshot_id, employee_detail_id,
                xero_payslip_id, xero_employee_id,
                line_category, line_type_id, display_name, description,
                rate_per_unit, number_of_units, fixed_amount, percentage, calculated_amount,
                is_linked_to_timesheet, is_average_daily_pay_rate, auto_calculate,
                tax_type, employee_contribution, employer_contribution,
                leave_type_id, leave_units,
                period_start_date, period_end_date, payment_date,
                full_line_json
            ) VALUES (
                ?, ?, ?,
                ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?
            )
        ");

        foreach ($xeroPayslips as $payslip) {
            $payslipId = $payslip['payslip_id'] ?? null;
            $employeeId = $payslip['employee_id'] ?? null;
            $employeeDetailId = $employeeDetailMap[$employeeId] ?? null;

            if (!$payslipId || !$employeeId || !$employeeDetailId) {
                continue; // Skip if we can't link properly
            }

            $periodStart = $payslip['period_start_date'] ?? null;
            $periodEnd = $payslip['period_end_date'] ?? null;
            $paymentDate = $payslip['payment_date'] ?? null;

            // Process earnings lines
            foreach (($payslip['earnings_lines'] ?? []) as $line) {
                $insertStmt->execute([
                    $runId, $snapshotId, $employeeDetailId,
                    $payslipId, $employeeId,
                    'earnings',
                    $line['earnings_rate_id'] ?? null,
                    $line['display_name'] ?? null,
                    null, // description
                    $line['rate_per_unit'] ?? null,
                    $line['number_of_units'] ?? null,
                    $line['fixed_amount'] ?? null,
                    null, // percentage
                    $line['amount'] ?? 0,
                    $line['is_linked_to_timesheet'] ?? false,
                    $line['is_average_daily_pay_rate'] ?? false,
                    false, // auto_calculate
                    null, null, null, // tax fields
                    null, null, // leave fields
                    $periodStart, $periodEnd, $paymentDate,
                    json_encode($line)
                ]);
            }

            // Process deduction lines
            foreach (($payslip['deduction_lines'] ?? []) as $line) {
                $insertStmt->execute([
                    $runId, $snapshotId, $employeeDetailId,
                    $payslipId, $employeeId,
                    'deduction',
                    $line['deduction_type_id'] ?? null,
                    $line['display_name'] ?? null,
                    null,
                    null, null, null, // rate/units/fixed
                    $line['percentage'] ?? null,
                    $line['amount'] ?? 0,
                    false, false, false,
                    null, null, null,
                    null, null,
                    $periodStart, $periodEnd, $paymentDate,
                    json_encode($line)
                ]);
            }

            // Process leave earnings lines
            foreach (($payslip['leave_earnings_lines'] ?? []) as $line) {
                $insertStmt->execute([
                    $runId, $snapshotId, $employeeDetailId,
                    $payslipId, $employeeId,
                    'leave_earnings',
                    $line['earnings_rate_id'] ?? null,
                    $line['display_name'] ?? null,
                    null,
                    $line['rate_per_unit'] ?? null,
                    $line['number_of_units'] ?? null,
                    $line['fixed_amount'] ?? null,
                    null,
                    $line['amount'] ?? 0,
                    false, false, false,
                    null, null, null,
                    null, $line['number_of_units'] ?? null,
                    $periodStart, $periodEnd, $paymentDate,
                    json_encode($line)
                ]);
            }

            // Process reimbursement lines
            foreach (($payslip['reimbursement_lines'] ?? []) as $line) {
                $insertStmt->execute([
                    $runId, $snapshotId, $employeeDetailId,
                    $payslipId, $employeeId,
                    'reimbursement',
                    $line['reimbursement_type_id'] ?? null,
                    $line['description'] ?? null,
                    $line['description'] ?? null,
                    null, null, null, null,
                    $line['amount'] ?? 0,
                    false, false, false,
                    null, null, null,
                    null, null,
                    $periodStart, $periodEnd, $paymentDate,
                    json_encode($line)
                ]);
            }

            // Process employee tax lines
            foreach (($payslip['employee_tax_lines'] ?? []) as $line) {
                $insertStmt->execute([
                    $runId, $snapshotId, $employeeDetailId,
                    $payslipId, $employeeId,
                    'employee_tax',
                    $line['tax_type_id'] ?? null,
                    $line['description'] ?? null,
                    $line['description'] ?? null,
                    null, null, null, null,
                    $line['amount'] ?? 0,
                    false, false, false,
                    $line['description'] ?? null,
                    null, null,
                    null, null,
                    $periodStart, $periodEnd, $paymentDate,
                    json_encode($line)
                ]);
            }

            // Process employer tax lines
            foreach (($payslip['employer_tax_lines'] ?? []) as $line) {
                $insertStmt->execute([
                    $runId, $snapshotId, $employeeDetailId,
                    $payslipId, $employeeId,
                    'employer_tax',
                    $line['tax_type_id'] ?? null,
                    $line['description'] ?? null,
                    $line['description'] ?? null,
                    null, null, null, null,
                    $line['amount'] ?? 0,
                    false, false, false,
                    $line['description'] ?? null,
                    null, null,
                    null, null,
                    $periodStart, $periodEnd, $paymentDate,
                    json_encode($line)
                ]);
            }

            // Process superannuation lines (KiwiSaver)
            foreach (($payslip['superannuation_lines'] ?? []) as $line) {
                $insertStmt->execute([
                    $runId, $snapshotId, $employeeDetailId,
                    $payslipId, $employeeId,
                    'superannuation',
                    $line['superannuation_type_id'] ?? null,
                    $line['display_name'] ?? null,
                    null,
                    null, null, null,
                    $line['percentage'] ?? null,
                    $line['amount'] ?? 0,
                    false, false, false,
                    null,
                    $line['employee_contribution'] ?? null,
                    $line['employer_contribution'] ?? null,
                    null, null,
                    $periodStart, $periodEnd, $paymentDate,
                    json_encode($line)
                ]);
            }

            // Process leave accrual lines
            foreach (($payslip['leave_accrual_lines'] ?? []) as $line) {
                $insertStmt->execute([
                    $runId, $snapshotId, $employeeDetailId,
                    $payslipId, $employeeId,
                    'leave_accrual',
                    null,
                    'Leave Accrual',
                    null,
                    null, null, null, null,
                    0, // amount is in leave_units
                    false, false,
                    $line['auto_calculate'] ?? false,
                    null, null, null,
                    $line['leave_type_id'] ?? null,
                    $line['number_of_units'] ?? null,
                    $periodStart, $periodEnd, $paymentDate,
                    json_encode($line)
                ]);
            }

            // Process statutory deduction lines
            foreach (($payslip['statutory_deduction_lines'] ?? []) as $line) {
                $insertStmt->execute([
                    $runId, $snapshotId, $employeeDetailId,
                    $payslipId, $employeeId,
                    'statutory_deduction',
                    $line['statutory_deduction_type_id'] ?? null,
                    $line['display_name'] ?? null,
                    null,
                    null, null, null, null,
                    $line['amount'] ?? 0,
                    false, false, false,
                    null, null, null,
                    null, null,
                    $periodStart, $periodEnd, $paymentDate,
                    json_encode($line)
                ]);
            }
        }

        payroll_log('info', 'Stored detailed Xero payslip lines', [
            'run_id' => $runId,
            'snapshot_id' => $snapshotId,
            'payslips_processed' => count($xeroPayslips)
        ]);
    }

    /**
     * Verify snapshot integrity by comparing stored hash with recomputed hash
     *
     * @param int $snapshotId Snapshot ID to verify
     * @return array Verification result with status and details
     */
    public function verifySnapshotIntegrity(int $snapshotId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, data_hash,
                   user_objects_json, deputy_timesheets_json,
                   vend_account_balances_json, xero_payslips_json,
                   xero_employees_json, public_holidays_json,
                   bonus_calculations_json, amendments_json,
                   config_snapshot_json, snapshot_at
            FROM payroll_snapshots
            WHERE id = ?
        ");
        $stmt->execute([$snapshotId]);
        $snapshot = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$snapshot) {
            return [
                'valid' => false,
                'error' => 'Snapshot not found',
                'snapshot_id' => $snapshotId
            ];
        }

        // Recompute hash using same algorithm as captureSnapshot()
        $computedHash = hash('sha256', implode('|', [
            $snapshot['user_objects_json'] ?? '',
            $snapshot['deputy_timesheets_json'] ?? '',
            $snapshot['vend_account_balances_json'] ?? '',
            $snapshot['xero_payslips_json'] ?? '',
            $snapshot['xero_employees_json'] ?? '',
            $snapshot['public_holidays_json'] ?? '',
            $snapshot['bonus_calculations_json'] ?? '',
            $snapshot['amendments_json'] ?? '',
            $snapshot['config_snapshot_json'] ?? ''
        ]));

        $valid = ($computedHash === $snapshot['data_hash']);

        payroll_log($valid ? 'info' : 'warning', 'Snapshot integrity verification', [
            'snapshot_id' => $snapshotId,
            'valid' => $valid,
            'stored_hash' => substr($snapshot['data_hash'], 0, 12),
            'computed_hash' => substr($computedHash, 0, 12)
        ]);

        return [
            'valid' => $valid,
            'snapshot_id' => $snapshotId,
            'snapshot_at' => $snapshot['snapshot_at'],
            'stored_hash' => $snapshot['data_hash'],
            'computed_hash' => $computedHash,
            'hash_match' => $valid
        ];
    }

    /**
     * Verify all snapshots for a pay run
     *
     * @param int $runId Pay run ID
     * @return array Verification results for all snapshots
     */
    public function verifyRunSnapshots(int $runId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id FROM payroll_snapshots WHERE run_id = ? ORDER BY snapshot_at
        ");
        $stmt->execute([$runId]);
        $snapshotIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $results = [];
        $validCount = 0;
        $invalidCount = 0;

        foreach ($snapshotIds as $snapshotId) {
            $result = $this->verifySnapshotIntegrity($snapshotId);
            $results[] = $result;

            if ($result['valid']) {
                $validCount++;
            } else {
                $invalidCount++;
            }
        }

        return [
            'run_id' => $runId,
            'total_snapshots' => count($snapshotIds),
            'valid' => $validCount,
            'invalid' => $invalidCount,
            'all_valid' => $invalidCount === 0,
            'snapshots' => $results
        ];
    }
}
