# 🚀 Flagged Products Cron System V2.0 - COMPLETE

## ✅ WHAT WAS DONE

### 1. **Moved Old Smart Cron** (/modules/smart-cron → backup)
   - Old conflicting smart-cron directory backed up to `smart-cron.OLD_BACKUP_[timestamp]`
   - Active Smart Cron V2 remains in `/assets/services/cron/` ✅
   - Working dashboards:
     - `/assets/services/cron/dashboard.php` (Full V2 dashboard - 1606 lines)
     - `/modules/admin-ui/dashboard.php` (Standalone simple dashboard)

### 2. **Created Professional Cron Wrapper**
   - **File**: `/modules/flagged_products/cron/FlaggedProductsCronWrapper.php`
   - **Features**:
     - ✅ Performance logging and metrics
     - ✅ Smart Cron V2 integration
     - ✅ Error handling and alerts
     - ✅ Memory and execution time tracking
     - ✅ Circuit breaker pattern (opens after 5 failures/hour)
     - ✅ Automatic retry logic (3 attempts with 60s delay)
     - ✅ Isolated execution environment
     - ✅ Comprehensive logging to file and database

### 3. **Database Schema for Metrics**
   - **File**: `/modules/flagged_products/database/cron_metrics_schema.sql`
   - **Tables**:
     - `flagged_products_cron_metrics` - Performance tracking
   - **Views**:
     - `vw_flagged_products_cron_performance` - 30-day summary
     - `vw_flagged_products_cron_daily_trends` - Daily trends
     - `vw_flagged_products_cron_health` - Health status monitoring

---

## 📋 NEXT STEPS - EXECUTE THESE COMMANDS

### Step 1: Install Database Schema

```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/flagged_products

# Import the schema
mysql jcepnzzkmj < database/cron_metrics_schema.sql
```

### Step 2: Copy Cron Files from Consignments Module

```bash
# Copy all 5 cron job files
cp /home/master/modules-consignments/flagged_products/cron/*.php /home/master/applications/jcepnzzkmj/public_html/modules/flagged_products/cron/

# Copy the cron lib directory
cp -r /home/master/modules-consignments/flagged_products/cron/lib /home/master/applications/jcepnzzkmj/public_html/modules/flagged_products/cron/
```

### Step 3: Create Wrapped Versions of Each Cron Job

I'll create these next - wrapped versions that use the FlaggedProductsCronWrapper.

---

## 🎯 CRON JOBS TO WRAP

### 1. **generate_daily_products.php** (CRITICAL - Priority 1)
   - **Schedule**: Daily at 7:05 AM
   - **Purpose**: Generate 20 smart-selected products per outlet
   - **Timeout**: 600 seconds (10 minutes)

### 2. **refresh_leaderboard.php** (Priority 3)
   - **Schedule**: Daily at 2:00 AM
   - **Purpose**: Update staff rankings and cache
   - **Timeout**: 300 seconds (5 minutes)

### 3. **generate_ai_insights.php** (Priority 4)
   - **Schedule**: Every hour
   - **Purpose**: ChatGPT-powered insights
   - **Timeout**: 600 seconds (10 minutes)

### 4. **check_achievements.php** (Priority 3)
   - **Schedule**: Every 6 hours
   - **Purpose**: Award badges and achievements
   - **Timeout**: 300 seconds (5 minutes)

### 5. **refresh_store_stats.php** (Priority 2)
   - **Schedule**: Every 30 minutes
   - **Purpose**: Cache store statistics
   - **Timeout**: 180 seconds (3 minutes)

---

## 📊 MONITORING & DASHBOARDS

### Smart Cron V2 Dashboard
**URL**: `/assets/services/cron/dashboard.php`

**Features**:
- Real-time task monitoring
- Performance metrics and charts
- Execution history with search
- Task management (enable/disable)
- Alert management
- Success rate tracking

### Admin Dashboard (Standalone)
**URL**: `/modules/admin-ui/dashboard.php`

**Features**:
- Simple job listing
- Execution history
- No authentication required (for quick access)

### API Endpoints
```bash
# Get all tasks
curl http://staff.vapeshed.co.nz/assets/services/cron/dashboard.php?action=tasks

# Get system status
curl http://staff.vapeshed.co.nz/assets/services/cron/dashboard.php?action=status

# Get metrics
curl http://staff.vapeshed.co.nz/assets/services/cron/dashboard.php?action=metrics&hours=24

# Get logs
curl http://staff.vapeshed.co.nz/assets/services/cron/dashboard.php?action=logs&lines=100
```

---

## 🔍 MONITORING QUERIES

### Check Performance
```sql
SELECT * FROM vw_flagged_products_cron_performance;
```

### Check Daily Trends
```sql
SELECT * FROM vw_flagged_products_cron_daily_trends
WHERE execution_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
ORDER BY execution_date DESC;
```

### Check Health Status
```sql
SELECT * FROM vw_flagged_products_cron_health;
```

### Get Recent Executions
```sql
SELECT
    task_name,
    success,
    ROUND(execution_time, 2) as time_seconds,
    ROUND(memory_used / 1024 / 1024, 2) as memory_mb,
    created_at
FROM flagged_products_cron_metrics
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY created_at DESC
LIMIT 50;
```

### Check Failures
```sql
SELECT
    task_name,
    COUNT(*) as failure_count,
    MAX(created_at) as last_failure
FROM flagged_products_cron_metrics
WHERE success = 0
AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY task_name
ORDER BY failure_count DESC;
```

---

## 📁 FILE STRUCTURE

```
/modules/flagged_products/
├── cron/
│   ├── FlaggedProductsCronWrapper.php          ✅ NEW - Main wrapper class
│   ├── generate_daily_products_wrapped.php     🔄 NEXT - Wrapped version
│   ├── refresh_leaderboard_wrapped.php         🔄 NEXT - Wrapped version
│   ├── generate_ai_insights_wrapped.php        🔄 NEXT - Wrapped version
│   ├── check_achievements_wrapped.php          🔄 NEXT - Wrapped version
│   ├── refresh_store_stats_wrapped.php         🔄 NEXT - Wrapped version
│   ├── register_tasks.php                      📋 Task registration
│   └── lib/
│       ├── CronMonitor.php                     📊 Monitoring utilities
│       └── README.md
├── database/
│   └── cron_metrics_schema.sql                 ✅ NEW - Database schema
├── logs/
│   ├── cron-YYYY-MM-DD.log                     📝 Daily logs
│   └── cron-metrics-YYYY-MM-DD.log             📊 Metrics logs
└── README_CRON_V2.md                           ✅ NEW - This file
```

---

## ⚡ WRAPPER USAGE EXAMPLE

```php
<?php
require_once __DIR__ . '/FlaggedProductsCronWrapper.php';

// Create wrapper
$wrapper = new FlaggedProductsCronWrapper(
    'flagged_products_generate_daily_products',
    __DIR__ . '/generate_daily_products.php',
    [
        'timeout' => 600,
        'max_retries' => 3,
        'retry_delay' => 60,
        'memory_limit' => '512M',
        'enable_circuit_breaker' => true,
        'alert_on_failure' => true,
    ]
);

// Execute
$success = $wrapper->execute();

exit($success ? 0 : 1);
```

---

## 🚨 ALERTS & CIRCUIT BREAKER

### Circuit Breaker Logic
- **Opens**: After 5 failures within 1 hour
- **Effect**: Task execution skipped until circuit closes
- **Closes**: Automatically after 1 hour or manual reset

### Alert Conditions
- Task failure after all retries
- Circuit breaker opens
- Execution time exceeds threshold
- Memory usage exceeds limit

### Alert Storage
Alerts stored in `smart_cron_alerts` table and viewable in dashboard.

---

## 🔧 CONFIGURATION

### Per-Task Config
Each wrapped task can override defaults:

```php
$config = [
    'timeout' => 600,              // Max execution time (seconds)
    'max_retries' => 3,            // Number of retry attempts
    'retry_delay' => 60,           // Seconds between retries
    'memory_limit' => '512M',      // PHP memory limit
    'enable_circuit_breaker' => true,  // Enable circuit breaker
    'log_level' => 'INFO',         // Logging verbosity
    'alert_on_failure' => true,    // Send alerts on failure
];
```

---

## 📈 PERFORMANCE BENCHMARKS

Expected performance for each task:

| Task | Avg Time | Avg Memory | Success Rate |
|------|----------|------------|--------------|
| generate_daily_products | 45s | 128MB | >99% |
| refresh_leaderboard | 12s | 64MB | >99% |
| generate_ai_insights | 25s | 96MB | >95% |
| check_achievements | 8s | 48MB | >99% |
| refresh_store_stats | 5s | 32MB | >99% |

---

## ✅ TESTING

### Manual Test Run
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/flagged_products/cron

# Test wrapper (once created)
php generate_daily_products_wrapped.php
```

### Check Logs
```bash
# View today's logs
tail -f logs/cron-$(date +%Y-%m-%d).log

# View metrics
tail -f logs/cron-metrics-$(date +%Y-%m-%d).log
```

### Verify Database
```bash
mysql jcepnzzkmj -e "SELECT * FROM vw_flagged_products_cron_health"
```

---

## 🎯 READY TO PROCEED?

**Say the word and I'll create the 5 wrapped cron job files that integrate with this system!**

Each wrapped file will be a clean, professional implementation that:
1. Loads the wrapper
2. Configures task-specific settings
3. Executes with full logging and monitoring
4. Returns proper exit codes for cron

---

**Status**: 🟢 Infrastructure Complete - Ready for Wrapped Task Creation
