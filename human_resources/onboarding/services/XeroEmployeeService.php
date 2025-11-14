<?php
/**
 * Xero Employee Service
 *
 * Manages employee creation/updates in Xero Payroll NZ
 *
 * @package CIS\EmployeeOnboarding
 */

namespace CIS\EmployeeOnboarding;

use PDO;
use Exception;

class XeroEmployeeService
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;

        // Load Xero functions
        $xeroFunctions = $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/xero-functions.php';
        if (file_exists($xeroFunctions)) {
            require_once $xeroFunctions;
        }
    }

    /**
     * Create employee in Xero Payroll
     */
    public function createEmployee(int $userId, array $data): array
    {
        // Get Xero API client
        if (!function_exists('getXeroPayrollAPI')) {
            throw new Exception('Xero API functions not available');
        }

        $xero = getXeroPayrollAPI();

        // Build employee payload
        $employee = [
            'FirstName' => $data['first_name'],
            'LastName' => $data['last_name'],
            'Email' => $data['email'],
            'DateOfBirth' => $data['date_of_birth'] ?? null,
            'Gender' => $data['gender'] ?? 'NotSpecified',
            'Phone' => $data['mobile'] ?? $data['phone'] ?? null,
            'StartDate' => $data['start_date'] ?? date('Y-m-d'),
            'PayrollCalendarID' => $data['payroll_calendar_id'] ?? $this->getDefaultPayrollCalendar(),
            'EmployeeStatus' => 'Active'
        ];

        // Add tax information if provided
        if (!empty($data['ird_number'])) {
            $employee['TaxCode'] = $data['tax_code'] ?? 'M';
            $employee['IRDNumber'] = $data['ird_number'];
        }

        // Add bank account if provided
        if (!empty($data['bank_account_number'])) {
            $employee['BankAccounts'] = [[
                'AccountName' => $data['first_name'] . ' ' . $data['last_name'],
                'AccountNumber' => $data['bank_account_number'],
                'StatementText' => 'Salary'
            ]];
        }

        // Add pay template if provided
        if (!empty($data['hourly_rate'])) {
            $employee['PayTemplate'] = [
                'EarningsLines' => [[
                    'EarningsRateID' => $this->getOrdinaryHoursRateId(),
                    'RatePerUnit' => $data['hourly_rate'],
                    'NumberOfUnits' => $data['weekly_hours'] ?? 40
                ]]
            ];
        }

        try {
            // Create in Xero
            $response = $xero->createEmployee($employee);

            if ($response && isset($response['Employees'][0]['EmployeeID'])) {
                $employeeId = $response['Employees'][0]['EmployeeID'];

                return [
                    'success' => true,
                    'employee_id' => $employeeId,
                    'response' => $response
                ];
            }

            throw new Exception('Xero API returned no employee ID');

        } catch (Exception $e) {
            throw new Exception('Xero API error: ' . $e->getMessage());
        }
    }

    /**
     * Update employee in Xero
     */
    public function updateEmployee(int $userId, array $updates): array
    {
        // Get Xero employee ID
        $xeroId = $this->getXeroEmployeeId($userId);

        if (!$xeroId) {
            throw new Exception('Employee not linked to Xero');
        }

        $xero = getXeroPayrollAPI();

        // Build update payload (only changed fields)
        $employee = ['EmployeeID' => $xeroId];

        $fieldMap = [
            'first_name' => 'FirstName',
            'last_name' => 'LastName',
            'email' => 'Email',
            'phone' => 'Phone',
            'date_of_birth' => 'DateOfBirth'
        ];

        foreach ($fieldMap as $cisField => $xeroField) {
            if (isset($updates[$cisField])) {
                $employee[$xeroField] = $updates[$cisField];
            }
        }

        try {
            $response = $xero->updateEmployee($xeroId, $employee);

            return [
                'success' => true,
                'response' => $response
            ];

        } catch (Exception $e) {
            throw new Exception('Xero update error: ' . $e->getMessage());
        }
    }

    /**
     * Deactivate employee in Xero
     */
    public function deactivateEmployee(int $userId): array
    {
        $xeroId = $this->getXeroEmployeeId($userId);

        if (!$xeroId) {
            return ['success' => true, 'message' => 'Not linked to Xero'];
        }

        $xero = getXeroPayrollAPI();

        try {
            $response = $xero->updateEmployee($xeroId, [
                'EmployeeID' => $xeroId,
                'EmployeeStatus' => 'Terminated',
                'TerminationDate' => date('Y-m-d')
            ]);

            return [
                'success' => true,
                'response' => $response
            ];

        } catch (Exception $e) {
            throw new Exception('Xero deactivation error: ' . $e->getMessage());
        }
    }

    /**
     * Get Xero employee ID from mapping
     */
    private function getXeroEmployeeId(int $userId): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT external_id
            FROM external_system_mappings
            WHERE user_id = ? AND system_name = 'xero'
        ");
        $stmt->execute([$userId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['external_id'] ?? null;
    }

    /**
     * Get default payroll calendar ID
     */
    private function getDefaultPayrollCalendar(): ?string
    {
        // This should be configured or fetched from Xero
        // For now, return null and let Xero use default
        return null;
    }

    /**
     * Get ordinary hours earnings rate ID
     */
    private function getOrdinaryHoursRateId(): ?string
    {
        // This should be fetched from Xero or cached
        // Standard earnings rate for ordinary hours
        return null;
    }
}
