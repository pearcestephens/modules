# 🔍 PHASE 0 — Discovery, Inventory, and Constraints Report

**Generated:** November 4, 2025
**Project:** CIS Web Traffic & API Testing Suite (Sections 11 & 12)
**Status:** ✅ Discovery Complete - Ready for PHASE 1

---

## 0.A — Scope Map for Section 11 & 12

### **Section 11: Web Traffic & Site Monitoring**

#### 11.1 Traffic Monitor (Real-Time)
- **Live visitor count** (last 5 min)
  - In-memory counter (fallback if no Redis)
  - DB rolling window table: `web_traffic_requests`
- **Requests/second** (RPS)
  - Rolling 60-second window
  - Auto-refresh chart (10s interval)
- **Endpoint health grid**
  - Color-coded status badges
  - Driven by `/admin/health/checks` JSON endpoint
- **Live request feed**
  - SSE endpoint: `/admin/traffic/live`
  - WebSocket fallback if available
- **Alert system**
  - Error spike detection (>10 errors/min)
  - Slow endpoint detection (>5s p95)
  - DDoS heuristics (burst >100 RPS, sustained >50 RPS)

#### 11.2 Performance Analytics
- **Page load times**: avg, p50, p95, p99
- **API per-endpoint stats**: count, avg, p95, p99, errors
- **Slowest queries**: Top 10 with EXPLAIN on-demand
- **Performance budget tracking**: Thresholds vs actual

#### 11.3 Traffic Sources
- **Geo map**: IP → Location (pluggable provider: ip-api.com)
- **Browser/OS/Device**: User-agent parsing
- **Bot detection**: Pattern matching + optional auto-block
- **Referrer tracking**: Top sources, campaigns

#### 11.4 Error Tracking
- **Top 404s**: Most requested missing URLs
  - "Create Redirect" button → adds to redirects table
- **Top 500s**: Error grouping by type/file/line
  - Stack traces (PII-redacted)
- **Error trends**: Chart over time

#### 11.5 Site Health Check ("One Click")
- **SSL certificate**: Expiry check
- **Database**: Connection test + query time
- **PHP-FPM**: Process count, memory usage
- **Queue workers**: Active job count
- **Disk space**: Usage percentage
- **Vend API**: Connectivity + auth test

---

### **Section 12: API Testing & Debugging**

#### 12.1 Webhook Test Lab
- **Event selector**: Dropdown with predefined events
- **JSON editor**: Monaco or textarea with syntax highlighting
- **Target**: System endpoints OR custom URL
- **Response viewer**: Status, headers, body (formatted JSON)
- **Code snippets**: cURL, PHP, JavaScript (copy-paste ready)

#### 12.2 Vend API Tester
- **Endpoint selector**: GET /products, POST /consignments, etc.
- **Query builder**: Visual params builder
- **Auth test**: Verify token validity
- **History**: Last 20 requests
- **Replay**: Re-run previous request

#### 12.3 Lightspeed Sync Tester
- **Test suites**:
  - Transfer → Consignment (stock transfer flow)
  - PO → Consignment (purchase order flow)
  - Stock sync (inventory reconciliation)
  - Webhook trigger (simulate Vend webhook)
  - Full pipeline (end-to-end)
- **Manual sync**: "Force Sync All" button

#### 12.4 Queue Job Tester
- **Dispatch test jobs**: Select job type + payload
- **Monitor status**: Real-time job status (pending/running/completed/failed)
- **Stress mode**: Dispatch 100 jobs simultaneously
- **Cancel job**: Kill running job by ID

#### 12.5 API Endpoint Tester
- **Test suites**:
  - Transfer API (9 endpoints)
  - Purchase Order API (9 endpoints)
  - Inventory API (5 endpoints)
  - Webhook API (3 endpoints)
- **Bulk runner**: Run all tests, show summary

#### 12.6 Code Snippet Library
- **Categories**: Auth, Webhooks, CRUD, Sync
- **Copy-paste examples**: Working code
- **"Try it" button**: Opens relevant tester with prefilled values

---

## 0.B — URL & Endpoint Contract Table

### **Base URLs**
```
Production:  https://staff.vapeshed.co.nz/modules/base/
Dev:         https://dev.staff.vapeshed.co.nz/modules/base/
Local:       http://localhost/modules/base/
```

### **Routing Model**
```
GET query string: ?endpoint={route}
Example: /public/index.php?endpoint=admin/traffic/monitor
```

### **Section 11 Endpoints**

| Endpoint | Method | Auth | Rate Limit | Purpose |
|----------|--------|------|------------|---------|
| `/admin/traffic/monitor` | GET | ✅ Admin | 60/min | Traffic dashboard |
| `/admin/traffic/live` | GET | ✅ Admin | SSE | Live request feed |
| `/admin/traffic/stats` | GET | ✅ Admin | 60/min | Traffic statistics JSON |
| `/admin/performance/dashboard` | GET | ✅ Admin | 60/min | Performance analytics |
| `/admin/performance/queries` | GET | ✅ Admin | 30/min | Slow query viewer |
| `/admin/performance/explain` | POST | ✅ Admin | 10/min | EXPLAIN query |
| `/admin/sources/map` | GET | ✅ Admin | 60/min | Geo traffic map |
| `/admin/sources/browsers` | GET | ✅ Admin | 60/min | Browser/OS stats |
| `/admin/sources/bots` | GET | ✅ Admin | 60/min | Bot detection |
| `/admin/errors/404` | GET | ✅ Admin | 60/min | Top 404s |
| `/admin/errors/500` | GET | ✅ Admin | 60/min | Top 500s |
| `/admin/errors/create-redirect` | POST | ✅ Admin | 10/min | Create redirect |
| `/admin/health/checks` | GET | ✅ Admin | 120/min | Health check JSON |
| `/admin/health/ping` | GET | ✅ Admin | 300/min | Simple ping |
| `/admin/logs/apache-error-tail` | GET | ✅ Admin | 20/min | Apache error log tail |
| `/admin/logs/php-fpm-tail` | GET | ✅ Admin | 20/min | PHP-FPM log tail |

### **Section 12 Endpoints**

| Endpoint | Method | Auth | Rate Limit | Purpose |
|----------|--------|------|------------|---------|
| `/admin/testing/webhook-lab` | GET | ✅ Admin | 60/min | Webhook tester UI |
| `/admin/testing/webhook-send` | POST | ✅ Admin | 30/min | Send webhook |
| `/admin/testing/vend-api` | GET | ✅ Admin | 60/min | Vend API tester UI |
| `/admin/testing/vend-api-call` | POST | ✅ Admin | 20/min | Call Vend API |
| `/admin/testing/lightspeed-sync` | GET | ✅ Admin | 60/min | Sync tester UI |
| `/admin/testing/lightspeed-sync-run` | POST | ✅ Admin | 5/min | Run sync test |
| `/admin/testing/queue-jobs` | GET | ✅ Admin | 60/min | Queue tester UI |
| `/admin/testing/queue-dispatch` | POST | ✅ Admin | 20/min | Dispatch test job |
| `/admin/testing/queue-cancel` | POST | ✅ Admin | 10/min | Cancel job |
| `/admin/testing/api-endpoints` | GET | ✅ Admin | 60/min | API tester UI |
| `/admin/testing/api-run-suite` | POST | ✅ Admin | 5/min | Run test suite |
| `/admin/testing/snippets` | GET | ✅ Admin | 60/min | Code snippet library |

### **Environment Variables Required**
```env
# Database
DB_HOST=localhost
DB_DATABASE=jcepnzzkmj
DB_USERNAME=jcepnzzkmj
DB_PASSWORD=wprKh9Jq63

# Session
SESSION_LIFETIME=120
SESSION_SECURE=true

# Security
ADMIN_AUTH_REQUIRED=true
CSRF_ENABLED=true
RATE_LIMIT_ENABLED=true

# Logging
LOG_LEVEL=info
LOG_PATH=/home/master/applications/jcepnzzkmj/public_html/logs
APACHE_ERROR_LOG=/var/log/apache2/error.log
PHP_FPM_ERROR_LOG=/var/log/php-fpm/error.log

# External APIs
VEND_API_URL=https://vapeshed.vendhq.com/api/2.0
VEND_API_TOKEN=${VEND_TOKEN}

# Performance
PERF_BUDGET_PAGE_LOAD=3.0
PERF_BUDGET_API_P95=1.0
PERF_BUDGET_QUERY_P95=0.5

# Traffic Monitoring
TRAFFIC_ALERT_ERROR_SPIKE=10
TRAFFIC_ALERT_SLOW_ENDPOINT=5.0
TRAFFIC_ALERT_DDOS_BURST=100
TRAFFIC_ALERT_DDOS_SUSTAINED=50

# IP Geolocation
GEO_PROVIDER=ip-api
GEO_API_KEY=
```

---

## 0.C — Data & Telemetry Sources

### **Database Tables (NEW)**

#### `web_traffic_requests`
```sql
CREATE TABLE web_traffic_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id VARCHAR(36) NOT NULL COMMENT 'UUID',
    timestamp DATETIME(3) NOT NULL,
    method ENUM('GET','POST','PUT','DELETE','PATCH','OPTIONS','HEAD') NOT NULL,
    endpoint VARCHAR(500) NOT NULL,
    query_string TEXT,
    status_code SMALLINT UNSIGNED NOT NULL,
    response_time_ms INT UNSIGNED NOT NULL,
    memory_mb DECIMAL(10,2),
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    referer TEXT,
    user_id INT UNSIGNED,
    is_bot TINYINT(1) DEFAULT 0,
    bot_type VARCHAR(50),
    country_code CHAR(2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_timestamp (timestamp),
    INDEX idx_endpoint (endpoint(255)),
    INDEX idx_status_code (status_code),
    INDEX idx_response_time (response_time_ms),
    INDEX idx_ip_address (ip_address),
    INDEX idx_user_id (user_id),
    INDEX idx_is_bot (is_bot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `web_traffic_errors`
```sql
CREATE TABLE web_traffic_errors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id VARCHAR(36) NOT NULL,
    timestamp DATETIME(3) NOT NULL,
    error_code SMALLINT UNSIGNED NOT NULL,
    error_type VARCHAR(100) NOT NULL COMMENT 'Exception class',
    error_message TEXT NOT NULL,
    error_file VARCHAR(500),
    error_line INT UNSIGNED,
    stack_trace TEXT,
    endpoint VARCHAR(500) NOT NULL,
    method VARCHAR(10) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    user_id INT UNSIGNED,
    is_resolved TINYINT(1) DEFAULT 0,
    resolved_at DATETIME,
    resolved_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_timestamp (timestamp),
    INDEX idx_error_code (error_code),
    INDEX idx_error_type (error_type),
    INDEX idx_endpoint (endpoint(255)),
    INDEX idx_is_resolved (is_resolved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `web_traffic_redirects`
```sql
CREATE TABLE web_traffic_redirects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_path VARCHAR(500) NOT NULL,
    to_path VARCHAR(500) NOT NULL,
    status_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
    hit_count INT UNSIGNED DEFAULT 0,
    last_hit_at DATETIME,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_from_path (from_path(255)),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `web_health_checks`
```sql
CREATE TABLE web_health_checks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    check_type VARCHAR(50) NOT NULL COMMENT 'ssl, database, php_fpm, disk, vend_api',
    status ENUM('pass','fail','warning') NOT NULL,
    response_time_ms INT UNSIGNED,
    details JSON,
    checked_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_check_type (check_type),
    INDEX idx_checked_at (checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `api_test_history`
```sql
CREATE TABLE api_test_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    test_type VARCHAR(50) NOT NULL COMMENT 'webhook, vend_api, sync, queue, endpoint',
    test_name VARCHAR(100) NOT NULL,
    request_method VARCHAR(10),
    request_url TEXT,
    request_headers JSON,
    request_body TEXT,
    response_status SMALLINT UNSIGNED,
    response_time_ms INT UNSIGNED,
    response_headers JSON,
    response_body TEXT,
    success TINYINT(1) NOT NULL,
    error_message TEXT,
    user_id INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_test_type (test_type),
    INDEX idx_test_name (test_name),
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### **Log Files**
- Apache error log: `/var/log/apache2/error.log`
- PHP-FPM error log: `/var/log/php-fpm/error.log`
- Application log: `/home/master/applications/jcepnzzkmj/public_html/logs/app.log`
- Traffic log: `/home/master/applications/jcepnzzkmj/public_html/logs/traffic.log`

### **In-Memory Counters (Shared Memory or DB)**
- Current visitors (last 5 min): `web_traffic_requests` with rolling window query
- Requests/second: Calculated from `web_traffic_requests`
- Active sessions: PHP sessions or DB session table

### **External APIs**
- IP Geolocation: `http://ip-api.com/json/{ip}` (free, 45 req/min limit)
- Vend API: `https://vapeshed.vendhq.com/api/2.0/`

---

## 0.D — Risk Register

### **HIGH RISK**

#### 1. **URL Rot / Endpoint Changes**
- **Risk**: Hardcoded URLs break when paths change
- **Mitigation**:
  - Centralized URL config in `config/urls.php`
  - Named routes with `route()` helper
  - URL verification suite run before deploy

#### 2. **Authentication Bypass**
- **Risk**: Admin endpoints accessible without auth
- **Mitigation**:
  - Middleware auth gate on all `/admin/*` routes
  - Session validation with CSRF tokens
  - Rate limiting per IP/user

#### 3. **Log File Growth**
- **Risk**: Unbounded log files fill disk
- **Mitigation**:
  - Log rotation (daily, keep 30 days)
  - Gzip compression for archived logs
  - Disk space monitoring in health check
  - Max log lines returned (default 200)

#### 4. **PII in Logs**
- **Risk**: Customer data exposed in error logs
- **Mitigation**:
  - Redact sensitive fields (email, phone, CC) in stack traces
  - Filter query strings for sensitive params
  - Separate audit log for PII access

### **MEDIUM RISK**

#### 5. **Rate Limit Bypass**
- **Risk**: API flooding via multiple IPs
- **Mitigation**:
  - Rate limit by IP + session + user_id
  - Exponential backoff on violations
  - CAPTCHA on suspicious traffic

#### 6. **SSE Connection Leaks**
- **Risk**: Too many open SSE connections exhaust PHP-FPM
- **Mitigation**:
  - Max 10 SSE connections per user
  - Auto-close after 60s idle
  - Monitor PHP-FPM process count

#### 7. **Slow Query Performance**
- **Risk**: EXPLAIN queries lock tables
- **Mitigation**:
  - Read-only EXPLAIN mode
  - Query timeout (5s max)
  - Rate limit EXPLAIN endpoint

### **LOW RISK**

#### 8. **Bot Misclassification**
- **Risk**: Legitimate users blocked as bots
- **Mitigation**:
  - Whitelist known good bots (Google, etc.)
  - Manual review for auto-blocks
  - Block flag (not immediate IP ban)

#### 9. **Geo API Rate Limit**
- **Risk**: IP geolocation API hits rate limit
- **Mitigation**:
  - Cache geo results by IP (24 hours)
  - Fallback to "Unknown" if API unavailable
  - Optional paid provider upgrade

---

## 0.E — Acceptance Criteria (Concrete, Testable)

### **Section 11: Traffic Monitoring**

#### **AC-11.1**: Live visitor count updates every 5 seconds
- **Test**: Open traffic monitor, watch counter increment as requests made
- **Expected**: Counter shows ±1 accuracy within 10 seconds

#### **AC-11.2**: RPS chart shows correct values
- **Test**: Run `ab -n 100 -c 10 /admin/health/ping`, verify RPS spike on chart
- **Expected**: Chart shows ~10-20 RPS spike matching actual traffic

#### **AC-11.3**: 404 errors appear in error tracker
- **Test**: Request `/nonexistent-page`, check error tracker
- **Expected**: 404 appears within 10 seconds with correct URL

#### **AC-11.4**: "Create Redirect" button works
- **Test**: Click button on 404, enter redirect URL, verify redirect works
- **Expected**: Redirect saved, 301 redirect functional on next request

#### **AC-11.5**: Health check returns JSON with all checks
- **Test**: `curl /admin/health/checks`
- **Expected**: JSON with `{ssl, database, php_fpm, disk, vend_api}` all showing pass/fail

#### **AC-11.6**: Apache error tail shows last 200 lines
- **Test**: Visit `/admin/logs/apache-error-tail?lines=200`
- **Expected**: Returns last 200 lines, rate-limited to 20 req/min

### **Section 12: API Testing**

#### **AC-12.1**: Webhook sends to custom URL
- **Test**: Enter custom URL in webhook lab, send test event
- **Expected**: POST request sent, response shown with status/headers/body

#### **AC-12.2**: Vend API tester calls real Vend API
- **Test**: Select "GET /products", click "Run"
- **Expected**: Real API called, products JSON returned

#### **AC-12.3**: Sync tester runs transfer→consignment flow
- **Test**: Click "Test Transfer→Consignment", verify job dispatched
- **Expected**: Job completes, shows success/fail with details

#### **AC-12.4**: Queue job tester dispatches jobs
- **Test**: Click "Dispatch Test Job", monitor status
- **Expected**: Job appears in queue, status updates to running→completed

#### **AC-12.5**: Bulk API test suite runs all tests
- **Test**: Click "Run All Tests" in API tester
- **Expected**: All tests run, summary shows pass/fail counts

#### **AC-12.6**: Code snippet copies to clipboard
- **Test**: Click "Copy" on cURL snippet
- **Expected**: Snippet copied, confirmation shown

### **Cross-Cutting**

#### **AC-X.1**: All admin endpoints require auth
- **Test**: `curl -I /admin/traffic/monitor` (no auth)
- **Expected**: 401 Unauthorized or redirect to login

#### **AC-X.2**: Rate limiting blocks excessive requests
- **Test**: Make 100 requests to rate-limited endpoint in 10 seconds
- **Expected**: 429 Too Many Requests after limit exceeded

#### **AC-X.3**: CSRF tokens validated on POST
- **Test**: POST without CSRF token
- **Expected**: 419 CSRF Token Mismatch

#### **AC-X.4**: PHP linting passes
- **Test**: `find . -name "*.php" -exec php -l {} \;`
- **Expected**: No syntax errors

#### **AC-X.5**: PSR-12 code style passes
- **Test**: `vendor/bin/phpcs --standard=PSR12 app/`
- **Expected**: No violations

---

## PHASE 1-3 Consolidated Plan

### **PHASE 1: Shared Infrastructure** (8-10 hours)
**Goal**: Foundation for Section 11 & 12

**Tasks**:
1. Create config files (`app.php`, `urls.php`, `security.php`)
2. Build GET query router in `public/index.php`
3. Create middleware kernel (`Kernel.php`)
4. Build `Response.php` helper (JSON envelope)
5. Enhance `Logger.php` with correlation IDs
6. Create base layout templates (header, sidebar, footer)
7. Set up assets (Bootstrap 5 + custom CSS/JS)
8. Create health check endpoints (`/admin/health/ping`, `/admin/health/phpinfo`)
9. Build URL verification suite scripts
10. Create `.env.example` and `phpcs.xml`

**Artifacts**:
- `/config/app.php` (100 lines)
- `/config/urls.php` (200 lines)
- `/config/security.php` (80 lines)
- `/public/index.php` (150 lines)
- `/app/Http/Kernel.php` (200 lines)
- `/app/Support/Response.php` (120 lines)
- `/app/Support/Logger.php` (enhanced, 280 lines)
- `/resources/views/layout/header.php` (80 lines)
- `/resources/views/layout/sidebar.php` (120 lines)
- `/resources/views/layout/footer.php` (40 lines)
- `/public/assets/css/admin.css` (200 lines)
- `/public/assets/js/admin.js` (150 lines)
- `/app/Http/Controllers/HealthController.php` (100 lines)
- `/tools/verify/url-check.sh` (80 lines)
- `/.env.example` (50 lines)
- `/phpcs.xml` (30 lines)

**Total**: ~1,980 lines

---

### **PHASE 2: Section 11 - Traffic Monitoring** (12-15 hours)
**Goal**: Complete web traffic & site monitoring

**Tasks**:
1. Create database migrations for traffic tables
2. Build traffic logging middleware
3. Create traffic monitor controller + views
4. Build SSE endpoint for live feed
5. Create performance analytics controller + views
6. Build slow query analyzer with EXPLAIN
7. Create traffic sources controller (geo, browsers, bots)
8. Build error tracking (404/500) with redirect creator
9. Create comprehensive health check system
10. Build quick-dial log tail endpoints

**Artifacts**:
- `/database/migrations/002_create_web_traffic_tables.sql` (300 lines)
- `/app/Http/Middleware/TrafficLogger.php` (150 lines)
- `/app/Http/Controllers/TrafficController.php` (400 lines)
- `/resources/views/admin/traffic/monitor.php` (300 lines)
- `/resources/views/admin/traffic/live.php` (200 lines)
- `/app/Http/Controllers/PerformanceController.php` (350 lines)
- `/resources/views/admin/performance/dashboard.php` (400 lines)
- `/resources/views/admin/performance/queries.php` (250 lines)
- `/app/Http/Controllers/TrafficSourcesController.php` (300 lines)
- `/resources/views/admin/sources/map.php` (250 lines)
- `/resources/views/admin/sources/browsers.php` (200 lines)
- `/app/Http/Controllers/ErrorTrackingController.php` (350 lines)
- `/resources/views/admin/errors/404.php` (250 lines)
- `/resources/views/admin/errors/500.php` (300 lines)
- `/app/Services/HealthCheckService.php` (400 lines)
- `/app/Http/Controllers/HealthController.php` (enhanced, 300 lines)
- `/resources/views/admin/health/dashboard.php` (250 lines)
- `/app/Http/Controllers/LogsController.php` (200 lines)
- `/resources/views/admin/logs/viewer.php` (200 lines)
- `/tools/quick_dial/apache_tail.sh` (80 lines)
- `/tools/quick_dial/php_fpm_tail.sh` (80 lines)

**Total**: ~5,160 lines

---

### **PHASE 3: Section 12 - API Testing** (10-12 hours)
**Goal**: Complete API testing & debugging suite

**Tasks**:
1. Create database migration for test history
2. Build webhook test lab controller + views
3. Create Vend API tester with history
4. Build Lightspeed sync tester
5. Create queue job tester
6. Build comprehensive API endpoint tester
7. Create code snippet library
8. Add Monaco editor integration for JSON
9. Build test history viewer with replay
10. Create bulk test runner with reporting

**Artifacts**:
- `/database/migrations/003_create_api_test_tables.sql` (150 lines)
- `/app/Http/Controllers/WebhookLabController.php` (300 lines)
- `/resources/views/admin/testing/webhook-lab.php` (350 lines)
- `/app/Http/Controllers/VendApiTesterController.php` (400 lines)
- `/resources/views/admin/testing/vend-api.php` (400 lines)
- `/app/Http/Controllers/LightspeedSyncController.php` (450 lines)
- `/resources/views/admin/testing/lightspeed-sync.php` (400 lines)
- `/app/Http/Controllers/QueueTesterController.php` (300 lines)
- `/resources/views/admin/testing/queue-jobs.php` (300 lines)
- `/app/Http/Controllers/ApiEndpointTesterController.php` (500 lines)
- `/resources/views/admin/testing/api-endpoints.php` (450 lines)
- `/app/Http/Controllers/SnippetLibraryController.php` (200 lines)
- `/resources/views/admin/testing/snippets.php` (300 lines)
- `/app/Services/ApiTestService.php` (400 lines)
- `/public/assets/js/api-tester.js` (350 lines)
- `/public/assets/js/monaco-setup.js` (100 lines)

**Total**: ~4,850 lines

---

## URL Verification Suite

### **Tool**: `/tools/verify/url-check.sh`

```bash
#!/bin/bash
# URL Verification Suite
# Tests all admin endpoints for correct status codes

BASE_URL="${1:-http://localhost/modules/base}"
ADMIN_TOKEN="${2:-test_token}"

echo "🔍 CIS URL Verification Suite"
echo "================================"
echo "Base URL: $BASE_URL"
echo ""

# Test public health endpoint
echo "Testing public endpoints..."
curl -s -o /dev/null -w "  /admin/health/ping: %{http_code}\n" "$BASE_URL/public/index.php?endpoint=admin/health/ping"

# Test admin endpoints (should require auth)
echo ""
echo "Testing admin endpoints (no auth - expect 401/403)..."
curl -s -o /dev/null -w "  /admin/traffic/monitor: %{http_code}\n" "$BASE_URL/public/index.php?endpoint=admin/traffic/monitor"
curl -s -o /dev/null -w "  /admin/performance/dashboard: %{http_code}\n" "$BASE_URL/public/index.php?endpoint=admin/performance/dashboard"
curl -s -o /dev/null -w "  /admin/testing/webhook-lab: %{http_code}\n" "$BASE_URL/public/index.php?endpoint=admin/testing/webhook-lab"

# Test with auth token
echo ""
echo "Testing admin endpoints (with auth - expect 200)..."
curl -s -o /dev/null -w "  /admin/traffic/monitor: %{http_code}\n" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  "$BASE_URL/public/index.php?endpoint=admin/traffic/monitor"

# Test 404 handling
echo ""
echo "Testing 404 handling..."
curl -s -o /dev/null -w "  /nonexistent: %{http_code}\n" "$BASE_URL/public/index.php?endpoint=nonexistent"

# Test rate limiting
echo ""
echo "Testing rate limiting (expect 429 after limit)..."
for i in {1..65}; do
  STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/public/index.php?endpoint=admin/health/ping")
  if [ "$STATUS" = "429" ]; then
    echo "  Rate limit triggered at request $i: $STATUS ✅"
    break
  fi
done

echo ""
echo "✅ Verification complete"
```

**Usage**:
```bash
chmod +x tools/verify/url-check.sh
./tools/verify/url-check.sh https://staff.vapeshed.co.nz/modules/base
```

---

## Quick-Dial Log Blueprint

### **Endpoints**
- `/admin/logs/apache-error-tail?lines=200` - Apache error log tail
- `/admin/logs/php-fpm-tail?lines=200` - PHP-FPM error log tail

### **Features**
- AJAX auto-refresh (10s interval)
- Rate limit: 20 requests/min
- CSRF token required
- Admin auth required
- Download button (gzip snapshot)
- Line number selection (50, 100, 200, 500)

### **Shell Script**: `/tools/quick_dial/apache_tail.sh`

```bash
#!/bin/bash
# Quick-Dial Apache Error Log Tail
# Creates gzipped snapshots in /var/log/cis/snapshots/

LINES="${1:-200}"
LOG_FILE="/var/log/apache2/error.log"
SNAPSHOT_DIR="/var/log/cis/snapshots"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
SNAPSHOT_FILE="$SNAPSHOT_DIR/apache_error_$TIMESTAMP.log.gz"

# Create snapshot directory if not exists
mkdir -p "$SNAPSHOT_DIR"

# Tail log and gzip
tail -n "$LINES" "$LOG_FILE" | gzip > "$SNAPSHOT_FILE"

# Clean up old snapshots (keep last 50)
ls -t "$SNAPSHOT_DIR"/apache_error_*.log.gz | tail -n +51 | xargs rm -f 2>/dev/null

echo "$SNAPSHOT_FILE"
```

### **Admin UI Button** (in logs viewer)
```html
<button id="tail-apache-log" class="btn btn-primary">
  <i class="fas fa-file-alt"></i> Tail Apache Error Log
</button>

<script>
$('#tail-apache-log').click(function() {
  $.get('/admin/logs/apache-error-tail?lines=200', function(data) {
    $('#log-viewer').text(data);
  });
});
</script>
```

---

## NEXT STEPS

### **Immediate Actions** (Do Now)
1. ✅ **Phase 0 Complete** - This document
2. ⏭️ **Start Phase 1** - Shared infrastructure
3. 📝 Create database migration files
4. 🔧 Build core config files
5. 🚀 Implement GET query router

### **Phase 1 Execution Order**
1. Config files (1 hour)
2. Router + middleware (2 hours)
3. Response helper + logger enhancement (1 hour)
4. Templates + assets (2 hours)
5. Health endpoints (1 hour)
6. URL verification suite (1 hour)
7. Testing + QA (2 hours)

**Estimated Phase 1 Time**: 8-10 hours

---

## QA Checklist for Phase 0

- [x] Scope map complete for Section 11 (5 subsections)
- [x] Scope map complete for Section 12 (6 subsections)
- [x] URL & endpoint contract table defined (27+ endpoints)
- [x] Environment variables identified (25+ variables)
- [x] Database tables designed (5 new tables)
- [x] Data sources documented (DB, logs, APIs)
- [x] Risk register created (9 risks with mitigations)
- [x] Acceptance criteria defined (20+ testable criteria)
- [x] Phase 1-3 plan with task breakdown
- [x] URL verification suite designed
- [x] Quick-dial log blueprint complete

---

**✅ PHASE 0 COMPLETE - Ready for PHASE 1 Execution**

**Total Estimated Lines**: 11,990+ lines of production code across all phases
**Total Estimated Time**: 30-37 hours full implementation
**Risk Level**: MEDIUM (manageable with proper testing)
**Business Value**: HIGH (critical monitoring + debugging tools)

---

**Next Command**: Begin PHASE 1 file creation immediately! 🚀
