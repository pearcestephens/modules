# Deputy Payroll Algorithm - Complete Documentation

**Last Updated:** October 29, 2025
**Source Files:**
- `/assets/functions/deputy.php` (Break calculation & API functions)
- `/payroll-process.php` (Main processing logic)
- `/modules/human_resources/payroll/services/PayslipCalculationEngine.php` (New implementation)

---

## 📋 Table of Contents
1. [Break Calculation Rules](#break-calculation-rules)
2. [Working Alone vs With Others](#working-alone-vs-with-others)
3. [Multiple Shifts Logic](#multiple-shifts-logic)
4. [Break Thresholds](#break-thresholds)
5. [Paid Break Policy](#paid-break-policy)
6. [Timesheet Update Logic](#timesheet-update-logic)
7. [Special Cases & Edge Handling](#special-cases--edge-handling)
8. [Implementation Details](#implementation-details)

---

## 🔢 Break Calculation Rules

### Core Algorithm
```php
function calculateDeputyHourBreaksInMinutesBasedOnHoursWorked(float $hoursWorked): int {
    if ($hoursWorked < getCurrentThresholdForFirstBreak()) return 0;    // < 5 hours = 0 min
    if ($hoursWorked < getCurrentThresholdForSecondBreak()) return 30;  // 5-12 hours = 30 min
    return 60;                                                          // 12+ hours = 60 min
}
```

### Break Thresholds
- **First Break Threshold:** `5.0 hours`
- **Second Break Threshold:** `12.0 hours`

**Break Schedule:**
| Hours Worked | Break Time |
|--------------|------------|
| < 5 hours    | 0 minutes  |
| 5-12 hours   | 30 minutes |
| 12+ hours    | 60 minutes |

---

## 👤 Working Alone vs With Others

### The Critical Rule
**This is the most important part of the Deputy algorithm:**

```
IF staff member worked ALONE during their shift:
    → They get PAID for their breaks (no break deduction)

IF staff member worked WITH OTHERS during their shift:
    → Auto-deduct break time based on hours worked
```

### How "Alone" is Determined

A staff member is considered "working alone" if:
1. Their shift has **NO overlapping shifts** from other staff
2. At the **same outlet/location**
3. During **any part of their shift time**

**Algorithm:**
```php
function isWorkingAlone($staffId, $startTime, $endTime, $outlet): bool {
    // Check all other timesheets at same outlet
    foreach ($allTimesheetsAtOutlet as $otherTimesheet) {
        if ($otherTimesheet->staffId === $staffId) {
            continue; // Skip self
        }

        // Check for time overlap
        if ($otherTimesheet->startTime <= $endTime &&
            $otherTimesheet->endTime >= $startTime) {
            return false; // NOT alone - someone else was there
        }
    }

    return true; // Worked alone
}
```

### Time Overlap Detection

Three scenarios for overlap:
```php
// Overlap occurs if ANY of these conditions are true:
1. (otherStart < myEnd) AND (otherEnd > myStart)     // Partial overlap
2. (otherStart >= myStart) AND (otherStart < myEnd)  // Other started during my shift
3. (otherEnd > myStart) AND (otherEnd <= myEnd)      // Other ended during my shift
```

---

## 🔄 Multiple Shifts Logic

### Scenario: Staff Works Multiple Shifts Same Day

**Rule:** Only the **longest shift** of the day gets the break deduction.

**Example:**
```
Staff works:
- Shift 1: 9am-1pm (4 hours)
- Shift 2: 5pm-10pm (5 hours)

Total: 9 hours worked
→ Only Shift 2 (longest) gets 30 min break deducted
→ Shift 1 keeps full 4 hours paid
```

**Algorithm:**
```php
function determineIfMultipleShiftsAndAssignBreak(array $timesheets): array {
    // Group by staff member
    foreach ($staffShifts as $staffId => $shifts) {
        $totalHours = array_sum(array_column($shifts, 'hours'));
        $maxShiftHours = max(array_column($shifts, 'hours'));

        // If total qualifies for break AND multiple shifts
        if ($totalHours >= 5.0 && count($shifts) > 1) {
            // Only apply break to LONGEST shift
            foreach ($shifts as $shift) {
                if ($shift->hours === $maxShiftHours) {
                    $shift->needsBreakDeducted = true;
                } else {
                    $shift->needsBreakDeducted = false;
                }
            }
        }
    }
}
```

---

## 💰 Paid Break Policy

### Locations with Paid Breaks
Certain outlets **always** pay breaks (no deduction):
```php
$locationsAcceptPaidBreaks = [18, 13, 15]; // Outlet IDs
```

### Staff with Paid Breaks
Certain staff members **always** get paid breaks:
```php
$staffAcceptPaidBreaks = [483, 492, 485, 459, 103]; // Staff user IDs
```

### Logic Flow
```
1. Calculate break normally based on hours
2. Check if staff worked alone → If yes, paid break (0 min deduction)
3. Check if outlet is in paid break list → If yes, paid break (0 min deduction)
4. Check if staff is in paid break list → If yes, paid break (0 min deduction)
5. Otherwise → Apply calculated break deduction
```

**Priority Order:**
1. Already recorded break time (honor existing)
2. Paid break policy (location or staff)
3. Worked alone rule
4. Standard calculation

---

## 🔧 Timesheet Update Logic

### Approved vs Draft Timesheets

**Critical Deputy Limitation:** Approved timesheets **CANNOT** be updated via API.

**Solution:**
```php
if (timesheet is APPROVED) {
    // Create NEW timesheet with updated times
    $newTimesheet = deputyCreateTimeSheet(...);

    // Approve the new one
    deputyApproveTimeSheet($newTimesheet->id);

    // Note: Old timesheet remains (manual cleanup needed)
} else {
    // Timesheet is DRAFT - can update directly
    updateDeputyTimeSheet(...);
}
```

### Merging Split Shifts

When an amendment **completely covers** multiple separate timesheets:

```php
// Example: Amendment covers 9am-5pm, but Deputy has:
// Timesheet 1: 9am-1pm
// Timesheet 2: 2pm-5pm

// Solution: CREATE ONE MERGED TIMESHEET
$mergedTimesheet = deputyCreateTimeSheet(
    $staffId,
    $amendmentStart,  // 9am
    $amendmentEnd,    // 5pm
    $breakMinutes,    // Calculated for full 8 hours
    $outletId,
    'Merged via CIS: Combined 2 split shifts'
);

// Old timesheets remain in Deputy (no auto-delete API)
```

---

## ⚙️ Special Cases & Edge Handling

### 1. Store Opening Hours Enforcement

Some staff timesheets are **clamped** to store hours:
```php
$staffIgnoreStartFinish = [456, 469]; // Staff IDs that bypass this rule

if (!in_array($staffId, $staffIgnoreStartFinish)) {
    // Clamp start time to store opening
    if ($shiftStart < $storeOpen) {
        $shiftStart = $storeOpen;
    }

    // Clamp end time to store closing
    if ($shiftEnd > $storeClose) {
        $shiftEnd = $storeClose;
    }
}
```

### 2. Existing Break Time Priority

If Deputy already has a break recorded, **honor it**:
```php
if ($timesheet->totalBreakTime > 0) {
    $breakMinutes = $timesheet->totalBreakTime * 60; // Convert hours to minutes
    // DON'T recalculate
}
```

### 3. Overnight Shifts

Shifts crossing midnight are handled:
```php
if ($endTime < $startTime) {
    $endTime += 86400; // Add 24 hours (1 day in seconds)
}

$hoursWorked = ($endTime - $startTime) / 3600;
```

### 4. Discarding Unsubmitted Rosters

After processing timesheets, clean up Deputy roster entries:
```php
// Find all unsubmitted roster entries in the same time window
$unsubmittedRosters = getUnsubmittedRosteredTimesheets($start, $end);

// Discard them (they're replaced by actual timesheets)
deputyDiscardRoster($rosterIds);
```

---

## 💻 Implementation Details

### Database Structure

**deputy_timesheets table:**
```sql
CREATE TABLE deputy_timesheets (
    id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    deputy_timesheet_id  BIGINT UNSIGNED NOT NULL UNIQUE,
    staff_id             INT UNSIGNED NOT NULL,
    outlet_id            INT NULL,
    date                 DATE NOT NULL,
    start_time           TIME NOT NULL,
    end_time             TIME NOT NULL,
    break_minutes        INT NOT NULL DEFAULT 0,
    total_hours          DECIMAL(10,2) NOT NULL,
    approved             BOOLEAN NOT NULL DEFAULT 0,
    notes                TEXT NULL,
    last_synced_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_staff_date (staff_id, date),
    INDEX idx_outlet_date (outlet_id, date),
    FOREIGN KEY (staff_id) REFERENCES users(id)
) ENGINE=InnoDB;
```

### New PayslipCalculationEngine Implementation

```php
class PayslipCalculationEngine {
    /**
     * Calculate earnings with Deputy break logic
     */
    public function calculateEarnings(array $timesheets, int $staffId): array {
        foreach ($timesheets as $timesheet) {
            $date = $timesheet['date'];
            $startTime = $timesheet['start_time'];
            $endTime = $timesheet['end_time'];
            $breakHours = (float)($timesheet['break_hours'] ?? 0.0);
            $hourlyRate = (float)($timesheet['hourly_rate'] ?? 0.0);

            // DEPUTY BREAK LOGIC: Check if worked alone
            $workedAlone = $this->didStaffWorkAlone($staffId, $date, $startTime, $endTime);

            // Calculate total hours
            $totalHours = $this->calculateHoursBetween($startTime, $endTime);

            // Apply Deputy break rule
            if (!$workedAlone && $totalHours >= 4.0 && $breakHours === 0.0) {
                // Auto-deduct 30 min if worked with others for 4+ hours
                $breakHours = 0.5;
            }

            $workedHours = $totalHours - $breakHours;

            // Continue with earnings calculation...
        }
    }

    /**
     * Check if staff member worked alone during shift
     *
     * NZ Employment Law / Deputy Rule:
     * - If worked ALONE = paid breaks (no deduction)
     * - If worked WITH OTHERS = auto-deduct 30 min for 4+ hour shifts
     */
    private function didStaffWorkAlone(
        int $staffId,
        string $date,
        string $startTime,
        string $endTime
    ): bool {
        // Get staff member's outlet
        $stmt = $this->db->prepare("SELECT default_outlet FROM users WHERE id = ?");
        $stmt->execute([$staffId]);
        $outlet = $stmt->fetchColumn();

        if (!$outlet) {
            return false; // Assume worked with others (safer for compliance)
        }

        // Check for overlapping shifts at same outlet
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM deputy_timesheets dt
            JOIN users u ON dt.staff_id = u.id
            WHERE dt.date = ?
            AND dt.staff_id != ?
            AND (u.default_outlet = ? OR dt.outlet_id = ?)
            AND (
                -- Check for overlapping shifts (any overlap counts)
                (dt.start_time < ? AND dt.end_time > ?)
                OR (dt.start_time >= ? AND dt.start_time < ?)
                OR (dt.end_time > ? AND dt.end_time <= ?)
            )
        ");

        $stmt->execute([
            $date,
            $staffId,
            $outlet,
            $outlet,
            $endTime, $startTime,  // dt.start < myEnd AND dt.end > myStart
            $startTime, $endTime,  // dt.start >= myStart AND dt.start < myEnd
            $startTime, $endTime   // dt.end > myStart AND dt.end <= myEnd
        ]);

        $overlappingStaff = (int)$stmt->fetchColumn();

        return $overlappingStaff === 0; // True if no overlapping staff found
    }
}
```

---

## 🎯 Key Takeaways

1. **Break calculation is hours-based:** < 5h = 0, 5-12h = 30min, 12h+ = 60min
2. **Worked alone = paid breaks:** No deduction if no other staff at outlet during shift
3. **Multiple shifts = longest gets break:** Only deduct from longest shift of the day
4. **Certain outlets/staff always paid:** Policy overrides standard rules
5. **Approved timesheets can't be updated:** Must create new ones instead
6. **Existing breaks are honored:** Don't recalculate if already set
7. **Store hours enforced:** Most staff clamped to opening hours (some exempt)
8. **Merging covers replacement:** One timesheet for multi-shift amendments

---

## 🚨 Important Notes

### NZ Employment Law Compliance
- Staff working alone must be paid for breaks (health & safety requirement)
- Auto-deducting breaks when working with others is legally compliant
- Minimum 30-minute break for shifts 5+ hours
- Additional break for shifts 12+ hours

### Deputy API Limitations
- **NO** unapprove endpoint exists
- **CANNOT** update approved timesheets
- **CANNOT** delete timesheets via API reliably
- **MUST** create new timesheets to replace approved ones

### Performance Considerations
- Cache outlet opening hours (don't query every timesheet)
- Batch overlapping staff queries by date
- Index deputy_timesheets on (staff_id, date, outlet_id)
- Consider Redis caching for "worked alone" checks

---

## 📊 Testing Examples

### Test Case 1: Worked Alone
```
Staff: Alice (ID: 1)
Outlet: Hamilton (ID: 1)
Shift: 9am-5pm (8 hours)
Other staff at outlet: None

Expected:
- Total hours: 8.0
- Break deduction: 0.0 (worked alone = paid break)
- Paid hours: 8.0
```

### Test Case 2: Worked With Others
```
Staff: Bob (ID: 2)
Outlet: Hamilton (ID: 1)
Shift: 9am-5pm (8 hours)
Other staff at outlet: Alice 9am-3pm

Expected:
- Total hours: 8.0
- Break deduction: 0.5 (30 minutes)
- Paid hours: 7.5
```

### Test Case 3: Multiple Shifts
```
Staff: Charlie (ID: 3)
Shifts:
  - Morning: 6am-10am (4 hours)
  - Evening: 4pm-10pm (6 hours)
Total: 10 hours

Expected:
- Morning shift: 4.0 hours paid (no break)
- Evening shift: 5.5 hours paid (30 min break deducted)
- Reason: Only longest shift gets break
```

### Test Case 4: Paid Break Location
```
Staff: David (ID: 4)
Outlet: Auckland Central (ID: 18) ← In paid break list
Shift: 9am-5pm (8 hours)
Other staff present: Yes

Expected:
- Total hours: 8.0
- Break deduction: 0.0 (outlet policy = paid breaks)
- Paid hours: 8.0
```

---

## 🔄 Migration from Old System

### Changes Made
1. ✅ Moved break calculation to `PayslipCalculationEngine`
2. ✅ Added `didStaffWorkAlone()` method with database query
3. ✅ Integrated with existing `calculateEarnings()` flow
4. ✅ Created test suite verifying both scenarios

### Backward Compatibility
- Existing `calculateDeputyHourBreaksInMinutesBasedOnHoursWorked()` still works
- Old payroll-process.php continues to function
- New system adds database-driven "worked alone" detection
- Both systems can run in parallel during transition

---

**End of Documentation**

For questions or clarifications, contact: Pearce Stephens (pearce.stephens@ecigdis.co.nz)
