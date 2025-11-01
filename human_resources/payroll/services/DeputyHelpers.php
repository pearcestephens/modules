<?php
declare(strict_types=1);

/**
 * Deputy Integration Helpers
 *
 * Legacy function wrappers that delegate to DeputyApiClient.
 * Maintains backward compatibility while using real API client.
 *
 * @package CIS\HumanResources\Payroll
 * @version 1.0.0
 */

use CIS\HumanResources\Payroll\Services\DeputyApiClient;

/**
 * Get global Deputy API client instance (lazy loaded)
 *
 * @return DeputyApiClient
 */
function getDeputyApiClient(): DeputyApiClient
{
    static $client = null;

    if ($client === null) {
        $client = new DeputyApiClient();
    }

    return $client;
}

/**
 * Create a Deputy timesheet
 *
 * Legacy wrapper for DeputyApiClient::createTimesheet()
 *
 * @param int $employeeId Deputy employee ID
 * @param int $startTimestamp Start time (UNIX timestamp)
 * @param int $endTimestamp End time (UNIX timestamp)
 * @param int $breakMinutes Break duration in minutes
 * @param int $operationalUnitId Location ID
 * @param string $comment Comment
 * @return array Deputy API response
 */
function deputyCreateTimeSheet(
    int $employeeId,
    int $startTimestamp,
    int $endTimestamp,
    int $breakMinutes,
    int $operationalUnitId,
    string $comment
): array {
    return getDeputyApiClient()->createTimesheet(
        $employeeId,
        $startTimestamp,
        $endTimestamp,
        $breakMinutes,
        $operationalUnitId,
        $comment
    );
}

/**
 * Update a Deputy timesheet
 *
 * Legacy wrapper for DeputyApiClient::updateTimesheet()
 *
 * @param int $timesheetId Timesheet ID
 * @param int $startTimestamp New start time
 * @param int $endTimestamp New end time
 * @param int $breakMinutes Break duration
 * @param int $operationalUnitId Location ID
 * @param string $comment Comment
 * @return array Deputy API response
 */
function updateDeputyTimeSheet(
    int $timesheetId,
    int $startTimestamp,
    int $endTimestamp,
    int $breakMinutes,
    int $operationalUnitId,
    string $comment
): array {
    return getDeputyApiClient()->updateTimesheet(
        $timesheetId,
        $startTimestamp,
        $endTimestamp,
        $breakMinutes,
        $operationalUnitId,
        $comment
    );
}

/**
 * Approve a Deputy timesheet
 *
 * Legacy wrapper for DeputyApiClient::approveTimesheet()
 *
 * @param int $timesheetId Timesheet ID to approve
 * @return array Deputy API response
 */
function deputyApproveTimeSheet(int $timesheetId): array
{
    return getDeputyApiClient()->approveTimesheet($timesheetId);
}

/**
 * Fetch Deputy timesheets for a specific date range
 *
 * Legacy wrapper for DeputyApiClient::fetchTimesheetsForDate()
 * Note: This currently only supports single-day queries (start == end)
 *
 * @param int $employeeId Deputy employee ID
 * @param string $startDate Start date (Y-m-d format)
 * @param string $endDate End date (Y-m-d format, typically same as start)
 * @return array Array of timesheets
 */
function getDeputyTimeSheetsSpecificDay(int $employeeId, string $startDate, string $endDate): array
{
    // If multi-day range requested, fetch each day and combine
    if ($startDate !== $endDate) {
        $results = [];
        $current = strtotime($startDate);
        $end = strtotime($endDate);

        while ($current <= $end) {
            $date = date('Y-m-d', $current);
            $dayResults = getDeputyApiClient()->fetchTimesheetsForDate($employeeId, $date);
            $results = array_merge($results, $dayResults);
            $current += 86400; // Add 1 day
        }

        return $results;
    }

    // Single day
    return getDeputyApiClient()->fetchTimesheetsForDate($employeeId, $startDate);
}

/**
 * Calculate break minutes based on hours worked (NZ employment law)
 *
 * Break rules:
 * - < 4 hours: No break required
 * - 4-6 hours: 15 minute paid break
 * - 6-8 hours: 30 minutes (15 paid + 15 unpaid)
 * - > 8 hours: 45 minutes (30 paid + 15 unpaid)
 *
 * @param float $hoursWorked Total hours worked
 * @return int Break minutes
 */
function calculateDeputyHourBreaksInMinutesBasedOnHoursWorked(float $hoursWorked): int
{
    if ($hoursWorked < 4.0) {
        return 0;
    }

    if ($hoursWorked < 6.0) {
        return 15; // One 15min paid break
    }

    if ($hoursWorked < 8.0) {
        return 30; // One 30min break (15 paid + 15 unpaid)
    }

    // 8+ hours
    return 45; // One 45min break (30 paid + 15 unpaid)
}
