<?php
declare(strict_types=1);

namespace PayrollModule\Services;

use DateTime;

/**
 * NZ Employment Law Helper
 *
 * Handles NZ public holidays, leave entitlements, and employment law compliance
 *
 * @package PayrollModule\Services
 * @version 1.0.0
 */
class NZEmploymentLaw
{
    /**
     * NZ Public Holidays 2025-2026
     *
     * Note: Some holidays are region-specific (Anniversary Days)
     * Update this array annually
     */
    private const PUBLIC_HOLIDAYS = [
        // 2025
        '2025-01-01' => 'New Year\'s Day',
        '2025-01-02' => 'Day after New Year\'s Day',
        '2025-01-27' => 'Auckland Anniversary Day', // Regional
        '2025-02-06' => 'Waitangi Day',
        '2025-04-18' => 'Good Friday',
        '2025-04-21' => 'Easter Monday',
        '2025-04-25' => 'ANZAC Day',
        '2025-06-02' => 'Queen\'s Birthday',
        '2025-10-27' => 'Labour Day',
        '2025-12-25' => 'Christmas Day',
        '2025-12-26' => 'Boxing Day',

        // 2026
        '2026-01-01' => 'New Year\'s Day',
        '2026-01-02' => 'Day after New Year\'s Day',
        '2026-01-26' => 'Auckland Anniversary Day', // Regional
        '2026-02-06' => 'Waitangi Day',
        '2026-04-03' => 'Good Friday',
        '2026-04-06' => 'Easter Monday',
        '2026-04-25' => 'ANZAC Day',
        '2026-06-01' => 'Queen\'s Birthday',
        '2026-10-26' => 'Labour Day',
        '2026-12-25' => 'Christmas Day',
        '2026-12-26' => 'Boxing Day',
    ];

    /**
     * Check if date is a public holiday
     */
    public static function isPublicHoliday(string $date): bool
    {
        return isset(self::PUBLIC_HOLIDAYS[$date]);
    }

    /**
     * Get public holiday name
     */
    public static function getPublicHolidayName(string $date): ?string
    {
        return self::PUBLIC_HOLIDAYS[$date] ?? null;
    }

    /**
     * Get all public holidays in date range
     */
    public static function getPublicHolidaysInRange(string $startDate, string $endDate): array
    {
        $holidays = [];

        $start = new DateTime($startDate);
        $end = new DateTime($endDate);

        foreach (self::PUBLIC_HOLIDAYS as $date => $name) {
            $holidayDate = new DateTime($date);

            if ($holidayDate >= $start && $holidayDate <= $end) {
                $holidays[$date] = $name;
            }
        }

        return $holidays;
    }

    /**
     * Check if holiday falls on weekend (triggers Mondayisation)
     */
    public static function isHolidayOnWeekend(string $date): bool
    {
        $dt = new DateTime($date);
        $dayOfWeek = (int)$dt->format('N'); // 1 = Monday, 7 = Sunday

        return $dayOfWeek >= 6; // Saturday or Sunday
    }

    /**
     * Get mondayised holiday date (if holiday falls on weekend)
     */
    public static function getMondayisedDate(string $date): ?string
    {
        if (!self::isHolidayOnWeekend($date)) {
            return null;
        }

        $dt = new DateTime($date);
        $dayOfWeek = (int)$dt->format('N');

        // If Saturday, move to Monday
        if ($dayOfWeek === 6) {
            $dt->modify('+2 days');
        }
        // If Sunday, move to Monday
        elseif ($dayOfWeek === 7) {
            $dt->modify('+1 day');
        }

        return $dt->format('Y-m-d');
    }

    /**
     * Calculate public holiday pay rate
     *
     * @return float Time-and-a-half (1.5) or double time (2.0)
     */
    public static function getPublicHolidayRate(bool $workedOnPublicHoliday): float
    {
        // NZ law: time-and-a-half for working on public holiday
        // Plus entitled to alternative holiday (day in lieu)
        return $workedOnPublicHoliday ? 1.5 : 0.0;
    }

    /**
     * Check if entitled to alternative holiday (day in lieu)
     *
     * Employee gets day in lieu if:
     * - They worked on a public holiday
     * - It would otherwise be a working day for them
     */
    public static function isEntitledToAlternativeHoliday(
        string $publicHolidayDate,
        array $normalWorkDays
    ): bool {
        $dt = new DateTime($publicHolidayDate);
        $dayOfWeek = (int)$dt->format('N'); // 1 = Monday, 7 = Sunday

        // Check if this day is normally a working day
        return in_array($dayOfWeek, $normalWorkDays);
    }

    /**
     * Calculate minimum wage compliance
     */
    public static function checkMinimumWage(float $hourlyRate): array
    {
        $minWage = 23.15; // NZ minimum wage as of April 1, 2024

        $compliant = $hourlyRate >= $minWage;

        return [
            'compliant' => $compliant,
            'hourly_rate' => $hourlyRate,
            'minimum_wage' => $minWage,
            'shortfall' => $compliant ? 0.0 : ($minWage - $hourlyRate)
        ];
    }

    /**
     * Calculate annual leave entitlement
     *
     * NZ law: 4 weeks (20 days) per year after 12 months
     */
    public static function calculateAnnualLeaveEntitlement(
        DateTime $startDate,
        DateTime $currentDate
    ): float {
        $interval = $startDate->diff($currentDate);
        $yearsEmployed = $interval->y + ($interval->m / 12);

        // 4 weeks per year
        $weeksEntitled = $yearsEmployed * 4;

        return $weeksEntitled;
    }

    /**
     * Calculate sick leave entitlement
     *
     * NZ law: 10 days per year after 6 months
     */
    public static function calculateSickLeaveEntitlement(
        DateTime $startDate,
        DateTime $currentDate
    ): int {
        $interval = $startDate->diff($currentDate);
        $monthsEmployed = $interval->y * 12 + $interval->m;

        if ($monthsEmployed < 6) {
            return 0;
        }

        // 10 days per year
        $yearsEmployed = $interval->y + ($interval->m / 12);
        return (int)ceil($yearsEmployed * 10);
    }

    /**
     * Calculate bereavement leave entitlement
     *
     * NZ law: 3 days for close family, 1 day for others
     */
    public static function getBereavementLeaveEntitlement(bool $closeFamily): int
    {
        return $closeFamily ? 3 : 1;
    }

    /**
     * Check if overtime is required to be paid
     *
     * NZ law: No automatic overtime unless in employment agreement
     * But industry standard is 1.5x after 8 hours/day or 40 hours/week
     */
    public static function requiresOvertimePay(
        float $hoursWorkedToday,
        float $hoursWorkedThisWeek
    ): bool {
        return $hoursWorkedToday > 8.0 || $hoursWorkedThisWeek > 40.0;
    }

    /**
     * Calculate KiwiSaver contribution
     *
     * @param float $grossPay Gross pay for period
     * @param float $employeeRate Employee contribution rate (3-10%)
     * @param float $employerRate Employer contribution rate (minimum 3%)
     */
    public static function calculateKiwiSaver(
        float $grossPay,
        float $employeeRate = 3.0,
        float $employerRate = 3.0
    ): array {
        $employeeContribution = $grossPay * ($employeeRate / 100);
        $employerContribution = $grossPay * ($employerRate / 100);

        return [
            'employee_contribution' => round($employeeContribution, 2),
            'employer_contribution' => round($employerContribution, 2),
            'total_contribution' => round($employeeContribution + $employerContribution, 2)
        ];
    }

    /**
     * Calculate student loan deduction
     *
     * 12% of earnings over weekly threshold ($432/week as of April 2024)
     */
    public static function calculateStudentLoan(
        float $grossPayWeekly,
        float $weeklyThreshold = 432.0
    ): float {
        if ($grossPayWeekly <= $weeklyThreshold) {
            return 0.0;
        }

        $excessEarnings = $grossPayWeekly - $weeklyThreshold;
        $deduction = $excessEarnings * 0.12;

        return round($deduction, 2);
    }

    /**
     * Get all public holidays for a year
     */
    public static function getPublicHolidaysForYear(int $year): array
    {
        $holidays = [];

        foreach (self::PUBLIC_HOLIDAYS as $date => $name) {
            if (substr($date, 0, 4) === (string)$year) {
                $holidays[$date] = $name;
            }
        }

        return $holidays;
    }

    /**
     * Add public holiday (for custom holidays or updates)
     */
    public static function addCustomHoliday(string $date, string $name): void
    {
        // This would update the holidays array
        // In production, you might store this in database
        // For now, this is just a placeholder
    }
}
