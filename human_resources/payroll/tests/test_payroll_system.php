<?php
declare(strict_types=1);

/**
 * Payroll System - Comprehensive Test Suite
 *
 * Tests all payslip calculations, bonuses, deductions, and bank exports
 * Run: php test_payroll_system.php
 *
 * @version 1.0.0
 */

require_once __DIR__ . '/bootstrap.php';

use PayrollModule\Services\PayslipCalculationEngine;
use PayrollModule\Services\BonusService;
use PayrollModule\Services\BankExportService;
use PayrollModule\Services\NZEmploymentLaw;

class PayrollSystemTest
{
    private PDO $db;
    private array $testResults = [];
    private int $testsPassed = 0;
    private int $testsFailed = 0;

    public function __construct()
    {
        global $pdo;

        if (!$pdo) {
            throw new RuntimeException('Database connection not available. Check app.php bootstrap.');
        }

        $this->db = $pdo;
    }

    /**
     * Run all tests
     */
    public function runAllTests(): void
    {
        echo "🚀 PAYROLL SYSTEM - COMPREHENSIVE TEST SUITE\n";
        echo str_repeat("=", 80) . "\n\n";

        // Core calculation tests
        $this->testOrdinaryHoursCalculation();
        $this->testOvertimeCalculation();
        $this->testNightShiftCalculation();
        $this->testPublicHolidayDetection();
        $this->testPublicHolidayPay();

        // Bonus tests
        $this->testVapeDropBonus();
        $this->testGoogleReviewBonus();
        $this->testMonthlyBonus();

        // Deduction tests
        $this->testKiwiSaverDeduction();
        $this->testStudentLoanDeduction();
        $this->testAdvancesDeduction();

        // NZ Law compliance
        $this->testMinimumWageCompliance();
        $this->testAlternativeHolidayEntitlement();

        // Deputy break logic
        $this->testDeputyBreakLogicWorkedAlone();
        $this->testDeputyBreakLogicWorkedWithOthers();

        // Bank export
        $this->testBankExportGeneration();
        $this->testBankExportIntegrity();

        // Integration tests
        $this->testFullPayslipCalculation();
        $this->testPayslipStatusWorkflow();

        // Output results
        $this->outputResults();
    }

    // ========================================================================
    // CALCULATION TESTS
    // ========================================================================

    private function testOrdinaryHoursCalculation(): void
    {
        $testName = "Ordinary Hours Calculation";

        try {
            $engine = new PayslipCalculationEngine($this->db);

            // 8 hour day, no overtime
            $timesheets = [
                [
                    'date' => '2025-01-20',
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'break_hours' => 0.0,
                    'hourly_rate' => 25.00
                ]
            ];

            $result = $engine->calculateEarnings($timesheets, 1);

            $this->assert(
                $result['ordinary_hours'] === 8.0,
                $testName,
                "Expected 8.0 ordinary hours, got {$result['ordinary_hours']}"
            );

            $this->assert(
                $result['ordinary_pay'] === 200.0,
                $testName,
                "Expected $200.00 ordinary pay, got \${$result['ordinary_pay']}"
            );

        } catch (Exception $e) {
            $this->fail($testName, $e->getMessage());
        }
    }

    private function testOvertimeCalculation(): void
    {
        $testName = "Overtime Calculation (>8 hours/day)";

        try {
            $engine = new PayslipCalculationEngine($this->db);

            // 10 hour day = 8 ordinary + 2 overtime
            $timesheets = [
                [
                    'date' => '2025-01-20',
                    'start_time' => '09:00:00',
                    'end_time' => '19:00:00',
                    'break_hours' => 0.0,
                    'hourly_rate' => 25.00
                ]
            ];

            $result = $engine->calculateEarnings($timesheets, 1);

            $this->assert(
                $result['overtime_hours'] === 2.0,
                $testName,
                "Expected 2.0 overtime hours, got {$result['overtime_hours']}"
            );

            // 2 hours * $25 * 1.5 = $75
            $this->assert(
                $result['overtime_pay'] === 75.0,
                $testName,
                "Expected $75.00 overtime pay (time-and-a-half), got \${$result['overtime_pay']}"
            );

        } catch (Exception $e) {
            $this->fail($testName, $e->getMessage());
        }
    }

    private function testNightShiftCalculation(): void
    {
        $testName = "Night Shift Calculation (10pm-6am)";

        try {
            $engine = new PayslipCalculationEngine($this->db);

            // Night shift: 10pm - 6am (8 hours, 30 min break)
            $timesheets = [
                [
                    'date' => '2025-01-15',
                    'start_time' => '22:00:00',
                    'end_time' => '06:00:00',
                    'break_hours' => 0.5,
                    'hourly_rate' => 25.00
                ]
            ];

            $result = $engine->calculateEarnings($timesheets, 1);

            // Night shift should be calculated automatically
            $this->assert(
                $result['night_shift_hours'] > 0,
                $testName,
                "Expected night shift hours to be calculated for 10pm-6am shift"
            );

        } catch (Exception $e) {
            $this->fail($testName, $e->getMessage());
        }
    }

    private function testPublicHolidayDetection(): void
    {
        $testName = "Public Holiday Detection";

        try {
            // Test known public holidays
            $this->assert(
                NZEmploymentLaw::isPublicHoliday('2025-01-01'),
                $testName,
                "New Year's Day 2025 should be detected"
            );

            $this->assert(
                NZEmploymentLaw::isPublicHoliday('2025-12-25'),
                $testName,
                "Christmas Day 2025 should be detected"
            );

            $this->assert(
                !NZEmploymentLaw::isPublicHoliday('2025-01-15'),
                $testName,
                "Regular day should not be detected as public holiday"
            );

        } catch (Exception $e) {
            $this->fail($testName, $e->getMessage());
        }
    }

    private function testPublicHolidayPay(): void
    {
        $testName = "Public Holiday Pay (Time-and-a-Half)";

        try {
            $engine = new PayslipCalculationEngine($this->db);

            // Work on New Year's Day (8 hours at $25/hour)
            $timesheets = [
                [
                    'date' => '2025-01-01',
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'break_hours' => 0.0,
                    'hourly_rate' => 25.00
                ]
            ];

            $result = $engine->calculateEarnings($timesheets, 1);

            $this->assert(
                $result['public_holiday_hours'] === 8.0,
                $testName,
                "Expected 8.0 public holiday hours, got {$result['public_holiday_hours']}"
            );

            // 8 hours * $25 * 1.5 = $300
            $this->assert(
                $result['public_holiday_pay'] === 300.0,
                $testName,
                "Expected $300.00 public holiday pay, got \${$result['public_holiday_pay']}"
            );

            $this->assert(
                $result['alternative_holidays_entitled'] === 1,
                $testName,
                "Expected 1 day in lieu, got {$result['alternative_holidays_entitled']}"
            );

        } catch (Exception $e) {
            $this->fail($testName, $e->getMessage());
        }
    }

    // ========================================================================
    // BONUS TESTS
    // ========================================================================

    private function testVapeDropBonus(): void
    {
        $testName = "Vape Drop Bonus Calculation";

        try {
            $bonusService = new BonusService($this->db);

            // Create test vape drops
            $this->db->exec("
                INSERT INTO vape_drops (staff_id, completed, completed_at, bonus_paid)
                VALUES (1, 1, '2025-01-15 14:00:00', 0),
                       (1, 1, '2025-01-16 15:00:00', 0),
                       (1, 1, '2025-01-17 16:00:00', 0)
            ");

            $bonuses = $bonusService->getBonusesForPeriod(1, '2025-01-13', '2025-01-19');

            // 3 drops * $6 = $18
            $this->assert(
                $bonuses['vape_drops'] === 18.0,
                $testName,
                "Expected $18.00 (3 drops * $6), got \${$bonuses['vape_drops']}"
            );

            // Cleanup
            $this->db->exec("DELETE FROM vape_drops WHERE staff_id = 1");

        } catch (Exception $e) {
            $this->fail($testName, $e->getMessage());
        }
    }

    private function testGoogleReviewBonus(): void
    {
        $testName = "Google Review Bonus Calculation";

        try {
            $bonusService = new BonusService($this->db);

            // Create test reviews
            $this->db->exec("
                INSERT INTO google_reviews (staff_id, rating, review_date, verified, bonus_paid)
                VALUES (1, 5, '2025-01-15', 1, 0),
                       (1, 4, '2025-01-16', 1, 0)
            ");

            $bonuses = $bonusService->getBonusesForPeriod(1, '2025-01-13', '2025-01-19');

            // 2 reviews * $10 = $20
            $this->assert(
                $bonuses['google_reviews'] === 20.0,
                $testName,
                "Expected $20.00 (2 reviews * $10), got \${$bonuses['google_reviews']}"
            );

            // Cleanup
            $this->db->exec("DELETE FROM google_reviews WHERE staff_id = 1");

        } catch (Exception $e) {
            $this->fail($testName, $e->getMessage());
        }
    }

    private function testMonthlyBonus(): void
    {
        $testName = "Monthly Bonus Calculation";

        try {
            $bonusService = new BonusService($this->db);

            // Create test monthly bonus
            $bonusId = $bonusService->createMonthlyBonus(
                1, // staff_id
                100.0, // amount
                'performance',
                'Excellent work',
                1, // created_by
                '2025-01-13',
                '2025-01-19'
            );

            // Approve it
            $bonusService->approveMonthlyBonus($bonusId, 1);

            $bonuses = $bonusService->getBonusesForPeriod(1, '2025-01-13', '2025-01-19');

            $this->assert(
                $bonuses['monthly'] === 100.0,
                $testName,
                "Expected $100.00 monthly bonus, got \${$bonuses['monthly']}"
            );

            // Cleanup
            $this->db->exec("DELETE FROM monthly_bonuses WHERE id = {$bonusId}");

        } catch (Exception $e) {
            $this->fail($testName, $e->getMessage());
        }
    }

    // ========================================================================
    // DEDUCTION TESTS
    // ========================================================================

    private function testKiwiSaverDeduction(): void
    {
        $testName = "KiwiSaver Deduction (3% default)";

        try {
            $grossPay = 1000.0;
            $kiwisaver = NZEmploymentLaw::calculateKiwiSaver($grossPay, 3.0, 3.0);

            $this->assert(
                $kiwisaver['employee_contribution'] === 30.0,
                $testName,
                "Expected $30.00 employee contribution (3%), got \${$kiwisaver['employee_contribution']}"
            );

            $this->assert(
                $kiwisaver['employer_contribution'] === 30.0,
                $testName,
                "Expected $30.00 employer contribution (3%), got \${$kiwisaver['employer_contribution']}"
            );

        } catch (Exception $e) {
            $this->fail($testName, $e->getMessage());
        }
    }

    private function testStudentLoanDeduction(): void
    {
        $testName = "Student Loan Deduction (12% over threshold)";

        try {
            // Weekly threshold is $432
            $grossPayWeekly = 1000.0;
            $deduction = NZEmploymentLaw::calculateStudentLoan($grossPayWeekly);

            // (1000 - 432) * 0.12 = 68.16
            $this->assert(
                $deduction === 68.16,
                $testName,
                "Expected $68.16 student loan deduction, got \${$deduction}"
            );

            // Below threshold = no deduction
            $noDeduction = NZEmploymentLaw::calculateStudentLoan(400.0);
            $this->assert(
                $noDeduction === 0.0,
                $testName,
                "Expected $0.00 for earnings below threshold, got \${$noDeduction}"
            );

        } catch (Exception $e) {
            $this->fail($testName, $e->getMessage());
        }
    }

    private function testAdvancesDeduction(): void
    {
        $testName = "Staff Advances Deduction";

        try {
            // Create test advance
            $this->db->exec("
                INSERT INTO staff_advances
                (staff_id, advance_amount, deduction_amount, deduction_start_date, balance_remaining, status, created_by)
                VALUES (1, 500.0, 50.0, '2025-01-01', 500.0, 'active', 1)
            ");

            $engine = new PayslipCalculationEngine($this->db);
            $earnings = ['ordinary_pay' => 800.0, 'overtime_pay' => 200.0]; // $1000 total
            $deductions = $engine->calculateDeductions(1, '2025-01-13', '2025-01-19', $earnings);

            $this->assert(
                $deductions['advances'] === 50.0,
                $testName,
                "Expected $50.00 advance deduction, got \${$deductions['advances']}"
            );

            // Cleanup
            $this->db->exec("DELETE FROM staff_advances WHERE staff_id = 1");

        } catch (Exception $e) {
            $this->fail($testName, $e->getMessage());
        }
    }

    // ========================================================================
    // NZ LAW COMPLIANCE TESTS
    // ========================================================================

    private function testMinimumWageCompliance(): void
    {
        $testName = "Minimum Wage Compliance Check";

        try {
            // Above minimum wage
            $compliant = NZEmploymentLaw::checkMinimumWage(25.00);
            $this->assert(
                $compliant['compliant'] === true,
                $testName,
                "$25.00/hour should be compliant"
            );

            // Below minimum wage
            $notCompliant = NZEmploymentLaw::checkMinimumWage(20.00);
            $this->assert(
                $notCompliant['compliant'] === false,
                $testName,
                "$20.00/hour should not be compliant"
            );

            $this->assert(
                $notCompliant['shortfall'] === 3.15,
                $testName,
                "Expected $3.15 shortfall, got \${$notCompliant['shortfall']}"
            );

        } catch (Exception $e) {
            $this->fail($testName, $e->getMessage());
        }
    }

    private function testAlternativeHolidayEntitlement(): void
    {
        $testName = "Alternative Holiday (Day in Lieu) Entitlement";

        try {
            // Work Monday (normally work Mon-Fri)
            $entitled = NZEmploymentLaw::isEntitledToAlternativeHoliday(
                '2025-01-01', // New Year's Day (Wednesday)
                [1, 2, 3, 4, 5] // Mon-Fri work days
            );

            $this->assert(
                $entitled === true,
                $testName,
                "Should be entitled to day in lieu for working public holiday on normal work day"
            );

            // Public holiday on Saturday (not normal work day)
            $notEntitled = NZEmploymentLaw::isEntitledToAlternativeHoliday(
                '2025-07-05', // Example Saturday
                [1, 2, 3, 4, 5] // Mon-Fri work days
            );

            $this->assert(
                $notEntitled === false,
                $testName,
                "Should not be entitled if public holiday not on normal work day"
            );

        } catch (Exception $e) {
            $this->fail($testName, $e->getMessage());
        }
    }

    // ========================================================================
    // BANK EXPORT TESTS
    // ========================================================================

    private function testBankExportGeneration(): void
    {
        $testName = "Bank Export CSV Generation";

        try {
            // Create test payslip
            $this->db->exec("
                INSERT INTO payroll_payslips
                (staff_id, period_start, period_end, gross_pay, net_pay, status)
                VALUES (1, '2025-01-13', '2025-01-19', 1000.0, 850.0, 'approved')
            ");

            $payslipId = (int)$this->db->lastInsertId();

            $bankService = new BankExportService($this->db);
            $result = $bankService->generateBankFile(
                [$payslipId],
                '12-3456-7890123-00',
                '2025-01-W3'
            );

            $this->assert(
                isset($result['export_id']),
                $testName,
                "Export should have an ID"
            );

            $this->assert(
                $result['payslip_count'] === 1,
                $testName,
                "Expected 1 payslip exported, got {$result['payslip_count']}"
            );

            $this->assert(
                file_exists($result['file_path']),
                $testName,
                "Export file should exist at {$result['file_path']}"
            );

            // Cleanup
            unlink($result['file_path']);
            $this->db->exec("DELETE FROM payroll_payslips WHERE id = {$payslipId}");
            $this->db->exec("DELETE FROM payroll_bank_exports WHERE id = {$result['export_id']}");

        } catch (Exception $e) {
            $this->fail($testName, $e->getMessage());
        }
    }

    private function testBankExportIntegrity(): void
    {
        $testName = "Bank Export File Integrity (SHA256)";

        try {
            // Create test payslip
            $this->db->exec("
                INSERT INTO payroll_payslips
                (staff_id, period_start, period_end, gross_pay, net_pay, status)
                VALUES (1, '2025-01-13', '2025-01-19', 1000.0, 850.0, 'approved')
            ");

            $payslipId = (int)$this->db->lastInsertId();

            $bankService = new BankExportService($this->db);
            $result = $bankService->generateBankFile(
                [$payslipId],
                '12-3456-7890123-00',
                '2025-01-W3'
            );

            // Verify integrity
            $valid = $bankService->verifyFileIntegrity($result['export_id']);

            $this->assert(
                $valid === true,
                $testName,
                "File integrity check should pass"
            );

            // Cleanup
            unlink($result['file_path']);
            $this->db->exec("DELETE FROM payroll_payslips WHERE id = {$payslipId}");
            $this->db->exec("DELETE FROM payroll_bank_exports WHERE id = {$result['export_id']}");

        } catch (Exception $e) {
            $this->fail($testName, $e->getMessage());
        }
    }

    // ========================================================================
    // INTEGRATION TESTS
    // ========================================================================

    private function testFullPayslipCalculation(): void
    {
        $testName = "Full Payslip Calculation (All Components)";

        try {
            $engine = new PayslipCalculationEngine($this->db);
            $bonusService = new BonusService($this->db);

            // Complex timesheet: ordinary, overtime, night shift
            $timesheets = [
                [
                    'date' => '2025-01-20',
                    'start_time' => '09:00:00',
                    'end_time' => '19:00:00', // 10 hours (2 overtime)
                    'break_hours' => 0.0,
                    'hourly_rate' => 25.00
                ],
                [
                    'date' => '2025-01-21',
                    'start_time' => '22:00:00',
                    'end_time' => '06:00:00', // Night shift
                    'break_hours' => 0.5,
                    'hourly_rate' => 25.00
                ]
            ];

            $earnings = $engine->calculateEarnings($timesheets, 1);
            $deductions = $engine->calculateDeductions(1, '2025-01-20', '2025-01-26', 500.0);

            $this->assert(
                $earnings['ordinary_hours'] > 0,
                $testName,
                "Should have ordinary hours"
            );

            $this->assert(
                $earnings['overtime_hours'] === 2.0,
                $testName,
                "Should have 2 overtime hours"
            );

            $this->assert(
                $earnings['night_shift_hours'] > 0,
                $testName,
                "Should have night shift hours"
            );

        } catch (Exception $e) {
            $this->fail($testName, $e->getMessage());
        }
    }

    private function testPayslipStatusWorkflow(): void
    {
        $testName = "Payslip Status Workflow (calculated → reviewed → approved → exported)";

        try {
            // Create payslip
            $this->db->exec("
                INSERT INTO payroll_payslips
                (staff_id, period_start, period_end, gross_pay, net_pay, status)
                VALUES (1, '2025-01-13', '2025-01-19', 1000.0, 850.0, 'calculated')
            ");

            $payslipId = (int)$this->db->lastInsertId();

            // Check initial status
            $stmt = $this->db->prepare("SELECT status FROM payroll_payslips WHERE id = ?");
            $stmt->execute([$payslipId]);
            $status = $stmt->fetchColumn();

            $this->assert(
                $status === 'calculated',
                $testName,
                "Initial status should be 'calculated'"
            );

            // Update to reviewed
            $this->db->exec("
                UPDATE payroll_payslips
                SET status = 'reviewed', reviewed_by = 1, reviewed_at = NOW()
                WHERE id = {$payslipId}
            ");

            // Update to approved
            $this->db->exec("
                UPDATE payroll_payslips
                SET status = 'approved', approved_by = 1, approved_at = NOW()
                WHERE id = {$payslipId}
            ");

            $stmt->execute([$payslipId]);
            $finalStatus = $stmt->fetchColumn();

            $this->assert(
                $finalStatus === 'approved',
                $testName,
                "Final status should be 'approved'"
            );

            // Cleanup
            $this->db->exec("DELETE FROM payroll_payslips WHERE id = {$payslipId}");

        } catch (Exception $e) {
            $this->fail($testName, $e->getMessage());
        }
    }

    /**
     * Test Deputy break logic when staff worked ALONE
     * Rule: If worked alone, staff get PAID for breaks (no auto-deduction)
     */
    private function testDeputyBreakLogicWorkedAlone(): void
    {
        $testName = "Deputy Break Logic: Worked Alone (Paid Break)";

        try {
            $engine = new PayslipCalculationEngine($this->db);

            // Create timesheet: 8 hour shift, worked ALONE, no break specified
            $timesheets = [
                [
                    'date' => '2025-01-20',
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'break_hours' => 0.0, // No break specified
                    'hourly_rate' => 25.00
                ]
            ];

            // No other staff at outlet - worked alone
            // (In real scenario, deputy_timesheets would be empty for this date/outlet)

            $result = $engine->calculateEarnings($timesheets, 1);

            // Should get paid for FULL 8 hours (no auto-deduction)
            $this->assert(
                $result['ordinary_hours'] === 8.0,
                $testName,
                "Staff who work alone should get paid for full 8 hours (no break deduction)"
            );

        } catch (Exception $e) {
            $this->fail($testName, $e->getMessage());
        }
    }

    /**
     * Test Deputy break logic when staff worked WITH OTHERS
     * Rule: If worked with others for 4+ hours, auto-deduct 30 min break
     */
    private function testDeputyBreakLogicWorkedWithOthers(): void
    {
        $testName = "Deputy Break Logic: Worked With Others (Auto 30 min deduction)";

        try {
            $engine = new PayslipCalculationEngine($this->db);

            // Create overlapping staff member timesheet
            $this->db->exec("
                INSERT INTO deputy_timesheets
                (deputy_timesheet_id, staff_id, outlet_id, date, start_time, end_time, total_hours)
                VALUES (999999, 2, 1, '2025-01-20', '09:00:00', '17:00:00', 8.0)
            ");

            // Create timesheet for staff 1: 8 hour shift, worked WITH OTHERS (staff 2)
            $timesheets = [
                [
                    'date' => '2025-01-20',
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'break_hours' => 0.0, // No break specified
                    'hourly_rate' => 25.00
                ]
            ];

            $result = $engine->calculateEarnings($timesheets, 1);

            // Should auto-deduct 30 min break = 7.5 hours paid
            $this->assert(
                $result['ordinary_hours'] === 7.5,
                $testName,
                "Staff who work with others should have 30 min break auto-deducted (7.5 hours paid)"
            );

            // Cleanup
            $this->db->exec("DELETE FROM deputy_timesheets WHERE deputy_timesheet_id = 999999");

        } catch (Exception $e) {
            $this->fail($testName, $e->getMessage());
        }
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function assert(bool $condition, string $testName, string $message): void
    {
        if ($condition) {
            $this->pass($testName, $message);
        } else {
            $this->fail($testName, $message);
        }
    }

    private function pass(string $testName, string $message): void
    {
        $this->testsPassed++;
        $this->testResults[] = [
            'status' => 'PASS',
            'test' => $testName,
            'message' => $message
        ];
        echo "✅ PASS: {$testName}\n";
        echo "   → {$message}\n\n";
    }

    private function fail(string $testName, string $message): void
    {
        $this->testsFailed++;
        $this->testResults[] = [
            'status' => 'FAIL',
            'test' => $testName,
            'message' => $message
        ];
        echo "❌ FAIL: {$testName}\n";
        echo "   → {$message}\n\n";
    }

    private function outputResults(): void
    {
        echo str_repeat("=", 80) . "\n";
        echo "📊 TEST RESULTS SUMMARY\n";
        echo str_repeat("=", 80) . "\n\n";

        $total = $this->testsPassed + $this->testsFailed;
        $passRate = $total > 0 ? round(($this->testsPassed / $total) * 100, 2) : 0;

        echo "Total Tests: {$total}\n";
        echo "✅ Passed: {$this->testsPassed}\n";
        echo "❌ Failed: {$this->testsFailed}\n";
        echo "📈 Pass Rate: {$passRate}%\n\n";

        if ($this->testsFailed === 0) {
            echo "🎉 ALL TESTS PASSED! System is production-ready!\n";
        } else {
            echo "⚠️  Some tests failed. Review failures above.\n";
        }

        echo "\n" . str_repeat("=", 80) . "\n";
    }
}

// Run tests
try {
    $tester = new PayrollSystemTest();
    $tester->runAllTests();
    exit(0);
} catch (Exception $e) {
    echo "❌ FATAL ERROR: {$e->getMessage()}\n";
    echo $e->getTraceAsString();
    exit(1);
}
