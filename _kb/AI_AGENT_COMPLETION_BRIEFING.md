# 🤖 AI AGENT COMPLETION BRIEFING
## VapeShed Payroll System - Remaining Work & Requirements

**Date:** November 1, 2025
**Project Status:** 80% Complete - Core Systems Production-Ready
**Target:** 100% Feature-Complete, Polished, Enterprise-Grade
**Urgency:** High Priority

---

## 📋 EXECUTIVE SUMMARY FOR AI AGENTS

### What's Already Done ✅
- **PDF Generation System** - 100% tested, TCPDF-based, 2.4ms avg generation
- **Email Queue System** - 100% tested, SendGrid integration, bulk operations
- **AI Monitoring Engine** - Autonomous health checks, self-healing capabilities
- **Master Test Suite** - 8/8 test categories passing (100% success rate)
- **Rate Limit Schema** - Database tables ready (`payroll_rate_limits`, `v_rate_limit_7d`)
- **Core Architecture** - PSR-12 compliant, secure, performant

### What Needs Completion 🎯
1. **Rate Limit Telemetry Integration** - Wire into Xero/Deputy services + Dashboard
2. **Reconciliation Dashboard** - Unified variance reporting interface
3. **Snapshot Integrity System** - SHA256 hashing for pay run verification
4. **PayrollAuthMiddleware** - Role-based access control + PII redaction
5. **Expense Workflow** - Complete CRUD + approval + Xero sync
6. **Polish & Integration Testing** - E2E workflows, edge cases, UX improvements

---

## 🎯 TASK 1: RATE LIMIT TELEMETRY INTEGRATION

### Context
We have HTTP rate limiting from Xero/Deputy APIs (429 responses). Need to track these events for monitoring and optimization.

### Already Completed
- ✅ Schema: `schema/12_rate_limits.sql`
  - Table: `payroll_rate_limits` (service, endpoint, status, headers, timestamp)
  - View: `v_rate_limit_7d` (7-day rolling aggregation)
- ✅ Service: `services/HttpRateLimitReporter.php`
  - Methods: `insert($service, $endpoint, $status, $headers)`, `insertBatch($events)`

### What You Need to Do

#### Step 1.1: Wire into XeroService
**File:** `services/XeroService.php`
**Location:** Find wrapper methods that make HTTP calls to Xero API

**Implementation:**
```php
<?php
// In each API call method (e.g., getPayRuns, createPayslip)

private function makeXeroRequest($endpoint, $method = 'GET', $data = null) {
    $response = $this->httpClient->request($method, $endpoint, ['json' => $data]);

    // NEW: Track rate limits
    if ($response->getStatusCode() === 429) {
        require_once __DIR__ . '/HttpRateLimitReporter.php';
        HttpRateLimitReporter::insert(
            'xero',
            $endpoint,
            429,
            json_encode($response->getHeaders())
        );
    }

    return $response;
}
```

**Where to Apply:**
- `getPayRuns()` → Endpoint: `/payroll.xro/1.0/payruns`
- `getEmployee($id)` → Endpoint: `/payroll.xro/1.0/employees/{id}`
- `createPayslip()` → Endpoint: `/payroll.xro/1.0/payslips`
- Any other HTTP call methods

#### Step 1.2: Wire into DeputyService
**File:** `services/DeputyService.php`
**Location:** Find wrapper methods for Deputy API calls

**Implementation:**
```php
<?php
// Similar pattern as XeroService

private function makeDeputyRequest($endpoint, $method = 'GET', $data = null) {
    $response = $this->httpClient->request($method, $endpoint, ['json' => $data]);

    if ($response->getStatusCode() === 429) {
        require_once __DIR__ . '/HttpRateLimitReporter.php';
        HttpRateLimitReporter::insert(
            'deputy',
            $endpoint,
            429,
            json_encode($response->getHeaders())
        );
    }

    return $response;
}
```

**Where to Apply:**
- `getTimesheets()` → Endpoint: `/api/v1/supervise/timesheet`
- `getEmployee($id)` → Endpoint: `/api/v1/resource/Employee/{id}`
- Any other Deputy API methods

#### Step 1.3: Create Dashboard Card
**File:** `views/dashboard_widgets/rate_limits.php` (CREATE NEW)

**Implementation:**
```php
<?php
/**
 * Rate Limit Telemetry Dashboard Card
 * Displays 7-day rolling view of 429 responses from external APIs
 */

require_once __DIR__ . '/../../lib/VapeShedDb.php';

$conn = \HumanResources\Payroll\Lib\getVapeShedConnection();

// Get rate limit summary from view
$stmt = $conn->query("
    SELECT service, total_hits, avg_daily_hits, peak_hour
    FROM v_rate_limit_7d
    ORDER BY total_hits DESC
");
$rateLimits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent events (last 24h)
$stmt = $conn->query("
    SELECT service, endpoint, timestamp,
           JSON_EXTRACT(response_headers, '$.Retry-After') as retry_after
    FROM payroll_rate_limits
    WHERE timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY timestamp DESC
    LIMIT 10
");
$recentEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-traffic-light"></i> API Rate Limit Monitoring</h5>
    </div>
    <div class="card-body">
        <h6>7-Day Summary</h6>
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Total Hits</th>
                    <th>Avg/Day</th>
                    <th>Peak Hour</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rateLimits as $limit): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($limit['service']) ?></strong></td>
                    <td><?= $limit['total_hits'] ?></td>
                    <td><?= number_format($limit['avg_daily_hits'], 1) ?></td>
                    <td><?= $limit['peak_hour'] ?>:00</td>
                    <td>
                        <?php if ($limit['total_hits'] > 50): ?>
                            <span class="badge badge-danger">Critical</span>
                        <?php elseif ($limit['total_hits'] > 10): ?>
                            <span class="badge badge-warning">Warning</span>
                        <?php else: ?>
                            <span class="badge badge-success">Normal</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h6 class="mt-3">Recent Events (24h)</h6>
        <ul class="list-group list-group-flush">
            <?php foreach ($recentEvents as $event): ?>
            <li class="list-group-item">
                <strong><?= htmlspecialchars($event['service']) ?></strong>
                <code><?= htmlspecialchars($event['endpoint']) ?></code>
                <small class="text-muted float-right">
                    <?= $event['timestamp'] ?>
                    <?php if ($event['retry_after']): ?>
                        (Retry: <?= $event['retry_after'] ?>s)
                    <?php endif; ?>
                </small>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
```

#### Step 1.4: Include in Main Dashboard
**File:** `views/dashboard.php`
**Action:** Add include for rate limit card

```php
<?php include __DIR__ . '/dashboard_widgets/rate_limits.php'; ?>
```

### Testing Requirements
1. **Simulate 429 Response**: Trigger rate limit from Xero/Deputy (use test mode)
2. **Verify Database Insert**: Check `payroll_rate_limits` table has new rows
3. **Check View**: Query `v_rate_limit_7d` and verify aggregation
4. **Dashboard Display**: Load dashboard and verify card shows data
5. **Alert Threshold**: Verify badge colors change based on hit counts

### Acceptance Criteria
- ✅ All Xero API calls tracked on 429
- ✅ All Deputy API calls tracked on 429
- ✅ Dashboard card displays 7-day summary
- ✅ Recent events list shows last 24h
- ✅ Color-coded severity badges (green/yellow/red)
- ✅ No performance impact on normal API calls

---

## 🎯 TASK 2: RECONCILIATION DASHBOARD

### Context
Payroll involves syncing data between 3 systems: VapeShed (internal), Xero (accounting), Deputy (timesheets). Need unified dashboard showing discrepancies and sync status.

### Data Sources
1. **Xero Sync Status**: `payroll_sync_log` table
2. **Deputy Variances**: `payroll_variances` table
3. **Pay Run Integrity**: `payroll_snapshots` table

### What You Need to Do

#### Step 2.1: Create Reconciliation Service
**File:** `services/ReconciliationService.php` (CREATE NEW)

```php
<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Services;

require_once __DIR__ . '/../lib/VapeShedDb.php';

class ReconciliationService {
    private \PDO $conn;

    public function __construct() {
        $this->conn = \HumanResources\Payroll\Lib\getVapeShedConnection();
    }

    /**
     * Get Xero sync status summary
     */
    public function getXeroSyncStatus(): array {
        $stmt = $this->conn->query("
            SELECT
                sync_type,
                COUNT(*) as total_syncs,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                MAX(sync_timestamp) as last_sync
            FROM payroll_sync_log
            WHERE sync_timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY sync_type
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get active variances requiring attention
     */
    public function getActiveVariances(): array {
        $stmt = $this->conn->query("
            SELECT
                variance_type,
                COUNT(*) as count,
                SUM(amount) as total_amount,
                AVG(amount) as avg_amount
            FROM payroll_variances
            WHERE resolved = 0
            GROUP BY variance_type
            ORDER BY total_amount DESC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get recent variance details
     */
    public function getRecentVariances(int $limit = 10): array {
        $stmt = $this->conn->prepare("
            SELECT
                v.*,
                e.first_name,
                e.last_name,
                pr.pay_period_start,
                pr.pay_period_end
            FROM payroll_variances v
            LEFT JOIN employees e ON v.employee_id = e.id
            LEFT JOIN payroll_pay_runs pr ON v.pay_run_id = pr.id
            WHERE v.resolved = 0
            ORDER BY v.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Auto-resolve variance if within tolerance
     */
    public function autoResolveVariance(int $varianceId, float $tolerance = 0.01): bool {
        $stmt = $this->conn->prepare("
            UPDATE payroll_variances
            SET resolved = 1,
                resolution_notes = 'Auto-resolved: within tolerance',
                resolved_at = NOW()
            WHERE id = ? AND ABS(amount) <= ?
        ");
        return $stmt->execute([$varianceId, $tolerance]);
    }

    /**
     * Get reconciliation summary
     */
    public function getSummary(): array {
        return [
            'xero_sync' => $this->getXeroSyncStatus(),
            'variances' => $this->getActiveVariances(),
            'recent_variances' => $this->getRecentVariances(),
            'health_score' => $this->calculateHealthScore()
        ];
    }

    /**
     * Calculate reconciliation health score (0-100)
     */
    private function calculateHealthScore(): int {
        // Get sync success rate
        $stmt = $this->conn->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful
            FROM payroll_sync_log
            WHERE sync_timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $syncStats = $stmt->fetch(\PDO::FETCH_ASSOC);
        $syncScore = $syncStats['total'] > 0
            ? ($syncStats['successful'] / $syncStats['total']) * 50
            : 50;

        // Get variance resolution rate
        $stmt = $this->conn->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN resolved = 1 THEN 1 ELSE 0 END) as resolved
            FROM payroll_variances
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $varianceStats = $stmt->fetch(\PDO::FETCH_ASSOC);
        $varianceScore = $varianceStats['total'] > 0
            ? ($varianceStats['resolved'] / $varianceStats['total']) * 50
            : 50;

        return (int) ($syncScore + $varianceScore);
    }
}
```

#### Step 2.2: Create Reconciliation Dashboard View
**File:** `views/reconciliation_dashboard.php` (CREATE NEW)

```php
<?php
/**
 * Reconciliation Dashboard
 * Unified view of Xero sync status and Deputy variances
 */

require_once __DIR__ . '/../services/ReconciliationService.php';

$service = new \HumanResources\Payroll\Services\ReconciliationService();
$summary = $service->getSummary();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reconciliation Dashboard</title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/fontawesome.min.css">
</head>
<body>
    <div class="container-fluid mt-4">
        <h2><i class="fas fa-balance-scale"></i> Reconciliation Dashboard</h2>

        <!-- Health Score -->
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body text-center">
                        <h3>Reconciliation Health Score</h3>
                        <h1 class="display-1
                            <?= $summary['health_score'] >= 90 ? 'text-success' :
                                ($summary['health_score'] >= 70 ? 'text-warning' : 'text-danger') ?>">
                            <?= $summary['health_score'] ?>%
                        </h1>
                    </div>
                </div>
            </div>
        </div>

        <!-- Xero Sync Status -->
        <div class="row mt-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-sync"></i> Xero Sync Status (7 Days)</h5>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Total</th>
                                    <th>Success</th>
                                    <th>Failed</th>
                                    <th>Success Rate</th>
                                    <th>Last Sync</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($summary['xero_sync'] as $sync): ?>
                                <tr>
                                    <td><?= htmlspecialchars($sync['sync_type']) ?></td>
                                    <td><?= $sync['total_syncs'] ?></td>
                                    <td class="text-success"><?= $sync['successful'] ?></td>
                                    <td class="text-danger"><?= $sync['failed'] ?></td>
                                    <td>
                                        <?php
                                        $rate = $sync['total_syncs'] > 0
                                            ? ($sync['successful'] / $sync['total_syncs']) * 100
                                            : 0;
                                        ?>
                                        <span class="badge badge-<?= $rate >= 90 ? 'success' : ($rate >= 70 ? 'warning' : 'danger') ?>">
                                            <?= number_format($rate, 1) ?>%
                                        </span>
                                    </td>
                                    <td><small><?= $sync['last_sync'] ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Variance Summary -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-exclamation-triangle"></i> Active Variances</h5>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Count</th>
                                    <th>Total Amount</th>
                                    <th>Avg Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($summary['variances'] as $variance): ?>
                                <tr>
                                    <td><?= htmlspecialchars($variance['variance_type']) ?></td>
                                    <td><?= $variance['count'] ?></td>
                                    <td class="<?= $variance['total_amount'] > 100 ? 'text-danger' : 'text-warning' ?>">
                                        $<?= number_format($variance['total_amount'], 2) ?>
                                    </td>
                                    <td>$<?= number_format($variance['avg_amount'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Variances -->
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> Recent Variances Requiring Attention</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Pay Period</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($summary['recent_variances'] as $v): ?>
                                <tr>
                                    <td><?= htmlspecialchars($v['first_name'] . ' ' . $v['last_name']) ?></td>
                                    <td><?= htmlspecialchars($v['variance_type']) ?></td>
                                    <td class="text-danger"><strong>$<?= number_format($v['amount'], 2) ?></strong></td>
                                    <td><small><?= $v['pay_period_start'] ?> to <?= $v['pay_period_end'] ?></small></td>
                                    <td><?= htmlspecialchars($v['description'] ?? 'N/A') ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary"
                                                onclick="resolveVariance(<?= $v['id'] ?>)">
                                            Resolve
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function resolveVariance(id) {
        if (confirm('Mark this variance as resolved?')) {
            fetch('/modules/human_resources/payroll/api/resolve_variance.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({variance_id: id})
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }
    }
    </script>
</body>
</html>
```

#### Step 2.3: Create API Endpoint for Variance Resolution
**File:** `api/resolve_variance.php` (CREATE NEW)

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/ReconciliationService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['variance_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing variance_id']);
    exit;
}

try {
    $service = new \HumanResources\Payroll\Services\ReconciliationService();
    $result = $service->autoResolveVariance((int)$input['variance_id']);

    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Variance resolved' : 'Failed to resolve variance'
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
```

### Testing Requirements
1. Create test variances in `payroll_variances` table
2. Create test sync logs in `payroll_sync_log` table
3. Load reconciliation dashboard and verify all sections display
4. Test variance resolution button
5. Verify health score calculation accuracy

### Acceptance Criteria
- ✅ Dashboard shows Xero sync status (7-day window)
- ✅ Dashboard shows active variances grouped by type
- ✅ Health score calculated correctly (0-100 scale)
- ✅ Recent variances list shows employee details
- ✅ Resolve button marks variance as resolved
- ✅ Color coding matches severity (green/yellow/red)

---

## 🎯 TASK 3: SNAPSHOT INTEGRITY SYSTEM

### Context
Pay run snapshots must be tamper-proof. Need SHA256 hashing to verify integrity.

### What You Need to Do

#### Step 3.1: Add Integrity Fields to Schema
**File:** `schema/13_snapshot_integrity.sql` (CREATE NEW)

```sql
-- Add integrity fields to payroll_snapshots table
ALTER TABLE payroll_snapshots
ADD COLUMN snapshot_hash VARCHAR(64) NULL AFTER snapshot_data,
ADD COLUMN hash_algorithm VARCHAR(20) DEFAULT 'sha256' AFTER snapshot_hash,
ADD COLUMN integrity_verified TINYINT(1) DEFAULT 0 AFTER hash_algorithm,
ADD COLUMN last_verification_at DATETIME NULL AFTER integrity_verified,
ADD INDEX idx_snapshot_hash (snapshot_hash);

-- Audit trail for integrity violations
CREATE TABLE IF NOT EXISTS payroll_snapshot_audit (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    snapshot_id INT UNSIGNED NOT NULL,
    event_type ENUM('created', 'verified_ok', 'verified_failed', 'tampered') NOT NULL,
    expected_hash VARCHAR(64) NULL,
    actual_hash VARCHAR(64) NULL,
    details TEXT NULL,
    detected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (snapshot_id) REFERENCES payroll_snapshots(id),
    INDEX idx_snapshot_id (snapshot_id),
    INDEX idx_event_type (event_type),
    INDEX idx_detected_at (detected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Step 3.2: Enhance PayrollSnapshotManager
**File:** `lib/PayrollSnapshotManager.php`
**Action:** Add integrity methods

```php
<?php
// Add these methods to existing PayrollSnapshotManager class

/**
 * Generate SHA256 hash of snapshot data
 */
public function generateHash(array $snapshotData): string {
    // Ensure consistent ordering for hash stability
    $normalized = $this->normalizeForHashing($snapshotData);
    $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return hash('sha256', $json);
}

/**
 * Normalize data for consistent hashing
 */
private function normalizeForHashing(array $data): array {
    // Remove metadata that changes on read (timestamps, etc)
    unset($data['last_modified'], $data['read_count']);

    // Sort keys recursively
    ksort($data);
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $data[$key] = $this->normalizeForHashing($value);
        }
    }

    return $data;
}

/**
 * Save snapshot with integrity hash
 */
public function saveWithIntegrity(int $payRunId, array $snapshotData): int {
    $hash = $this->generateHash($snapshotData);
    $json = json_encode($snapshotData);

    $stmt = $this->conn->prepare("
        INSERT INTO payroll_snapshots
        (pay_run_id, snapshot_data, snapshot_hash, hash_algorithm, created_at)
        VALUES (?, ?, ?, 'sha256', NOW())
    ");
    $stmt->execute([$payRunId, $json, $hash]);
    $snapshotId = (int) $this->conn->lastInsertId();

    // Audit log
    $this->logAuditEvent($snapshotId, 'created', $hash, $hash);

    return $snapshotId;
}

/**
 * Verify snapshot integrity
 */
public function verifyIntegrity(int $snapshotId): bool {
    $stmt = $this->conn->prepare("
        SELECT snapshot_data, snapshot_hash
        FROM payroll_snapshots
        WHERE id = ?
    ");
    $stmt->execute([$snapshotId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new \Exception("Snapshot not found: $snapshotId");
    }

    $data = json_decode($row['snapshot_data'], true);
    $expectedHash = $row['snapshot_hash'];
    $actualHash = $this->generateHash($data);

    $isValid = ($expectedHash === $actualHash);

    // Update verification status
    $stmt = $this->conn->prepare("
        UPDATE payroll_snapshots
        SET integrity_verified = ?, last_verification_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$isValid ? 1 : 0, $snapshotId]);

    // Audit log
    $eventType = $isValid ? 'verified_ok' : 'verified_failed';
    $this->logAuditEvent($snapshotId, $eventType, $expectedHash, $actualHash);

    return $isValid;
}

/**
 * Verify all snapshots for a pay run
 */
public function verifyPayRunSnapshots(int $payRunId): array {
    $stmt = $this->conn->prepare("
        SELECT id FROM payroll_snapshots WHERE pay_run_id = ?
    ");
    $stmt->execute([$payRunId]);
    $snapshotIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $results = [];
    foreach ($snapshotIds as $id) {
        $results[$id] = $this->verifyIntegrity($id);
    }

    return $results;
}

/**
 * Log audit event
 */
private function logAuditEvent(
    int $snapshotId,
    string $eventType,
    ?string $expectedHash,
    ?string $actualHash,
    ?string $details = null
): void {
    $stmt = $this->conn->prepare("
        INSERT INTO payroll_snapshot_audit
        (snapshot_id, event_type, expected_hash, actual_hash, details)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$snapshotId, $eventType, $expectedHash, $actualHash, $details]);
}
```

#### Step 3.3: Create CLI Tool for Verification
**File:** `cli/verify_snapshots.php` (CREATE NEW)

```php
#!/usr/bin/env php
<?php
/**
 * CLI tool to verify all snapshot integrity
 * Usage: php cli/verify_snapshots.php [--pay-run-id=123] [--fix]
 */

require_once __DIR__ . '/../lib/PayrollSnapshotManager.php';

$options = getopt('', ['pay-run-id:', 'fix', 'help']);

if (isset($options['help'])) {
    echo "Snapshot Integrity Verification Tool\n\n";
    echo "Usage:\n";
    echo "  php verify_snapshots.php [OPTIONS]\n\n";
    echo "Options:\n";
    echo "  --pay-run-id=ID    Verify specific pay run only\n";
    echo "  --fix              Regenerate hashes for failed snapshots\n";
    echo "  --help             Show this help\n";
    exit(0);
}

$manager = new \HumanResources\Payroll\Lib\PayrollSnapshotManager();

if (isset($options['pay-run-id'])) {
    $payRunId = (int) $options['pay-run-id'];
    echo "Verifying snapshots for pay run $payRunId...\n";
    $results = $manager->verifyPayRunSnapshots($payRunId);
} else {
    echo "Verifying all snapshots...\n";
    $results = $manager->verifyAllSnapshots();
}

$total = count($results);
$passed = array_sum($results);
$failed = $total - $passed;

echo "\nResults:\n";
echo "  Total:  $total\n";
echo "  Passed: $passed ✅\n";
echo "  Failed: $failed ❌\n";

if ($failed > 0) {
    echo "\nFailed snapshot IDs:\n";
    foreach ($results as $id => $result) {
        if (!$result) {
            echo "  - Snapshot #$id\n";
        }
    }

    if (isset($options['fix'])) {
        echo "\nRegenerating hashes...\n";
        // TODO: Implement hash regeneration
        echo "Fix functionality not yet implemented.\n";
    }

    exit(1);
} else {
    echo "\n✅ All snapshots verified successfully!\n";
    exit(0);
}
```

### Testing Requirements
1. Create test snapshot with known data
2. Calculate expected hash manually
3. Verify hash matches
4. Tamper with snapshot data
5. Verify tamper detection works
6. Test CLI verification tool

### Acceptance Criteria
- ✅ SHA256 hash generated on snapshot save
- ✅ Hash stored in `snapshot_hash` field
- ✅ Verification detects tampered data
- ✅ Audit trail logs all verification events
- ✅ CLI tool reports verification results
- ✅ Failed verifications flagged for investigation

---

## 🎯 TASK 4: PAYROLL AUTH MIDDLEWARE

### Context
Need role-based access control for payroll operations with PII redaction for unauthorized users.

### What You Need to Do

#### Step 4.1: Create PayrollAuthMiddleware
**File:** `middleware/PayrollAuthMiddleware.php` (CREATE NEW)

```php
<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Middleware;

class PayrollAuthMiddleware {
    private const ROLES = [
        'payroll_admin' => ['view', 'create', 'edit', 'delete', 'approve', 'export'],
        'payroll_manager' => ['view', 'create', 'edit', 'approve'],
        'payroll_clerk' => ['view', 'create', 'edit'],
        'employee' => ['view_own'],
        'viewer' => ['view']
    ];

    private const PII_FIELDS = [
        'ird_number', 'bank_account', 'tax_code', 'kiwisaver_rate',
        'home_address', 'phone', 'email', 'date_of_birth'
    ];

    /**
     * Check if user has permission for action
     */
    public static function authorize(string $role, string $action): bool {
        if (!isset(self::ROLES[$role])) {
            return false;
        }
        return in_array($action, self::ROLES[$role], true);
    }

    /**
     * Get user role from session
     */
    public static function getUserRole(): string {
        // TODO: Integrate with actual auth system
        return $_SESSION['payroll_role'] ?? 'viewer';
    }

    /**
     * Check permission and throw if unauthorized
     */
    public static function requirePermission(string $action): void {
        $role = self::getUserRole();
        if (!self::authorize($role, $action)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Insufficient permissions',
                'required' => $action,
                'your_role' => $role
            ]);
            exit;
        }
    }

    /**
     * Redact PII from data based on user role
     */
    public static function redactPII(array $data, ?int $employeeId = null): array {
        $role = self::getUserRole();

        // Admins and managers see everything
        if (in_array($role, ['payroll_admin', 'payroll_manager'], true)) {
            return $data;
        }

        // Employees can see their own PII
        if ($role === 'employee' && isset($_SESSION['employee_id'])) {
            if ($employeeId === $_SESSION['employee_id']) {
                return $data;
            }
        }

        // Redact PII for everyone else
        foreach (self::PII_FIELDS as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***REDACTED***';
            }
        }

        return $data;
    }

    /**
     * Apply to array of records
     */
    public static function redactBatch(array $records, string $employeeIdKey = 'employee_id'): array {
        return array_map(function($record) use ($employeeIdKey) {
            $employeeId = $record[$employeeIdKey] ?? null;
            return self::redactPII($record, $employeeId);
        }, $records);
    }

    /**
     * Log access for audit trail
     */
    public static function logAccess(
        string $resource,
        string $action,
        ?int $resourceId = null
    ): void {
        $userId = $_SESSION['user_id'] ?? 0;
        $role = self::getUserRole();

        $stmt = \HumanResources\Payroll\Lib\getVapeShedConnection()->prepare("
            INSERT INTO payroll_access_log
            (user_id, user_role, resource, action, resource_id, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $userId,
            $role,
            $resource,
            $action,
            $resourceId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
}
```

#### Step 4.2: Create Access Log Table
**File:** `schema/14_access_log.sql` (CREATE NEW)

```sql
CREATE TABLE IF NOT EXISTS payroll_access_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    user_role VARCHAR(50) NOT NULL,
    resource VARCHAR(100) NOT NULL,
    action VARCHAR(50) NOT NULL,
    resource_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_resource (resource, resource_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Step 4.3: Apply Middleware to API Endpoints
**Example:** `api/get_payslip.php`

```php
<?php
require_once __DIR__ . '/../middleware/PayrollAuthMiddleware.php';

use HumanResources\Payroll\Middleware\PayrollAuthMiddleware;

// Check permission
PayrollAuthMiddleware::requirePermission('view');

// Log access
PayrollAuthMiddleware::logAccess('payslip', 'view', $_GET['id'] ?? null);

// Get payslip data
$payslip = getPayslipData($_GET['id']);

// Redact PII
$payslip = PayrollAuthMiddleware::redactPII($payslip, $payslip['employee_id']);

// Return
echo json_encode(['success' => true, 'data' => $payslip]);
```

### Testing Requirements
1. Test each role permission matrix
2. Verify PII redaction for unauthorized roles
3. Verify employees can see own PII
4. Test 403 responses for unauthorized access
5. Verify access log captures all events

### Acceptance Criteria
- ✅ Role-based permission checking works
- ✅ PII redaction applies correctly per role
- ✅ Access logging captures all API calls
- ✅ 403 responses returned for unauthorized
- ✅ Employees can view own PII only

---

## 🎯 TASK 5: EXPENSE WORKFLOW

### Context
Need complete expense submission, approval, and Xero sync workflow.

### What You Need to Do

#### Step 5.1: Create Expense Schema
**File:** `schema/15_expenses.sql` (CREATE NEW)

```sql
CREATE TABLE IF NOT EXISTS payroll_expenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    expense_type VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    expense_date DATE NOT NULL,
    description TEXT NULL,
    receipt_path VARCHAR(255) NULL,
    status ENUM('pending', 'approved', 'rejected', 'paid') DEFAULT 'pending',
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    approval_notes TEXT NULL,
    xero_expense_claim_id VARCHAR(100) NULL,
    xero_synced_at DATETIME NULL,
    pay_run_id INT UNSIGNED NULL COMMENT 'If reimbursed via payroll',
    INDEX idx_employee_id (employee_id),
    INDEX idx_status (status),
    INDEX idx_expense_date (expense_date),
    INDEX idx_xero_expense_claim_id (xero_expense_claim_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Step 5.2: Create ExpenseService
**File:** `services/ExpenseService.php` (CREATE NEW)

```php
<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Services;

class ExpenseService {
    private \PDO $conn;

    public function __construct() {
        $this->conn = \HumanResources\Payroll\Lib\getVapeShedConnection();
    }

    /**
     * Submit new expense
     */
    public function submit(array $data): int {
        $stmt = $this->conn->prepare("
            INSERT INTO payroll_expenses
            (employee_id, expense_type, amount, expense_date, description, receipt_path)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['employee_id'],
            $data['expense_type'],
            $data['amount'],
            $data['expense_date'],
            $data['description'] ?? null,
            $data['receipt_path'] ?? null
        ]);

        return (int) $this->conn->lastInsertId();
    }

    /**
     * Approve expense
     */
    public function approve(int $expenseId, int $approvedBy, ?string $notes = null): bool {
        $stmt = $this->conn->prepare("
            UPDATE payroll_expenses
            SET status = 'approved',
                approved_by = ?,
                approved_at = NOW(),
                approval_notes = ?
            WHERE id = ? AND status = 'pending'
        ");

        return $stmt->execute([$approvedBy, $notes, $expenseId]);
    }

    /**
     * Reject expense
     */
    public function reject(int $expenseId, int $rejectedBy, string $reason): bool {
        $stmt = $this->conn->prepare("
            UPDATE payroll_expenses
            SET status = 'rejected',
                approved_by = ?,
                approved_at = NOW(),
                approval_notes = ?
            WHERE id = ? AND status = 'pending'
        ");

        return $stmt->execute([$rejectedBy, $reason, $expenseId]);
    }

    /**
     * Sync to Xero expense claim
     */
    public function syncToXero(int $expenseId): bool {
        $stmt = $this->conn->prepare("
            SELECT * FROM payroll_expenses WHERE id = ? AND status = 'approved'
        ");
        $stmt->execute([$expenseId]);
        $expense = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$expense) {
            throw new \Exception("Expense not found or not approved");
        }

        // TODO: Integrate with XeroService to create expense claim
        $xeroExpenseClaimId = $this->createXeroExpenseClaim($expense);

        // Update with Xero ID
        $stmt = $this->conn->prepare("
            UPDATE payroll_expenses
            SET xero_expense_claim_id = ?, xero_synced_at = NOW()
            WHERE id = ?
        ");

        return $stmt->execute([$xeroExpenseClaimId, $expenseId]);
    }

    /**
     * Get pending expenses for approval
     */
    public function getPendingExpenses(): array {
        $stmt = $this->conn->query("
            SELECT e.*,
                   emp.first_name, emp.last_name, emp.email
            FROM payroll_expenses e
            JOIN employees emp ON e.employee_id = emp.id
            WHERE e.status = 'pending'
            ORDER BY e.submitted_at ASC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function createXeroExpenseClaim(array $expense): string {
        // TODO: Implement actual Xero API call
        // This is a placeholder
        return 'XERO-' . uniqid();
    }
}
```

#### Step 5.3: Create Expense Submission Form
**File:** `views/submit_expense.php` (CREATE NEW)

```php
<!DOCTYPE html>
<html>
<head>
    <title>Submit Expense</title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-4">
        <h2>Submit Expense Claim</h2>

        <form id="expenseForm" enctype="multipart/form-data">
            <div class="form-group">
                <label>Expense Type</label>
                <select name="expense_type" class="form-control" required>
                    <option value="">Select type...</option>
                    <option value="travel">Travel</option>
                    <option value="meals">Meals & Entertainment</option>
                    <option value="accommodation">Accommodation</option>
                    <option value="supplies">Office Supplies</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label>Amount</label>
                <input type="number" name="amount" class="form-control" step="0.01" required>
            </div>

            <div class="form-group">
                <label>Expense Date</label>
                <input type="date" name="expense_date" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label>Receipt (optional)</label>
                <input type="file" name="receipt" class="form-control-file" accept="image/*,application/pdf">
            </div>

            <button type="submit" class="btn btn-primary">Submit Expense</button>
        </form>
    </div>

    <script>
    document.getElementById('expenseForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch('/modules/human_resources/payroll/api/submit_expense.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Expense submitted successfully!');
                window.location.href = '/modules/human_resources/payroll/views/my_expenses.php';
            } else {
                alert('Error: ' + data.error);
            }
        });
    });
    </script>
</body>
</html>
```

### Testing Requirements
1. Submit test expense with receipt
2. Test approval workflow
3. Test rejection workflow
4. Verify Xero sync (mock if no credentials)
5. Test file upload handling

### Acceptance Criteria
- ✅ Expense submission form works
- ✅ File upload saves receipts
- ✅ Approval/rejection workflow functional
- ✅ Xero sync integration ready (placeholders OK)
- ✅ Pending expenses list displays correctly

---

## 🎯 TASK 6: POLISH & INTEGRATION TESTING

### What You Need to Do

#### Step 6.1: E2E Test Scenarios
Create comprehensive test scenarios covering:

1. **Complete Pay Run Cycle**
   - Import Deputy timesheets
   - Calculate pay
   - Generate payslips (PDF)
   - Email payslips
   - Sync to Xero
   - Verify snapshot integrity
   - Check reconciliation

2. **Variance Resolution**
   - Trigger variance (mismatched hours)
   - Detect via reconciliation dashboard
   - Resolve variance
   - Re-sync to Xero
   - Verify resolution

3. **Expense Claim Lifecycle**
   - Submit expense with receipt
   - Manager approval
   - Sync to Xero
   - Pay via next pay run
   - Verify payment

4. **Access Control**
   - Test each role permission
   - Verify PII redaction
   - Test unauthorized access (expect 403)

#### Step 6.2: UX Improvements
- Add loading spinners
- Add success/error toasts
- Improve form validation
- Add keyboard shortcuts
- Improve mobile responsiveness

#### Step 6.3: Code Quality
- Run `php -l` on all files
- Fix any PSR-12 violations
- Add missing PHPDoc comments
- Remove commented-out code
- Consolidate duplicate logic

#### Step 6.4: Performance Audit
- Profile slow queries
- Add missing indexes
- Optimize N+1 queries
- Test with 1000+ employees
- Test with 100+ concurrent users

### Acceptance Criteria
- ✅ All E2E scenarios pass
- ✅ No console errors
- ✅ No PHP warnings/notices
- ✅ All pages load < 2 seconds
- ✅ Mobile-friendly

---

## 📝 FINAL CHECKLIST

### Code Quality
- [ ] All PHP files have `declare(strict_types=1);`
- [ ] All functions have PHPDoc comments
- [ ] PSR-12 compliant
- [ ] No security vulnerabilities
- [ ] Input validation on all entry points
- [ ] Output escaping for all user data

### Testing
- [ ] All unit tests passing
- [ ] All integration tests passing
- [ ] All E2E scenarios passing
- [ ] Load testing completed
- [ ] Security testing completed

### Documentation
- [ ] README updated
- [ ] API documentation complete
- [ ] Installation guide updated
- [ ] User guide created
- [ ] Admin guide created

### Deployment
- [ ] Database migrations tested
- [ ] Rollback procedures documented
- [ ] Backup strategy in place
- [ ] Monitoring alerts configured
- [ ] AI agent running in production

---

## 💬 COMMUNICATION PROTOCOL

### Reporting Progress
After completing each task, create a status update:
```markdown
## Task X: [Task Name] - COMPLETED ✅

### What Was Done
- [Item 1]
- [Item 2]

### Files Created/Modified
- path/to/file1.php
- path/to/file2.php

### Test Results
- [Test scenario 1]: PASSED
- [Test scenario 2]: PASSED

### Next Steps
- [What's next]
```

### Asking Questions
If unclear on requirements:
```markdown
## Question: [Brief Title]

### Context
[What you're working on]

### Question
[Specific question]

### Options Considered
1. Option A - [pros/cons]
2. Option B - [pros/cons]

### Recommendation
[Your suggested approach with reasoning]
```

---

## 🚀 GETTING STARTED

### For AI Agents Reading This

1. **Read the entire briefing** - Understand context
2. **Check current state** - Review files mentioned, run tests
3. **Pick a task** - Start with Task 1 (easiest) or your assigned task
4. **Follow the implementation guide** - Code provided, adapt as needed
5. **Test thoroughly** - Don't skip testing requirements
6. **Report progress** - Use communication protocol
7. **Move to next task** - Keep momentum

### Priority Order (Recommended)
1. Task 1: Rate Limit Telemetry (quick win, visible impact)
2. Task 2: Reconciliation Dashboard (high value, improves confidence)
3. Task 4: PayrollAuthMiddleware (security critical)
4. Task 3: Snapshot Integrity (data integrity critical)
5. Task 5: Expense Workflow (new feature, not blocking)
6. Task 6: Polish & Testing (final pass)

### Estimated Time
- Task 1: 3-4 hours
- Task 2: 6-8 hours
- Task 3: 4-5 hours
- Task 4: 3-4 hours
- Task 5: 8-10 hours
- Task 6: 10-15 hours

**Total:** 34-46 hours (1 week sprint)

---

## 🎓 KNOWLEDGE TRANSFER

### Key Architecture Patterns
- **VapeShedDb**: Shared database connection via `getVapeShedConnection()`
- **Services Layer**: Business logic in `services/` directory
- **API Endpoints**: REST-ish JSON APIs in `api/` directory
- **Views**: PHP templates with Bootstrap in `views/` directory
- **Lib**: Core utilities and helpers in `lib/` directory

### Existing Services You Can Use
- `XeroService.php` - Xero API wrapper
- `DeputyService.php` - Deputy API wrapper
- `SendGridService.php` - Email sending
- `PdfService.php` - PDF generation
- `PayslipPdfGenerator.php` - Payslip-specific PDF
- `EmailQueueHelper.php` - Email queue operations
- `PayrollSnapshotManager.php` - Snapshot management

### Database Tables
- `employees` - Employee master data
- `payroll_pay_runs` - Pay run headers
- `payroll_payslips` - Individual payslips
- `payroll_snapshots` - Immutable pay run snapshots
- `payroll_sync_log` - Xero/Deputy sync history
- `payroll_variances` - Detected discrepancies
- `email_queue` - Queued emails
- `payroll_rate_limits` - (NEW) Rate limit tracking
- `payroll_expenses` - (NEW) Expense claims
- `payroll_access_log` - (NEW) Access audit trail

---

## ✅ SUCCESS DEFINITION

The payroll system is **100% complete** when:

1. ✅ All 6 tasks completed and tested
2. ✅ All acceptance criteria met
3. ✅ Zero critical bugs
4. ✅ Documentation complete and accurate
5. ✅ AI agent running autonomously in production
6. ✅ Team trained on new features
7. ✅ Client satisfied and signed off

---

**Generated:** November 1, 2025
**For:** AI Development Agents
**By:** Senior AI Development Agent
**Version:** 1.0.0

**LET'S FINISH THIS! 🚀**
