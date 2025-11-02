<?php
/**
 * ReconciliationServiceTest
 * 
 * Unit tests for ReconciliationService variance detection.
 *
 * @package CIS\Payroll\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../services/ReconciliationService.php';

use HumanResources\Payroll\Services\ReconciliationService;

final class ReconciliationServiceTest extends TestCase
{
    private PDO $db;
    private ReconciliationService $reconciler;

    protected function setUp(): void
    {
        $this->db = new PDO(
            'mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4',
            'jcepnzzkmj',
            'wprKh9Jq63',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $this->reconciler = ReconciliationService::make($this->db);

        $this->db->exec("DELETE FROM payroll_activity_log WHERE action LIKE 'reconciliation%'");
    }

    public function testInstantiationViaFactory(): void
    {
        $this->assertInstanceOf(ReconciliationService::class, $this->reconciler);
    }

    public function testDetectPayrollVariancesWithEmptyData(): void
    {
        $deputyTimesheets = [];
        $xeroPayRuns = [];

        $variances = $this->reconciler->detectPayrollVariances($deputyTimesheets, $xeroPayRuns);

        $this->assertIsArray($variances);
        $this->assertEmpty($variances, 'Expected no variances for empty data');
    }

    public function testDetectVariancesLogsActivity(): void
    {
        $beforeCount = (int) $this->db->query(
            "SELECT COUNT(*) FROM payroll_activity_log WHERE action LIKE 'reconciliation%'"
        )->fetchColumn();

        $deputyTimesheets = [
            ['employee_id' => '123', 'hours' => 40.0, 'pay_period' => '2024-W44']
        ];
        $xeroPayRuns = [
            ['employee_id' => '123', 'hours' => 40.0, 'pay_period' => '2024-W44']
        ];

        $this->reconciler->detectPayrollVariances($deputyTimesheets, $xeroPayRuns);

        $afterCount = (int) $this->db->query(
            "SELECT COUNT(*) FROM payroll_activity_log WHERE action LIKE 'reconciliation%'"
        )->fetchColumn();

        $this->assertGreaterThan($beforeCount, $afterCount, 'Expected reconciliation activity log entry');
    }

    public function testRunReconciliationReturnsStructuredResult(): void
    {
        $result = $this->reconciler->runReconciliation(
            payPeriod: '2024-W44',
            employeeFilter: null
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('variances', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertIsArray($result['variances']);
        $this->assertIsArray($result['summary']);
    }
}
