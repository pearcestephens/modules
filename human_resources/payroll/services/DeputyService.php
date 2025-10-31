<?php
declare(strict_types=1);

namespace CIS\HumanResources\Payroll\Services;

use PDO;
use stdClass;
use Throwable;

/**
 * DeputyService
 *
 * Handles all Deputy API operations including:
 * - Timesheet parsing and matching
 * - Timesheet creation and updates
 * - Amendment synchronization
 * - Multi-shift handling
 *
 * Extracted from payroll-process.php (Lines 243-636)
 *
 * @package CIS\HumanResources\Payroll\Services
 * @version 2.0.0
 * @author CIS Development Team
 */
class DeputyService
{
    /**
     * @var PDO Database connection
     */
    private PDO $pdo;

    /**
     * @var int Maximum age in days for Deputy timesheets (older = slower API)
     */
    private const MAX_TIMESHEET_AGE_DAYS = 21;

    /**
     * @var int API timeout in seconds
     */
    private const API_TIMEOUT_SECONDS = 45;

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection for outlet lookups
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Parse datetime-local input value to UNIX timestamp and MySQL datetime string
     *
     * Converts HTML5 datetime-local format (YYYY-MM-DDTHH:MM) to:
     * - UNIX timestamp (int)
     * - MySQL datetime string (Y-m-d H:i:s)
     *
     * @param string|null $value Datetime-local value or null
     * @return array [timestamp (int), mysql_datetime (string|null)]
     *
     * @example
     * [$ts, $str] = $service->parseDateTime('2025-10-28T09:30');
     * // $ts = 1729849800, $str = '2025-10-28 09:30:00'
     */
    public function parseDateTime(?string $value): array
    {
        $value = trim((string)$value);

        if ($value === '') {
            return [0, null];
        }

        // Replace T separator with space for strtotime
        $ts = strtotime(str_replace('T', ' ', $value));

        return [
            $ts ?: 0,
            $ts ? date('Y-m-d H:i:s', $ts) : null
        ];
    }

    /**
     * Pick the best Deputy timesheet based on maximum overlap with target window
     *
     * Finds the timesheet with the most time overlap with the specified window.
     * Used when multiple timesheets exist and we need to pick which one to update.
     *
     * @param array $rows Array of Deputy timesheet rows
     * @param int $startTs Target window start timestamp
     * @param int $endTs Target window end timestamp
     * @return array|null Best matching timesheet row, or null if no valid overlap
     *
     * @example
     * $timesheets = [
     *   ['Id' => 1, 'StartTime' => 1000, 'EndTime' => 2000],
     *   ['Id' => 2, 'StartTime' => 1500, 'EndTime' => 2500]
     * ];
     * $best = $service->pickBestTimesheetRow($timesheets, 1400, 2200);
     * // Returns row with Id=2 (more overlap)
     */
    public function pickBestTimesheetRow(array $rows, int $startTs, int $endTs): ?array
    {
        $best = null;
        $bestOverlap = -1;

        foreach ($rows as $r) {
            $s = (int)($r['StartTime'] ?? 0);
            $e = (int)($r['EndTime'] ?? 0);

            // Skip invalid timesheets
            if ($s <= 0 || $e <= 0 || $e <= $s) {
                continue;
            }

            // Calculate overlap: min(end times) - max(start times)
            $overlap = max(0, min($endTs, $e) - max($startTs, $s));

            if ($overlap > $bestOverlap) {
                $bestOverlap = $overlap;
                $best = $r;
            }
        }

        return $best;
    }

    /**
     * Update an existing Deputy timesheet with new time window
     *
     * Handles two scenarios:
     * 1. APPROVED timesheets: Creates NEW timesheet (Deputy API doesn't allow updates)
     * 2. DRAFT timesheets: Updates existing timesheet directly
     *
     * Break time is calculated automatically based on hours worked unless overridden.
     *
     * @param array $row Deputy timesheet row (must have Id and OperationalUnitObject)
     * @param int $startTs New start timestamp
     * @param int $endTs New end timestamp
     * @param int|null $overrideBreakMin Override break minutes (null = auto-calculate)
     * @return array Result with keys: updated (bool), timesheetId, ouId, breakMin, error
     *
     * @throws None - All errors returned in result array
     */
    public function updateTimesheet(array $row, int $startTs, int $endTs, ?int $overrideBreakMin = null): array
    {
        $tsId = (int)($row['Id'] ?? 0);
        $ouId = (int)($row['OperationalUnitObject']['Id'] ?? 0);

        if ($tsId <= 0 || $ouId <= 0) {
            return [
                'updated' => false,
                'error' => 'Deputy row missing Id or OperationalUnit ID'
            ];
        }

        // Calculate hours and break time
        $hours = max(0, ($endTs - $startTs) / 3600);
        $breakMin = $overrideBreakMin !== null
            ? max(0, (int)$overrideBreakMin)
            : (int)calculateDeputyHourBreaksInMinutesBasedOnHoursWorked($hours);

        // Check if timesheet is approved (locked)
        $wasApproved = !empty($row['TimeApproved']);

        if ($wasApproved) {
            // APPROVED timesheets cannot be updated - must create new one
            error_log("DeputyService: Timesheet $tsId is APPROVED (locked). Creating NEW timesheet instead.");

            try {
                // Create replacement timesheet
                $createResult = deputyCreateTimeSheet(
                    (int)($row['Employee'] ?? 0),
                    $startTs,
                    $endTs,
                    $breakMin,
                    $ouId,
                    'Replaced approved timesheet via CIS amendment (original ID: ' . $tsId . ')'
                );

                if (!empty($createResult['Id'])) {
                    $newTimesheetId = (int)$createResult['Id'];
                    error_log("DeputyService: Created NEW timesheet $newTimesheetId to replace approved $tsId");

                    // Approve the new one to match original state
                    deputyApproveTimeSheet($newTimesheetId);
                    error_log("DeputyService: Approved new timesheet $newTimesheetId");

                    return [
                        'updated' => true,
                        'timesheetId' => $newTimesheetId,
                        'ouId' => $ouId,
                        'breakMin' => $breakMin,
                        'wasApproved' => true,
                        'replacedApprovedTimesheet' => $tsId
                    ];
                } else {
                    return [
                        'updated' => false,
                        'error' => 'Failed to create replacement timesheet for approved timesheet'
                    ];
                }
            } catch (Throwable $e) {
                error_log("DeputyService ERROR: Failed to create replacement - " . $e->getMessage());
                return [
                    'updated' => false,
                    'error' => 'Cannot update approved timesheet: ' . $e->getMessage()
                ];
            }
        }

        // DRAFT timesheet - can be updated directly
        error_log("DeputyService: Timesheet $tsId is NOT approved (draft). Updating directly.");

        try {
            updateDeputyTimeSheet(
                $tsId,
                $startTs,
                $endTs,
                $breakMin,
                $ouId,
                'Auto via CIS: amendment commit'
            );

            error_log("DeputyService: Successfully updated draft timesheet $tsId");

            // Leave draft timesheets as draft (don't auto-approve)
            return [
                'updated' => true,
                'timesheetId' => $tsId,
                'ouId' => $ouId,
                'breakMin' => $breakMin,
                'wasApproved' => false
            ];

        } catch (Throwable $e) {
            error_log("DeputyService ERROR: Failed to update draft timesheet - " . $e->getMessage());
            return [
                'updated' => false,
                'error' => 'Failed to update timesheet: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create a new Deputy timesheet
     *
     * Creates a timesheet in DRAFT state. Caller should approve if needed.
     *
     * @param int $deputyEmployeeId Deputy employee ID
     * @param int $startTs Start timestamp
     * @param int $endTs End timestamp
     * @param int $breakMin Break minutes
     * @param int $operationalUnitId Deputy operational unit (location) ID
     * @param string $comment Comment for the timesheet
     * @return array Result with keys: created (bool), timesheetId, error
     */
    public function createTimesheet(
        int $deputyEmployeeId,
        int $startTs,
        int $endTs,
        int $breakMin,
        int $operationalUnitId,
        string $comment
    ): array {
        try {
            $result = deputyCreateTimeSheet(
                $deputyEmployeeId,
                $startTs,
                $endTs,
                $breakMin,
                $operationalUnitId,
                $comment
            );

            if (!empty($result['Id'])) {
                $timesheetId = (int)$result['Id'];
                error_log("DeputyService: Created new timesheet ID=$timesheetId");

                return [
                    'created' => true,
                    'timesheetId' => $timesheetId,
                    'ouId' => $operationalUnitId,
                    'breakMin' => $breakMin
                ];
            } else {
                return [
                    'created' => false,
                    'error' => 'Deputy API returned no timesheet ID'
                ];
            }
        } catch (Throwable $e) {
            error_log("DeputyService ERROR: Failed to create timesheet - " . $e->getMessage());
            return [
                'created' => false,
                'error' => 'Failed to create timesheet: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Synchronize amendment to Deputy with comprehensive logic
     *
     * Handles multiple scenarios:
     * 1. Multi-shift intent: Update specific selected timesheets
     * 2. No existing timesheets: Create new timesheet (requires outlet mapping)
     * 3. Multiple covered timesheets: Merge into one
     * 4. Single overlapping timesheet: Update it
     *
     * This is the main entry point for amendment→Deputy sync.
     *
     * @param stdClass $amendment Amendment object (staff_id, vend_outlet_id, actual_start_time, actual_end_time)
     * @param int $startTs Amendment start timestamp
     * @param int $endTs Amendment end timestamp
     * @param array|null $multiShiftIntent Multi-shift selection data from modal
     * @return array Result with keys: synced (bool), details (array), failure_reason (string|null)
     */
    public function syncAmendmentToDeputy(
        stdClass $amendment,
        int $startTs,
        int $endTs,
        ?array $multiShiftIntent = null
    ): array {
        $result = [
            'synced' => false,
            'details' => [],
            'failure_reason' => null
        ];

        // Log sync attempt
        error_log(sprintf(
            "DeputyService: Sync amendment %d - staff=%d, window=%s to %s",
            $amendment->id ?? 0,
            $amendment->staff_id ?? 0,
            date('Y-m-d H:i', $startTs),
            date('H:i', $endTs)
        ));

        // Get employee Deputy ID
        $user = getCISUserObjectById((int)$amendment->staff_id);
        $deputyId = (int)($user->deputy_id ?? 0);

        error_log("DeputyService: Employee deputy_id=$deputyId");

        if ($deputyId <= 0) {
            $result['failure_reason'] = "Employee has no Deputy ID linked";
            error_log("DeputyService FAIL: " . $result['failure_reason']);
            return $result;
        }

        // SCENARIO 1: Multi-shift intent provided
        if ($this->hasMultiShiftIntent($multiShiftIntent)) {
            return $this->syncMultiShift($deputyId, $startTs, $endTs, $multiShiftIntent, $result);
        }

        // SCENARIO 2-4: Single day operations
        $day = date('Y-m-d', $startTs);

        // Check if amendment is too old (Deputy API slow for old data)
        $this->checkTimesheetAge($startTs);

        // Fetch existing timesheets for the day
        $rows = $this->fetchTimesheetsForDay($deputyId, $day, $result);
        if ($rows === null) {
            return $result; // Error already set in $result
        }

        // SCENARIO 2: No existing timesheets - CREATE new one
        if (empty($rows)) {
            return $this->createTimesheetFromAmendment($amendment, $deputyId, $startTs, $endTs, $result);
        }

        // Log all found timesheets for debugging
        $this->logFoundTimesheets($rows);

        // SCENARIO 3: Amendment covers 2+ timesheets - MERGE
        $coveredTimesheets = $this->findCoveredTimesheets($rows, $startTs, $endTs);
        if (count($coveredTimesheets) >= 2) {
            return $this->mergeTimesheets($deputyId, $startTs, $endTs, $coveredTimesheets, $result);
        }

        // SCENARIO 4: Update best matching timesheet
        return $this->updateBestMatchingTimesheet($rows, $startTs, $endTs, $result);
    }

    /**
     * Check if multi-shift intent is provided and has selected shifts
     *
     * @param array|null $multiShiftIntent Multi-shift data
     * @return bool
     */
    private function hasMultiShiftIntent(?array $multiShiftIntent): bool
    {
        if (!is_array($multiShiftIntent) || empty($multiShiftIntent['shifts'])) {
            return false;
        }

        $picked = array_filter(
            $multiShiftIntent['shifts'],
            fn($x) => !empty($x['picked']) && !empty($x['id'])
        );

        return !empty($picked);
    }

    /**
     * Sync multi-shift intent (update specific selected timesheets)
     *
     * @param int $deputyId Deputy employee ID
     * @param int $startTs Amendment start
     * @param int $endTs Amendment end
     * @param array $multiShiftIntent Multi-shift selection
     * @param array $result Result array to populate
     * @return array Updated result
     */
    private function syncMultiShift(
        int $deputyId,
        int $startTs,
        int $endTs,
        array $multiShiftIntent,
        array $result
    ): array {
        $picked = array_filter(
            $multiShiftIntent['shifts'],
            fn($x) => !empty($x['picked']) && !empty($x['id'])
        );

        $day = date('Y-m-d', $startTs);
        $rows = getDeputyTimeSheetsSpecificDay($deputyId, $day, $day);
        $rowsById = [];

        foreach (($rows ?? []) as $r) {
            $rowsById[(int)$r['Id']] = $r;
        }

        foreach ($picked as $p) {
            $rid = (int)$p['id'];
            if (!isset($rowsById[$rid])) {
                continue;
            }

            $r = $rowsById[$rid];

            // Allow per-row overrides if modal provided new times
            [$ps] = $this->parseDateTime($p['start'] ?? '');
            [$pe] = $this->parseDateTime($p['end'] ?? '');
            $uStart = $ps ?: $startTs;
            $uEnd = $pe ?: $endTs;

            if ($uEnd <= $uStart) {
                continue;
            }

            $res = $this->updateTimesheet($r, $uStart, $uEnd, null);
            $result['details'][] = $res;
        }

        if (!empty($result['details'])) {
            $result['synced'] = true;
        } else {
            $result['failure_reason'] = "No valid multi-shift timesheets found to update";
        }

        return $result;
    }

    /**
     * Check timesheet age and warn if too old
     *
     * @param int $startTs Start timestamp
     * @return void
     */
    private function checkTimesheetAge(int $startTs): void
    {
        $daysAgo = (time() - $startTs) / 86400;

        if ($daysAgo > self::MAX_TIMESHEET_AGE_DAYS) {
            error_log(sprintf(
                "DeputyService WARNING: Amendment is %.1f days old - Deputy API may be slow",
                $daysAgo
            ));
        }
    }

    /**
     * Fetch timesheets for a specific day with timeout protection
     *
     * @param int $deputyId Deputy employee ID
     * @param string $day Date string (Y-m-d)
     * @param array $result Result array to update on error
     * @return array|null Array of timesheets or null on error
     */
    private function fetchTimesheetsForDay(int $deputyId, string $day, array &$result): ?array
    {
        error_log("DeputyService: Fetching timesheets for deputy_id=$deputyId on day=$day");

        try {
            set_time_limit(self::API_TIMEOUT_SECONDS);
            $rows = getDeputyTimeSheetsSpecificDay($deputyId, $day, $day);

            error_log("DeputyService: Found " . count($rows ?? []) . " timesheets for $day");

            return $rows;
        } catch (Throwable $e) {
            $result['failure_reason'] = "Deputy API timeout or error: " . $e->getMessage();
            error_log("DeputyService FAIL: " . $result['failure_reason']);
            return null;
        }
    }

    /**
     * Create new timesheet from amendment when no existing timesheet found
     *
     * @param stdClass $amendment Amendment object
     * @param int $deputyId Deputy employee ID
     * @param int $startTs Start timestamp
     * @param int $endTs End timestamp
     * @param array $result Result array to populate
     * @return array Updated result
     */
    private function createTimesheetFromAmendment(
        stdClass $amendment,
        int $deputyId,
        int $startTs,
        int $endTs,
        array $result
    ): array {
        error_log("DeputyService: No existing timesheet found, creating new one");

        // Get outlet Deputy location ID
        $outletId = $amendment->vend_outlet_id ?? null;
        if (!$outletId) {
            $result['failure_reason'] = "Cannot create timesheet: Amendment has no outlet";
            error_log("DeputyService FAIL: " . $result['failure_reason']);
            return $result;
        }

        $stmt = $this->pdo->prepare("SELECT deputy_location_id FROM vend_outlets WHERE id = ?");
        $stmt->execute([$outletId]);
        $outletRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$outletRow || empty($outletRow['deputy_location_id'])) {
            $result['failure_reason'] = "Cannot create timesheet: Outlet $outletId has no deputy_location_id";
            error_log("DeputyService FAIL: " . $result['failure_reason']);
            return $result;
        }

        $deputyLocationId = (int)$outletRow['deputy_location_id'];

        // Calculate break time
        $hours = max(0, ($endTs - $startTs) / 3600);
        $breakMin = (int)calculateDeputyHourBreaksInMinutesBasedOnHoursWorked($hours);

        $createResult = $this->createTimesheet(
            $deputyId,
            $startTs,
            $endTs,
            $breakMin,
            $deputyLocationId,
            'Created via CIS: amendment commit (no existing timesheet)'
        );

        if (!empty($createResult['created'])) {
            $newTimesheetId = $createResult['timesheetId'];

            // Approve newly created timesheet
            deputyApproveTimeSheet($newTimesheetId);
            error_log("DeputyService: Approved newly created timesheet $newTimesheetId");

            $result['synced'] = true;
            $result['details'][] = $createResult;
        } else {
            $result['failure_reason'] = $createResult['error'] ?? 'Unknown error creating timesheet';
        }

        return $result;
    }

    /**
     * Log all found timesheets for debugging
     *
     * @param array $rows Timesheet rows
     * @return void
     */
    private function logFoundTimesheets(array $rows): void
    {
        foreach ($rows as $i => $row) {
            error_log(sprintf(
                "DeputyService: Timesheet #%d - ID=%s, Start=%s, End=%s",
                $i,
                $row['Id'] ?? 'unknown',
                $row['StartTime'] ?? 'unknown',
                $row['EndTime'] ?? 'unknown'
            ));
        }
    }

    /**
     * Find timesheets completely covered by amendment window
     *
     * @param array $rows All timesheets for the day
     * @param int $startTs Amendment start
     * @param int $endTs Amendment end
     * @return array Timesheets completely covered
     */
    private function findCoveredTimesheets(array $rows, int $startTs, int $endTs): array
    {
        $covered = [];

        foreach ($rows as $row) {
            $tsStart = (int)($row['StartTime'] ?? 0);
            $tsEnd = (int)($row['EndTime'] ?? 0);

            if ($tsStart <= 0 || $tsEnd <= 0) {
                continue;
            }

            // Timesheet is covered if: amendment starts before/at timesheet start
            // AND amendment ends after/at timesheet end
            if ($startTs <= $tsStart && $endTs >= $tsEnd) {
                $covered[] = $row;
            }
        }

        return $covered;
    }

    /**
     * Merge multiple covered timesheets into one
     *
     * @param int $deputyId Deputy employee ID
     * @param int $startTs Merged start
     * @param int $endTs Merged end
     * @param array $coveredTimesheets Timesheets to merge
     * @param array $result Result array to populate
     * @return array Updated result
     */
    private function mergeTimesheets(
        int $deputyId,
        int $startTs,
        int $endTs,
        array $coveredTimesheets,
        array $result
    ): array {
        error_log("DeputyService: Amendment covers " . count($coveredTimesheets) . " timesheets - MERGING");

        // Get operational unit from first timesheet
        $firstTs = $coveredTimesheets[0];
        $ouId = (int)($firstTs['OperationalUnitObject']['Id'] ?? 0);

        if ($ouId <= 0) {
            $result['failure_reason'] = "Cannot merge timesheets: Missing OperationalUnit ID";
            error_log("DeputyService FAIL: " . $result['failure_reason']);
            return $result;
        }

        // Track old IDs and approval status
        $replacedIds = [];
        $hasApproved = false;

        foreach ($coveredTimesheets as $ts) {
            $tsId = (int)($ts['Id'] ?? 0);
            if ($tsId > 0) {
                $replacedIds[] = $tsId;
                if (!empty($ts['TimeApproved'])) {
                    $hasApproved = true;
                }
            }
        }

        // Calculate break for merged timesheet
        $hours = max(0, ($endTs - $startTs) / 3600);
        $breakMin = (int)calculateDeputyHourBreaksInMinutesBasedOnHoursWorked($hours);

        // Create merged timesheet
        $createResult = $this->createTimesheet(
            $deputyId,
            $startTs,
            $endTs,
            $breakMin,
            $ouId,
            'Merged via CIS: Combined ' . count($coveredTimesheets) . ' shifts. Old IDs: ' . implode(',', $replacedIds)
        );

        if (!empty($createResult['created'])) {
            $newTimesheetId = $createResult['timesheetId'];

            // Approve if original was approved
            if ($hasApproved) {
                deputyApproveTimeSheet($newTimesheetId);
                error_log("DeputyService: Approved merged timesheet $newTimesheetId");
            } else {
                error_log("DeputyService: Leaving merged timesheet $newTimesheetId as DRAFT");
            }

            $result['synced'] = true;
            $result['details'][] = array_merge($createResult, [
                'merged' => true,
                'replacedTimesheets' => $replacedIds,
                'note' => 'Old timesheets not auto-deleted - clean up in Deputy UI if needed'
            ]);
        } else {
            $result['failure_reason'] = $createResult['error'] ?? 'Failed to create merged timesheet';
        }

        return $result;
    }

    /**
     * Update the best matching timesheet (normal case)
     *
     * @param array $rows All timesheets for the day
     * @param int $startTs Amendment start
     * @param int $endTs Amendment end
     * @param array $result Result array to populate
     * @return array Updated result
     */
    private function updateBestMatchingTimesheet(
        array $rows,
        int $startTs,
        int $endTs,
        array $result
    ): array {
        $best = $this->pickBestTimesheetRow($rows, $startTs, $endTs);

        if (!$best) {
            $result['failure_reason'] = sprintf(
                "No overlapping timesheet for window %s-%s (found %d timesheets)",
                date('H:i', $startTs),
                date('H:i', $endTs),
                count($rows)
            );
            error_log("DeputyService FAIL: " . $result['failure_reason']);
            return $result;
        }

        error_log(sprintf(
            "DeputyService: Selected best match - ID=%s, Start=%s, End=%s",
            $best['Id'] ?? 'unknown',
            $best['StartTime'] ?? 'unknown',
            $best['EndTime'] ?? 'unknown'
        ));

        $updateResult = $this->updateTimesheet($best, $startTs, $endTs, null);

        if (!empty($updateResult['updated'])) {
            $result['synced'] = true;
            $result['details'][] = $updateResult;
            error_log("DeputyService SUCCESS: Timesheet updated");
        } else {
            $result['failure_reason'] = $updateResult['error'] ?? 'Update failed for unknown reason';
            error_log("DeputyService FAIL: " . $result['failure_reason']);
        }

        return $result;
    }
}
