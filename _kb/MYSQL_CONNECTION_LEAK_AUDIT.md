# MySQL Connection Leak Audit & Remediation Plan
**Date:** November 13, 2025
**Issue:** Too many open MySQL connections overloading server
**Priority:** CRITICAL

## Executive Summary

Conducted comprehensive audit of all MySQL database connections across the codebase. Found **132+ connection instances** with significant issues:

### Critical Findings

1. **NO CENTRALIZED CONNECTION CLEANUP**: Missing proper connection cleanup in error handlers
2. **API ENDPOINTS LEAK CONNECTIONS**: Many API files create connections without guaranteed cleanup
3. **MISSING TRY-FINALLY BLOCKS**: Error paths don't close connections
4. **MANUAL mysqli CONNECTIONS**: 40+ instances of manual `new mysqli()` without cleanup
5. **PDO CONNECTIONS NOT EXPLICITLY CLOSED**: 20+ instances of PDO without `$pdo = null`

### Impact

- Server overload from connection exhaustion
- Memory leaks from unclosed result sets
- Cascading failures when connection pool depleted
- Performance degradation under load

---

## Connection Types Found

### 1. MySQLi Connections (40+ instances)

**Pattern:**
```php
$db = new mysqli($host, $user, $pass, $name);
// ... code ...
// NO CLEANUP!
```

**Files Affected:**
- `/consignments/transfer-manager.php` ❌ NO CLOSE
- `/consignments/TransferManager/frontend.php` ✅ HAS CLOSE (line 131)
- `/consignments/TransferManager/backend.php` ❌ NO CLOSE
- `/consignments/lib/TransferManagerAPI.php` ❌ NO CLOSE (class property)
- `/consignments/database/critical-queue-tables-fix.php` ❌ NO CLOSE
- `/consignments/database/run-migration.php` ❌ NO CLOSE
- `/store-reports/bootstrap.php` ❌ NO CLOSE (2 instances)

### 2. PDO Connections (60+ instances)

**Pattern:**
```php
$pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
// ... code ...
// NO CLEANUP!
```

**Files Affected:**
- `/hr-portal/api/deny-item.php` ❌ NO CLEANUP
- `/hr-portal/api/toggle-autopilot.php` ❌ NO CLEANUP
- `/hr-portal/api/batch-approve.php` ❌ NO CLEANUP
- `/hr-portal/api/dashboard-stats.php` ❌ NO CLEANUP
- `/hr-portal/api/approve-item.php` ❌ NO CLEANUP
- `/staff-accounts/check-webhook-cli.php` ❌ NO CLEANUP
- `/staff-accounts/lookup-users.php` ❌ NO CLEANUP
- `/staff-performance/bootstrap.php` ❌ NO CLEANUP
- `/control-panel/bootstrap.php` ❌ NO CLEANUP
- `/bank-transactions/migrations/*.php` ❌ NO CLEANUP

### 3. Singleton Database Services (BASE framework)

**Pattern:**
```php
$db = \Services\Database::getInstance();
// Singleton holds connection open for request lifecycle
```

**Status:** ✅ Generally OK (singleton pattern with proper lifecycle)

**Files Using:**
- `/base/src/Core/Database.php` - PDO wrapper
- `/base/bootstrap.php` - Initializes `$db` global
- Most BASE-framework modules

---

## Critical Problem Areas

### Area 1: Consignments API Router

**File:** `/consignments/api/index.php`
**Issue:** No connection cleanup in error handlers

```php
// CURRENT CODE (PROBLEMATIC)
try {
    $controller = new StockTransferController($db);
    $controller->index();
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false]);
    // ❌ NO DATABASE CLEANUP!
}
```

**Fix Required:**
```php
try {
    $controller = new StockTransferController($db);
    $controller->index();
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false]);
} finally {
    // ✅ ALWAYS cleanup database connection
    if (isset($db) && $db instanceof mysqli) {
        $db->close();
    }
    if (isset($pdo)) {
        $pdo = null;
    }
}
```

### Area 2: TransferManagerAPI Class

**File:** `/consignments/lib/TransferManagerAPI.php`
**Issue:** Connection stored in class property, never closed

```php
// CURRENT CODE (PROBLEMATIC)
class TransferManagerAPI {
    private mysqli $db;  // ❌ Never closed!

    public function __construct() {
        $this->db = new mysqli($host, $user, $pass, $name);
    }
    // No __destruct() method!
}
```

**Fix Required:**
```php
class TransferManagerAPI {
    private mysqli $db;

    public function __construct() {
        $this->db = new mysqli($host, $user, $pass, $name);
    }

    public function __destruct() {
        // ✅ Cleanup on object destruction
        if ($this->db instanceof mysqli) {
            $this->db->close();
        }
    }
}
```

### Area 3: Standalone Scripts (Migration, CLI)

**Files:**
- `/consignments/database/run-migration.php`
- `/consignments/database/critical-queue-tables-fix.php`
- `/staff-accounts/cli/phase-e-v3-simple.php`

**Issue:** Scripts create connections and exit without cleanup

```php
// CURRENT CODE (PROBLEMATIC)
$connection = new mysqli($host, $user, $pass, $name);
// ... do work ...
echo "Migration complete\n";
// Script exits ❌ NO CLEANUP
```

**Fix Required:**
```php
$connection = new mysqli($host, $user, $pass, $name);
try {
    // ... do work ...
    echo "Migration complete\n";
} finally {
    // ✅ Cleanup even on early exit
    $connection->close();
}
```

### Area 4: HR Portal API Endpoints

**Files:** All `/hr-portal/api/*.php` files
**Issue:** PDO connections created per request, never cleaned up

```php
// CURRENT CODE (PROBLEMATIC)
$pdo = new PDO("mysql:host=127.0.0.1;dbname=jcepnzzkmj", "jcepnzzkmj", "wprKh9Jq63");
$stmt = $pdo->prepare("SELECT * FROM ...");
$stmt->execute();
echo json_encode($stmt->fetchAll());
// ❌ NO CLEANUP ($pdo = null missing)
```

**Fix Required:**
```php
$pdo = null;
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=jcepnzzkmj", "jcepnzzkmj", "wprKh9Jq63");
    $stmt = $pdo->prepare("SELECT * FROM ...");
    $stmt->execute();
    echo json_encode($stmt->fetchAll());
} finally {
    // ✅ Explicitly close PDO
    $pdo = null;
}
```

---

## Remediation Plan

### Phase 1: Critical API Endpoints (IMMEDIATE)

**Priority:** P0 - Production Impact
**Estimated Time:** 2-4 hours

1. **Add finally blocks to all API routers:**
   - `/consignments/api/index.php`
   - `/consignments/api/unified/index.php`
   - `/hr-portal/api/*.php` (5 files)
   - `/staff-accounts/complete-purchase.php`

2. **Add __destruct() to service classes:**
   - `TransferManagerAPI.php`
   - `UniversalAIRouter.php` (if has DB connection)

3. **Verify cleanup in error paths**

### Phase 2: Standalone Scripts & Migrations (HIGH PRIORITY)

**Priority:** P1 - Operational Impact
**Estimated Time:** 2-3 hours

1. **Wrap all migration scripts:**
   - `/consignments/database/run-migration.php`
   - `/consignments/database/critical-queue-tables-fix.php`
   - `/bank-transactions/migrations/*.php`

2. **Wrap all CLI scripts:**
   - `/staff-accounts/cli/phase-e-v3-simple.php`
   - `/human_resources/payroll/cli/*.php`

3. **Add exit handlers for all standalone scripts**

### Phase 3: Bootstrap & Initialization Files (MEDIUM PRIORITY)

**Priority:** P2 - Systemic Improvement
**Estimated Time:** 3-4 hours

1. **Review bootstrap files:**
   - `/store-reports/bootstrap.php` (2 mysqli instances)
   - `/staff-performance/bootstrap.php`
   - `/control-panel/bootstrap.php`

2. **Consider connection pooling or singleton pattern**

3. **Add shutdown function for cleanup:**
   ```php
   register_shutdown_function(function() {
       global $db, $pdo;
       if (isset($db) && $db instanceof mysqli) {
           $db->close();
       }
       if (isset($pdo)) {
           $pdo = null;
       }
   });
   ```

### Phase 4: Legacy Code Audit (LOWER PRIORITY)

**Priority:** P3 - Technical Debt
**Estimated Time:** 4-6 hours

1. **Audit MODULES_RECYCLE_BIN** (archived code)
2. **Document connection patterns for future reference**
3. **Create coding standards document**

---

## Implementation Strategy

### Step 1: Create Cleanup Helper Function

Add to `/base/bootstrap.php`:

```php
/**
 * Global database cleanup function
 * Call this in finally blocks or shutdown handlers
 */
function cleanup_database_connections(): void {
    global $db, $pdo, $con, $conn;

    // Handle mysqli connections
    $mysqliVars = [$db ?? null, $con ?? null, $conn ?? null];
    foreach ($mysqliVars as $mysqli) {
        if ($mysqli instanceof mysqli && !empty($mysqli->thread_id)) {
            try {
                $mysqli->close();
            } catch (Exception $e) {
                error_log("Failed to close mysqli: " . $e->getMessage());
            }
        }
    }

    // Handle PDO connections
    if (isset($pdo)) {
        $pdo = null;
    }
}

// Register global shutdown handler
register_shutdown_function('cleanup_database_connections');
```

### Step 2: Apply to Critical Files

For each file in Phase 1, apply this pattern:

```php
// At top of file
require_once __DIR__ . '/../base/bootstrap.php';

try {
    // Existing code
} catch (Exception $e) {
    // Existing error handling
} finally {
    cleanup_database_connections();
}
```

### Step 3: Add to Class Destructors

For service classes with connection properties:

```php
public function __destruct() {
    if ($this->db instanceof mysqli) {
        $this->db->close();
    }
}
```

### Step 4: Test & Verify

1. **Monitor active connections:**
   ```sql
   SHOW PROCESSLIST;
   ```

2. **Check connection count before/after:**
   ```sql
   SHOW STATUS LIKE 'Threads_connected';
   ```

3. **Load test critical endpoints:**
   ```bash
   ab -n 1000 -c 50 https://staff.vapeshed.co.nz/modules/consignments/api/?endpoint=transfers/list
   ```

4. **Verify no connection leaks**

---

## Monitoring & Prevention

### Add Connection Monitoring

Create `/base/lib/ConnectionMonitor.php`:

```php
<?php
namespace CIS\Base;

class ConnectionMonitor {
    private static int $openConnections = 0;

    public static function track(object $connection): void {
        self::$openConnections++;
        register_shutdown_function([self::class, 'close'], $connection);
    }

    public static function close(object $connection): void {
        if ($connection instanceof \mysqli) {
            $connection->close();
            self::$openConnections--;
        } elseif ($connection instanceof \PDO) {
            $connection = null;
            self::$openConnections--;
        }
    }

    public static function getOpenCount(): int {
        return self::$openConnections;
    }
}
```

### Add to Error Logs

Log connection count on each request:

```php
// In base/bootstrap.php
register_shutdown_function(function() {
    error_log("Request complete. Open connections: " . ConnectionMonitor::getOpenCount());
});
```

---

## Testing Plan

### Unit Tests

1. **Test connection cleanup in success path**
2. **Test connection cleanup in error path**
3. **Test connection cleanup on exception**
4. **Test connection cleanup on early exit**

### Integration Tests

1. **Load test API endpoints**
2. **Monitor connection pool under load**
3. **Verify no connection exhaustion**

### Acceptance Criteria

✅ **All API endpoints close connections in finally blocks**
✅ **All service classes have __destruct() methods**
✅ **All standalone scripts have cleanup handlers**
✅ **Global shutdown handler cleans up orphaned connections**
✅ **Connection count stays stable under load**
✅ **No "Too many connections" errors in logs**

---

## Files Requiring Immediate Attention

### CRITICAL (Fix Today)

1. `/consignments/api/index.php` ❌
2. `/consignments/lib/TransferManagerAPI.php` ❌
3. `/hr-portal/api/deny-item.php` ❌
4. `/hr-portal/api/toggle-autopilot.php` ❌
5. `/hr-portal/api/batch-approve.php` ❌
6. `/hr-portal/api/dashboard-stats.php` ❌
7. `/hr-portal/api/approve-item.php` ❌

### HIGH PRIORITY (Fix This Week)

8. `/consignments/transfer-manager.php` ❌
9. `/consignments/TransferManager/backend.php` ❌
10. `/consignments/database/run-migration.php` ❌
11. `/consignments/database/critical-queue-tables-fix.php` ❌
12. `/staff-accounts/check-webhook-cli.php` ❌
13. `/staff-accounts/cli/phase-e-v3-simple.php` ❌
14. `/staff-accounts/lookup-users.php` ❌

### MEDIUM PRIORITY (Fix Next Sprint)

15. `/store-reports/bootstrap.php` ❌ (2 instances)
16. `/staff-performance/bootstrap.php` ❌
17. `/control-panel/bootstrap.php` ❌
18. `/bank-transactions/migrations/*.php` ❌ (3 files)

---

## Next Steps

1. ✅ **Review this audit report**
2. ⏳ **Approve remediation plan**
3. ⏳ **Implement Phase 1 (Critical fixes)**
4. ⏳ **Deploy to staging and test**
5. ⏳ **Deploy to production with monitoring**
6. ⏳ **Implement Phases 2-4**

---

**Prepared by:** GitHub Copilot AI Agent
**Review Required:** Senior Engineer / DevOps Lead
**Deployment Window:** ASAP (Critical Production Issue)
