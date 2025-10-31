<?php
declare(strict_types=1);

/**
 * Deputy Break Algorithm - Comprehensive Test Suite
 *
 * Tests complete Deputy break calculation algorithm:
 * - 5-hour threshold (first break - 30 min)
 * - 12-hour threshold (second break - 60 min)
 * - Working alone vs with others
 * - Paid break policies (outlets and staff)
 *
 * Run: php test_deputy_algorithm.php
 *
 * @version 1.0.0
 */

require_once __DIR__ . '/bootstrap.php';

use PayrollModule\Services\PayslipCalculationEngine;

class DeputyAlgorithmTest
{
    private PDO $db;
    private PayslipCalculationEngine $engine;
    private array $testResults = [];
    private int $testsPassed = 0;
    private int $testsFailed = 0;

    public function __construct()
    {
        global $pdo;

        if (!$pdo) {
            throw new RuntimeException('Database connection not available. Check bootstrap.php');
        }

        $this->db = $pdo;
        $this->engine = new PayslipCalculationEngine($this->db);
    }

    /**
     * Run all Deputy algorithm tests
     */
    public function runAllTests(): void
    {
        echo "🚀 DEPUTY BREAK ALGORITHM - COMPREHENSIVE TEST SUITE\n";
        echo str_repeat("=", 80) . "\n\n";

        // Threshold tests
        $this->testLessThan5HoursNoBreak();
        $this->testExactly5HoursGets30MinBreak();
        $this->test8HoursGets30MinBreak();
        $this->testExactly12HoursGets60MinBreak();
        $this->test14HoursGets60MinBreak();

        // Working alone tests
        $this->testWorkedAloneNoBreakDeduction();

        // Paid break policy tests
        $this->testPaidBreakOutlet18NoDeduction();
        $this->testPaidBreakStaff483NoDeduction();

        // Edge cases
        $this->testExistingBreakHonored();
        $this->test4Point5HoursNoBreakAnymore();

        // Output results
        $this->outputResults();
    }

    // ============================================================================
    // THRESHOLD TESTS
    // ============================================================================

    private function testLessThan5HoursNoBreak(): void
    {
        $testName = "< 5 hours worked = NO break deduction";

        try {
            $timesheets = [
                [
                    'date' => '2025-01-15',
                    'start_time' => '09:00:00',
                    'end_time' => '13:30:00', // 4.5 hours
                    'break_hours' => 0.0, // Converted from break_minutes
                    'hourly_rate' => 25.0,
                    'outlet_id' => 1,
                ]
            ];

            $result = $this->engine->calculateEarnings($timesheets, 10);

            // Debug output
            if (!isset($result['ordinary_hours'])) {
                throw new Exception("Result missing ordinary_hours. Keys: " . implode(', ', array_keys($result)));
            }

            $this->assert($result['ordinary_hours'] === 4.5, $testName,
                "Should NOT deduct break for < 5 hours. Got: {$result['ordinary_hours']}");
            $this->assert($result['ordinary_pay'] === 112.5, $testName,
                "Expected \$112.50 (4.5h * \$25/h). Got: \${$result['ordinary_pay']}");

            $this->recordPass($testName);
        } catch (Exception $e) {
            $this->recordFail($testName, $e->getMessage());
        }
    }
    private function testExactly5HoursGets30MinBreak(): void
    {
        $testName = "Exactly 5 hours = 30 min break";

        try {
            // Insert overlapping staff (ID 4 = Van Tilsley)
            $this->insertTestTimesheet(4, '2025-01-15', '09:00:00', '14:00:00', 1);

            $timesheets = [
                [
                    'date' => '2025-01-15',
                    'start_time' => '09:00:00',
                    'end_time' => '14:00:00', // 5.0 hours
                    'break_hours' => 0.0,
                    'hourly_rate' => 25.0,
                    'outlet_id' => 1,
                ]
            ];

            $result = $this->engine->calculateEarnings($timesheets, 10); // ID 10 = Billy Downs

            $this->assert($result['ordinary_hours'] === 4.5, $testName,
                "Should deduct 30 min: 5.0 - 0.5 = 4.5h. Got: {$result['ordinary_hours']}");
            $this->assert($result['ordinary_pay'] === 112.5, $testName,
                "Expected \$112.50. Got: \${$result['ordinary_pay']}");

            $this->recordPass($testName);
        } catch (Exception $e) {
            $this->recordFail($testName, $e->getMessage());
        } finally {
            $this->cleanupTestTimesheets();
        }
    }
    private function test8HoursGets30MinBreak(): void
    {
        $testName = "8 hours = 30 min break (not 60)";

        try {
            $this->insertTestTimesheet(4, '2025-01-15', '09:00:00', '17:00:00', 1);

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

            $result = $this->engine->calculateEarnings($timesheets, 10);

            $this->assert($result['ordinary_hours'] === 7.5, $testName,
                "Should deduct 30 min: 8.0 - 0.5 = 7.5h. Got: {$result['ordinary_hours']}");

            $this->recordPass($testName);
        } catch (Exception $e) {
            $this->recordFail($testName, $e->getMessage());
        } finally {
            $this->cleanupTestTimesheets();
        }
    }

    private function testExactly12HoursGets60MinBreak(): void
    {
        $testName = "Exactly 12 hours = 60 min break";

        try {
            $this->insertTestTimesheet(4, '2025-01-15', '08:00:00', '20:00:00', 1);

            $timesheets = [
                [
                    'date' => '2025-01-15',
                    'start_time' => '08:00:00',
                    'end_time' => '20:00:00', // 12.0 hours
                    'break_hours' => 0.0,
                    'hourly_rate' => 25.0,
                    'outlet_id' => 1,
                ]
            ];

                        $result = $this->engine->calculateEarnings($timesheets, 10);

            // 12 hours - 60 min break = 11 hours worked
            // Ordinary hours capped at 8, so 8 ordinary + 3 overtime
            $this->assert($result['ordinary_hours'] === 8.0, $testName,
                "Should have 8h ordinary (capped). Got: {$result['ordinary_hours']}");
            $this->assert($result['overtime_hours'] === 3.0, $testName,
                "Should have 3h overtime (11 - 8). Got: {$result['overtime_hours']}");

            // Total pay: 8h * $25 + 3h * $37.50 = $200 + $112.50 = $312.50
            $expectedPay = (8.0 * 25.0) + (3.0 * 37.5);
            $totalPay = $result['ordinary_pay'] + $result['overtime_pay'];
            $this->assert(abs($totalPay - $expectedPay) < 0.01, $testName,
                "Expected total pay ~\${$expectedPay}. Got: \${$totalPay}");

            $this->recordPass($testName);

        } catch (Exception $e) {
            $this->recordFail($testName, $e->getMessage());
        } finally {
            $this->cleanupTestTimesheets();
        }
    }

    private function test14HoursGets60MinBreak(): void
    {
        $testName = "14 hours = 60 min break";

        try {
            $this->insertTestTimesheet(4, '2025-01-15', '06:00:00', '20:00:00', 1);

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

            $result = $this->engine->calculateEarnings($timesheets, 10);

            // 14 hours - 60 min break = 13 hours worked
            // Ordinary hours capped at 8, so 8 ordinary + 5 overtime
            $this->assert($result['ordinary_hours'] === 8.0, $testName,
                "Should have 8h ordinary (capped). Got: {$result['ordinary_hours']}");
            $this->assert($result['overtime_hours'] === 5.0, $testName,
                "Should have 5h overtime (13 - 8). Got: {$result['overtime_hours']}");

            $this->recordPass($testName);

        } catch (Exception $e) {
            $this->recordFail($testName, $e->getMessage());
        } finally {
            $this->cleanupTestTimesheets();
        }
    }

    // ============================================================================
    // WORKING ALONE TESTS
    // ============================================================================

    private function testWorkedAloneNoBreakDeduction(): void
    {
        $testName = "Worked ALONE = NO break deduction";

        try {
            // NO overlapping staff = working alone

            $timesheets = [
                [
                    'date' => '2025-01-15',
                    'start_time' => '09:00:00',
                    'end_time' => '15:00:00', // 6.0 hours (qualifies)
                    'break_hours' => 0.0,
                    'hourly_rate' => 25.0,
                    'outlet_id' => 1,
                ]
            ];

            $result = $this->engine->calculateEarnings($timesheets, 10);

            $this->assert($result['ordinary_hours'] === 6.0, $testName,
                "Should get full 6.0 hours when alone. Got: {$result['ordinary_hours']}");

            $this->recordPass($testName);
        } catch (Exception $e) {
            $this->recordFail($testName, $e->getMessage());
        }
    }

    // ============================================================================
    // PAID BREAK POLICY TESTS
    // ============================================================================

    private function testPaidBreakOutlet18NoDeduction(): void
    {
        $testName = "Paid break outlet (18) = NO deduction";

        try {
            $this->insertTestTimesheet(4, '2025-01-15', '09:00:00', '15:00:00', 18);

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

            $result = $this->engine->calculateEarnings($timesheets, 10);

            $this->assert($result['ordinary_hours'] === 6.0, $testName,
                "Outlet 18 has paid breaks. Got: {$result['ordinary_hours']}");

            $this->recordPass($testName);
        } catch (Exception $e) {
            $this->recordFail($testName, $e->getMessage());
        } finally {
            $this->cleanupTestTimesheets();
        }
    }

    private function testPaidBreakStaff483NoDeduction(): void
    {
        $testName = "Paid break staff (483) = NO deduction";

        try {
            $this->insertTestTimesheet(4, '2025-01-15', '09:00:00', '15:00:00', 1);

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

            $this->assert($result['ordinary_hours'] === 6.0, $testName,
                "Staff 483 has paid breaks. Got: {$result['ordinary_hours']}");

            $this->recordPass($testName);
        } catch (Exception $e) {
            $this->recordFail($testName, $e->getMessage());
        } finally {
            $this->cleanupTestTimesheets();
        }
    }

    // ============================================================================
    // EDGE CASES
    // ============================================================================

    private function testExistingBreakHonored(): void
    {
        $testName = "Existing break = HONOR it (don't override)";

        try {
            $this->insertTestTimesheet(4, '2025-01-15', '09:00:00', '15:00:00', 1);

            $timesheets = [
                [
                    'date' => '2025-01-15',
                    'start_time' => '09:00:00',
                    'end_time' => '15:00:00', // 6.0 hours
                    'break_hours' => 1.0, // EXISTING 1-hour break
                    'hourly_rate' => 25.0,
                    'outlet_id' => 1,
                ]
            ];

            $result = $this->engine->calculateEarnings($timesheets, 10);

            $this->assert($result['ordinary_hours'] === 5.0, $testName,
                "Should honor existing 1h break: 6.0 - 1.0 = 5.0h. Got: {$result['ordinary_hours']}");

            $this->recordPass($testName);
        } catch (Exception $e) {
            $this->recordFail($testName, $e->getMessage());
        } finally {
            $this->cleanupTestTimesheets();
        }
    }

    private function test4Point5HoursNoBreakAnymore(): void
    {
        $testName = "4.5 hours = NO break (fixed from 4.0 threshold bug)";

        try {
            $this->insertTestTimesheet(4, '2025-01-15', '09:00:00', '13:30:00', 1);

            $timesheets = [
                [
                    'date' => '2025-01-15',
                    'start_time' => '09:00:00',
                    'end_time' => '13:30:00', // 4.5 hours
                    'break_hours' => 0.0,
                    'hourly_rate' => 25.0,
                    'outlet_id' => 1,
                ]
            ];

            $result = $this->engine->calculateEarnings($timesheets, 10);

            $this->assert($result['ordinary_hours'] === 4.5, $testName,
                "4.5h should NOT trigger break (old bug was 4.0 threshold). Got: {$result['ordinary_hours']}");

            $this->recordPass($testName);
        } catch (Exception $e) {
            $this->recordFail($testName, $e->getMessage());
        } finally {
            $this->cleanupTestTimesheets();
        }
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
        // Calculate total_hours for the deputy_timesheets table
        $start = new DateTime($date . ' ' . $startTime);
        $end = new DateTime($date . ' ' . $endTime);
        if ($end < $start) {
            $end->modify('+1 day');
        }
        $interval = $start->diff($end);
        $totalHours = $interval->h + ($interval->i / 60);

        $sql = "INSERT INTO deputy_timesheets
                (deputy_timesheet_id, staff_id, date, start_time, end_time, outlet_id,
                 break_minutes, total_hours, approved, last_synced_at)
                VALUES (:deputy_id, :staff_id, :date, :start_time, :end_time, :outlet_id,
                        0, :total_hours, 1, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'deputy_id' => rand(1000000, 9999999), // Fake deputy ID for testing
            'staff_id' => $staffId,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'outlet_id' => $outletId,
            'total_hours' => $totalHours,
        ]);
    }
    private function cleanupTestTimesheets(): void
    {
        // Clean up test data for staff IDs 4, 5, 10, 13 (real test staff)
        $sql = "DELETE FROM deputy_timesheets
                WHERE staff_id IN (4, 5, 10, 13)
                AND date = '2025-01-15'
                AND deputy_timesheet_id >= 1000000"; // Only delete our test records
        $this->db->exec($sql);
    }

    private function assert(bool $condition, string $testName, string $message): void
    {
        if (!$condition) {
            throw new Exception($message);
        }
    }

    private function recordPass(string $testName): void
    {
        $this->testResults[] = ['name' => $testName, 'status' => 'PASS'];
        $this->testsPassed++;
        echo "✅ PASS: {$testName}\n";
    }

    private function recordFail(string $testName, string $error): void
    {
        $this->testResults[] = ['name' => $testName, 'status' => 'FAIL', 'error' => $error];
        $this->testsFailed++;
        echo "❌ FAIL: {$testName}\n";
        echo "   Error: {$error}\n";
    }

    private function outputResults(): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "📊 TEST RESULTS SUMMARY\n";
        echo str_repeat("=", 80) . "\n\n";

        echo "Total Tests: " . ($this->testsPassed + $this->testsFailed) . "\n";
        echo "✅ Passed: {$this->testsPassed}\n";
        echo "❌ Failed: {$this->testsFailed}\n\n";

        if ($this->testsFailed === 0) {
            echo "🎉 ALL TESTS PASSED! Deputy algorithm working correctly.\n";
        } else {
            echo "⚠️  Some tests failed. Review errors above.\n";
        }
    }
}

// Run tests
try {
    $tester = new DeputyAlgorithmTest();
    $tester->runAllTests();
} catch (Exception $e) {
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
