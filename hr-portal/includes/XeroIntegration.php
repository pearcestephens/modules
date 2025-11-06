<?php
/**
 * XERO INTEGRATION WRAPPER
 * 
 * Simplified wrapper around existing Xero services
 * Uses: /modules/human_resources/payroll/services/
 */

require_once __DIR__ . '/../../human_resources/payroll/services/XeroServiceSDK.php';
require_once __DIR__ . '/../../human_resources/payroll/services/PayrollXeroService.php';

use PayrollModule\Services\XeroServiceSDK;
use PayrollModule\Services\PayrollXeroService;

class XeroIntegration
{
    private $pdo;
    private $xeroService;
    private $payrollXeroService;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->xeroService = new XeroServiceSDK();
        $this->payrollXeroService = new PayrollXeroService($pdo);
    }
    
    public function getEmployee(string $employeeId): array
    {
        return $this->xeroService->getEmployee($employeeId);
    }
    
    public function getAllEmployees(): array
    {
        return $this->xeroService->getAllEmployees();
    }
    
    public function getPayRuns(string $status = null): array
    {
        return $this->xeroService->getPayRuns($status);
    }
    
    public function syncPayrunAmendment(int $amendmentId): array
    {
        try {
            $result = $this->payrollXeroService->syncAmendment($amendmentId);
            return ['success' => true, 'amendment_id' => $amendmentId, 'result' => $result];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function getLeaveApplications(): array
    {
        return $this->xeroService->getLeaveApplications();
    }
    
    public function testConnection(): array
    {
        try {
            $result = $this->xeroService->testConnection();
            return ['success' => true, 'message' => 'Connected to Xero', 'data' => $result];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
