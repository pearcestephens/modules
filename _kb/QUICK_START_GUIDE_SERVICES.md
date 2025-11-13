# 🚀 QUICK START GUIDE - BUILDING SERVICES

**For:** Developers implementing the 6 remaining tasks
**Status:** Ready to build immediately
**No Placeholders:** All references are to real, existing code

---

## 📁 FILE LOCATIONS QUICK REFERENCE

### Existing API Clients (DO NOT MODIFY)
```
assets/functions/deputy.php              - Deputy API client (production-ready)
assets/functions/xero-functions.php      - Xero API client (production-ready)
assets/functions/pdo.php                 - Database connection factory
```

### Service Layer (TO BE CREATED)
```
modules/human_resources/payroll/services/PayrollDeputyService.php
modules/human_resources/payroll/services/PayrollXeroService.php
modules/human_resources/payroll/services/ReconciliationService.php
modules/human_resources/payroll/services/ExpenseService.php
```

### Existing Services (REFERENCE ONLY)
```
modules/consignments/ConsignmentService.php  - Architecture blueprint (333 lines)
modules/human_resources/payroll/services/HttpRateLimitReporter.php - Rate limit tracking
```

---

## 🔑 AUTHENTICATION QUICK REFERENCE

### Deputy API
```php
// Load Deputy functions
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/deputy.php';

// Get credentials (from env or fallback)
$endpoint = getDeputyURL();        // Returns: vapeshed.au.deputy.com
$token = getDeputyTokenKey();      // Returns: Bearer token

// Make API call
$response = dp_wget("https://{$endpoint}/api/v1/timesheets", $postData);
$data = arr_deep(json_decode($response, true));
```

### Xero API
```php
// Initialize OAuth2 (must be done first)
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/xeroAPI/oauth2/xeroAuth2Setup.php';

// Load Xero functions
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/xero-functions.php';

// Make API call
$employees = getXeroEmployeesForDisplay($payrollNzApi, $xeroTenantId);
```

---

## 💾 DATABASE QUICK REFERENCE

### Get PDO Connection
```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/pdo.php';

$roDb = \CIS\DB\PDO\getCisReadOnlyPdo();  // Read-only (SELECT)
$rwDb = \CIS\DB\PDO\getCisPdo();           // Read-write (INSERT/UPDATE/DELETE)
```

### Key Tables
```sql
deputy_timesheets              -- Deputy timesheet data
xero_payrolls                  -- Xero pay run data
xero_payroll_deductions        -- Xero deductions
payroll_activity_log           -- PRIMARY sync/audit log
payroll_rate_limits            -- Rate limit tracking (auto-populated)
v_rate_limit_7d                -- 7-day rate limit view
payroll_wage_discrepancies     -- Variance tracking
```

---

## 📊 LOGGING PATTERN

### Log to Activity Log
```php
function logToActivity(PDO $pdo, string $category, string $action, array $context = []): void
{
    $stmt = $pdo->prepare("
        INSERT INTO payroll_activity_log
        (log_level, category, action, message, details, request_id, execution_time_ms, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $context['level'] ?? 'info',
        $category,  // e.g., 'deputy_sync', 'xero_export'
        $action,    // e.g., 'fetch_timesheets', 'create_payrun'
        $context['message'] ?? "Action: {$action}",
        json_encode($context['details'] ?? $context),
        $context['request_id'] ?? uniqid('req_'),
        $context['duration_ms'] ?? null
    ]);
}

// Usage:
logToActivity($this->rwDb, 'deputy_sync', 'fetch_timesheets', [
    'employee_id' => 123,
    'start_date' => '2025-01-01',
    'end_date' => '2025-01-31',
    'count' => 20,
    'duration_ms' => 450
]);
```

---

## ⚡ RATE LIMIT TRACKING PATTERN

### Track 429 Responses
```php
function trackRateLimit(string $service, string $endpoint, int $httpCode, ?int $retryAfter = null): void
{
    if ($httpCode === 429) {
        require_once __DIR__ . '/HttpRateLimitReporter.php';

        HttpRateLimitReporter::insert(
            service: $service,        // 'deputy' or 'xero'
            endpoint: $endpoint,      // e.g., '/api/v1/timesheets'
            httpCode: 429,
            retryAfter: $retryAfter,  // From Retry-After header
            requestId: uniqid('req_')
        );
    }
}

// Usage in API call:
$startTime = microtime(true);
$response = dp_wget($url, $postData);
$duration = (int)((microtime(true) - $startTime) * 1000);

// Extract HTTP code (in real implementation, use curl_getinfo)
$httpCode = 200; // TODO: Get from curl_getinfo($ch, CURLINFO_HTTP_CODE)

trackRateLimit('deputy', '/api/v1/timesheets', $httpCode);
```

---

## 🏗️ SERVICE CLASS TEMPLATE

### Copy-Paste Starting Point
```php
<?php
declare(strict_types=1);

namespace VapeShed\Payroll\Services;

use PDO;

/**
 * [Service Name] Service
 *
 * [Brief description of what this service does]
 *
 * Architecture: Service layer pattern (ConsignmentService blueprint)
 * Rate Limits: Tracks 429 responses in payroll_rate_limits table
 * Logging: All operations logged to payroll_activity_log
 *
 * @package VapeShed\Payroll\Services
 */
class [ServiceName]Service
{
    private PDO $roDb;    // Read-only connection (SELECT queries)
    private PDO $rwDb;    // Read-write connection (INSERT/UPDATE/DELETE)

    /**
     * Private constructor - use ::make() factory method
     */
    private function __construct(PDO $roDb, PDO $rwDb)
    {
        $this->roDb = $roDb;
        $this->rwDb = $rwDb;
    }

    /**
     * Factory method with automatic PDO injection
     *
     * @param PDO|null $roDb Read-only connection (optional)
     * @param PDO|null $rwDb Read-write connection (optional)
     * @return self
     */
    public static function make(?PDO $roDb = null, ?PDO $rwDb = null): self
    {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/pdo.php';

        $roDb ??= \CIS\DB\PDO\getCisReadOnlyPdo();
        $rwDb ??= \CIS\DB\PDO\getCisPdo();

        return new self($roDb, $rwDb);
    }

    /**
     * Log activity to payroll_activity_log
     *
     * @param string $category Category slug (e.g., 'deputy_sync')
     * @param string $action Action slug (e.g., 'fetch_timesheets')
     * @param array $context Additional context data
     */
    private function logActivity(string $category, string $action, array $context = []): void
    {
        $stmt = $this->rwDb->prepare("
            INSERT INTO payroll_activity_log
            (log_level, category, action, message, details, request_id, execution_time_ms, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $context['level'] ?? 'info',
            $category,
            $action,
            $context['message'] ?? "Action: {$action}",
            json_encode($context['details'] ?? $context),
            $context['request_id'] ?? uniqid('req_'),
            $context['duration_ms'] ?? null
        ]);
    }

    /**
     * Track rate limit events
     *
     * @param string $service Service name ('deputy' or 'xero')
     * @param string $endpoint API endpoint
     * @param int $httpCode HTTP status code
     * @param int|null $retryAfter Retry-After header value (seconds)
     */
    private function trackRateLimit(string $service, string $endpoint, int $httpCode, ?int $retryAfter = null): void
    {
        if ($httpCode === 429) {
            require_once __DIR__ . '/HttpRateLimitReporter.php';

            \HttpRateLimitReporter::insert(
                service: $service,
                endpoint: $endpoint,
                httpCode: 429,
                retryAfter: $retryAfter,
                requestId: uniqid('req_')
            );
        }
    }

    // ========================================================================
    // PUBLIC API METHODS (implement your service logic here)
    // ========================================================================

    /**
     * [Method description]
     *
     * @param [params]
     * @return [return type]
     */
    public function exampleMethod(): array
    {
        $startTime = microtime(true);

        try {
            // Your implementation here
            $result = [];

            // Log success
            $this->logActivity('category', 'action', [
                'duration_ms' => (int)((microtime(true) - $startTime) * 1000),
                'count' => count($result)
            ]);

            return $result;

        } catch (\Exception $e) {
            // Log error
            $this->logActivity('category', 'action', [
                'level' => 'error',
                'message' => $e->getMessage(),
                'duration_ms' => (int)((microtime(true) - $startTime) * 1000)
            ]);

            throw $e;
        }
    }
}
```

---

## 🧪 TESTING PATTERN

### Service Test Template
```php
<?php
/**
 * Test [ServiceName] Service
 *
 * Run: php test_[service_name]_service.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../services/[ServiceName]Service.php';

echo "==================================================\n";
echo "[SERVICE NAME] SERVICE TEST SUITE\n";
echo "==================================================\n\n";

$passed = 0;
$failed = 0;

function test(string $name, callable $fn): void {
    global $passed, $failed;

    echo "TEST: {$name} ... ";

    try {
        $fn();
        echo "✅ PASS\n";
        $passed++;
    } catch (Exception $e) {
        echo "❌ FAIL - {$e->getMessage()}\n";
        $failed++;
    }
}

// Test 1: Factory method
test("Factory method creates instance", function() {
    $service = VapeShed\Payroll\Services\[ServiceName]Service::make();
    if (!$service instanceof VapeShed\Payroll\Services\[ServiceName]Service) {
        throw new Exception("Factory did not return correct instance");
    }
});

// Test 2: [Your test here]
test("[Test description]", function() {
    // Your test implementation
});

// Summary
echo "\n==================================================\n";
echo "RESULTS: {$passed} passed, {$failed} failed\n";
echo "==================================================\n";

exit($failed > 0 ? 1 : 0);
```

---

## 📋 IMPLEMENTATION CHECKLIST

When building each service, ensure:

### Code Quality
- [ ] `declare(strict_types=1);` at top of file
- [ ] Namespace: `VapeShed\Payroll\Services`
- [ ] PHPDoc comments on all public methods
- [ ] Type hints on all parameters and returns
- [ ] PSR-12 coding style (4 spaces, brace placement)

### Architecture
- [ ] Factory pattern with `::make()` method
- [ ] PDO injection (roDb + rwDb)
- [ ] Private constructor (force factory usage)
- [ ] Separation: read methods use roDb, write methods use rwDb

### Logging
- [ ] All operations logged to `payroll_activity_log`
- [ ] Include duration_ms for performance tracking
- [ ] Error cases logged with 'error' level
- [ ] Request ID for tracing

### Rate Limiting
- [ ] Track 429 responses with `HttpRateLimitReporter::insert()`
- [ ] Extract Retry-After header when available
- [ ] Log rate limit events to activity log

### Error Handling
- [ ] Try-catch blocks around API calls
- [ ] Meaningful error messages
- [ ] No silent failures
- [ ] Log exceptions before re-throwing

### Testing
- [ ] Create test file for service
- [ ] Test factory method
- [ ] Test happy path scenarios
- [ ] Test error scenarios
- [ ] Add to master test suite

---

## 🔥 COMMON PITFALLS TO AVOID

### ❌ DON'T DO THIS:
```php
// Hardcoded credentials
$token = '164c35e82da7af14d6cd8c02ff198f81';

// Ignoring rate limits
curl_exec($ch); // No tracking

// Silent failures
try { apiCall(); } catch (Exception $e) { /* nothing */ }

// No logging
$result = doSomething();
return $result;

// Mixed read/write on single connection
$this->db->query("SELECT ...");
$this->db->query("INSERT ...");

// Placeholder comments
// TODO: implement this
return [];
```

### ✅ DO THIS INSTEAD:
```php
// Environment variables with fallback
$token = getenv('DEPUTY_TOKEN') ?: getDeputyTokenKey();

// Track rate limits
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$this->trackRateLimit('deputy', $endpoint, $httpCode);

// Handle errors properly
try {
    apiCall();
} catch (Exception $e) {
    $this->logActivity('category', 'action', ['level' => 'error', 'message' => $e->getMessage()]);
    throw $e;
}

// Comprehensive logging
$startTime = microtime(true);
$result = doSomething();
$this->logActivity('category', 'action', [
    'duration_ms' => (int)((microtime(true) - $startTime) * 1000),
    'count' => count($result)
]);

// Proper read/write separation
$data = $this->roDb->query("SELECT ...")->fetchAll();  // Read-only
$this->rwDb->exec("INSERT ...");                       // Read-write

// Full implementation
require_once __DIR__ . '/../../../assets/functions/deputy.php';
$response = dp_wget($url, $postData);
return arr_deep(json_decode($response, true));
```

---

## 🎯 READY TO BUILD?

1. Read `INTEGRATION_STATUS_REPORT.md` for API details
2. Read `CURRENT_STATUS_AND_NEXT_ACTIONS.md` for task breakdown
3. Copy service template from this guide
4. Follow checklist above
5. Test as you build
6. Commit when tests pass

**First Target:** `PayrollDeputyService.php`
**Estimated Time:** 4-5 hours
**Complexity:** Medium (proven API client, clear requirements)

---

**Let's build production-grade services!** 🚀

No placeholders. No stubs. Real integration. Full testing.

This is how we do it. 💪
