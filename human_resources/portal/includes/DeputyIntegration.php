<?php
/**
 * DEPUTY INTEGRATION WRAPPER
 *
 * Simplified wrapper around existing Deputy services
 * Uses: /modules/human_resources/payroll/services/
 */

require_once __DIR__ . '/../../human_resources/payroll/services/DeputyService.php';
require_once __DIR__ . '/../../human_resources/payroll/services/DeputyApiClient.php';
require_once __DIR__ . '/../../human_resources/payroll/services/PayrollDeputyService.php';

use PayrollModule\Services\DeputyService;
use PayrollModule\Services\DeputyApiClient;
use PayrollModule\Services\PayrollDeputyService;

class DeputyIntegration
{
    private $pdo;
    private $deputyService;
    private $deputyApiClient;
    private $payrollDeputyService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->deputyService = new DeputyService($pdo);
        $this->deputyApiClient = new DeputyApiClient();
        $this->payrollDeputyService = new PayrollDeputyService($pdo);
    }

    public function getEmployee(int $deputyEmployeeId): array
    {
        return $this->deputyApiClient->getEmployee($deputyEmployeeId);
    }

    public function getTimesheets(string $dateFrom, string $dateTo, ?int $employeeId = null): array
    {
        return $this->deputyApiClient->getTimesheets($dateFrom, $dateTo, $employeeId);
    }

    public function syncTimesheetAmendment(int $amendmentId): array
    {
        try {
            $result = $this->payrollDeputyService->syncAmendment($amendmentId);
            return ['success' => true, 'amendment_id' => $amendmentId, 'result' => $result];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getAllEmployees(): array
    {
        return $this->deputyApiClient->getAllEmployees();
    }

    public function testConnection(): array
    {
        try {
            $result = $this->deputyApiClient->testConnection();
            return ['success' => true, 'message' => 'Connected to Deputy', 'data' => $result];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
