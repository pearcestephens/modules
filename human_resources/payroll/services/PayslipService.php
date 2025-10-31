<?php
declare(strict_types=1);

namespace PayrollModule\Services;

use PayrollModule\Lib\PayrollLogger;
use PDO;
use PDOException;
use DateTime;

/**
 * Payslip Service
 *
 * Complete payslip calculation and management:
 * - Calculate gross pay (base + overtime + penalties)
 * - Apply bonuses (vape drops, Google reviews, monthly bonuses, commission)
 * - Handle public holidays (time and a half OR day in lieu)
 * - Calculate deductions (leave, advances, other)
 * - Generate bank export files (ASB CSV, direct credit)
 * - Integration with Xero and Deputy
 *
 * @package PayrollModule\Services
 * @version 1.0.0
 */
class PayslipService extends BaseService
{
    /**
     * NZ minimum wage (as of 2025)
     */
    private const NZ_MINIMUM_WAGE = 23.15;

    /**
     * Time and a half multiplier
     */
    private const TIME_AND_A_HALF = 1.5;

    /**
     * Double time multiplier
     */
    private const DOUBLE_TIME = 2.0;

    /**
     * Vape drop bonus rate (per drop)
     */
    private const VAPE_DROP_RATE = 6.0;

    /**
     * Google review bonus (per review)
     */
    private const GOOGLE_REVIEW_BONUS = 10.0;

    /**
     * Calculate complete payslip for a staff member
     *
     * @param int $staffId Staff ID
     * @param string $periodStart Pay period start date (Y-m-d)
     * @param string $periodEnd Pay period end date (Y-m-d)
     * @param array $options Optional overrides
     * @return array Complete payslip data
     */
    public function calculatePayslip(int $staffId, string $periodStart, string $periodEnd, array $options = []): array
    {
        $startTime = $this->logger->startTimer('calculate_payslip');

        try {
            // 1. Get staff details
            $staff = $this->getStaffDetails($staffId);
            if (!$staff) {
                throw new \Exception("Staff member not found: {$staffId}");
            }

            // 2. Get Deputy timesheets for period
            $timesheets = $this->getTimesheets($staffId, $periodStart, $periodEnd);

            // 3. Get any amendments approved in this period
            $amendments = $this->getApprovedAmendments($staffId, $periodStart, $periodEnd);

            // 4. Apply amendments to timesheets
            $adjustedTimesheets = $this->applyAmendments($timesheets, $amendments);

            // 5. Calculate base hours and earnings
            $earnings = $this->calculateEarnings($staff, $adjustedTimesheets, $periodStart, $periodEnd);

            // 6. Calculate public holiday entitlements
            $publicHolidayPay = $this->calculatePublicHolidayPay($staff, $adjustedTimesheets, $periodStart, $periodEnd);

            // 7. Add bonuses
            $bonuses = $this->calculateBonuses($staff, $periodStart, $periodEnd);

            // 8. Calculate deductions
            $deductions = $this->calculateDeductions($staff, $periodStart, $periodEnd);

            // 9. Calculate gross and net pay
            $grossPay = $earnings['total'] + $publicHolidayPay['total'] + $bonuses['total'];
            $netPay = $grossPay - $deductions['total'];

            $payslip = [
                'staff_id' => $staffId,
                'staff_name' => $staff['full_name'],
                'xero_id' => $staff['xero_id'],
                'bank_account' => $staff['bank_account'],
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'earnings' => $earnings,
                'public_holiday_pay' => $publicHolidayPay,
                'bonuses' => $bonuses,
                'deductions' => $deductions,
                'gross_pay' => round($grossPay, 2),
                'net_pay' => round($netPay, 2),
                'timesheets' => $adjustedTimesheets,
                'amendments_applied' => count($amendments),
                'calculated_at' => date('Y-m-d H:i:s')
            ];

            // 10. Save payslip to database
            $payslipId = $this->savePayslip($payslip);
            $payslip['id'] = $payslipId;

            $this->logger->logDuration($startTime, 'Payslip calculated', [
                'staff_id' => $staffId,
                'payslip_id' => $payslipId,
                'gross_pay' => $grossPay,
                'net_pay' => $netPay
            ]);

            return $payslip;

        } catch (\Exception $e) {
            $this->logger->error('Failed to calculate payslip', [
                'staff_id' => $staffId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get staff details including pay rate, xero_id, bank account
     */
    private function getStaffDetails(int $staffId): ?array
    {
        $sql = "SELECT
                    u.id,
                    u.first_name,
                    u.last_name,
                    CONCAT(u.first_name, ' ', u.last_name) as full_name,
                    u.xero_id,
                    u.bank_account,
                    u.pay_rate,
                    u.employment_type,
                    u.xero_start_date,
                    o.id as outlet_id,
                    o.outlet_name,
                    o.deputy_location_id
                FROM users u
                LEFT JOIN vend_outlets o ON u.outlet = o.id
                WHERE u.id = :staff_id
                AND u.staff_active = 1";

        $result = $this->queryOne($sql, ['staff_id' => $staffId]);

        return $result ?: null;
    }

    /**
     * Get Deputy timesheets for period
     */
    private function getTimesheets(int $staffId, string $periodStart, string $periodEnd): array
    {
        // This would call Deputy API or fetch from cached table
        $sql = "SELECT *
                FROM deputy_timesheets
                WHERE staff_id = :staff_id
                AND date >= :period_start
                AND date <= :period_end
                AND approved = 1
                ORDER BY date, start_time";

        return $this->query($sql, [
            'staff_id' => $staffId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd
        ]);
    }

    /**
     * Get approved amendments for period
     */
    private function getApprovedAmendments(int $staffId, string $periodStart, string $periodEnd): array
    {
        $sql = "SELECT *
                FROM payroll_timesheet_amendments
                WHERE staff_id = :staff_id
                AND status = 'approved'
                AND deputy_synced = 1
                AND DATE(new_start) BETWEEN :period_start AND :period_end
                ORDER BY created_at";

        return $this->query($sql, [
            'staff_id' => $staffId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd
        ]);
    }

    /**
     * Apply amendments to timesheets
     */
    private function applyAmendments(array $timesheets, array $amendments): array
    {
        // Create a map of amendments by date/original time
        $amendmentMap = [];
        foreach ($amendments as $amendment) {
            $key = date('Y-m-d', strtotime($amendment['original_start'])) . '_' .
                   date('H:i', strtotime($amendment['original_start']));
            $amendmentMap[$key] = $amendment;
        }

        // Apply amendments
        $adjusted = [];
        foreach ($timesheets as $ts) {
            $key = $ts['date'] . '_' . date('H:i', strtotime($ts['start_time']));

            if (isset($amendmentMap[$key])) {
                $amendment = $amendmentMap[$key];
                $ts['original_start'] = $ts['start_time'];
                $ts['original_end'] = $ts['end_time'];
                $ts['original_break_minutes'] = $ts['break_minutes'];
                $ts['start_time'] = date('H:i:s', strtotime($amendment['new_start']));
                $ts['end_time'] = date('H:i:s', strtotime($amendment['new_end']));
                $ts['break_minutes'] = $amendment['new_break_minutes'] ?? $ts['break_minutes'];
                $ts['amended'] = true;
                $ts['amendment_id'] = $amendment['id'];
                $ts['amendment_reason'] = $amendment['reason'];
            }

            $adjusted[] = $ts;
        }

        return $adjusted;
    }

    /**
     * Calculate earnings (base + overtime + penalties)
     */
    private function calculateEarnings(array $staff, array $timesheets, string $periodStart, string $periodEnd): array
    {
        $payRate = (float)($staff['pay_rate'] ?? self::NZ_MINIMUM_WAGE);

        $earnings = [
            'ordinary_hours' => 0,
            'ordinary_pay' => 0,
            'overtime_hours' => 0,
            'overtime_pay' => 0,
            'night_shift_hours' => 0,
            'night_shift_pay' => 0,
            'total' => 0,
            'breakdown' => []
        ];

        foreach ($timesheets as $ts) {
            $start = strtotime($ts['date'] . ' ' . $ts['start_time']);
            $end = strtotime($ts['date'] . ' ' . $ts['end_time']);
            $breakMins = (int)($ts['break_minutes'] ?? 0);

            $totalMins = (int)(($end - $start) / 60) - $breakMins;
            $hours = $totalMins / 60;

            // Check if overtime (after 10pm or before 6am)
            $isNightShift = $this->isNightShift($start, $end);
            $isOvertime = $this->isOvertime($staff['outlet_id'], $start, $end);

            if ($isNightShift) {
                $earnings['night_shift_hours'] += $hours;
                $earnings['night_shift_pay'] += $hours * $payRate * self::TIME_AND_A_HALF;
            } elseif ($isOvertime) {
                $earnings['overtime_hours'] += $hours;
                $earnings['overtime_pay'] += $hours * $payRate * self::TIME_AND_A_HALF;
            } else {
                $earnings['ordinary_hours'] += $hours;
                $earnings['ordinary_pay'] += $hours * $payRate;
            }

            $earnings['breakdown'][] = [
                'date' => $ts['date'],
                'start' => $ts['start_time'],
                'end' => $ts['end_time'],
                'hours' => round($hours, 2),
                'rate' => $payRate,
                'multiplier' => $isNightShift || $isOvertime ? self::TIME_AND_A_HALF : 1.0,
                'pay' => round($hours * $payRate * ($isNightShift || $isOvertime ? self::TIME_AND_A_HALF : 1.0), 2),
                'type' => $isNightShift ? 'night_shift' : ($isOvertime ? 'overtime' : 'ordinary')
            ];
        }

        $earnings['total'] = $earnings['ordinary_pay'] + $earnings['overtime_pay'] + $earnings['night_shift_pay'];

        // Round all values
        foreach (['ordinary_hours', 'overtime_hours', 'night_shift_hours'] as $key) {
            $earnings[$key] = round($earnings[$key], 2);
        }
        foreach (['ordinary_pay', 'overtime_pay', 'night_shift_pay', 'total'] as $key) {
            $earnings[$key] = round($earnings[$key], 2);
        }

        return $earnings;
    }

    /**
     * Check if timesheet is during night shift hours (10pm - 6am)
     */
    private function isNightShift(int $startTs, int $endTs): bool
    {
        $startHour = (int)date('H', $startTs);
        $endHour = (int)date('H', $endTs);

        // Night shift if starts after 10pm or ends before 6am
        return ($startHour >= 22 || $endHour <= 6);
    }

    /**
     * Check if timesheet is overtime based on outlet hours
     */
    private function isOvertime(int $outletId, int $startTs, int $endTs): bool
    {
        // Get outlet operating hours
        $sql = "SELECT open_time, close_time FROM vend_outlets WHERE id = :outlet_id";
        $outlet = $this->queryOne($sql, ['outlet_id' => $outletId]);

        if (!$outlet) {
            return false;
        }

        $outletOpen = strtotime($outlet['open_time']);
        $outletClose = strtotime($outlet['close_time']);

        // Overtime if starts before open or ends after close
        return ($startTs < $outletOpen || $endTs > $outletClose);
    }

    /**
     * Calculate public holiday pay
     *
     * NZ law: If worked on public holiday, entitled to:
     * - Time and a half for hours worked, PLUS
     * - Alternative holiday (day in lieu) if it would otherwise be a working day
     */
    private function calculatePublicHolidayPay(array $staff, array $timesheets, string $periodStart, string $periodEnd): array
    {
        $payRate = (float)($staff['pay_rate'] ?? self::NZ_MINIMUM_WAGE);

        $phPay = [
            'holidays_worked' => [],
            'total_hours' => 0,
            'time_and_half_pay' => 0,
            'alternative_holidays_entitled' => 0,
            'total' => 0
        ];

        // Get public holidays in period
        $holidays = $this->getPublicHolidays($periodStart, $periodEnd);

        foreach ($holidays as $holiday) {
            $holidayDate = $holiday['date'];

            // Find timesheets on this public holiday
            $workedOnHoliday = array_filter($timesheets, function($ts) use ($holidayDate) {
                return $ts['date'] === $holidayDate;
            });

            if (!empty($workedOnHoliday)) {
                $hoursWorked = 0;
                foreach ($workedOnHoliday as $ts) {
                    $start = strtotime($ts['date'] . ' ' . $ts['start_time']);
                    $end = strtotime($ts['date'] . ' ' . $ts['end_time']);
                    $breakMins = (int)($ts['break_minutes'] ?? 0);
                    $totalMins = (int)(($end - $start) / 60) - $breakMins;
                    $hoursWorked += $totalMins / 60;
                }

                $timeAndHalfPay = $hoursWorked * $payRate * self::TIME_AND_A_HALF;

                $phPay['holidays_worked'][] = [
                    'name' => $holiday['name'],
                    'date' => $holidayDate,
                    'hours_worked' => round($hoursWorked, 2),
                    'time_and_half_pay' => round($timeAndHalfPay, 2),
                    'alternative_holiday_entitled' => true
                ];

                $phPay['total_hours'] += $hoursWorked;
                $phPay['time_and_half_pay'] += $timeAndHalfPay;
                $phPay['alternative_holidays_entitled']++;
            }
        }

        $phPay['total'] = $phPay['time_and_half_pay'];
        $phPay['total_hours'] = round($phPay['total_hours'], 2);
        $phPay['time_and_half_pay'] = round($phPay['time_and_half_pay'], 2);

        return $phPay;
    }

    /**
     * Get NZ public holidays for period
     */
    private function getPublicHolidays(string $startDate, string $endDate): array
    {
        // Load from JSON file (same as your existing system)
        $jsonPath = dirname(__DIR__, 4) . '/assets/functions/xeroAPI/public-holidays.json';

        if (!file_exists($jsonPath)) {
            $this->logger->warning('Public holidays file not found', ['path' => $jsonPath]);
            return [];
        }

        $json = file_get_contents($jsonPath);
        $allHolidays = json_decode($json, true);

        if (!is_array($allHolidays)) {
            return [];
        }

        // Filter to period
        $filtered = [];
        foreach ($allHolidays as $holiday) {
            $date = $holiday['date'] ?? null;
            if ($date && $date >= $startDate && $date <= $endDate) {
                $filtered[] = $holiday;
            }
        }

        return $filtered;
    }

    /**
     * Calculate all bonuses
     */
    private function calculateBonuses(array $staff, string $periodStart, string $periodEnd): array
    {
        $bonuses = [
            'vape_drops' => 0,
            'google_reviews' => 0,
            'monthly_bonus' => 0,
            'commission' => 0,
            'acting_position' => 0,
            'total' => 0,
            'breakdown' => []
        ];

        $staffId = $staff['id'];

        // 1. Vape drops
        $vapeDrops = $this->getVapeDrops($staffId, $periodStart, $periodEnd);
        if ($vapeDrops > 0) {
            $bonuses['vape_drops'] = $vapeDrops * self::VAPE_DROP_RATE;
            $bonuses['breakdown'][] = [
                'type' => 'vape_drops',
                'quantity' => $vapeDrops,
                'rate' => self::VAPE_DROP_RATE,
                'amount' => $bonuses['vape_drops']
            ];
        }

        // 2. Google reviews
        $googleReviews = $this->getGoogleReviews($staffId, $periodStart, $periodEnd);
        if ($googleReviews > 0) {
            $bonuses['google_reviews'] = $googleReviews * self::GOOGLE_REVIEW_BONUS;
            $bonuses['breakdown'][] = [
                'type' => 'google_reviews',
                'quantity' => $googleReviews,
                'rate' => self::GOOGLE_REVIEW_BONUS,
                'amount' => $bonuses['google_reviews']
            ];
        }

        // 3. Monthly bonus (from database or calculated)
        $monthlyBonus = $this->getMonthlyBonus($staffId, $periodStart, $periodEnd);
        if ($monthlyBonus > 0) {
            $bonuses['monthly_bonus'] = $monthlyBonus;
            $bonuses['breakdown'][] = [
                'type' => 'monthly_bonus',
                'amount' => $monthlyBonus
            ];
        }

        // 4. Commission (Adam - user ID 5)
        if ($staffId == 5) {
            $commission = $this->getCommission($periodStart, $periodEnd);
            if ($commission > 0) {
                $bonuses['commission'] = $commission;
                $bonuses['breakdown'][] = [
                    'type' => 'commission',
                    'amount' => $commission
                ];
            }
        }

        // 5. Acting position pay (user ID 58 gets $3/hr extra)
        if ($staffId == 58) {
            $totalHours = $this->getTotalHours($staffId, $periodStart, $periodEnd);
            $actingPay = $totalHours * 3.0;
            if ($actingPay > 0) {
                $bonuses['acting_position'] = $actingPay;
                $bonuses['breakdown'][] = [
                    'type' => 'acting_position',
                    'hours' => $totalHours,
                    'rate' => 3.0,
                    'amount' => $actingPay
                ];
            }
        }

        $bonuses['total'] = array_sum([
            $bonuses['vape_drops'],
            $bonuses['google_reviews'],
            $bonuses['monthly_bonus'],
            $bonuses['commission'],
            $bonuses['acting_position']
        ]);

        // Round all
        foreach (['vape_drops', 'google_reviews', 'monthly_bonus', 'commission', 'acting_position', 'total'] as $key) {
            $bonuses[$key] = round($bonuses[$key], 2);
        }

        return $bonuses;
    }

    /**
     * Get vape drops count for period
     */
    private function getVapeDrops(int $staffId, string $periodStart, string $periodEnd): int
    {
        $sql = "SELECT COUNT(*) as count
                FROM vape_drops
                WHERE staff_id = :staff_id
                AND completed = 1
                AND DATE(completed_at) BETWEEN :period_start AND :period_end";

        $result = $this->queryOne($sql, [
            'staff_id' => $staffId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd
        ]);

        return (int)($result['count'] ?? 0);
    }

    /**
     * Get Google reviews count for period
     */
    private function getGoogleReviews(int $staffId, string $periodStart, string $periodEnd): int
    {
        $sql = "SELECT COUNT(*) as count
                FROM google_reviews
                WHERE staff_id = :staff_id
                AND verified = 1
                AND DATE(review_date) BETWEEN :period_start AND :period_end";

        $result = $this->queryOne($sql, [
            'staff_id' => $staffId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd
        ]);

        return (int)($result['count'] ?? 0);
    }

    /**
     * Get monthly bonus
     */
    private function getMonthlyBonus(int $staffId, string $periodStart, string $periodEnd): float
    {
        $sql = "SELECT bonus_amount
                FROM monthly_bonuses
                WHERE staff_id = :staff_id
                AND pay_period_start = :period_start
                AND pay_period_end = :period_end
                AND approved = 1";

        $result = $this->queryOne($sql, [
            'staff_id' => $staffId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd
        ]);

        return (float)($result['bonus_amount'] ?? 0);
    }

    /**
     * Get commission (from Vend API)
     */
    private function getCommission(string $periodStart, string $periodEnd): float
    {
        // This would call your existing commission calculation
        // For now, return 0 - integrate with actual commission system
        return 0.0;
    }

    /**
     * Get total hours worked in period
     */
    private function getTotalHours(int $staffId, string $periodStart, string $periodEnd): float
    {
        $sql = "SELECT SUM(
                    TIMESTAMPDIFF(MINUTE,
                        CONCAT(date, ' ', start_time),
                        CONCAT(date, ' ', end_time)
                    ) - COALESCE(break_minutes, 0)
                ) / 60 as total_hours
                FROM deputy_timesheets
                WHERE staff_id = :staff_id
                AND date BETWEEN :period_start AND :period_end
                AND approved = 1";

        $result = $this->queryOne($sql, [
            'staff_id' => $staffId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd
        ]);

        return (float)($result['total_hours'] ?? 0);
    }

    /**
     * Calculate deductions
     */
    private function calculateDeductions(array $staff, string $periodStart, string $periodEnd): array
    {
        $deductions = [
            'leave' => 0,
            'advances' => 0,
            'student_loan' => 0,
            'kiwisaver' => 0,
            'other' => 0,
            'total' => 0,
            'breakdown' => []
        ];

        // 1. Leave taken (unpaid leave)
        $leaveTaken = $this->getUnpaidLeave($staff['id'], $periodStart, $periodEnd);
        if ($leaveTaken['hours'] > 0) {
            $payRate = (float)($staff['pay_rate'] ?? self::NZ_MINIMUM_WAGE);
            $deductions['leave'] = $leaveTaken['hours'] * $payRate;
            $deductions['breakdown'][] = [
                'type' => 'unpaid_leave',
                'hours' => $leaveTaken['hours'],
                'rate' => $payRate,
                'amount' => $deductions['leave']
            ];
        }

        // 2. Advances/loans
        $advances = $this->getAdvances($staff['id'], $periodStart, $periodEnd);
        if ($advances > 0) {
            $deductions['advances'] = $advances;
            $deductions['breakdown'][] = [
                'type' => 'advances',
                'amount' => $advances
            ];
        }

        $deductions['total'] = array_sum([
            $deductions['leave'],
            $deductions['advances'],
            $deductions['student_loan'],
            $deductions['kiwisaver'],
            $deductions['other']
        ]);

        // Round all
        foreach (['leave', 'advances', 'student_loan', 'kiwisaver', 'other', 'total'] as $key) {
            $deductions[$key] = round($deductions[$key], 2);
        }

        return $deductions;
    }

    /**
     * Get unpaid leave hours
     */
    private function getUnpaidLeave(int $staffId, string $periodStart, string $periodEnd): array
    {
        $sql = "SELECT SUM(hours_requested) as total_hours
                FROM leave_requests
                WHERE staff_id = :staff_id
                AND status = 'approved'
                AND leave_type = 'unpaid'
                AND date_from BETWEEN :period_start AND :period_end";

        $result = $this->queryOne($sql, [
            'staff_id' => $staffId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd
        ]);

        return [
            'hours' => (float)($result['total_hours'] ?? 0)
        ];
    }

    /**
     * Get advances/loans to be deducted
     */
    private function getAdvances(int $staffId, string $periodStart, string $periodEnd): float
    {
        $sql = "SELECT SUM(deduction_amount) as total
                FROM staff_advances
                WHERE staff_id = :staff_id
                AND status = 'approved'
                AND deduction_start_date <= :period_end
                AND (deduction_end_date IS NULL OR deduction_end_date >= :period_start)";

        $result = $this->queryOne($sql, [
            'staff_id' => $staffId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd
        ]);

        return (float)($result['total'] ?? 0);
    }

    /**
     * Save payslip to database
     */
    private function savePayslip(array $payslip): int
    {
        $sql = "INSERT INTO payroll_payslips (
                    staff_id, period_start, period_end,
                    ordinary_hours, ordinary_pay,
                    overtime_hours, overtime_pay,
                    night_shift_hours, night_shift_pay,
                    public_holiday_hours, public_holiday_pay,
                    vape_drops_bonus, google_reviews_bonus,
                    monthly_bonus, commission, acting_position_pay,
                    total_bonuses,
                    leave_deduction, advances_deduction, total_deductions,
                    gross_pay, net_pay,
                    timesheets_json, amendments_applied,
                    status, created_at
                ) VALUES (
                    :staff_id, :period_start, :period_end,
                    :ordinary_hours, :ordinary_pay,
                    :overtime_hours, :overtime_pay,
                    :night_shift_hours, :night_shift_pay,
                    :public_holiday_hours, :public_holiday_pay,
                    :vape_drops_bonus, :google_reviews_bonus,
                    :monthly_bonus, :commission, :acting_position_pay,
                    :total_bonuses,
                    :leave_deduction, :advances_deduction, :total_deductions,
                    :gross_pay, :net_pay,
                    :timesheets_json, :amendments_applied,
                    'calculated', NOW()
                )";

        $this->execute($sql, [
            'staff_id' => $payslip['staff_id'],
            'period_start' => $payslip['period_start'],
            'period_end' => $payslip['period_end'],
            'ordinary_hours' => $payslip['earnings']['ordinary_hours'],
            'ordinary_pay' => $payslip['earnings']['ordinary_pay'],
            'overtime_hours' => $payslip['earnings']['overtime_hours'],
            'overtime_pay' => $payslip['earnings']['overtime_pay'],
            'night_shift_hours' => $payslip['earnings']['night_shift_hours'],
            'night_shift_pay' => $payslip['earnings']['night_shift_pay'],
            'public_holiday_hours' => $payslip['public_holiday_pay']['total_hours'],
            'public_holiday_pay' => $payslip['public_holiday_pay']['time_and_half_pay'],
            'vape_drops_bonus' => $payslip['bonuses']['vape_drops'],
            'google_reviews_bonus' => $payslip['bonuses']['google_reviews'],
            'monthly_bonus' => $payslip['bonuses']['monthly_bonus'],
            'commission' => $payslip['bonuses']['commission'],
            'acting_position_pay' => $payslip['bonuses']['acting_position'],
            'total_bonuses' => $payslip['bonuses']['total'],
            'leave_deduction' => $payslip['deductions']['leave'],
            'advances_deduction' => $payslip['deductions']['advances'],
            'total_deductions' => $payslip['deductions']['total'],
            'gross_pay' => $payslip['gross_pay'],
            'net_pay' => $payslip['net_pay'],
            'timesheets_json' => json_encode($payslip['timesheets']),
            'amendments_applied' => $payslip['amendments_applied']
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Export payslips to ASB CSV format
     *
     * Format from your example:
     * Pay Ended DD/MM/YY,YYYY/MM/DD,FromAccount,Amount,PaymentCode,Particulars,DD-MMM-YYYY,ToAccount,PaymentCode,Reference,DD-MMM-YYYY,Name
     */
    public function exportToASBCSV(array $payslips, array $options = []): string
    {
        $fromAccount = $options['from_account'] ?? '1232490032052000'; // Your Ecigdis account
        $paymentCode = $options['payment_code'] ?? 'Salary/Wages';
        $particulars = $options['particulars'] ?? 'Pay Ended';
        $reference = $options['reference'] ?? 'Pay Ended';

        $csv = '';

        foreach ($payslips as $payslip) {
            $periodEnd = new DateTime($payslip['period_end']);
            $payEndedShort = $periodEnd->format('d/m/y'); // 28/10/25
            $payEndedISO = $periodEnd->format('Y/m/d'); // 2025/10/28
            $payEndedMedium = $periodEnd->format('d-M-Y'); // 27-Oct-2025 (payment date is day before)

            $row = [
                "Pay Ended {$payEndedShort}",
                $payEndedISO,
                $fromAccount,
                number_format($payslip['net_pay'], 2, '.', ''),
                $paymentCode,
                $particulars,
                $payEndedMedium,
                $payslip['bank_account'],
                $paymentCode,
                $reference,
                $payEndedMedium,
                $payslip['staff_name']
            ];

            $csv .= implode(',', $row) . "\n";
        }

        return $csv;
    }

    /**
     * Calculate payslips for all staff in period
     */
    public function calculatePayslipsForPeriod(string $periodStart, string $periodEnd, array $options = []): array
    {
        $startTime = $this->logger->startTimer('calculate_payslips_for_period');

        try {
            // Get all active staff
            $sql = "SELECT id FROM users WHERE staff_active = 1 AND id NOT IN (1, 18)"; // Exclude system users
            $staff = $this->query($sql);

            $payslips = [];
            $errors = [];

            foreach ($staff as $s) {
                try {
                    $payslip = $this->calculatePayslip($s['id'], $periodStart, $periodEnd, $options);
                    $payslips[] = $payslip;
                } catch (\Exception $e) {
                    $errors[] = [
                        'staff_id' => $s['id'],
                        'error' => $e->getMessage()
                    ];
                    $this->logger->error('Failed to calculate payslip for staff', [
                        'staff_id' => $s['id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->logger->logDuration($startTime, 'Payslips calculated for period', [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'total_staff' => count($staff),
                'successful' => count($payslips),
                'errors' => count($errors)
            ]);

            return [
                'payslips' => $payslips,
                'errors' => $errors,
                'summary' => [
                    'total_staff' => count($staff),
                    'successful' => count($payslips),
                    'failed' => count($errors),
                    'total_gross_pay' => array_sum(array_column($payslips, 'gross_pay')),
                    'total_net_pay' => array_sum(array_column($payslips, 'net_pay'))
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to calculate payslips for period', [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
