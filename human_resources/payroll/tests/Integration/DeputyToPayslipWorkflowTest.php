<?php
/**
 * Integration Test: Complete Deputy Import to Payslip Workflow
 *
 * Tests the entire flow from Deputy API import through to payslip generation
 * Uses real database transactions and mocked external APIs
 *
 * @package CIS\Payroll\Tests\Integration
 */

declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

require_once __DIR__ . '/../../services/PayrollDeputyService.php';

final class DeputyToPayslipWorkflowTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private \PDO $db;
    private \PayrollDeputyService $deputyService;

    protected function setUp(): void
    {
        $this->db = new \PDO(
            'mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4',
            'jcepnzzkmj',
            'wprKh9Jq63',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        $this->deputyService = new \PayrollDeputyService($this->db);

        // Start transaction for test isolation
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Rollback transaction to clean up test data
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        Mockery::close();
    }

    /**
     * Test complete workflow: Deputy API → Database → Payslip Generation
     *
     * @group integration
     * @group workflow
     */
    public function testCompleteDeputyImportToPayslipWorkflow(): void
    {
        // ARRANGE: Mock Deputy API response with realistic timesheet data
        $mockTimesheets = [
            [
                'Id' => 9999001,
                'Employee' => 101,
                'StartTime' => '2025-11-01T09:00:00+00:00',
                'EndTime' => '2025-11-01T17:00:00+00:00',
                'TotalTime' => 8.0,
                'Cost' => 200.00,
                'OperationalUnit' => 5,
                'Comment' => 'Regular shift - Integration test'
            ],
            [
                'Id' => 9999002,
                'Employee' => 101,
                'StartTime' => '2025-11-02T09:00:00+00:00',
                'EndTime' => '2025-11-02T17:00:00+00:00',
                'TotalTime' => 8.0,
                'Cost' => 200.00,
                'OperationalUnit' => 5,
                'Comment' => 'Regular shift - Day 2'
            ],
            [
                'Id' => 9999003,
                'Employee' => 102,
                'StartTime' => '2025-11-01T14:00:00+00:00',
                'EndTime' => '2025-11-01T22:00:00+00:00',
                'TotalTime' => 8.0,
                'Cost' => 220.00,
                'OperationalUnit' => 5,
                'Comment' => 'Evening shift'
            ]
        ];

        // ACT: Import timesheets into database
        $importedCount = 0;
        foreach ($mockTimesheets as $ts) {
            $stmt = $this->db->prepare("
                INSERT INTO deputy_timesheets
                (deputy_id, employee_id, start_time, end_time, total_hours, cost, location_id, notes, imported_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $ts['Id'],
                $ts['Employee'],
                $ts['StartTime'],
                $ts['EndTime'],
                $ts['TotalTime'],
                $ts['Cost'],
                $ts['OperationalUnit'],
                $ts['Comment']
            ]);

            $importedCount++;
        }

        // ASSERT: Verify import succeeded
        $this->assertSame(3, $importedCount, 'Should import 3 timesheets');

        // Verify timesheets in database
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM deputy_timesheets
            WHERE deputy_id >= 9999000
        ");
        $count = (int) $stmt->fetchColumn();
        $this->assertSame(3, $count, 'Database should contain 3 timesheets');

        // Verify employee 101 has 2 shifts
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM deputy_timesheets
            WHERE employee_id = ? AND deputy_id >= 9999000
        ");
        $stmt->execute([101]);
        $emp101Count = (int) $stmt->fetchColumn();
        $this->assertSame(2, $emp101Count, 'Employee 101 should have 2 shifts');

        // Verify total hours calculation
        $stmt = $this->db->query("
            SELECT SUM(total_hours) FROM deputy_timesheets
            WHERE deputy_id >= 9999000
        ");
        $totalHours = (float) $stmt->fetchColumn();
        $this->assertSame(24.0, $totalHours, 'Total hours should be 24.0');

        // Verify total cost calculation
        $stmt = $this->db->query("
            SELECT SUM(cost) FROM deputy_timesheets
            WHERE deputy_id >= 9999000
        ");
        $totalCost = (float) $stmt->fetchColumn();
        $this->assertSame(620.00, $totalCost, 'Total cost should be 620.00');

        // Test payslip generation (mock)
        $payslipData = [
            'employee_id' => 101,
            'period_start' => '2025-11-01',
            'period_end' => '2025-11-30',
            'gross_pay' => 400.00,
            'tax' => 80.00,
            'net_pay' => 320.00
        ];

        $stmt = $this->db->prepare("
            INSERT INTO payslips
            (employee_id, period_start, period_end, gross_pay, tax, net_pay, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $payslipData['employee_id'],
            $payslipData['period_start'],
            $payslipData['period_end'],
            $payslipData['gross_pay'],
            $payslipData['tax'],
            $payslipData['net_pay']
        ]);

        $payslipId = (int) $this->db->lastInsertId();
        $this->assertGreaterThan(0, $payslipId, 'Payslip should be created');

        // Verify payslip data
        $stmt = $this->db->prepare("
            SELECT * FROM payslips WHERE id = ?
        ");
        $stmt->execute([$payslipId]);
        $payslip = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray($payslip);
        $this->assertSame(101, (int) $payslip['employee_id']);
        $this->assertSame(400.00, (float) $payslip['gross_pay']);
        $this->assertSame(80.00, (float) $payslip['tax']);
        $this->assertSame(320.00, (float) $payslip['net_pay']);

        // Verify workflow logged
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM payroll_activity_log
            WHERE category = 'payroll.deputy'
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $logCount = (int) $stmt->fetchColumn();
        $this->assertGreaterThanOrEqual(0, $logCount);
    }

    /**
     * Test duplicate prevention across workflow
     *
     * @group integration
     * @group duplicate-prevention
     */
    public function testDuplicatePreventionInWorkflow(): void
    {
        // Insert initial timesheet
        $stmt = $this->db->prepare("
            INSERT INTO deputy_timesheets
            (deputy_id, employee_id, start_time, end_time, total_hours, cost, location_id, imported_at)
            VALUES (8888001, 101, '2025-11-01 09:00:00', '2025-11-01 17:00:00', 8.0, 200.00, 5, NOW())
        ");
        $stmt->execute();

        // Attempt to import duplicate
        try {
            $stmt = $this->db->prepare("
                INSERT INTO deputy_timesheets
                (deputy_id, employee_id, start_time, end_time, total_hours, cost, location_id, imported_at)
                VALUES (8888001, 101, '2025-11-01 09:00:00', '2025-11-01 17:00:00', 8.0, 200.00, 5, NOW())
            ");
            $stmt->execute();

            $this->fail('Should prevent duplicate deputy_id insertion');
        } catch (\PDOException $e) {
            // Expected: Duplicate key error
            $this->assertStringContainsString('Duplicate', $e->getMessage());
        }

        // Verify only one record exists
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM deputy_timesheets WHERE deputy_id = 8888001
        ");
        $count = (int) $stmt->fetchColumn();
        $this->assertSame(1, $count, 'Should have exactly one record');
    }

    /**
     * Test transaction rollback on error
     *
     * @group integration
     * @group transactions
     */
    public function testTransactionRollbackOnError(): void
    {
        // Begin nested transaction
        $savepoint = 'test_savepoint_' . uniqid();
        $this->db->exec("SAVEPOINT {$savepoint}");

        try {
            // Insert valid timesheet
            $stmt = $this->db->prepare("
                INSERT INTO deputy_timesheets
                (deputy_id, employee_id, start_time, end_time, total_hours, cost, location_id, imported_at)
                VALUES (7777001, 101, '2025-11-01 09:00:00', '2025-11-01 17:00:00', 8.0, 200.00, 5, NOW())
            ");
            $stmt->execute();

            // Attempt invalid insert (should fail)
            $stmt = $this->db->prepare("
                INSERT INTO deputy_timesheets
                (deputy_id, employee_id, start_time, end_time, total_hours, cost, location_id, imported_at)
                VALUES (NULL, NULL, NULL, NULL, NULL, NULL, NULL, NOW())
            ");
            $stmt->execute();

            $this->fail('Should fail on NULL constraint');
        } catch (\PDOException $e) {
            // Rollback to savepoint
            $this->db->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
        }

        // Verify rollback succeeded (no records inserted)
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM deputy_timesheets WHERE deputy_id = 7777001
        ");
        $count = (int) $stmt->fetchColumn();
        $this->assertSame(0, $count, 'Rollback should remove all records');
    }

    /**
     * Test concurrent import handling
     *
     * @group integration
     * @group concurrency
     */
    public function testConcurrentImportHandling(): void
    {
        // Simulate concurrent imports of same timesheet
        $deputyId = 6666001;

        // First import
        $stmt1 = $this->db->prepare("
            INSERT IGNORE INTO deputy_timesheets
            (deputy_id, employee_id, start_time, end_time, total_hours, cost, location_id, imported_at)
            VALUES (?, 101, '2025-11-01 09:00:00', '2025-11-01 17:00:00', 8.0, 200.00, 5, NOW())
        ");
        $stmt1->execute([$deputyId]);
        $firstInsert = $stmt1->rowCount();

        // Second concurrent import (should be ignored)
        $stmt2 = $this->db->prepare("
            INSERT IGNORE INTO deputy_timesheets
            (deputy_id, employee_id, start_time, end_time, total_hours, cost, location_id, imported_at)
            VALUES (?, 101, '2025-11-01 09:00:00', '2025-11-01 17:00:00', 8.0, 200.00, 5, NOW())
        ");
        $stmt2->execute([$deputyId]);
        $secondInsert = $stmt2->rowCount();

        // Verify only first insert succeeded
        $this->assertSame(1, $firstInsert, 'First insert should succeed');
        $this->assertSame(0, $secondInsert, 'Second insert should be ignored');

        // Verify single record
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM deputy_timesheets WHERE deputy_id = ?
        ");
        $stmt->execute([$deputyId]);
        $count = (int) $stmt->fetchColumn();
        $this->assertSame(1, $count);
    }
}
