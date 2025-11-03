<?php
/**
 * PayrollDeputyService
 *
 * Thin wrapper for Deputy API (via assets/functions/deputy.php)
 * Adds rate-limit telemetry and logs all calls to payroll_activity_log.
 *
 * @author GitHub Copilot
 * @created 2025-11-02
 */

declare(strict_types=1);

use PayrollModule\Lib\PayrollLogger;

class PayrollDeputyService
{
    private PDO $db;
    private PayrollLogger $logger;

    private function __construct(PDO $db)
    {
        $this->db = $db;

        require_once __DIR__ . '/../lib/PayrollLogger.php';
        $this->logger = new PayrollLogger();
    $docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : dirname(__DIR__, 4);
        $deputyPath = rtrim((string) $docRoot, '/') . '/assets/functions/deputy.php';
        if (!is_file($deputyPath)) {
            throw new RuntimeException('Deputy library not found at ' . $deputyPath);
        }
        /** @psalm-suppress UnresolvableInclude */
        require_once $deputyPath;
    }

    public static function make(PDO $db): self
    {
        return new self($db);
    }

    /**
     * Fetch timesheets from Deputy
     *
     * @param string $start Start date YYYY-MM-DD
     * @param string $end End date YYYY-MM-DD
     * @return array
     */
    public function fetchTimesheets(string $start, string $end): array
    {
        $endpoint = 'Deputy::getTimesheets';
        $params = ['start' => $start, 'end' => $end];

        try {
            $result = Deputy::getTimesheets($params);
            $this->logInfo('deputy.api.call', 'Deputy API call successful', [
                'endpoint' => $endpoint,
                'start' => $start,
                'end' => $end,
                'result_count' => is_array($result) ? count($result) : 0
            ]);
            return $result;
        } catch (DeputyRateLimitException $e) {
            $retryAfter = $e->getRetryAfter() ?? null;
            $this->logWarning('deputy.api.rate_limit', 'Deputy API returned 429', [
                'endpoint' => $endpoint,
                'start' => $start,
                'end' => $end,
                'error' => 'rate_limit',
                'retry_after' => $retryAfter
            ]);
            $this->persistRateLimit('deputy', $endpoint, $retryAfter);
            throw $e;
        } catch (\Throwable $e) {
            $this->logError('deputy.api.error', 'Deputy API error', [
                'endpoint' => $endpoint,
                'start' => $start,
                'end' => $end,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Import timesheets from Deputy into deputy_timesheets table
     *
     * @param string $startDate Start date YYYY-MM-DD
     * @param string $endDate End date YYYY-MM-DD
     * @return array Stats: ['fetched' => int, 'validated' => int, 'inserted' => int, 'skipped' => int]
     */
    public function importTimesheets(string $startDate, string $endDate): array
    {
        $this->logInfo('deputy.import.start', 'Starting Deputy timesheet import', [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        try {
            // 1. Fetch from Deputy API
            $rawTimesheets = $this->fetchTimesheets($startDate, $endDate);
            $fetched = is_array($rawTimesheets) ? count($rawTimesheets) : 0;

            if ($fetched === 0) {
                $this->logInfo('deputy.import.complete', 'No timesheets to import', [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]);
                return ['fetched' => 0, 'validated' => 0, 'inserted' => 0, 'skipped' => 0];
            }

            // 2. Transform & validate
            $validated = $this->validateAndTransform($rawTimesheets);

            // 3. Filter duplicates
            $filtered = $this->filterDuplicates($validated);

            // 4. Bulk insert
            $inserted = $this->bulkInsert($filtered);

            $skipped = count($validated) - $inserted;

            $this->logInfo('deputy.import.complete', 'Deputy import completed', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'fetched' => $fetched,
                'validated' => count($validated),
                'inserted' => $inserted,
                'skipped' => $skipped
            ]);

            return [
                'fetched' => $fetched,
                'validated' => count($validated),
                'inserted' => $inserted,
                'skipped' => $skipped
            ];

        } catch (\Exception $e) {
            $this->logError('deputy.import.failed', 'Deputy import failed', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Validate and transform Deputy timesheets
     *
     * @param array $rawTimesheets Raw Deputy API response
     * @return array Validated timesheets ready for insert
     */
    private function validateAndTransform(array $rawTimesheets): array
    {
        $validated = [];

        foreach ($rawTimesheets as $timesheet) {
            // Skip if missing critical fields
            if (!isset($timesheet['Id'], $timesheet['Employee'], $timesheet['StartTime'], $timesheet['EndTime'])) {
                $this->logWarning('deputy.validation.skip', 'Skipping timesheet with missing fields', [
                    'timesheet_id' => $timesheet['Id'] ?? 'unknown'
                ]);
                continue;
            }

            // Convert UTC to Pacific/Auckland timezone
            $startTime = $this->convertTimezone($timesheet['StartTime']);
            $endTime = $this->convertTimezone($timesheet['EndTime']);

            // Calculate hours worked
            $hoursWorked = $this->calculateHours($startTime, $endTime);

            // Validate hours are positive
            if ($hoursWorked <= 0) {
                $this->logWarning('deputy.validation.skip', 'Skipping timesheet with invalid hours', [
                    'timesheet_id' => $timesheet['Id'],
                    'hours' => $hoursWorked
                ]);
                continue;
            }

            // Extract break time (in minutes)
            $breakMinutes = isset($timesheet['TotalBreak']) ? (int)$timesheet['TotalBreak'] : 0;

            // Transform to our schema
            $validated[] = [
                'deputy_id' => (int)$timesheet['Id'],
                'staff_id' => (int)$timesheet['Employee'],
                'outlet_id' => isset($timesheet['Location']) ? (int)$timesheet['Location'] : null,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'hours_worked' => $hoursWorked,
                'break_minutes' => $breakMinutes,
                'is_approved' => isset($timesheet['Approved']) ? (bool)$timesheet['Approved'] : false,
                'deputy_data' => json_encode($timesheet), // Store raw data for reference
                'imported_at' => date('Y-m-d H:i:s')
            ];
        }

        return $validated;
    }

    /**
     * Convert Deputy UTC timestamp to Pacific/Auckland
     *
     * @param string $utcTime UTC timestamp
     * @return string NZ time in Y-m-d H:i:s format
     */
    private function convertTimezone(string $utcTime): string
    {
        try {
            $dt = new DateTime($utcTime, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('Pacific/Auckland'));
            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            $this->logError('deputy.timezone.error', 'Timezone conversion failed', [
                'utc_time' => $utcTime,
                'error' => $e->getMessage()
            ]);
            // Fallback: return as-is
            return $utcTime;
        }
    }

    /**
     * Calculate hours between two timestamps
     *
     * @param string $start Start time Y-m-d H:i:s
     * @param string $end End time Y-m-d H:i:s
     * @return float Hours (decimal)
     */
    private function calculateHours(string $start, string $end): float
    {
        $startDt = new DateTime($start);
        $endDt = new DateTime($end);
        $diff = $endDt->getTimestamp() - $startDt->getTimestamp();
        return round($diff / 3600, 2); // Convert seconds to hours
    }

    /**
     * Filter out duplicate timesheets (already in DB)
     *
     * @param array $timesheets Validated timesheets
     * @return array Filtered timesheets (duplicates removed)
     */
    private function filterDuplicates(array $timesheets): array
    {
        if (empty($timesheets)) {
            return [];
        }

        // Get all deputy_ids from this batch
        $deputyIds = array_column($timesheets, 'deputy_id');

        if (empty($deputyIds)) {
            return $timesheets;
        }

        // Check which ones already exist
        $placeholders = implode(',', array_fill(0, count($deputyIds), '?'));
        $sql = "SELECT deputy_id FROM deputy_timesheets WHERE deputy_id IN ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($deputyIds);
        $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Filter out existing
        $filtered = array_filter($timesheets, function($ts) use ($existing) {
            return !in_array($ts['deputy_id'], $existing);
        });

        $duplicates = count($timesheets) - count($filtered);
        if ($duplicates > 0) {
            $this->logInfo('deputy.duplicates.filtered', "Filtered $duplicates duplicate timesheets", [
                'total' => count($timesheets),
                'duplicates' => $duplicates,
                'remaining' => count($filtered)
            ]);
        }

        return array_values($filtered); // Re-index array
    }

    /**
     * Bulk insert timesheets into database
     *
     * @param array $timesheets Validated, filtered timesheets
     * @return int Number of records inserted
     */
    private function bulkInsert(array $timesheets): int
    {
        if (empty($timesheets)) {
            return 0;
        }

        $inserted = 0;

        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO deputy_timesheets
                    (deputy_id, staff_id, outlet_id, start_time, end_time, hours_worked,
                     break_minutes, is_approved, deputy_data, imported_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);

            foreach ($timesheets as $ts) {
                $stmt->execute([
                    $ts['deputy_id'],
                    $ts['staff_id'],
                    $ts['outlet_id'],
                    $ts['start_time'],
                    $ts['end_time'],
                    $ts['hours_worked'],
                    $ts['break_minutes'],
                    $ts['is_approved'] ? 1 : 0,
                    $ts['deputy_data'],
                    $ts['imported_at']
                ]);
                $inserted++;
            }

            $this->db->commit();

            $this->logInfo('deputy.bulk_insert.success', 'Bulk insert completed', [
                'inserted' => $inserted
            ]);

        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logError('deputy.bulk_insert.failed', 'Bulk insert failed', [
                'error' => $e->getMessage(),
                'inserted_before_error' => $inserted
            ]);
            throw $e;
        }

        return $inserted;
    }

    /**
     * Check if staff member worked alone during a shift (for paid break calculation)
     *
     * @param int $staffId Staff member ID
     * @param int $outletId Outlet ID
     * @param string $startTime Shift start time Y-m-d H:i:s
     * @param string $endTime Shift end time Y-m-d H:i:s
     * @return bool True if worked alone (no overlapping shifts)
     */
    public function didStaffWorkAlone(int $staffId, int $outletId, string $startTime, string $endTime): bool
    {
        $sql = "SELECT COUNT(*) FROM deputy_timesheets
                WHERE outlet_id = ?
                AND staff_id != ?
                AND (
                    (start_time <= ? AND end_time >= ?) OR
                    (start_time <= ? AND end_time >= ?) OR
                    (start_time >= ? AND end_time <= ?)
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $outletId,
            $staffId,
            $startTime, $startTime,
            $endTime, $endTime,
            $startTime, $endTime
        ]);

        $overlappingShifts = (int)$stmt->fetchColumn();

        return $overlappingShifts === 0;
    }

    /**
     * Persist rate-limit event
     *
     * @param string $provider
     * @param string $endpoint
     * @param int|null $retryAfter
     */
    private function persistRateLimit(string $provider, string $endpoint, $retryAfter): void
    {
        $sql = "INSERT INTO payroll_rate_limits (provider, endpoint, retry_after, occurred_at) VALUES (?, ?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$provider, $endpoint, $retryAfter]);
    }

    private function logInfo(string $action, string $message, array $context = []): void
    {
        $this->logger->log(PayrollLogger::INFO, $message, array_merge($context, [
            'module' => 'payroll.deputy',
            'action' => $action,
        ]));
    }

    private function logWarning(string $action, string $message, array $context = []): void
    {
        $this->logger->log(PayrollLogger::WARNING, $message, array_merge($context, [
            'module' => 'payroll.deputy',
            'action' => $action,
        ]));
    }

    private function logError(string $action, string $message, array $context = []): void
    {
        $this->logger->log(PayrollLogger::ERROR, $message, array_merge($context, [
            'module' => 'payroll.deputy',
            'action' => $action,
        ]));
    }
}
