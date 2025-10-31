<?php
declare(strict_types=1);

namespace CIS\Modules\StaffAccounts;

use Exception;
use XeroAPI\XeroPHP\Api\PayrollNzApi;
use XeroAPI\XeroPHP\ApiException;

/**
 * Xero API Service
 * 
 * Wrapper for Xero Payroll API interactions with deep diagnostics
 * 
 * @package CIS\Modules\StaffAccounts
 * @version 2.0.0
 */
class XeroApiService
{
    /**
     * Deep PayrollNZ connectivity test - checks scopes and all required endpoints
     * 
     * Tests:
     * - employees (scope: payroll.employees.read)
     * - payruns page 1 (scope: payroll.payruns.read)
     * - payslips for first payrun (scope: payroll.payslip.read)
     * - settings (scope: payroll.settings.read - optional for deduction names)
     * 
     * Returns correlation IDs and rate limit info for troubleshooting
     * 
     * @param PayrollNzApi $payrollNzApi
     * @param string $xeroTenantId
     * @return array
     */
    public static function deepTest(PayrollNzApi $payrollNzApi, string $xeroTenantId): array
    {
        $out = ['success' => true, 'checks' => [], 'errors' => []];

        $probe = function (string $name, callable $fn) use (&$out) {
            try {
                $res = $fn();
                $count = null;
                if ($res && method_exists($res, 'getEmployees'))  $count = count($res->getEmployees());
                if ($res && method_exists($res, 'getPayRuns'))    $count = count($res->getPayRuns());
                if ($res && method_exists($res, 'getPaySlips'))   $count = count($res->getPaySlips());
                $out['checks'][] = ['op' => $name, 'ok' => true, 'count' => $count];
                return $res;
            } catch (ApiException $e) {
                $hdr = $e->getResponseHeaders() ?? [];
                $err = [
                    'op' => $name,
                    'ok' => false,
                    'code' => $e->getCode(),
                    'msg'  => $e->getMessage(),
                    'correlation' => $hdr['X-Correlation-Id'][0] ?? ($hdr['x-correlation-id'][0] ?? null),
                    'rate_problem' => $hdr['X-Rate-Limit-Problem'][0] ?? null,
                    'retry_after'  => $hdr['Retry-After'][0] ?? null,
                    'body' => (string)$e->getResponseBody(),
                ];
                $out['success'] = false;
                $out['errors'][] = $err;
                return null;
            } catch (Exception $e) {
                $out['success'] = false;
                $out['errors'][] = ['op' => $name, 'ok' => false, 'msg' => $e->getMessage()];
                return null;
            }
        };

        // 1) Employees (confirms tenant + scope)
        $employees = $probe('getEmployees', fn() => $payrollNzApi->getEmployees($xeroTenantId));

        // 2) PayRuns page 1 (unfiltered)
        $payRuns = $probe('getPayRuns[page=1]', fn() => $payrollNzApi->getPayRuns($xeroTenantId, 1));

        // 3) Payslips for first run
        if ($payRuns && method_exists($payRuns, 'getPayRuns') && $payRuns->getPayRuns()) {
            $pr = $payRuns->getPayRuns()[0];
            $payRunId = method_exists($pr, 'getPayRunID') ? $pr->getPayRunID() : (method_exists($pr, 'getPayRunId') ? $pr->getPayRunId() : null);
            if ($payRunId) {
                $probe("getPaySlips[payRun={$payRunId},page=1]", fn() => $payrollNzApi->getPaySlips($xeroTenantId, $payRunId, 1));
            }
        }

        // 4) Optional: settings (detect missing payroll.settings.read scope)
        $probe('getSettings (optional)', fn() => $payrollNzApi->getSettings($xeroTenantId));

        return $out;
    }

    /**
     * Legacy test connection method (kept for backward compatibility)
     * 
     * @return array<string, mixed>
     */
    public static function testConnection(): array
    {
        try {
            global $payrollNzApi, $accountingApi, $xeroTenantId;
            
            if (!isset($payrollNzApi) || !isset($xeroTenantId)) {
                return ['success' => false, 'error' => 'Xero SDK not properly initialized'];
            }
            
            // Use deep test instead
            return self::deepTest($payrollNzApi, $xeroTenantId);
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Xero API Error: ' . $e->getMessage()];
        }
    }
}
