<?php
declare(strict_types=1);

namespace CIS\Modules\StaffAccounts;

use PDO;
use Exception;
use DateTime;
use DateTimeZone;
use XeroAPI\XeroPHP\Api\PayrollNzApi;
use XeroAPI\XeroPHP\ApiException;

/**
 * Xero Payroll Service (NZ) — production-hardened
 *
 * - Pulls PayRuns (paged), and for each PayRun pulls its PaySlips (paged)
 * - Stores/updates xero_payrolls
 * - Extracts & upserts staff-account deductions into xero_payroll_deductions
 *
 * Notes on the PHP SDK:
 * - Methods exist: getPayRuns, getPayRun, getPaySlips, getPaySlip, getEmployee, getDeduction
 * - Model ID getters use uppercase 'ID' (e.g., getPayRunID, getEmployeeID, getDeductionTypeID)
 * - PaySlips are retrieved by PayRunID (required) with optional pagination
 */
class XeroPayrollService
{
    private const CACHE_DAYS = 7;

    private PDO $db;
    private PayrollNzApi $api;
    private string $tenantId;

    /** @var array<string,string> cache of DeductionTypeID => DeductionName */
    private array $deductionNameCache = [];

    /**
     * @param PDO $db
     * @param PayrollNzApi $api
     * @param string $xeroTenantId
     */
    public function __construct(PDO $db, PayrollNzApi $api, string $xeroTenantId)
    {
        $this->db = $db;
        $this->api = $api;
        $this->tenantId = $xeroTenantId;

        // Reasonable PDO safety defaults (in case not set globally)
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /**
     * Sync payrolls from Xero (default: last 12 weeks)
     *
     * @param int|null $limitWeeks Number of weeks to sync (null = all time)
     * @return array{success:bool, synced?:int, cached?:int, errors?:array, total_processed?:int, error?:string}
     */
    public function syncPayrollsFromXero(?int $limitWeeks = 12): array
    {
        // Set execution time limit to 5 minutes max
        set_time_limit(300);
        
        $errors = [];
        $syncedCount = 0;
        $cachedCount = 0;
        $startTime = time();
        $maxExecutionTime = 300; // 5 minutes

        try {
            $sinceDate = $limitWeeks ? (new DateTime("now", new DateTimeZone('Pacific/Auckland')))
                ->modify("-{$limitWeeks} weeks") : null;

            error_log("Xero Sync Started - Fetching PayRuns from last {$limitWeeks} weeks" . 
                      ($sinceDate ? " (since " . $sinceDate->format('Y-m-d') . ")" : " (all time)"));

            $page = 1;
            $retryCount = 0;
            $maxRetries = 3;
            $totalPayRunsFound = 0;
            
            while (true) {
                // getPayRuns supports pagination (page) and optional status filter
                // Signature is (tenantId, page = null, status = null)
                try {
                    $payRunsResp = $this->api->getPayRuns($this->tenantId, $page, null);
                    $payRuns = method_exists($payRunsResp, 'getPayRuns') ? $payRunsResp->getPayRuns() : [];
                    $retryCount = 0; // Reset retry count on success
                    
                    $pageCount = is_array($payRuns) ? count($payRuns) : 0;
                    $totalPayRunsFound += $pageCount;
                    error_log("Xero Sync - Page {$page}: Found {$pageCount} PayRuns (Total so far: {$totalPayRunsFound})");
                    
                    // End pagination when no results OR less than page size (100)
                    if ($pageCount === 0) {
                        error_log("Xero Sync - Page {$page}: Empty page, stopping pagination");
                        break;
                    }
                    
                } catch (ApiException $e) {
                    // 429 Too Many Requests - exponential backoff and retry
                    if ($e->getCode() === 429 && $retryCount < $maxRetries) {
                        $retryCount++;
                        $hdr = $e->getResponseHeaders() ?? [];
                        $retryAfter = (int)($hdr['Retry-After'][0] ?? 2);
                        $waitTime = max($retryAfter, pow(2, $retryCount)) * 1000000; // Use larger of retry-after or exponential
                        error_log("Xero Sync - Page {$page}: Rate limited (429), retrying in " . ($waitTime/1000000) . "s (attempt {$retryCount}/{$maxRetries})");
                        usleep($waitTime);
                        continue; // Retry the same page
                    }
                    
                    // Log all API exceptions with correlation ID
                    $this->logApiException('getPayRuns', $e, ['page' => $page]);
                    
                    // Don't treat 400 as normal end-of-list - it could be a real error
                    throw $e;
                }

                // Check timeout
                if (time() - $startTime > $maxExecutionTime) {
                    error_log("Xero Sync - Timeout reached after " . (time() - $startTime) . "s, stopping");
                    $errors[] = "Sync timeout reached after 5 minutes - partial sync completed";
                    break;
                }

                // Process PayRuns on this page
                foreach ($payRuns as $payRun) {
                    /** @var mixed $payRun */
                    try {
                        $payRunID = $this->getFirst($payRun, ['getPayRunID', 'getPayRunId']);
                        $paymentDate = $this->asDate($this->getFirst($payRun, ['getPaymentDate']));
                        
                        error_log("Processing PayRun: ID={$payRunID}, PaymentDate=" . ($paymentDate ? $paymentDate->format('Y-m-d') : 'NULL'));
                        
                        if (!$payRunID || !$paymentDate) {
                            $errors[] = 'Skipping pay run with missing ID or payment date.';
                            error_log("  ⚠️ Skipped: Missing ID or payment date");
                            continue;
                        }

                        // Range filter
                        if ($sinceDate && $paymentDate < $sinceDate) {
                            error_log("  ⚠️ Skipped: PaymentDate {$paymentDate->format('Y-m-d')} is before cutoff {$sinceDate->format('Y-m-d')}");
                            continue;
                        }

                        // Cache rule (7+ days)
                        $shouldCache = ((new DateTime('now', new DateTimeZone('Pacific/Auckland')))
                                            ->diff($paymentDate)->days) >= self::CACHE_DAYS;

                        error_log("  Fetching PaySlips for PayRun {$payRunID}...");
                        // Pull payslips for this pay run (PayRunID required)
                        $allPaySlips = $this->fetchAllPaySlipsForRun($payRunID);
                        error_log("  Found " . count($allPaySlips) . " PaySlips");

                        // Persist
                        $this->db->beginTransaction();
                        $payrollId = $this->storePayroll($payRun, $allPaySlips, $shouldCache);
                        $deductionsProcessed = $this->processDeductions($payrollId, $allPaySlips);
                        $this->db->commit();

                        if ($shouldCache) {
                            $cachedCount++;
                            error_log("  ✅ Stored as CACHED (payroll_id={$payrollId}, deductions={$deductionsProcessed})");
                        } else {
                            $syncedCount++;
                            error_log("  ✅ Stored as SYNCED (payroll_id={$payrollId}, deductions={$deductionsProcessed})");
                        }

                    } catch (ApiException $e) {
                        if ($e->getCode() === 429) {
                            // Backoff & retry once
                            usleep(750_000); // 750ms
                            try {
                                $allPaySlips = $this->fetchAllPaySlipsForRun($payRunID);
                                $this->db->beginTransaction();
                                $payrollId = $this->storePayroll($payRun, $allPaySlips, true);
                                $this->processDeductions($payrollId, $allPaySlips);
                                $this->db->commit();
                                $cachedCount++;
                            } catch (\Throwable $retryEx) {
                                $this->safeRollback();
                                $errors[] = "Retry after 429 failed for PayRun {$payRunID}: " . $retryEx->getMessage();
                            }
                        } else {
                            $this->safeRollback();
                            $errors[] = "Xero API error for PayRun: [{$e->getCode()}] {$e->getMessage()}";
                        }
                    } catch (\Throwable $t) {
                        $this->safeRollback();
                        $errors[] = "Unexpected error: " . $t->getMessage();
                    }
                }

                // Check if we've hit the last page (Xero default page size is 100)
                $pageCount = is_array($payRuns) ? count($payRuns) : 0;
                if ($pageCount < 100) {
                    error_log("Xero Sync - Page {$page}: Got {$pageCount} results (< 100), last page reached");
                    break;
                }

                $page++;
            }

            error_log("Xero Sync Complete - Total PayRuns found: {$totalPayRunsFound}, Synced: {$syncedCount}, Cached: {$cachedCount}, Errors: " . count($errors));

            return [
                'success' => true,
                'synced' => $syncedCount,
                'cached' => $cachedCount,
                'errors' => $errors,
                'total_processed' => $syncedCount + $cachedCount,
                'total_payruns_found' => $totalPayRunsFound
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Xero sync failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Store / update a pay run row and return local payroll_id.
     *
     * @param object $payRun  SDK PayRun model
     * @param array  $paySlips SDK PaySlip[] for the pay run
     * @param bool   $isCache mark as cached
     */
    private function storePayroll(object $payRun, array $paySlips, bool $isCache): int
    {
        $xeroPayrollId = (string)$this->getFirst($payRun, ['getPayRunID', 'getPayRunId']);
        if ($xeroPayrollId === '') {
            throw new Exception('Invalid PayRun object - missing PayRunID');
        }

        $periodStart = $this->asStringDate($this->getFirst($payRun, ['getPeriodStartDate']));
        $periodEnd   = $this->asStringDate($this->getFirst($payRun, ['getPeriodEndDate']));
        $paymentDate = $this->asStringDate($this->getFirst($payRun, ['getPaymentDate']));

        $statusRaw = $this->getFirst($payRun, ['getPayRunStatus']);
        $status = $statusRaw !== null ? strtolower((string)$statusRaw) : 'draft';

        // Totals from payslips (robust to SDK variations)
        $employeeCount = count($paySlips);
        [$totalGross, $totalDeductions] = $this->sumTotalsFromPaySlips($paySlips);

        // Safe JSON: SDK models implement __toString() -> JSON
        $rawData = (string)$payRun;

        $existing = $this->getPayrollByXeroId($xeroPayrollId);

        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE xero_payrolls
                   SET pay_period_start = ?,
                       pay_period_end   = ?,
                       payment_date     = ?,
                       total_gross_pay  = ?,
                       total_deductions = ?,
                       employee_count   = ?,
                       status           = ?,
                       raw_data         = ?,
                       cached_at        = NOW(),
                       is_cached        = ?
                 WHERE xero_payroll_id   = ?
            ");
            $stmt->execute([
                $periodStart,
                $periodEnd,
                $paymentDate,
                $totalGross,
                $totalDeductions,
                $employeeCount,
                $status,
                $rawData,
                $isCache ? 1 : 0,
                $xeroPayrollId
            ]);

            return (int)$existing['id'];
        }

        $stmt = $this->db->prepare("
            INSERT INTO xero_payrolls (
                xero_payroll_id, pay_period_start, pay_period_end, payment_date,
                total_gross_pay, total_deductions, employee_count, status,
                raw_data, cached_at, is_cached
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([
            $xeroPayrollId,
            $periodStart,
            $periodEnd,
            $paymentDate,
            $totalGross,
            $totalDeductions,
            $employeeCount,
            $status,
            $rawData,
            $isCache ? 1 : 0
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Insert/update staff-account deductions extracted from payslips.
     *
     * @param int   $payrollId
     * @param array $paySlips  array of PaySlip models
     * @return int rows processed
     */
    private function processDeductions(int $payrollId, array $paySlips): int
    {
        $processed = 0;

        foreach ($paySlips as $slip) {
            $employeeId = (string)$this->getFirst($slip, ['getEmployeeID', 'getEmployeeId']);
            $employeeName = $this->resolveEmployeeName($employeeId);
            $mapping = $this->getEmployeeMapping($employeeId);

            $deductionLines = $this->getFirst($slip, ['getDeductionLines']);
            if (!is_array($deductionLines) || count($deductionLines) === 0) {
                continue;
            }

            foreach ($deductionLines as $line) {
                $deductionTypeId = (string)$this->getFirst($line, ['getDeductionTypeID', 'getDeductionTypeId']);
                $amount = (float)$this->getFirst($line, ['getAmount', 'getCalculationAmount', 'getValue'], 0.0);
                if ($amount <= 0) {
                    continue;
                }

                // Name for filtering & storage: prefer the configured Deduction name; fall back to displayName on the line
                $deductionName = $this->resolveDeductionName($deductionTypeId)
                    ?? (string)$this->getFirst($line, ['getDisplayName', 'getName', 'getDescription'], '');

                if (!$this->isStaffAccountDeduction($deductionName)) {
                    continue;
                }

                // Upsert by (payroll_id, xero_employee_id, deduction_type)
                $existing = $this->getDeduction($payrollId, $employeeId, $deductionName);

                if ($existing) {
                    $stmt = $this->db->prepare("
                        UPDATE xero_payroll_deductions
                           SET amount = ?,
                               description = ?
                         WHERE id = ?
                    ");
                    $stmt->execute([
                        $amount,
                        $deductionName,
                        $existing['id']
                    ]);
                } else {
                    $stmt = $this->db->prepare("
                        INSERT INTO xero_payroll_deductions (
                            payroll_id, xero_employee_id, employee_name,
                            user_id, vend_customer_id,
                            deduction_type, deduction_code, amount, description,
                            allocation_status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $stmt->execute([
                        $payrollId,
                        $employeeId,
                        $employeeName,
                        $mapping['user_id'] ?? null,
                        $mapping['vend_customer_id'] ?? null,
                        $deductionName,
                        $deductionTypeId ?: null,
                        $amount,
                        $deductionName
                    ]);
                }

                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Return true if the deduction name looks like a staff-account deduction.
     */
    private function isStaffAccountDeduction(string $name): bool
    {
        $nameLower = strtolower($name);
        foreach ([
            'staff account', 'staff purchase', 'staff debt',
            'employee purchase', 'staff balance', 'account payment'
        ] as $needle) {
            if ($needle !== '' && str_contains($nameLower, $needle)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get employee name via API (handles wrapper) with fallbacks.
     */
    private function resolveEmployeeName(string $employeeId): string
    {
        if ($employeeId === '') {
            return 'Unknown Employee';
        }

        try {
            $employeeObjOrWrapper = $this->api->getEmployee($this->tenantId, $employeeId); // exists :contentReference[oaicite:4]{index=4}
            $employee = $this->getFirst($employeeObjOrWrapper, ['getEmployee']) ?? $employeeObjOrWrapper;

            $first = (string)$this->getFirst($employee, ['getFirstName'], '');
            $last  = (string)$this->getFirst($employee, ['getLastName'], '');
            $name = trim($first . ' ' . $last);
            return $name !== '' ? $name : 'Unknown Employee';
        } catch (\Throwable $e) {
            return 'Unknown Employee';
        }
    }

    /**
     * Deduction name cache (by DeductionTypeID) via getDeduction.
     */
    private function resolveDeductionName(?string $deductionTypeId): ?string
    {
        if (!$deductionTypeId) return null;
        if (isset($this->deductionNameCache[$deductionTypeId])) {
            return $this->deductionNameCache[$deductionTypeId];
        }
        try {
            $deductionObjOrWrapper = $this->api->getDeduction($this->tenantId, $deductionTypeId); // exists :contentReference[oaicite:5]{index=5}
            $deduction = $this->getFirst($deductionObjOrWrapper, ['getDeduction']) ?? $deductionObjOrWrapper;
            $name = (string)$this->getFirst($deduction, ['getDeductionName', 'getName'], '');
            if ($name !== '') {
                return $this->deductionNameCache[$deductionTypeId] = $name;
            }
        } catch (\Throwable $e) {}
        return null;
    }

    /**
     * Fetch all payslips for a pay run with proper pagination and retry logic
     *
     * API: getPaySlips requires PayRunID (NZ Payroll)
     *
     * @param string $payRunID
     * @return array list of PaySlip models
     */
    private function fetchAllPaySlipsForRun(string $payRunID): array
    {
        $all = [];
        $page = 1;
        $retries = 0;
        $maxRetries = 3;

        while (true) {
            try {
                // SDK signature: getPaySlips($xero_tenant_id, $pay_run_id, $page = null)
                $resp = $this->api->getPaySlips($this->tenantId, $payRunID, $page);
                $batch = $resp && method_exists($resp, 'getPaySlips') ? $resp->getPaySlips() : [];
                $batchCount = is_array($batch) ? count($batch) : 0;

                if ($batchCount === 0) {
                    break; // No more payslips
                }

                array_push($all, ...$batch);
                
                // If less than page size returned, we're done (Xero default page size is 100)
                if ($batchCount < 100) {
                    break;
                }

                $page++;
                $retries = 0; // Reset retry counter on success
                
            } catch (ApiException $e) {
                // Respect 429 and Retry-After header
                if ($e->getCode() === 429 && $retries < $maxRetries) {
                    $hdr = $e->getResponseHeaders() ?? [];
                    $retryAfter = (int)($hdr['Retry-After'][0] ?? 2);
                    $waitTime = max($retryAfter, pow(2, $retries)) * 1000000;
                    error_log("  PaySlips page {$page} rate limited, retrying in " . ($waitTime/1000000) . "s");
                    usleep($waitTime);
                    $retries++;
                    continue; // Retry same page
                }
                
                // Log with correlation ID for all other errors
                $this->logApiException('getPaySlips', $e, ['payRunID' => $payRunID, 'page' => $page]);
                throw $e;
            }
        }
        
        return $all;
    }

    /**
     * Sum gross & deduction totals robustly (tolerate schema differences).
     *
     * @param array $paySlips
     * @return array{0:float,1:float}
     */
    private function sumTotalsFromPaySlips(array $paySlips): array
    {
        $gross = 0.0;
        $ded   = 0.0;

        foreach ($paySlips as $slip) {
            $grossVal = (float)$this->getFirst($slip, ['getTotalEarnings', 'getTotalGross'], 0.0);
            if ($grossVal > 0) {
                $gross += $grossVal;
            } else {
                // Fallback: sum earnings lines if provided
                foreach (['getEarningsLines', 'getTimesheetEarningsLines', 'getLeaveEarningsLines'] as $getter) {
                    $lines = $this->getFirst($slip, [$getter]);
                    if (is_array($lines)) {
                        foreach ($lines as $line) {
                            $gross += (float)$this->getFirst($line, ['getAmount', 'getRatePerUnit', 'getFixedAmount'], 0.0);
                        }
                    }
                }
            }

            $dedVal = (float)$this->getFirst($slip, ['getTotalDeductions', 'getTotalStatutoryDeductions'], 0.0);
            if ($dedVal > 0) {
                $ded += $dedVal;
            } else {
                $dedLines = $this->getFirst($slip, ['getDeductionLines']);
                if (is_array($dedLines)) {
                    foreach ($dedLines as $line) {
                        $ded += (float)$this->getFirst($line, ['getAmount', 'getCalculationAmount', 'getValue'], 0.0);
                    }
                }
            }
        }

        return [round($gross, 2), round($ded, 2)];
    }

    /**
     * Get employee mapping using ONLY pre-existing Vend customer codes
     * NO name matching - uses only manually mapped customer codes from database
     */
    private function getEmployeeMapping(string $xeroEmployeeId): ?array
    {
        // Get mapping from existing xero_payroll_deductions table
        // This only uses customer codes that have been manually mapped
        $stmt = $this->db->prepare("
            SELECT 
                vend_customer_id,
                employee_name,
                user_id
            FROM xero_payroll_deductions 
            WHERE xero_employee_id = ?
            AND vend_customer_id IS NOT NULL
            LIMIT 1
        ");
        $stmt->execute([$xeroEmployeeId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return [
                'user_id' => $result['user_id'],
                'vend_customer_id' => $result['vend_customer_id'],
                'matched_by' => 'customer_code_only',
                'employee_name' => $result['employee_name'],
                'staff_active' => 1 // Assume active if we have a customer code mapping
            ];
        }
        
        // NO name matching, NO fuzzy matching - customer code mapping only
        return null;
    }

    private function getPayrollByXeroId(string $xeroPayrollId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM xero_payrolls WHERE xero_payroll_id = ? LIMIT 1");
        $stmt->execute([$xeroPayrollId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    private function getDeduction(int $payrollId, string $employeeId, string $deductionType): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM xero_payroll_deductions
             WHERE payroll_id = ? AND xero_employee_id = ? AND deduction_type = ?
             LIMIT 1
        ");
        $stmt->execute([$payrollId, $employeeId, $deductionType]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public function getPendingDeductions(): array
    {
        $stmt = $this->db->query("
            SELECT d.*, p.payment_date, p.pay_period_start, p.pay_period_end
              FROM xero_payroll_deductions d
              JOIN xero_payrolls p ON d.payroll_id = p.id
             WHERE d.allocation_status = 'pending'
               AND d.amount > 0
          ORDER BY p.payment_date DESC, d.employee_name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDeductionsSummary(): array
    {
        $stmt = $this->db->query("
            SELECT 
                d.vend_customer_id,
                d.employee_name,
                COUNT(*) AS deduction_count,
                COALESCE(SUM(d.amount),0) AS total_deductions,
                COALESCE(SUM(d.allocated_amount),0) AS total_allocated,
                COALESCE(SUM(CASE WHEN d.allocation_status='pending' THEN d.amount ELSE 0 END),0) AS pending_amount,
                MAX(p.payment_date) AS last_deduction_date
            FROM xero_payroll_deductions d
            JOIN xero_payrolls p ON d.payroll_id = p.id
           WHERE d.vend_customer_id IS NOT NULL
           GROUP BY d.vend_customer_id, d.employee_name
           HAVING total_deductions > 0
           ORDER BY pending_amount DESC, d.employee_name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markOldPayrollsAsCached(): int
    {
        $cacheDate = (new DateTime('now', new DateTimeZone('Pacific/Auckland')))
            ->modify('-' . self::CACHE_DAYS . ' days')
            ->format('Y-m-d');

        $stmt = $this->db->prepare("
            UPDATE xero_payrolls
               SET is_cached = 1, cached_at = NOW()
             WHERE payment_date < ? AND is_cached = 0
        ");
        $stmt->execute([$cacheDate]);
        return $stmt->rowCount();
    }

    /**
     * Utilities
     */
    private function safeRollback(): void
    {
        try { if ($this->db->inTransaction()) { $this->db->rollBack(); } } catch (\Throwable $e) {}
    }

    /** Try a list of getters and return the first non-null result. */
    private function getFirst(object $obj, array $getters, mixed $default=null): mixed
    {
        foreach ($getters as $g) {
            if (method_exists($obj, $g)) {
                try {
                    $v = $obj->{$g}();
                    if ($v !== null) return $v;
                } catch (\Throwable $e) {}
            }
        }
        return $default;
    }

    private function asDate(mixed $v): ?DateTime
    {
        if ($v instanceof DateTime) return $v;
        if (is_string($v) && $v !== '') {
            try { return new DateTime($v, new DateTimeZone('Pacific/Auckland')); }
            catch (\Throwable $e) { return null; }
        }
        if (is_object($v) && method_exists($v, '__toString')) {
            try { return new DateTime((string)$v, new DateTimeZone('Pacific/Auckland')); }
            catch (\Throwable $e) { return null; }
        }
        return null;
    }

    private function asStringDate(mixed $v): ?string
    {
        $d = $this->asDate($v);
        return $d ? $d->format('Y-m-d') : null;
    }

    /**
     * Log API exception with correlation ID and rate limit info
     * 
     * @param string $op Operation name
     * @param ApiException $e Exception
     * @param array $ctx Additional context
     */
    private function logApiException(string $op, ApiException $e, array $ctx = []): void
    {
        $hdr = $e->getResponseHeaders() ?? [];
        $corr = $hdr['X-Correlation-Id'][0] ?? ($hdr['x-correlation-id'][0] ?? null);
        $rate = $hdr['X-Rate-Limit-Problem'][0] ?? null;
        error_log(sprintf(
            "Xero %s error [%s] %s | corr=%s rate=%s ctx=%s body=%s",
            $op,
            (string)$e->getCode(),
            $e->getMessage(),
            $corr ?: '-',
            $rate ?: '-',
            json_encode($ctx),
            (string)$e->getResponseBody()
        ));
    }
}
