<?php
declare(strict_types=1);

namespace HumanResources\Payroll;

use PDO;
use XeroAPI\XeroPHP\Api\PayrollNzApi;
use XeroAPI\XeroPHP\Models\PayrollNz\PayRun;

/**
 * PayrollSyncService - Robust Xero PayrollNZ ingestion
 *
 * Syncs payruns, payslips, and deductions with idempotent upserts.
 * Handles API errors, rate limiting, and provides detailed logging.
 */
final class PayrollSyncService
{
    private PDO $db;
    private PayrollNzApi $api;
    private string $tenantId;

    public function __construct(PDO $db, PayrollNzApi $api, string $tenantId)
    {
        $this->db = $db;
        $this->api = $api;
        $this->tenantId = $tenantId;
    }

    /**
     * Sync all payruns from Xero
     *
     * @param int|null $limit Maximum number of payruns to fetch (null = all)
     * @return array Stats: ['payruns'=>int, 'payslips'=>int, 'deductions'=>int, 'errors'=>array]
     */
    public function syncPayruns(?int $limit = null): array
    {
        $stats = ['payruns' => 0, 'payslips' => 0, 'deductions' => 0, 'errors' => []];

        try {
            // Fetch payruns from Xero
            $page = 1;
            $fetchedCount = 0;

            while (true) {
                try {
                    $response = $this->api->getPayRuns($this->tenantId, null, null, $page, 100);
                    $payruns = $response->getPayRuns() ?? [];

                    if (empty($payruns)) {
                        break; // No more payruns
                    }

                    foreach ($payruns as $payrun) {
                        try {
                            $this->syncPayrun($payrun);
                            $stats['payruns']++;
                            $fetchedCount++;

                            if ($limit && $fetchedCount >= $limit) {
                                break 2; // Break out of both loops
                            }
                        } catch (\Throwable $e) {
                            $stats['errors'][] = sprintf(
                                "Payrun %s: %s",
                                $payrun->getPayRunId() ?? 'unknown',
                                $e->getMessage()
                            );
                        }
                    }

                    $page++;

                } catch (\XeroAPI\XeroPHP\ApiException $e) {
                    $this->handleApiException($e, $stats['errors']);
                    break; // Stop on API error
                }
            }

        } catch (\Throwable $e) {
            $stats['errors'][] = "Fatal: " . $e->getMessage();
        }

        return $stats;
    }

    /**
     * Sync a single payrun with its payslips and deductions
     */
    private function syncPayrun(PayRun $payrun): void
    {
        $payrunId = $payrun->getPayRunId();
        $payslips = $payrun->getPayslips() ?? [];

        // Upsert payrun
        $stmt = $this->db->prepare("
            INSERT INTO xero_payruns (
                payrun_id, payroll_calendar_id, period_start_date, period_end_date,
                payment_date, total_cost, total_pay, status, posted_date_time, synced_at
            ) VALUES (
                :payrun_id, :calendar_id, :period_start, :period_end,
                :payment_date, :total_cost, :total_pay, :status, :posted_at, NOW()
            )
            ON DUPLICATE KEY UPDATE
                payroll_calendar_id = VALUES(payroll_calendar_id),
                period_start_date = VALUES(period_start_date),
                period_end_date = VALUES(period_end_date),
                payment_date = VALUES(payment_date),
                total_cost = VALUES(total_cost),
                total_pay = VALUES(total_pay),
                status = VALUES(status),
                posted_date_time = VALUES(posted_date_time),
                synced_at = NOW()
        ");

        $stmt->execute([
            ':payrun_id' => $payrunId,
            ':calendar_id' => $payrun->getPayrollCalendarId(),
            ':period_start' => $payrun->getPeriodStartDate()?->format('Y-m-d H:i:s'),
            ':period_end' => $payrun->getPeriodEndDate()?->format('Y-m-d H:i:s'),
            ':payment_date' => $payrun->getPaymentDate()?->format('Y-m-d'),
            ':total_cost' => $payrun->getTotalCost(),
            ':total_pay' => $payrun->getTotalPay(),
            ':status' => $payrun->getPayRunStatus(),
            ':posted_at' => $payrun->getPostedDateTime()?->format('Y-m-d H:i:s'),
        ]);

        // Sync payslips
        foreach ($payslips as $payslip) {
            $this->syncPayslip($payrunId, $payslip);
        }
    }

    /**
     * Sync a single payslip with its deductions
     */
    private function syncPayslip(string $payrunId, $payslip): void
    {
        $payslipId = $payslip->getPaySlipId();
        $deductions = $payslip->getDeductions() ?? [];

        // Upsert payslip
        $stmt = $this->db->prepare("
            INSERT INTO xero_payslips (
                payslip_id, payrun_id, employee_id, first_name, last_name,
                total_earnings, gross_earnings, total_pay, total_employer_taxes,
                total_employee_taxes, total_deductions, total_reimbursements,
                total_statutory_deductions, total_superannuation, bacs_hash,
                payment_method, synced_at
            ) VALUES (
                :payslip_id, :payrun_id, :employee_id, :first_name, :last_name,
                :total_earnings, :gross_earnings, :total_pay, :total_employer_taxes,
                :total_employee_taxes, :total_deductions, :total_reimbursements,
                :total_statutory_deductions, :total_superannuation, :bacs_hash,
                :payment_method, NOW()
            )
            ON DUPLICATE KEY UPDATE
                payrun_id = VALUES(payrun_id),
                employee_id = VALUES(employee_id),
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                total_earnings = VALUES(total_earnings),
                gross_earnings = VALUES(gross_earnings),
                total_pay = VALUES(total_pay),
                total_employer_taxes = VALUES(total_employer_taxes),
                total_employee_taxes = VALUES(total_employee_taxes),
                total_deductions = VALUES(total_deductions),
                total_reimbursements = VALUES(total_reimbursements),
                total_statutory_deductions = VALUES(total_statutory_deductions),
                total_superannuation = VALUES(total_superannuation),
                bacs_hash = VALUES(bacs_hash),
                payment_method = VALUES(payment_method),
                synced_at = NOW()
        ");

        $stmt->execute([
            ':payslip_id' => $payslipId,
            ':payrun_id' => $payrunId,
            ':employee_id' => $payslip->getEmployeeId(),
            ':first_name' => $payslip->getFirstName(),
            ':last_name' => $payslip->getLastName(),
            ':total_earnings' => $payslip->getTotalEarnings(),
            ':gross_earnings' => $payslip->getGrossEarnings(),
            ':total_pay' => $payslip->getTotalPay(),
            ':total_employer_taxes' => $payslip->getTotalEmployerTaxes(),
            ':total_employee_taxes' => $payslip->getTotalEmployeeTaxes(),
            ':total_deductions' => $payslip->getTotalDeductions(),
            ':total_reimbursements' => $payslip->getTotalReimbursements(),
            ':total_statutory_deductions' => $payslip->getTotalStatutoryDeductions(),
            ':total_superannuation' => $payslip->getTotalSuperannuation(),
            ':bacs_hash' => $payslip->getBacsHash(),
            ':payment_method' => $payslip->getPaymentMethod(),
        ]);

        // Sync deductions
        foreach ($deductions as $deduction) {
            $this->syncDeduction($payslipId, $deduction);
        }
    }

    /**
     * Sync a single deduction
     */
    private function syncDeduction(string $payslipId, $deduction): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO xero_payslip_deductions (
                payslip_id, deduction_type_id, display_name, calculation_type,
                standard_amount, reduces_super_liability, reduces_tax_liability,
                amount, synced_at
            ) VALUES (
                :payslip_id, :deduction_type_id, :display_name, :calculation_type,
                :standard_amount, :reduces_super, :reduces_tax,
                :amount, NOW()
            )
            ON DUPLICATE KEY UPDATE
                display_name = VALUES(display_name),
                calculation_type = VALUES(calculation_type),
                standard_amount = VALUES(standard_amount),
                reduces_super_liability = VALUES(reduces_super_liability),
                reduces_tax_liability = VALUES(reduces_tax_liability),
                amount = VALUES(amount),
                synced_at = NOW()
        ");

        $stmt->execute([
            ':payslip_id' => $payslipId,
            ':deduction_type_id' => $deduction->getDeductionTypeId(),
            ':display_name' => $deduction->getDisplayName(),
            ':calculation_type' => $deduction->getCalculationType(),
            ':standard_amount' => $deduction->getStandardAmount(),
            ':reduces_super' => $deduction->getReducesSuperLiability() ? 1 : 0,
            ':reduces_tax' => $deduction->getReducesTaxLiability() ? 1 : 0,
            ':amount' => $deduction->getAmount(),
        ]);
    }

    /**
     * Handle Xero API exceptions with proper logging and retry-after detection
     */
    private function handleApiException(\XeroAPI\XeroPHP\ApiException $e, array &$errors): void
    {
        $code = $e->getCode();
        $body = $e->getResponseBody();
        $headers = $e->getResponseHeaders();

        $errorMsg = sprintf(
            "Xero API Error %d: %s",
            $code,
            $body ?: $e->getMessage()
        );

        // Check for rate limiting
        if ($code === 429 && isset($headers['Retry-After'])) {
            $errorMsg .= sprintf(" (Retry after %s seconds)", $headers['Retry-After'][0] ?? 'unknown');
        }

        $errors[] = $errorMsg;

        // Log to system
        error_log("[PayrollSync] " . $errorMsg);
    }
}
