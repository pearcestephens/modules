# 🎯 PR: Payroll Hardening - Phase 1-12 Implementation

## 🚨 @github/copilot - WE NEED YOUR EXPERTISE!

**Priority:** HIGH
**Deadline:** Tuesday (3 days)
**Complexity:** Enterprise payroll with zero-tolerance for errors
**Foundation Status:** ✅ Complete (Phase 0 done)
**Your Mission:** Implement critical Phases 1, 4, 5, 8 (high-complexity, high-value)

---

## 📋 THE SITUATION

We're building a **production-grade payroll system** that must:
1. **Never duplicate payments** (idempotency + UNIQUE constraints)
2. **Handle errors gracefully** (DLQ + replay)
3. **Track every cent** (FIFO allocation + residuals)
4. **Detect drift** (reconciliation Deputy ↔ Xero)

**What's Done:** Complete foundation - autoloader, bootstrap, validation libs, error envelopes, comprehensive KB (10,000+ words)

**What's Needed:** Database schema, FIFO allocation logic, DLQ system, reconciliation - the complex, critical pieces where AI Agent excels.

---

## 🎯 YOUR SCOPE (4 PHASES)

### **PHASE 1: Core Schema Migration** ⭐ CRITICAL FOUNDATION
**Why You:** Schema design requires:
- Perfect constraint modeling (UNIQUE, FK, indexes)
- Idempotency-safe DDL (CREATE TABLE IF NOT EXISTS)
- Understanding of transaction isolation

**What to Build:**
```sql
-- File: migrations/2025_11_02_payroll_core.sql

CREATE TABLE IF NOT EXISTS payroll_runs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_run_id VARCHAR(255) UNIQUE NOT NULL, -- Xero run ID
    state ENUM('NEW','APPLYING','APPLIED','FAILED') DEFAULT 'NEW',
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_state_period (state, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payroll_applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_run_id VARCHAR(255) NOT NULL,
    employee_id VARCHAR(64) NOT NULL,
    vend_customer_id VARCHAR(64),
    idempotency_key VARCHAR(64) UNIQUE NOT NULL, -- SHA-256 hash
    amount_cents INT NOT NULL,
    status ENUM('SUCCESS','FAILED','PENDING') DEFAULT 'PENDING',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_run_id) REFERENCES payroll_runs(provider_run_id) ON DELETE CASCADE,
    INDEX idx_run_employee (provider_run_id, employee_id),
    INDEX idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payroll_dlq (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id VARCHAR(32) NOT NULL,
    category ENUM('DB','API','VALIDATION','INTERNAL') NOT NULL,
    code VARCHAR(64) NOT NULL,
    message TEXT NOT NULL,
    meta_json JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    retried_at DATETIME,
    resolved_at DATETIME,
    INDEX idx_category_code (category, code),
    INDEX idx_created (created_at),
    INDEX idx_unresolved (resolved_at, category) -- For finding items to replay
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payroll_residuals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(64) NOT NULL,
    period_end DATE NOT NULL,
    residual_cents INT NOT NULL,
    applied_in_run_id VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (applied_in_run_id) REFERENCES payroll_runs(provider_run_id) ON DELETE SET NULL,
    INDEX idx_employee_period (employee_id, period_end),
    INDEX idx_unapplied (applied_in_run_id, created_at) -- For finding outstanding residuals
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff_leave_balances (
    employee_id VARCHAR(64) NOT NULL,
    leave_type ENUM('ANNUAL','SICK','ALT','LIEU','UNPAID') NOT NULL,
    unit ENUM('HOURS','DAYS') NOT NULL,
    balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (employee_id, leave_type),
    INDEX idx_type_balance (leave_type, balance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS leave_conversion_rules (
    leave_type ENUM('ANNUAL','SICK','ALT','LIEU','UNPAID') NOT NULL,
    unit_from ENUM('HOURS','DAYS') NOT NULL,
    unit_to ENUM('HOURS','DAYS') NOT NULL,
    factor DECIMAL(10,6) NOT NULL, -- e.g., 8.0 for HOURS→DAYS
    PRIMARY KEY (leave_type, unit_from, unit_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed conversion rules
INSERT IGNORE INTO leave_conversion_rules (leave_type, unit_from, unit_to, factor) VALUES
('ANNUAL', 'HOURS', 'DAYS', 8.000000),
('ANNUAL', 'DAYS', 'HOURS', 0.125000),
('SICK', 'HOURS', 'DAYS', 8.000000),
('SICK', 'DAYS', 'HOURS', 0.125000),
('ALT', 'HOURS', 'DAYS', 8.000000),
('ALT', 'DAYS', 'HOURS', 0.125000);

CREATE TABLE IF NOT EXISTS payroll_bonus_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(64) NOT NULL,
    type ENUM('MANUAL','GOOGLE_REVIEW','PERFORMANCE') NOT NULL,
    amount_cents INT NOT NULL,
    period_end DATE NOT NULL,
    evidence_url VARCHAR(512),
    approved_by VARCHAR(255) NOT NULL,
    idempotency_key VARCHAR(64) UNIQUE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_employee_period (employee_id, period_end),
    INDEX idx_type_created (type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Acceptance Criteria:**
- [ ] Run migration → all tables created
- [ ] Run migration again → no errors (idempotent)
- [ ] Test duplicate insert on idempotency_key → fails with UNIQUE constraint
- [ ] Foreign keys work (test cascade delete)
- [ ] All indexes present

**Estimated Time:** 2-3 hours
**Commit Message:** `feat(payroll): Phase 1 - Core schema with idempotency`

---

### **PHASE 4: FIFO Allocation + Idempotent Apply** ⭐ CRITICAL BUSINESS LOGIC
**Why You:** This is complex financial logic requiring:
- Perfect FIFO ordering (oldest invoice first)
- Cent-perfect arithmetic (no float rounding)
- Idempotency key generation
- Duplicate detection with INSERT IGNORE pattern

**What to Build:**

#### **File 1: `services/AllocationService.php`**
```php
<?php
declare(strict_types=1);

namespace Payroll\Services;

use Payroll\Lib\Validate;

/**
 * FIFO Allocation Service
 *
 * Allocates deduction amounts to open invoices using First-In-First-Out logic.
 * All amounts in integer cents (no floats).
 *
 * @package Payroll\Services
 */
class AllocationService
{
    /**
     * Allocate deduction to open invoices using FIFO
     *
     * @param int $deductionCents Total amount to allocate (e.g., 15000 = $150.00)
     * @param array $openInvoices Array of ['invoice_id'=>'X', 'due_date'=>'YYYY-MM-DD', 'balance_cents'=>int]
     * @return array [
     *     'allocated_cents' => int,
     *     'residual_cents' => int,
     *     'applications' => [['invoice_id'=>'X', 'allocated_cents'=>int], ...],
     *     'notes' => string
     * ]
     */
    public function allocate(int $deductionCents, array $openInvoices): array
    {
        // Validate input
        if ($deductionCents <= 0) {
            throw new \InvalidArgumentException("Deduction must be positive, got: {$deductionCents}");
        }

        // Sort invoices by due_date ASC (oldest first)
        usort($openInvoices, function($a, $b) {
            return strcmp($a['due_date'] ?? '', $b['due_date'] ?? '');
        });

        $remaining = $deductionCents;
        $applications = [];
        $notes = [];

        foreach ($openInvoices as $invoice) {
            if ($remaining <= 0) {
                break;
            }

            $invoiceId = $invoice['invoice_id'] ?? null;
            $balanceCents = (int)($invoice['balance_cents'] ?? 0);

            if (!$invoiceId || $balanceCents <= 0) {
                continue; // Skip invalid entries
            }

            // Allocate as much as possible to this invoice
            $toAllocate = min($remaining, $balanceCents);

            $applications[] = [
                'invoice_id' => $invoiceId,
                'allocated_cents' => $toAllocate,
                'balance_before_cents' => $balanceCents,
                'balance_after_cents' => $balanceCents - $toAllocate,
            ];

            $remaining -= $toAllocate;

            if ($toAllocate === $balanceCents) {
                $notes[] = "Invoice {$invoiceId} paid in full (\${$this->centsToDisplay($toAllocate)})";
            } else {
                $notes[] = "Invoice {$invoiceId} partial payment (\${$this->centsToDisplay($toAllocate)})";
            }
        }

        return [
            'allocated_cents' => $deductionCents - $remaining,
            'residual_cents' => $remaining,
            'applications' => $applications,
            'notes' => implode('; ', $notes),
        ];
    }

    /**
     * Format cents for display
     */
    private function centsToDisplay(int $cents): string
    {
        return number_format($cents / 100, 2);
    }
}
```

#### **File 2: `services/VendApplyService.php`**
```php
<?php
declare(strict_types=1);

namespace Payroll\Services;

use Payroll\Lib\Idempotency;
use Payroll\Lib\Validate;
use PDO;

/**
 * Vend Application Service
 *
 * Applies allocations to Vend with idempotency protection.
 * Uses INSERT IGNORE pattern to detect duplicates.
 *
 * @package Payroll\Services
 */
class VendApplyService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Apply pay run to Vend accounts
     *
     * @param string $providerRunId Xero pay run ID
     * @param array $items Array of ['employee_id'=>'X', 'vend_customer_id'=>'Y', 'amount_cents'=>int, 'applications'=>[...]]
     * @return array ['applications'=>int, 'duplicates'=>int, 'failed'=>int, 'details'=>[...]]
     */
    public function applyRun(string $providerRunId, array $items): array
    {
        $providerRunId = Validate::employeeId($providerRunId); // Reuse validation

        $applications = 0;
        $duplicates = 0;
        $failed = 0;
        $details = [];

        foreach ($items as $item) {
            try {
                $employeeId = Validate::employeeId($item['employee_id']);
                $vendCustomerId = $item['vend_customer_id'] ?? null;
                $amountCents = Validate::cents($item['amount_cents']);
                $applicationData = $item['applications'] ?? [];

                // Generate idempotency key
                $idempotencyKey = Idempotency::keyFor('vend.apply', [
                    'provider_run_id' => $providerRunId,
                    'employee_id' => $employeeId,
                    'amount_cents' => $amountCents,
                ]);

                // INSERT IGNORE pattern for idempotency
                $stmt = $this->pdo->prepare("
                    INSERT IGNORE INTO payroll_applications
                    (provider_run_id, employee_id, vend_customer_id, idempotency_key, amount_cents, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'PENDING', NOW())
                ");

                $stmt->execute([
                    $providerRunId,
                    $employeeId,
                    $vendCustomerId,
                    $idempotencyKey,
                    $amountCents,
                ]);

                if ($stmt->rowCount() === 0) {
                    // Duplicate detected (idempotency key already exists)
                    $duplicates++;
                    $details[] = [
                        'employee_id' => $employeeId,
                        'status' => 'DUPLICATE',
                        'idempotency_key' => $idempotencyKey,
                    ];
                } else {
                    // New application inserted
                    $applications++;
                    $applicationId = (int)$this->pdo->lastInsertId();

                    // TODO: Actually call Vend API here
                    // For now, just mark as SUCCESS
                    $this->pdo->prepare("
                        UPDATE payroll_applications
                        SET status = 'SUCCESS'
                        WHERE id = ?
                    ")->execute([$applicationId]);

                    $details[] = [
                        'employee_id' => $employeeId,
                        'status' => 'SUCCESS',
                        'idempotency_key' => $idempotencyKey,
                        'application_id' => $applicationId,
                    ];
                }

            } catch (\Throwable $e) {
                $failed++;
                $details[] = [
                    'employee_id' => $item['employee_id'] ?? 'UNKNOWN',
                    'status' => 'FAILED',
                    'error' => substr($e->getMessage(), 0, 200),
                ];
            }
        }

        return [
            'applications' => $applications,
            'duplicates' => $duplicates,
            'failed' => $failed,
            'details' => $details,
        ];
    }
}
```

**Acceptance Criteria:**
- [ ] FIFO works: oldest invoice allocated first (test with sample data)
- [ ] Residuals tracked: remaining cents returned correctly
- [ ] Idempotency works: re-running same items → duplicates incremented, no new applications
- [ ] Unit tests pass for both services
- [ ] No float arithmetic (all integer cents)

**Estimated Time:** 4-5 hours
**Commit Message:** `feat(payroll): Phase 4 - FIFO allocation + idempotent Vend apply`

---

### **PHASE 5: DLQ + Replay System** ⭐ CRITICAL ERROR HANDLING
**Why You:** Error recovery requires:
- Understanding exception hierarchies
- Retry logic with exponential backoff
- Idempotent replay design
- CLI argument parsing

**What to Build:**

#### **File 1: `lib/DlqWriter.php`**
```php
<?php
declare(strict_types=1);

namespace Payroll\Lib;

use Payroll\Lib\ErrorEnvelope;
use PDO;

/**
 * Dead Letter Queue Writer
 *
 * Captures failed operations for later replay.
 *
 * @package Payroll\Lib
 */
class DlqWriter
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Write exception to DLQ
     *
     * @param \Throwable $e Exception to log
     * @param array $context Additional context (operation, params, etc.)
     * @return int DLQ entry ID
     */
    public function write(\Throwable $e, array $context = []): int
    {
        $envelope = ErrorEnvelope::from($e, $context);

        $stmt = $this->pdo->prepare("
            INSERT INTO payroll_dlq
            (request_id, category, code, message, meta_json, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $envelope['request_id'],
            $envelope['category'],
            $envelope['code'],
            $envelope['message'],
            json_encode($envelope['meta']),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Mark DLQ entry as retried
     */
    public function markRetried(int $dlqId): void
    {
        $this->pdo->prepare("
            UPDATE payroll_dlq
            SET retried_at = NOW()
            WHERE id = ?
        ")->execute([$dlqId]);
    }

    /**
     * Mark DLQ entry as resolved
     */
    public function markResolved(int $dlqId): void
    {
        $this->pdo->prepare("
            UPDATE payroll_dlq
            SET resolved_at = NOW()
            WHERE id = ?
        ")->execute([$dlqId]);
    }
}
```

#### **File 2: `cli/payroll-replay.php`**
```php
#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Payroll DLQ Replay CLI
 *
 * Replays failed operations from Dead Letter Queue.
 *
 * Usage:
 *   php payroll-replay.php --run=PR_123456        Replay specific run
 *   php payroll-replay.php --employee=E12345      Replay specific employee
 *   php payroll-replay.php --code=RATE_LIMIT      Replay specific error code
 *   php payroll-replay.php --all                  Replay all unresolved
 *
 * @package Payroll\CLI
 */

require_once __DIR__ . '/../bootstrap.php';

use Payroll\Lib\DlqWriter;
use Payroll\Services\VendApplyService;

// Parse CLI arguments
$options = getopt('', ['run:', 'employee:', 'code:', 'all']);

if (empty($options)) {
    echo "Usage: php payroll-replay.php [OPTIONS]\n";
    echo "  --run=PR_XXX        Replay specific run\n";
    echo "  --employee=EXX      Replay specific employee\n";
    echo "  --code=ERROR_CODE   Replay specific error code\n";
    echo "  --all               Replay all unresolved\n";
    exit(1);
}

// Connect to database
$pdo = new PDO(
    "mysql:host=" . env('DB_HOST') . ";dbname=" . env('DB_NAME') . ";charset=utf8mb4",
    env('DB_USER'),
    env('DB_PASS'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$dlqWriter = new DlqWriter($pdo);
$vendService = new VendApplyService($pdo);

// Build WHERE clause
$where = ['resolved_at IS NULL'];
$params = [];

if (isset($options['run'])) {
    $where[] = "JSON_EXTRACT(meta_json, '$.provider_run_id') = ?";
    $params[] = $options['run'];
}
if (isset($options['employee'])) {
    $where[] = "JSON_EXTRACT(meta_json, '$.employee_id') = ?";
    $params[] = $options['employee'];
}
if (isset($options['code'])) {
    $where[] = "code = ?";
    $params[] = $options['code'];
}

// Fetch DLQ entries
$sql = "SELECT * FROM payroll_dlq WHERE " . implode(' AND ', $where) . " ORDER BY created_at ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($entries) . " DLQ entries to replay\n";

$replayed = 0;
$failed = 0;

foreach ($entries as $entry) {
    $dlqId = (int)$entry['id'];
    $meta = json_decode($entry['meta_json'], true);

    echo "Replaying DLQ #{$dlqId} ({$entry['code']})... ";

    try {
        // Extract operation context
        $operation = $meta['operation'] ?? 'unknown';

        if ($operation === 'apply_run') {
            $providerRunId = $meta['provider_run_id'] ?? null;
            $items = $meta['items'] ?? [];

            if ($providerRunId && !empty($items)) {
                $result = $vendService->applyRun($providerRunId, $items);
                echo "SUCCESS ({$result['applications']} applied, {$result['duplicates']} duplicates)\n";

                $dlqWriter->markResolved($dlqId);
                $replayed++;
            } else {
                echo "SKIP (missing context)\n";
            }
        } else {
            echo "SKIP (unknown operation: {$operation})\n";
        }

        // Sleep to avoid rate limits
        usleep(500000); // 500ms

    } catch (\Throwable $e) {
        echo "FAILED ({$e->getMessage()})\n";

        // Write new DLQ entry
        $dlqWriter->write($e, [
            'operation' => 'replay',
            'original_dlq_id' => $dlqId,
        ]);

        $dlqWriter->markRetried($dlqId);
        $failed++;
    }
}

echo "\nReplay Summary:\n";
echo "  Replayed: {$replayed}\n";
echo "  Failed: {$failed}\n";

exit($failed > 0 ? 1 : 0);
```

**Acceptance Criteria:**
- [ ] Exceptions go to DLQ (test with forced error)
- [ ] Replay CLI works with filters (--run, --employee, --code)
- [ ] Idempotency prevents re-applying same operation
- [ ] Sleep delays prevent rate limit cascade
- [ ] Integration test passes

**Estimated Time:** 3-4 hours
**Commit Message:** `feat(payroll): Phase 5 - DLQ writer + replay CLI`

---

### **PHASE 8: Reconciliation & Drift Detection** ⭐ CRITICAL AUDIT TOOL
**Why You:** Data reconciliation requires:
- Cross-system data comparison logic
- Threshold-based alerting
- CSV export formatting
- Understanding of financial drift detection

**What to Build:**

#### **File 1: `services/ReconciliationService.php`**
```php
<?php
declare(strict_types=1);

namespace Payroll\Services;

use Payroll\Services\PayrollDeputyService;
use Payroll\Services\PayrollXeroService;

/**
 * Payroll Reconciliation Service
 *
 * Compares Deputy timesheets with Xero payslips to detect drift.
 *
 * @package Payroll\Services
 */
class ReconciliationService
{
    private PayrollDeputyService $deputyService;
    private PayrollXeroService $xeroService;
    private int $driftThresholdCents;
    private float $driftThresholdHours;

    public function __construct(
        PayrollDeputyService $deputyService,
        PayrollXeroService $xeroService,
        int $driftThresholdCents = 100,
        float $driftThresholdHours = 0.25
    ) {
        $this->deputyService = $deputyService;
        $this->xeroService = $xeroService;
        $this->driftThresholdCents = $driftThresholdCents;
        $this->driftThresholdHours = $driftThresholdHours;
    }

    /**
     * Compare Deputy to Xero for given period
     *
     * @return array ['drifts' => [...], 'summary' => [...]]
     */
    public function compareDeputyToXero(string $periodStart, string $periodEnd): array
    {
        // Fetch Deputy timesheets
        $deputyTimesheets = $this->deputyService->fetchTimesheets($periodStart, $periodEnd);

        // Fetch Xero payslips
        $xeroPayslips = $this->xeroService->fetchPayslips($periodStart, $periodEnd);

        // Group by employee
        $deputyByEmployee = $this->groupByEmployee($deputyTimesheets);
        $xeroByEmployee = $this->groupByEmployee($xeroPayslips);

        $drifts = [];
        $totalDriftCents = 0;
        $totalDriftHours = 0.0;

        foreach ($deputyByEmployee as $employeeId => $deputyData) {
            $xeroData = $xeroByEmployee[$employeeId] ?? null;

            if (!$xeroData) {
                $drifts[] = [
                    'employee_id' => $employeeId,
                    'type' => 'MISSING_IN_XERO',
                    'deputy_hours' => $deputyData['total_hours'],
                    'xero_hours' => 0,
                    'drift_hours' => $deputyData['total_hours'],
                    'severity' => 'HIGH',
                ];
                continue;
            }

            $driftHours = abs($deputyData['total_hours'] - $xeroData['total_hours']);
            $driftCents = abs($deputyData['total_cents'] - $xeroData['total_cents']);

            if ($driftHours >= $this->driftThresholdHours || $driftCents >= $this->driftThresholdCents) {
                $drifts[] = [
                    'employee_id' => $employeeId,
                    'type' => 'DRIFT',
                    'deputy_hours' => $deputyData['total_hours'],
                    'xero_hours' => $xeroData['total_hours'],
                    'drift_hours' => $driftHours,
                    'deputy_cents' => $deputyData['total_cents'],
                    'xero_cents' => $xeroData['total_cents'],
                    'drift_cents' => $driftCents,
                    'severity' => $this->calculateSeverity($driftHours, $driftCents),
                ];

                $totalDriftCents += $driftCents;
                $totalDriftHours += $driftHours;
            }
        }

        return [
            'drifts' => $drifts,
            'summary' => [
                'total_drifts' => count($drifts),
                'total_drift_cents' => $totalDriftCents,
                'total_drift_hours' => round($totalDriftHours, 2),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ],
        ];
    }

    private function groupByEmployee(array $records): array
    {
        $grouped = [];
        foreach ($records as $record) {
            $employeeId = $record['employee_id'] ?? $record['Employee'] ?? 'UNKNOWN';

            if (!isset($grouped[$employeeId])) {
                $grouped[$employeeId] = [
                    'total_hours' => 0.0,
                    'total_cents' => 0,
                ];
            }

            $grouped[$employeeId]['total_hours'] += (float)($record['TotalHours'] ?? $record['hours'] ?? 0);
            $grouped[$employeeId]['total_cents'] += (int)($record['GrossAmount'] ?? $record['amount_cents'] ?? 0);
        }
        return $grouped;
    }

    private function calculateSeverity(float $driftHours, int $driftCents): string
    {
        if ($driftCents >= 1000 || $driftHours >= 2.0) {
            return 'HIGH';
        } elseif ($driftCents >= 500 || $driftHours >= 1.0) {
            return 'MEDIUM';
        }
        return 'LOW';
    }
}
```

#### **File 2: `cli/payroll-drift-scan.php`**
```php
#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Payroll Drift Scanner CLI
 *
 * Scans for drift between Deputy and Xero.
 * Exports CSV report and alerts on threshold breaches.
 *
 * Usage:
 *   php payroll-drift-scan.php --start=2025-10-28 --end=2025-11-03
 *
 * @package Payroll\CLI
 */

require_once __DIR__ . '/../bootstrap.php';

use Payroll\Services\ReconciliationService;
use Payroll\Services\PayrollDeputyService;
use Payroll\Services\PayrollXeroService;
use Payroll\Lib\DlqWriter;

// Parse CLI arguments
$options = getopt('', ['start:', 'end:']);

if (!isset($options['start']) || !isset($options['end'])) {
    echo "Usage: php payroll-drift-scan.php --start=YYYY-MM-DD --end=YYYY-MM-DD\n";
    exit(1);
}

$periodStart = $options['start'];
$periodEnd = $options['end'];

// Connect to database
$pdo = new PDO(
    "mysql:host=" . env('DB_HOST') . ";dbname=" . env('DB_NAME') . ";charset=utf8mb4",
    env('DB_USER'),
    env('DB_PASS'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Initialize services
$deputyService = new PayrollDeputyService($pdo);
$xeroService = new PayrollXeroService($pdo);
$reconciliationService = new ReconciliationService($deputyService, $xeroService);
$dlqWriter = new DlqWriter($pdo);

echo "Scanning for drift: {$periodStart} to {$periodEnd}\n";

try {
    $result = $reconciliationService->compareDeputyToXero($periodStart, $periodEnd);

    echo "Found {$result['summary']['total_drifts']} drifts\n";
    echo "Total drift: \${$result['summary']['total_drift_cents'] / 100} / {$result['summary']['total_drift_hours']} hours\n";

    // Export CSV
    $csvPath = __DIR__ . "/../reports/drift_" . date('Ymd_His') . ".csv";
    $csvDir = dirname($csvPath);
    if (!is_dir($csvDir)) {
        mkdir($csvDir, 0755, true);
    }

    $fp = fopen($csvPath, 'w');
    fputcsv($fp, ['Employee ID', 'Type', 'Deputy Hours', 'Xero Hours', 'Drift Hours', 'Deputy $', 'Xero $', 'Drift $', 'Severity']);

    foreach ($result['drifts'] as $drift) {
        fputcsv($fp, [
            $drift['employee_id'],
            $drift['type'],
            $drift['deputy_hours'] ?? 0,
            $drift['xero_hours'] ?? 0,
            $drift['drift_hours'] ?? 0,
            ($drift['deputy_cents'] ?? 0) / 100,
            ($drift['xero_cents'] ?? 0) / 100,
            ($drift['drift_cents'] ?? 0) / 100,
            $drift['severity'],
        ]);

        // Alert on high-severity drift
        if ($drift['severity'] === 'HIGH') {
            $dlqWriter->write(
                new \RuntimeException("High drift detected for {$drift['employee_id']}"),
                [
                    'operation' => 'drift_scan',
                    'employee_id' => $drift['employee_id'],
                    'drift_cents' => $drift['drift_cents'] ?? 0,
                    'drift_hours' => $drift['drift_hours'] ?? 0,
                ]
            );
        }
    }

    fclose($fp);
    echo "CSV exported: {$csvPath}\n";

    exit(0);

} catch (\Throwable $e) {
    echo "ERROR: {$e->getMessage()}\n";

    $dlqWriter->write($e, [
        'operation' => 'drift_scan',
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
    ]);

    exit(1);
}
```

**Acceptance Criteria:**
- [ ] Drift detected (test with mismatched sample data)
- [ ] CSV exports correctly with all columns
- [ ] DLQ alert on high-severity drift
- [ ] E2E test passes

**Estimated Time:** 3-4 hours
**Commit Message:** `feat(payroll): Phase 8 - Reconciliation service + drift scanner`

---

## 🎯 PULL REQUEST STRUCTURE

Please create **4 separate PRs** (one per phase):

```
PR #1: feat(payroll): Phase 1 - Core schema migration
├── migrations/2025_11_02_payroll_core.sql
└── _kb/SCHEMA_MIGRATION_NOTES.md

PR #2: feat(payroll): Phase 4 - FIFO allocation + idempotent apply
├── services/AllocationService.php
├── services/VendApplyService.php
├── tests/Unit/AllocationServiceTest.php
└── tests/Unit/VendApplyServiceTest.php

PR #3: feat(payroll): Phase 5 - DLQ writer + replay system
├── lib/DlqWriter.php
├── cli/payroll-replay.php
├── tests/Integration/DlqReplayTest.php
└── _kb/DLQ_REPLAY_GUIDE.md

PR #4: feat(payroll): Phase 8 - Reconciliation + drift detection
├── services/ReconciliationService.php
├── cli/payroll-drift-scan.php
├── tests/E2E/ReconciliationFlowTest.php
└── _kb/DRIFT_DETECTION_GUIDE.md
```

---

## 📚 RESOURCES AT YOUR DISPOSAL

### **Comprehensive Knowledge Base:**
- `_kb/INDEX.md` - Start here (navigation hub)
- `_kb/QUICK_REFERENCE.md` - 1-page fast lookup
- `_kb/PAYROLL_DEEP_DIVE_ANALYSIS.md` - 7,500-word complete analysis
- `_kb/GITHUB_AI_AGENT_BRIEFING.md` - This document

### **Foundation Code (Ready to Use):**
- `autoload.php` - PSR-4 autoloader working
- `bootstrap.php` - Environment configured
- `config.php` - All thresholds/caps defined
- `lib/Respond.php` - JSON envelope helper
- `lib/Validate.php` - Input validation
- `lib/Idempotency.php` - Key generation
- `lib/ErrorEnvelope.php` - Exception normalization

### **Existing Services (Patterns to Follow):**
- `PayrollDeputyService.php` - Production-ready API wrapper
- `PayrollLogger.php` - Activity logging pattern

### **Database Access:**
```php
// Connection pattern (from bootstrap.php):
$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4',
    'jcepnzzkmj',
    'wprKh9Jq63',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
```

---

## ✅ QUALITY CHECKLIST

Before submitting each PR:

- [ ] **Code Style:** `declare(strict_types=1)` on all PHP files
- [ ] **PSR-12:** Follow coding standards
- [ ] **PHPDoc:** All classes/methods documented
- [ ] **Type Hints:** Parameters and returns typed
- [ ] **No Floats:** All money in integer cents
- [ ] **Error Handling:** Try-catch with DLQ on critical paths
- [ ] **Idempotency:** UNIQUE constraints + INSERT IGNORE pattern
- [ ] **Logging:** Use PayrollLogger for operations
- [ ] **Tests:** Unit/integration tests pass
- [ ] **Syntax:** `php -l` clean on all files
- [ ] **Commit Size:** ≤ 2 files per commit, ≤ 20KB per commit

---

## 🚨 RED FLAGS TO AVOID

❌ **Float arithmetic for money** (use int cents)
❌ **Missing idempotency keys** (every payment operation needs one)
❌ **Silent failures** (all errors to DLQ)
❌ **No duplicate detection** (INSERT IGNORE pattern required)
❌ **Hardcoded credentials** (use env() function)
❌ **Missing indexes** (hot paths need indexes)
❌ **No type hints** (strict types required)

---

## 🎯 SUCCESS METRICS

**Phase 1 Success:**
- Migration runs idempotently ✅
- All 6 tables exist ✅
- UNIQUE constraints prevent duplicates ✅
- Foreign keys work ✅

**Phase 4 Success:**
- FIFO allocates correctly (oldest first) ✅
- Residuals tracked ✅
- Idempotency prevents duplicate payments ✅
- Tests pass ✅

**Phase 5 Success:**
- Errors go to DLQ ✅
- Replay CLI works with filters ✅
- Idempotency prevents re-apply ✅
- Integration test passes ✅

**Phase 8 Success:**
- Drift detected ✅
- CSV exports ✅
- DLQ alerts on threshold ✅
- E2E test passes ✅

---

## 💪 LET'S BUILD THIS!

**Timeline:**
- **Phase 1:** 2-3 hours (schema foundation)
- **Phase 4:** 4-5 hours (complex business logic)
- **Phase 5:** 3-4 hours (error handling)
- **Phase 8:** 3-4 hours (reconciliation)

**Total Estimate:** 12-16 hours for all 4 phases

**You've got everything you need:**
- ✅ Complete foundation code
- ✅ 10,000+ words of documentation
- ✅ Clear acceptance criteria
- ✅ Working patterns to follow
- ✅ Database access configured

**Questions?** Check the KB first:
- Quick lookup → `_kb/QUICK_REFERENCE.md`
- Deep dive → `_kb/PAYROLL_DEEP_DIVE_ANALYSIS.md`
- Patterns → See `services/PayrollDeputyService.php`

**This is production code for a financial system. Zero tolerance for errors. But you've got this! 🚀**

---

**Ready when you are! Tag me in the PR and let's ship this by Tuesday! 💪**
