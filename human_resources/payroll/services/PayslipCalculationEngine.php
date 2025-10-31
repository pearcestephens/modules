<?php
declare(strict_types=1);

namespace PayrollModule\Services;

use PDO;
use DateTime;

/**
 * Payslip Calculation Engine
 *
 * Handles all rate calculations and NZ employment law compliance:
 * - Time-and-a-half for overtime and public holidays
 * - Night shift penalty rates
 * - Public holiday detection
 * - Alternative holiday (day in lieu) entitlements
 * - Break time calculations
 *
 * Based on business logic from xero-payruns.php
 *
 * @package PayrollModule\Services
 * @version 1.0.0
 */
class PayslipCalculationEngine
{
    private PDO $db;
    private NZEmploymentLaw $employmentLaw;

    // Rate multipliers
    private const TIME_AND_A_HALF = 1.5;
    private const DOUBLE_TIME = 2.0;
    private const NIGHT_SHIFT_RATE = 1.2; // 20% penalty

    // Time boundaries
    private const NIGHT_SHIFT_START = '22:00:00';
    private const NIGHT_SHIFT_END = '06:00:00';
    private const STANDARD_WORK_HOURS_PER_DAY = 8.0;
    private const STANDARD_WORK_HOURS_PER_WEEK = 40.0;

    // Deputy break thresholds (from assets/functions/deputy.php)
    private const FIRST_BREAK_THRESHOLD = 5.0;   // Hours: < 5h = no break
    private const SECOND_BREAK_THRESHOLD = 12.0; // Hours: 12h+ = 60 min break
    private const FIRST_BREAK_MINUTES = 30;      // 5-12 hours = 30 min break
    private const SECOND_BREAK_MINUTES = 60;     // 12+ hours = 60 min break

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->employmentLaw = new NZEmploymentLaw();
    }

    /**
     * Calculate earnings from timesheets
     *
     * @param array $timesheets Array of timesheet records
     * @param int $staffId Staff member ID
     * @return array Earnings breakdown
     */
    public function calculateEarnings(array $timesheets, int $staffId): array
    {
        $totalOrdinaryHours = 0.0;
        $totalOvertimeHours = 0.0;
        $nightShiftHours = 0.0;
        $ordinaryPay = 0.0;
        $overtimePay = 0.0;
        $nightShiftPay = 0.0;
        $publicHolidayHours = 0.0;
        $publicHolidayPay = 0.0;
        $altHolidaysEntitled = 0;

        foreach ($timesheets as $timesheet) {
            $date = $timesheet['date'];
            $startTime = $timesheet['start_time'];
            $endTime = $timesheet['end_time'];
            $breakHours = (float)($timesheet['break_hours'] ?? 0.0);
            $hourlyRate = (float)($timesheet['hourly_rate'] ?? 0.0);
            $outletId = (int)($timesheet['outlet_id'] ?? 0);

            // Calculate total hours worked (before break deduction)
            $start = new DateTime($date . ' ' . $startTime);
            $end = new DateTime($date . ' ' . $endTime);

            // Handle overnight shifts
            if ($end < $start) {
                $end->modify('+1 day');
            }

            $interval = $start->diff($end);
            $totalHours = $interval->h + ($interval->i / 60);

            // DEPUTY BREAK LOGIC: Complete algorithm from deputy.php
            // Only deduct break if NO existing break recorded AND didn't work alone
            if ($breakHours === 0.0) {
                $workedAlone = $this->didStaffWorkAlone($staffId, $date, $startTime, $endTime, $outletId);

                // Check if outlet or staff has paid break policy
                $hasPaidBreak = $this->shouldHavePaidBreak($outletId, $staffId);

                if (!$workedAlone && !$hasPaidBreak && $totalHours >= self::FIRST_BREAK_THRESHOLD) {
                    // Auto-deduct break using Deputy algorithm (5h=30min, 12h=60min)
                    $breakMinutes = $this->calculateDeputyBreakMinutes($totalHours);
                    $breakHours = $breakMinutes / 60.0;
                }
            }
            $workedHours = $totalHours - $breakHours;

            // Check if this is a public holiday
            $isPublicHoliday = $this->employmentLaw->isPublicHoliday($date);

            if ($isPublicHoliday) {
                // Public holiday: time and a half, plus alt day earned
                $publicHolidayHours += $workedHours;
                $publicHolidayPay += $workedHours * $hourlyRate * 1.5;
                $altHolidaysEntitled++;
            } else {
                // Ordinary hours (capped at 8 per day)
                $ordinaryHours = min($workedHours, 8.0);
                $overtimeHours = max(0, $workedHours - 8.0);

                $ordinaryPay += $ordinaryHours * $hourlyRate;
                $overtimePay += $overtimeHours * $hourlyRate * 1.5; // Time and a half for overtime

                $totalOrdinaryHours += $ordinaryHours;
                $totalOvertimeHours += $overtimeHours;

                // Calculate night shift hours
                $nightHours = $this->calculateNightShiftHours($startTime, $endTime, $breakHours);
                $nightShiftHours += $nightHours;
                $nightShiftPay += $nightHours * $hourlyRate * 0.2; // 20% night shift loading
            }
        }

        return [
            'ordinary_hours' => round($totalOrdinaryHours, 2),
            'ordinary_pay' => round($ordinaryPay, 2),
            'overtime_hours' => round($totalOvertimeHours, 2),
            'overtime_pay' => round($overtimePay, 2),
            'night_shift_hours' => round($nightShiftHours, 2),
            'night_shift_pay' => round($nightShiftPay, 2),
            'public_holiday_hours' => round($publicHolidayHours, 2),
            'public_holiday_pay' => round($publicHolidayPay, 2),
            'alternative_holidays_entitled' => $altHolidaysEntitled,
        ];
    }    /**
     * Calculate night shift hours (10pm - 6am)
     */
    private function calculateNightShiftHours(string $startTime, string $endTime, float $breakHours): float
    {
        $start = new DateTime($startTime);
        $end = new DateTime($endTime);

        // If end is before start, shift crossed midnight
        if ($end < $start) {
            $end->modify('+1 day');
        }

        $nightStart = new DateTime(self::NIGHT_SHIFT_START);
        $nightEnd = new DateTime(self::NIGHT_SHIFT_END);
        $nightEnd->modify('+1 day'); // Night end is next day

        $nightShiftSeconds = 0;

        // Calculate overlap with night shift period
        $overlapStart = max($start, $nightStart);
        $overlapEnd = min($end, $nightEnd);

        if ($overlapStart < $overlapEnd) {
            $nightShiftSeconds = $overlapEnd->getTimestamp() - $overlapStart->getTimestamp();
        }

        $nightShiftHours = $nightShiftSeconds / 3600;

        // Deduct break hours proportionally
        if ($breakHours > 0) {
            $totalSeconds = $end->getTimestamp() - $start->getTimestamp();
            $breakProportion = ($totalSeconds > 0) ? ($breakHours * 3600) / $totalSeconds : 0;
            $nightShiftHours *= (1 - $breakProportion);
        }

        return max(0, $nightShiftHours);
    }

    /**
     * Calculate overtime hours (>8/day or >40/week)
     */
    private function calculateOvertimeHours(float $dayHours, float $weekHours): float
    {
        $overtimeHours = 0.0;

        // Daily overtime: hours over 8 per day
        if ($dayHours > self::STANDARD_WORK_HOURS_PER_DAY) {
            $overtimeHours = $dayHours - self::STANDARD_WORK_HOURS_PER_DAY;
        }

        // Weekly overtime: hours over 40 per week
        $weeklyTotal = $weekHours + $dayHours;
        if ($weeklyTotal > self::STANDARD_WORK_HOURS_PER_WEEK) {
            $weeklyOvertime = $weeklyTotal - self::STANDARD_WORK_HOURS_PER_WEEK;
            $overtimeHours = max($overtimeHours, $weeklyOvertime);
        }

        return $overtimeHours;
    }

    /**
     * Get total hours worked in week before given date
     */
    private function getWeekHoursBeforeDate(int $staffId, string $date, array $timesheets): float
    {
        $targetDate = new DateTime($date);
        $weekStart = clone $targetDate;
        $weekStart->modify('monday this week');

        $hours = 0.0;

        foreach ($timesheets as $timesheet) {
            $timesheetDate = new DateTime($timesheet['date']);

            if ($timesheetDate >= $weekStart && $timesheetDate < $targetDate) {
                $hours += ($timesheet['total_hours'] - ($timesheet['break_hours'] ?? 0.0));
            }
        }

        return $hours;
    }

    /**
     * Calculate all deductions for staff member
     */
    public function calculateDeductions(int $staffId, string $periodStart, string $periodEnd, array $earnings): array
    {
        $deductions = [
            'leave' => 0.0,
            'advances' => 0.0,
            'student_loan' => 0.0,
            'kiwisaver' => 0.0,
            'other' => 0.0
        ];

        $grossPay = $earnings['total_pay'];

        // 1. Advances/loans deduction
        $advances = $this->getActiveAdvances($staffId, $periodStart);
        foreach ($advances as $advance) {
            $deductions['advances'] += $advance['deduction_amount'];
        }

        // 2. KiwiSaver (employee contribution - typically 3-10%)
        $staff = $this->getStaffDetails($staffId);
        $kiwisaverRate = $staff['kiwisaver_rate'] ?? 0.03; // Default 3%
        if ($kiwisaverRate > 0) {
            $deductions['kiwisaver'] = $grossPay * $kiwisaverRate;
        }

        // 3. Student loan (based on income threshold)
        $studentLoanRate = 0.12; // 12% over threshold
        $studentLoanThreshold = 24128; // Annual threshold (2025)
        $weeklyThreshold = $studentLoanThreshold / 52;

        if ($staff['has_student_loan'] ?? false) {
            if ($grossPay > $weeklyThreshold) {
                $deductions['student_loan'] = ($grossPay - $weeklyThreshold) * $studentLoanRate;
            }
        }

        // 4. Leave deductions (unpaid leave)
        // This would be calculated if staff took unpaid leave

        // Round all deductions
        foreach ($deductions as $key => $value) {
            $deductions[$key] = round($value, 2);
        }

        return $deductions;
    }

    /**
     * Get Adam's commission from Vend API (user ID 5)
     * Based on xero-payruns.php logic
     */
    public function getAdamsCommissionFromVend(): float
    {
        // This would call the Vend API to get commission data
        // Placeholder for now - actual implementation in VendService

        try {
            $stmt = $this->db->prepare("
                SELECT SUM(commission_amount) as total
                FROM vend_sales_commission
                WHERE user_id = 5
                AND paid = 0
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['total'] ?? 0.0;
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    /**
     * Get staff details including hourly rate
     */
    private function getStaffDetails(int $staffId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                first_name,
                last_name,
                xero_id,
                hourly_rate,
                kiwisaver_rate,
                has_student_loan
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$staffId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            throw new \Exception("Staff member not found: $staffId");
        }

        return $result;
    }

    /**
     * Get active advances for staff member
     */
    private function getActiveAdvances(int $staffId, string $periodStart): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM staff_advances
            WHERE staff_id = ?
            AND status = 'active'
            AND deduction_start_date <= ?
            AND (deduction_end_date IS NULL OR deduction_end_date >= ?)
        ");
        $stmt->execute([$staffId, $periodStart, $periodStart]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if staff member worked alone during shift (Deputy break logic)
     *
     * NZ Employment Law / Deputy Rule:
     * - If worked ALONE: Staff get PAID for breaks (no deduction)
     * - If worked WITH OTHERS: Auto-deduct 30 min break for shifts 4+ hours
     *
     * This checks if ANY other staff member was rostered at the same outlet
     * during overlapping time periods.
     *
     * @param int $staffId Staff member ID
     * @param string $date Shift date
     * @param string $startTime Shift start time
     * @param string $endTime Shift end time
     * @param int $outletId Outlet ID
     * @return bool True if worked alone, false if worked with others
     */
    private function didStaffWorkAlone(int $staffId, string $date, string $startTime, string $endTime, int $outletId): bool
    {
        // Check if any other staff were working at same outlet during overlapping time
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM deputy_timesheets dt
            WHERE dt.date = ?
            AND dt.staff_id != ?
            AND dt.outlet_id = ?
            AND (
                -- Check for overlapping shifts
                (dt.start_time < ? AND dt.end_time > ?)
                OR (dt.start_time >= ? AND dt.start_time < ?)
                OR (dt.end_time > ? AND dt.end_time <= ?)
            )
        ");

        $stmt->execute([
            $date,
            $staffId,
            $outletId,
            $endTime,
            $startTime,
            $startTime,
            $endTime,
            $startTime,
            $endTime
        ]);

        $overlappingStaff = (int)$stmt->fetchColumn();

        // Worked alone if NO overlapping staff found
        return $overlappingStaff === 0;
    }

    /**
     * Calculate break minutes based on hours worked (Deputy algorithm)
     *
     * From: assets/functions/deputy.php - calculateDeputyHourBreaksInMinutesBasedOnHoursWorked()
     *
     * Break Schedule:
     * - < 5 hours: 0 minutes
     * - 5-12 hours: 30 minutes
     * - 12+ hours: 60 minutes
     *
     * @param float $hoursWorked Total hours worked
     * @return int Break minutes to deduct
     */
    private function calculateDeputyBreakMinutes(float $hoursWorked): int
    {
        if ($hoursWorked < self::FIRST_BREAK_THRESHOLD) {
            return 0; // < 5 hours = no break
        }

        if ($hoursWorked < self::SECOND_BREAK_THRESHOLD) {
            return self::FIRST_BREAK_MINUTES; // 5-12 hours = 30 min
        }

        return self::SECOND_BREAK_MINUTES; // 12+ hours = 60 min
    }

    /**
     * Check if this outlet/staff should receive paid breaks (no deduction)
     *
     * From: assets/functions/deputy.php - performUpdates() logic
     *
     * Paid Break Policies:
     * - Outlets: 18, 13, 15 (always paid breaks)
     * - Staff: 483, 492, 485, 459, 103 (always paid breaks)
     *
     * @param int $outletId Outlet ID
     * @param int $staffId Staff ID
     * @return bool True if breaks should be paid (no deduction)
     */
    private function shouldHavePaidBreak(int $outletId, int $staffId): bool
    {
        // Outlets that always pay breaks
        $locationsAcceptPaidBreaks = [18, 13, 15];

        // Staff that always get paid breaks
        $staffAcceptPaidBreaks = [483, 492, 485, 459, 103];

        return in_array($outletId, $locationsAcceptPaidBreaks, true) ||
               in_array($staffId, $staffAcceptPaidBreaks, true);
    }
}
