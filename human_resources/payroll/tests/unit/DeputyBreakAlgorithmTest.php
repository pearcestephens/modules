<?php
declare(strict_types=1);

namespace CIS\HumanResources\Payroll\Tests\Unit;

use CIS\HumanResources\Payroll\Services\PayslipCalculationEngine;
use PHPUnit\Framework\TestCase;
use PDO;

/**
 * Deputy Break Algorithm Tests
 *
 * Tests complete Deputy break calculation algorithm:
 * - 5-hour threshold (first break)
 * - 12-hour threshold (second break)
 * - Working alone vs with others
 * - Paid break policies (outlets and staff)
 * - Break minute calculations
 *
 * @package CIS\HumanResources\Payroll\Tests\Unit
 */
class DeputyBreakAlgorithmTest extends TestCase
{
    private PayslipCalculationEngine $engine;
    private PDO $db;

    protected function setUp(): void
    {
        // Connect to test database
        $dbHost = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $dbName = $_ENV['DB_NAME'] ?? 'jcepnzzkmj';
        $dbUser = $_ENV['DB_USER'] ?? 'jcepnzzkmj';
        $dbPass = $_ENV['DB_PASS'] ?? 'wprKh9Jq63';

        $this->db = new PDO(
            "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
            $dbUser,
            $dbPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $this->engine = new PayslipCalculationEngine($this->db);
    }

    /**
     * Test: < 5 hours worked = NO break deduction
     * Deputy Algorithm: < FIRST_BREAK_THRESHOLD (5.0 hours) = 0 minutes
     */
    public function testLessThan5HoursNoBreak(): void
    {
        $timesheets = [
            [
                'date' => '2025-01-15',
                'start_time' => '09:00:00',
                'end_time' => '13:30:00', // 4.5 hours
                'break_hours' => 0.0,
                'hourly_rate' => 25.0,
                'outlet_id' => 1, // Not in paid break list
            ]
        ];

        $result = $this->engine->calculateEarnings($timesheets, 999);

        // Should get full 4.5 hours paid (no break deduction)
        $this->assertEquals(4.5, $result['total_ordinary_hours'], 'Should NOT deduct break for < 5 hours');
        $this->assertEquals(112.5, $result['ordinary_pay'], '4.5h * $25/h = $112.50');
    }

    /**
     * Test: Exactly 5 hours worked = 30 min break
     * Deputy Algorithm: 5-12 hours = FIRST_BREAK_MINUTES (30 minutes)
     */
    public function testExactly5HoursGets30MinBreak(): void
    {
        // Insert overlapping staff to ensure NOT working alone
        $this->insertTestTimesheet(888, '2025-01-15', '09:00:00', '14:00:00', 1);

        $timesheets = [
            [
                'date' => '2025-01-15',
                'start_time' => '09:00:00',
                'end_time' => '14:00:00', // Exactly 5.0 hours
                'break_hours' => 0.0,
                'hourly_rate' => 25.0,
                'outlet_id' => 1,
            ]
        ];

        $result = $this->engine->calculateEarnings($timesheets, 999);

        // Should deduct 30 min break: 5.0 - 0.5 = 4.5 hours paid
        $this->assertEquals(4.5, $result['total_ordinary_hours'], 'Should deduct 30 min for 5 hours worked');
        $this->assertEquals(112.5, $result['ordinary_pay'], '4.5h * $25/h = $112.50');

        $this->cleanupTestTimesheets();
    }

    /**
     * Test: 8 hours worked = 30 min break (not 60)
     * Deputy Algorithm: 5-12 hours = 30 minutes
     */
    public function test8HoursGets30MinBreak(): void
    {
        $this->insertTestTimesheet(888, '2025-01-15', '09:00:00', '17:00:00', 1);

        $timesheets = [
            [
                'date' => '2025-01-15',
                'start_time' => '09:00:00',
                'end_time' => '17:00:00', // 8.0 hours
                'break_hours' => 0.0,
                'hourly_rate' => 25.0,
                'outlet_id' => 1,
            ]
        ];

        $result = $this->engine->calculateEarnings($timesheets, 999);

        // Should deduct 30 min: 8.0 - 0.5 = 7.5 hours paid
        $this->assertEquals(7.5, $result['total_ordinary_hours'], 'Should deduct 30 min for 8 hours');
        $this->assertEquals(187.5, $result['ordinary_pay'], '7.5h * $25/h = $187.50');

        $this->cleanupTestTimesheets();
    }

    /**
     * Test: Exactly 12 hours worked = 60 min break
     * Deputy Algorithm: >= SECOND_BREAK_THRESHOLD (12.0 hours) = 60 minutes
     */
    public function testExactly12HoursGets60MinBreak(): void
    {
        $this->insertTestTimesheet(888, '2025-01-15', '08:00:00', '20:00:00', 1);

        $timesheets = [
            [
                'date' => '2025-01-15',
                'start_time' => '08:00:00',
                'end_time' => '20:00:00', // Exactly 12.0 hours
                'break_hours' => 0.0,
                'hourly_rate' => 25.0,
                'outlet_id' => 1,
            ]
        ];

        $result = $this->engine->calculateEarnings($timesheets, 999);

        // Should deduct 60 min: 12.0 - 1.0 = 11.0 hours paid
        $this->assertEquals(11.0, $result['total_ordinary_hours'], 'Should deduct 60 min for 12 hours');
        $this->assertEquals(200.0, $result['ordinary_pay'], '8h * $25/h + 3h * $37.5/h (overtime) = $200');

        $this->cleanupTestTimesheets();
    }

    /**
     * Test: 14 hours worked = 60 min break
     * Deputy Algorithm: >= 12 hours = 60 minutes
     */
    public function test14HoursGets60MinBreak(): void
    {
        $this->insertTestTimesheet(888, '2025-01-15', '06:00:00', '20:00:00', 1);

        $timesheets = [
            [
                'date' => '2025-01-15',
                'start_time' => '06:00:00',
                'end_time' => '20:00:00', // 14.0 hours
                'break_hours' => 0.0,
                'hourly_rate' => 25.0,
                'outlet_id' => 1,
            ]
        ];

        $result = $this->engine->calculateEarnings($timesheets, 999);

        // Should deduct 60 min: 14.0 - 1.0 = 13.0 hours paid
        $this->assertEquals(13.0, $result['total_ordinary_hours'], 'Should deduct 60 min for 14 hours');

        $this->cleanupTestTimesheets();
    }

    /**
     * Test: Worked ALONE (no overlapping staff) = NO break deduction
     * Deputy Algorithm: Only deduct if NOT working alone
     */
    public function testWorkedAloneNoBreakDeduction(): void
    {
        // NO overlapping staff inserted = working alone

        $timesheets = [
            [
                'date' => '2025-01-15',
                'start_time' => '09:00:00',
                'end_time' => '15:00:00', // 6.0 hours (qualifies for break)
                'break_hours' => 0.0,
                'hourly_rate' => 25.0,
                'outlet_id' => 1,
            ]
        ];

        $result = $this->engine->calculateEarnings($timesheets, 999);

        // Should get full 6.0 hours (no break deduction when alone)
        $this->assertEquals(6.0, $result['total_ordinary_hours'], 'No break when working alone');
        $this->assertEquals(150.0, $result['ordinary_pay'], '6h * $25/h = $150');
    }

    /**
     * Test: Paid break outlet (outlet ID 18) = NO break deduction
     * Deputy Algorithm: $locationsAcceptPaidBreaks = [18, 13, 15]
     */
    public function testPaidBreakOutlet18NoDeduction(): void
    {
        $this->insertTestTimesheet(888, '2025-01-15', '09:00:00', '15:00:00', 18);

        $timesheets = [
            [
                'date' => '2025-01-15',
                'start_time' => '09:00:00',
                'end_time' => '15:00:00', // 6.0 hours
                'break_hours' => 0.0,
                'hourly_rate' => 25.0,
                'outlet_id' => 18, // PAID BREAK OUTLET
            ]
        ];

        $result = $this->engine->calculateEarnings($timesheets, 999);

        // Should get full 6.0 hours (paid break policy)
        $this->assertEquals(6.0, $result['total_ordinary_hours'], 'Outlet 18 has paid breaks');
        $this->assertEquals(150.0, $result['ordinary_pay'], '6h * $25/h = $150');

        $this->cleanupTestTimesheets();
    }

    /**
     * Test: Paid break staff (staff ID 483) = NO break deduction
     * Deputy Algorithm: $staffAcceptPaidBreaks = [483, 492, 485, 459, 103]
     */
    public function testPaidBreakStaff483NoDeduction(): void
    {
        $this->insertTestTimesheet(888, '2025-01-15', '09:00:00', '15:00:00', 1);

        $timesheets = [
            [
                'date' => '2025-01-15',
                'start_time' => '09:00:00',
                'end_time' => '15:00:00', // 6.0 hours
                'break_hours' => 0.0,
                'hourly_rate' => 25.0,
                'outlet_id' => 1,
            ]
        ];

        $result = $this->engine->calculateEarnings($timesheets, 483); // PAID BREAK STAFF

        // Should get full 6.0 hours (paid break policy)
        $this->assertEquals(6.0, $result['total_ordinary_hours'], 'Staff 483 has paid breaks');
        $this->assertEquals(150.0, $result['ordinary_pay'], '6h * $25/h = $150');

        $this->cleanupTestTimesheets();
    }

    /**
     * Test: Existing break recorded = HONOR it (don't override)
     * Deputy Algorithm: Only auto-deduct if breakHours === 0.0
     */
    public function testExistingBreakHonored(): void
    {
        $this->insertTestTimesheet(888, '2025-01-15', '09:00:00', '15:00:00', 1);

        $timesheets = [
            [
                'date' => '2025-01-15',
                'start_time' => '09:00:00',
                'end_time' => '15:00:00', // 6.0 hours
                'break_hours' => 1.0, // EXISTING BREAK (1 hour)
                'hourly_rate' => 25.0,
                'outlet_id' => 1,
            ]
        ];

        $result = $this->engine->calculateEarnings($timesheets, 999);

        // Should honor existing 1-hour break: 6.0 - 1.0 = 5.0 hours paid
        $this->assertEquals(5.0, $result['total_ordinary_hours'], 'Should honor existing break');
        $this->assertEquals(125.0, $result['ordinary_pay'], '5h * $25/h = $125');

        $this->cleanupTestTimesheets();
    }

    /**
     * Test: Old threshold (4.0 hours) should NOT trigger break
     * Ensures we fixed the bug where 4-hour threshold was used
     */
    public function test4Point5HoursNoBreakAnymore(): void
    {
        $this->insertTestTimesheet(888, '2025-01-15', '09:00:00', '13:30:00', 1);

        $timesheets = [
            [
                'date' => '2025-01-15',
                'start_time' => '09:00:00',
                'end_time' => '13:30:00', // 4.5 hours (was triggering break with old 4.0 threshold)
                'break_hours' => 0.0,
                'hourly_rate' => 25.0,
                'outlet_id' => 1,
            ]
        ];

        $result = $this->engine->calculateEarnings($timesheets, 999);

        // Should get full 4.5 hours (new 5.0 threshold not reached)
        $this->assertEquals(4.5, $result['total_ordinary_hours'], 'Fixed: 4.5h should NOT trigger break');
        $this->assertEquals(112.5, $result['ordinary_pay'], '4.5h * $25/h = $112.50');

        $this->cleanupTestTimesheets();
    }

    // ============================================================================
    // HELPER METHODS
    // ============================================================================

    private function insertTestTimesheet(
        int $staffId,
        string $date,
        string $startTime,
        string $endTime,
        int $outletId
    ): void {
        $sql = "INSERT INTO deputy_timesheets
                (staff_id, date, start_time, end_time, outlet_id, break_hours, hourly_rate, created_at)
                VALUES (:staff_id, :date, :start_time, :end_time, :outlet_id, 0.0, 25.0, NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'staff_id' => $staffId,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'outlet_id' => $outletId,
        ]);
    }

    private function cleanupTestTimesheets(): void
    {
        $sql = "DELETE FROM deputy_timesheets WHERE staff_id IN (888, 999)";
        $this->db->exec($sql);
    }

    protected function tearDown(): void
    {
        $this->cleanupTestTimesheets();
    }
}
