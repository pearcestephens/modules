# Smart Cron 2.0 - Complete Documentation

## 🎯 **BULLETPROOF, ROBUST, RELIABLE Task Scheduling System**

Built from the ground up for **Ecigdis Limited** with enterprise-grade reliability, comprehensive logging, failsafes, and real-time monitoring.

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Installation](#installation)
4. [Configuration](#configuration)
5. [Dashboard Guide](#dashboard-guide)
6. [Task Management](#task-management)
7. [Monitoring & Logs](#monitoring--logs)
8. [Alerts & Notifications](#alerts--notifications)
9. [Backup & Recovery](#backup--recovery)
10. [Troubleshooting](#troubleshooting)
11. [API Reference](#api-reference)
12. [Best Practices](#best-practices)

---

## 🌟 Overview

### What is Smart Cron 2.0?

Smart Cron is an ultra-robust task scheduling system that goes far beyond traditional cron:

✅ **Process Isolation** - Every task runs in its own sandboxed process
✅ **Timeout Enforcement** - Hard limits prevent runaway tasks
✅ **Automatic Retries** - Failed tasks retry with exponential backoff
✅ **Complete Logging** - Every execution logged with full output capture
✅ **Real-time Monitoring** - Live dashboard with performance metrics
✅ **Health Checks** - Continuous system monitoring with alerts
✅ **Failsafe Design** - Deadlock detection, stale lock cleanup, resource limits
✅ **Alert System** - Email/Slack notifications for failures
✅ **Backup & Recovery** - Automated backups with restore procedures

### Key Features

- **Web Dashboard** - Beautiful, responsive UI with real-time updates
- **Task Priority System** - 1-10 priority levels for execution order
- **Concurrent Execution** - Run multiple tasks simultaneously with limits
- **Resource Monitoring** - Memory, CPU, disk space tracking
- **Audit Trail** - Complete history of all actions and changes
- **Rate Limiting** - Prevent abuse with configurable limits
- **Security** - Admin authentication, input validation, CSRF protection

---

## 🏗️ Architecture

### System Components

```
Smart Cron 2.0
├── Database Layer (MySQL)
│   ├── 8 Core Tables
│   ├── 4 Views for Reporting
│   ├── Stored Procedures
│   └── Auto-cleanup Events
│
├── Execution Engine
│   ├── Master Runner (runs every minute)
│   ├── Task Runner (process isolation)
│   ├── Health Monitor (runs every 5 minutes)
│   └── Cleanup Script (runs daily at 2 AM)
│
├── Core Classes
│   ├── SmartCronRunner - Task execution engine
│   ├── SmartCronLogger - Structured logging
│   ├── SmartCronHealth - System health monitoring
│   └── SmartCronAlert - Alert management
│
├── Web Dashboard
│   ├── Real-time Task Monitoring
│   ├── Execution History Viewer
│   ├── Live Log Streaming
│   ├── Alert Management
│   └── Performance Charts
│
└── Security Layer
    ├── Admin Authentication
    ├── CSRF Protection
    ├── Rate Limiting
    └── Input Validation
```

### Database Schema

**Core Tables:**
- `smart_cron_tasks_config` - Task definitions and configuration
- `smart_cron_executions` - Complete execution history
- `smart_cron_audit_log` - Security audit trail
- `smart_cron_alerts` - Alert management
- `smart_cron_health_checks` - System health records
- `smart_cron_metrics` - Performance metrics
- `smart_cron_rate_limits` - Rate limiting data
- `smart_cron_backups` - Backup metadata

**Reporting Views:**
- `smart_cron_task_performance` - Task performance summary
- `smart_cron_recent_failures` - Recent failure analysis
- `smart_cron_active_alerts` - Current alerts
- `smart_cron_system_status` - Overall system health

---

## 🚀 Installation

### Prerequisites

- PHP 7.4+ with CLI access
- MySQL 5.7+ or MariaDB 10.3+
- Web server (Apache/Nginx) with mod_rewrite
- Command-line access (SSH)
- Root/sudo access for initial setup

### Quick Install (5 Minutes)

```bash
# 1. Download/extract Smart Cron to your modules directory
cd /home/master/applications/jcepnzzkmj/public_html/modules/smart-cron

# 2. Make install script executable
chmod +x install.sh

# 3. Run installation (as root)
sudo bash install.sh

# 4. Follow the prompts to configure database
# The script will:
# - Create directories
# - Set permissions
# - Import database schema
# - Configure crontab
# - Run health checks
# - Create initial backup
```

### Manual Installation

If you prefer manual installation:

1. **Create Directories:**
```bash
sudo mkdir -p /var/log/smart-cron
sudo mkdir -p /var/run/smart-cron
sudo mkdir -p /var/backups/smart-cron
sudo chown -R www-data:www-data /var/log/smart-cron /var/run/smart-cron /var/backups/smart-cron
sudo chmod 750 /var/log/smart-cron /var/run/smart-cron /var/backups/smart-cron
```

2. **Import Database Schema:**
```bash
mysql -u your_user -p your_database < database/schema.sql
```

3. **Configure Crontab:**
```bash
crontab -u www-data -e

# Add these lines:
* * * * * /usr/bin/php /path/to/smart-cron/cron/master_runner.php >> /var/log/smart-cron/master.log 2>&1
*/5 * * * * /usr/bin/php /path/to/smart-cron/cron/health_monitor.php >> /var/log/smart-cron/health.log 2>&1
0 2 * * * /usr/bin/php /path/to/smart-cron/cron/cleanup_old_data.php >> /var/log/smart-cron/cleanup.log 2>&1
```

4. **Set Permissions:**
```bash
sudo chown -R www-data:www-data /path/to/smart-cron
sudo chmod 750 /path/to/smart-cron/cron/*.php
sudo chmod 640 /path/to/smart-cron/includes/*.php
sudo chmod 750 /path/to/smart-cron/dashboard
```

5. **Test Installation:**
```bash
# Run master runner manually
/usr/bin/php /path/to/smart-cron/cron/master_runner.php

# Check logs
tail -f /var/log/smart-cron/master-$(date +%Y-%m-%d).log
```

---

## ⚙️ Configuration

### Database Configuration

Create `/config/database.php`:

```php
<?php
// Database Connection
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'your_db_name');
define('DB_PORT', 3306);

function getDatabaseConnection(): ?mysqli
{
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

    if ($db->connect_error) {
        error_log('Smart Cron DB Connection Failed: ' . $db->connect_error);
        return null;
    }

    $db->set_charset('utf8mb4');
    return $db;
}
```

### Authentication Configuration

Create `/config/auth.php`:

```php
<?php
function isAdminAuthenticated(): bool
{
    // Implement your authentication logic
    return isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin';
}
```

### System Constants

Edit `master_runner.php` to adjust:

```php
define('SMART_CRON_MAX_EXECUTION_TIME', 55); // Seconds
define('SMART_CRON_MAX_CONCURRENT', 10);     // Max parallel tasks
define('SMART_CRON_DEADLOCK_TIMEOUT', 600);  // 10 minutes
```

---

## 📊 Dashboard Guide

### Accessing the Dashboard

Navigate to: `https://your-domain.com/modules/smart-cron/dashboard/`

### Dashboard Sections

#### 1. **Dashboard Home**
- System status indicator (healthy/unhealthy)
- Active tasks count
- Execution statistics (last hour)
- Failing tasks alert
- Critical alerts count
- Performance chart (24 hours)
- Success rate doughnut chart
- Task overview table

#### 2. **Tasks**
- View all configured tasks
- Add new tasks
- Edit existing tasks
- Enable/disable tasks
- Run tasks manually
- View task details and history

#### 3. **Execution History**
- Complete execution log
- Filter by task, status, date
- View stdout/stderr output
- Execution time statistics
- Exit codes and error messages

#### 4. **Alerts**
- Active alerts list
- Alert severity levels (critical, error, warning, info)
- Acknowledge alerts
- Resolve alerts
- View alert history

#### 5. **Live Logs**
- Real-time log viewer
- Color-coded by log level
- Auto-refresh every 30 seconds
- Search and filter logs
- Download logs

#### 6. **System Health**
- Database connectivity
- Filesystem permissions
- Disk space usage
- Memory usage
- Stuck task detection
- Critical failure monitoring

---

## 📝 Task Management

### Creating a New Task

#### Via Dashboard:
1. Navigate to "Tasks" section
2. Click "Add Task"
3. Fill in task details:
   - **Task Name**: Unique identifier (alphanumeric, underscores, hyphens)
   - **Description**: What the task does
   - **Script Path**: Full path to PHP script
   - **Schedule**: Cron pattern (e.g., `*/5 * * * *`)
   - **Priority**: 1 (highest) to 10 (lowest)
   - **Timeout**: Maximum execution time in seconds
   - **Alert Email**: Where to send failure notifications

#### Via SQL:
```sql
INSERT INTO smart_cron_tasks_config (
    task_name,
    task_description,
    task_script,
    schedule_pattern,
    priority,
    timeout_seconds,
    enabled,
    alert_on_failure,
    alert_email
) VALUES (
    'my_task_name',
    'Processes daily reports',
    '/path/to/your/script.php',
    '0 2 * * *',    -- Daily at 2 AM
    5,              -- Medium priority
    300,            -- 5 minute timeout
    1,              -- Enabled
    1,              -- Send alerts
    'admin@ecigdis.co.nz'
);
```

### Schedule Patterns (Cron Syntax)

```
 ┌───────────── minute (0 - 59)
 │ ┌───────────── hour (0 - 23)
 │ │ ┌───────────── day of month (1 - 31)
 │ │ │ ┌───────────── month (1 - 12)
 │ │ │ │ ┌───────────── day of week (0 - 6) (Sunday=0)
 │ │ │ │ │
 * * * * *
```

**Common Examples:**
- `* * * * *` - Every minute
- `*/5 * * * *` - Every 5 minutes
- `0 * * * *` - Every hour
- `0 */6 * * *` - Every 6 hours
- `0 2 * * *` - Daily at 2 AM
- `0 2 * * 0` - Weekly on Sunday at 2 AM
- `0 2 1 * *` - Monthly on the 1st at 2 AM

### Task Priority

Tasks are executed in priority order (1 = highest):

- **Priority 1-3**: Critical tasks (system health, backups)
- **Priority 4-6**: Normal tasks (data processing, reports)
- **Priority 7-10**: Low priority (cleanup, optimization)

### Failure Handling

Tasks support automatic retry with configurable settings:

```sql
UPDATE smart_cron_tasks_config
SET
    max_retries = 3,              -- Retry up to 3 times
    retry_delay_seconds = 60,     -- Wait 60 seconds between retries
    failure_threshold = 5,        -- Alert after 5 consecutive failures
    alert_on_failure = 1
WHERE task_name = 'my_task_name';
```

---

## 📈 Monitoring & Logs

### Log Files

All logs are stored in `/var/log/smart-cron/`:

- `master-YYYY-MM-DD.log` - Master runner execution logs
- `health-YYYY-MM-DD.log` - System health checks
- `cleanup-YYYY-MM-DD.log` - Data cleanup operations
- `*.log.gz` - Compressed archived logs (older than 7 days)

### Log Format

Logs are JSON-formatted for easy parsing:

```json
{
  "timestamp": "2025-11-05 14:30:45.123456",
  "level": "INFO",
  "message": "Task completed successfully: process_daily_reports",
  "context": {
    "execution_time": 12.456,
    "exit_code": 0
  },
  "pid": 12345,
  "memory": "45.2MB"
}
```

### Viewing Logs

**Via Dashboard:**
- Navigate to "Live Logs" section
- Auto-refreshes every 30 seconds
- Filter by level: DEBUG, INFO, WARNING, ERROR, CRITICAL

**Via Command Line:**
```bash
# Tail today's master log
tail -f /var/log/smart-cron/master-$(date +%Y-%m-%d).log

# View last 100 lines with colors
tail -n 100 /var/log/smart-cron/master-$(date +%Y-%m-%d).log | jq -C '.'

# Search for errors
grep ERROR /var/log/smart-cron/master-*.log

# Count executions today
grep "Task completed" /var/log/smart-cron/master-$(date +%Y-%m-%d).log | wc -l
```

### Performance Metrics

View metrics in dashboard or query directly:

```sql
-- Average execution time by task (last 24 hours)
SELECT
    task_name,
    COUNT(*) as executions,
    AVG(execution_time) as avg_time,
    MAX(execution_time) as max_time
FROM smart_cron_executions
WHERE started_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY task_name
ORDER BY avg_time DESC;

-- Success rate by task
SELECT
    task_name,
    total_executions,
    total_successes,
    ROUND((total_successes / total_executions) * 100, 2) as success_rate
FROM smart_cron_tasks_config
WHERE total_executions > 0
ORDER BY success_rate ASC;
```

---

## 🔔 Alerts & Notifications

### Alert Types

1. **task_failure** - Task execution failed
2. **task_timeout** - Task exceeded timeout
3. **consecutive_failures** - Multiple consecutive failures
4. **system_health** - System health check failed
5. **stuck_task** - Task stuck in running state
6. **disk_space** - Low disk space
7. **memory** - High memory usage

### Alert Severity Levels

- **critical** - Requires immediate attention
- **error** - Significant issue, action needed soon
- **warning** - Potential issue, monitor closely
- **info** - Informational, no action required

### Email Notifications

Configure per-task email alerts:

```sql
UPDATE smart_cron_tasks_config
SET
    alert_on_failure = 1,
    alert_email = 'admin@ecigdis.co.nz'
WHERE task_name = 'critical_task';
```

Emails are automatically sent when:
- Task fails after reaching `failure_threshold`
- Task times out
- System health check fails (critical issues)

### Slack Notifications (Optional)

Add Slack webhook URL:

```sql
UPDATE smart_cron_tasks_config
SET alert_slack_webhook = 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL'
WHERE task_name = 'important_task';
```

### Managing Alerts

**Acknowledge Alert** (marks as seen):
```sql
UPDATE smart_cron_alerts
SET acknowledged = 1, acknowledged_at = NOW(), acknowledged_by = 1
WHERE id = 123;
```

**Resolve Alert** (marks as fixed):
```sql
UPDATE smart_cron_alerts
SET resolved = 1, resolved_at = NOW()
WHERE id = 123;
```

---

## 💾 Backup & Recovery

### Automated Backups

Backups are created automatically:
- **Initial backup** - During installation
- **Daily backups** - Via cleanup script (2 AM)
- **On-demand backups** - Manual via script

Backups are stored in `/var/backups/smart-cron/`

### Manual Backup

```bash
# Full database backup
mysqldump -u user -p database_name | gzip > /var/backups/smart-cron/manual-backup-$(date +%Y%m%d-%H%M%S).sql.gz

# Backup configuration only
mysqldump -u user -p database_name smart_cron_tasks_config | gzip > /var/backups/smart-cron/config-backup-$(date +%Y%m%d-%H%M%S).sql.gz
```

### Restore from Backup

```bash
# List available backups
ls -lh /var/backups/smart-cron/

# Restore full backup
gunzip < /var/backups/smart-cron/backup-YYYYMMDD-HHMMSS.sql.gz | mysql -u user -p database_name

# Restore specific table
gunzip < /var/backups/smart-cron/config-backup.sql.gz | mysql -u user -p database_name

# Verify restore
mysql -u user -p -e "SELECT COUNT(*) FROM smart_cron_tasks_config" database_name
```

### Backup Retention

- **Daily backups**: Kept for 30 days
- **Weekly backups**: Kept for 90 days
- **Monthly backups**: Kept for 1 year

The cleanup script automatically removes old backups.

---

## 🔧 Troubleshooting

### Common Issues

#### 1. Tasks Not Running

**Symptoms:** Tasks show "never run" or last run is old

**Diagnosis:**
```bash
# Check if cron is running
systemctl status cron

# View crontab
crontab -u www-data -l

# Check master runner log for errors
tail -n 50 /var/log/smart-cron/master-$(date +%Y-%m-%d).log
```

**Solutions:**
- Verify cron daemon is running: `sudo systemctl restart cron`
- Check crontab is configured correctly
- Verify script paths are absolute and correct
- Check file permissions: `ls -la /path/to/smart-cron/cron/`

#### 2. Tasks Stuck in "Running" State

**Symptoms:** Task shows `is_running = 1` but not actually running

**Diagnosis:**
```sql
SELECT * FROM smart_cron_tasks_config WHERE is_running = 1;
```

**Solution:**
```bash
# Manual cleanup (run master runner with stale lock cleanup)
/usr/bin/php /path/to/smart-cron/cron/master_runner.php

# Or SQL fix
mysql -u user -p -e "UPDATE smart_cron_tasks_config SET is_running = 0" database_name
```

#### 3. High Consecutive Failures

**Symptoms:** Task repeatedly failing

**Diagnosis:**
```sql
SELECT task_name, consecutive_failures, error_message, stderr_output
FROM smart_cron_tasks_config t
JOIN smart_cron_executions e ON t.id = e.task_id
WHERE t.consecutive_failures > 0
ORDER BY e.started_at DESC;
```

**Solutions:**
- Check task script for bugs
- Verify script dependencies are installed
- Check file/directory permissions
- Review error output in execution logs
- Increase timeout if tasks are timing out

#### 4. Database Connection Errors

**Symptoms:** "Failed to connect to database" in logs

**Diagnosis:**
```bash
# Test MySQL connection
mysql -h localhost -u user -p -e "SELECT 1"

# Check database config
cat /path/to/config/database.php
```

**Solutions:**
- Verify database credentials are correct
- Check MySQL service is running: `systemctl status mysql`
- Test connection from PHP: `php -r "mysqli_connect('host','user','pass','db') or die(mysqli_connect_error());"`

#### 5. Disk Space Issues

**Symptoms:** Logs growing too large, low disk space warnings

**Diagnosis:**
```bash
# Check disk usage
df -h /var/log/smart-cron

# Check log sizes
du -sh /var/log/smart-cron/*

# Count log files
ls /var/log/smart-cron/*.log | wc -l
```

**Solutions:**
```bash
# Run cleanup manually
/usr/bin/php /path/to/smart-cron/cron/cleanup_old_data.php

# Compress old logs
find /var/log/smart-cron -name "*.log" -mtime +7 -exec gzip {} \;

# Remove very old compressed logs
find /var/log/smart-cron -name "*.log.gz" -mtime +90 -delete
```

### Emergency Procedures

#### Stop All Tasks Immediately
```bash
# 1. Disable cron
crontab -u www-data -r

# 2. Kill running tasks
pkill -f "smart-cron"

# 3. Clear running flags
mysql -u user -p -e "UPDATE smart_cron_tasks_config SET is_running = 0" database_name
```

#### System Recovery
```bash
# 1. Restore from latest backup
gunzip < /var/backups/smart-cron/latest.sql.gz | mysql -u user -p database_name

# 2. Clear stale locks
rm -f /var/run/smart-cron/*.lock

# 3. Reset failure counts
mysql -u user -p -e "UPDATE smart_cron_tasks_config SET consecutive_failures = 0" database_name

# 4. Re-enable cron
crontab -u www-data -e
# Add back cron jobs

# 5. Restart services
systemctl restart cron
systemctl restart apache2  # or nginx
```

---

## 📚 API Reference

### Database Tables

#### smart_cron_tasks_config
```sql
-- Core task configuration
id                    INT (PK)
task_name             VARCHAR(100) UNIQUE
task_description      VARCHAR(500)
task_script           VARCHAR(255)
schedule_pattern      VARCHAR(50)    -- Cron pattern
priority              TINYINT        -- 1-10
timeout_seconds       INT
enabled               TINYINT(1)
max_retries           TINYINT
retry_delay_seconds   INT
failure_threshold     INT
consecutive_failures  INT
alert_on_failure      TINYINT(1)
alert_email           VARCHAR(255)
is_running            TINYINT(1)
last_run_at           DATETIME
next_run_at           DATETIME
-- Statistics
total_executions      BIGINT
total_successes       BIGINT
total_failures        BIGINT
avg_execution_time    FLOAT
```

#### smart_cron_executions
```sql
-- Complete execution history
id                BIGINT (PK)
task_id           INT (FK)
task_name         VARCHAR(100)
started_at        DATETIME
completed_at      DATETIME
execution_time    FLOAT           -- Seconds
exit_code         INT
success           TINYINT(1)
stdout_output     TEXT
stderr_output     TEXT
error_message     VARCHAR(1000)
error_type        VARCHAR(100)
memory_peak_mb    INT
triggered_by      VARCHAR(50)     -- 'cron'|'manual'|'retry'
```

### PHP Classes

#### SmartCronRunner
```php
class SmartCronRunner
{
    public function __construct(mysqli $db, SmartCronLogger $logger);

    // Get tasks due for execution
    public function getDueTasks(): array;

    // Execute multiple tasks
    public function executeTasks(array $tasks): array;

    // Execute single task
    public function executeTask(array $task): array;

    // Clean stale locks
    public function cleanStaleLocks(): void;

    // Cleanup resources
    public function cleanup(): void;
}
```

#### SmartCronLogger
```php
class SmartCronLogger
{
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';

    public function __construct(string $logFile, string $logLevel = self::LEVEL_INFO);

    public function debug(string $message, array $context = []): void;
    public function info(string $message, array $context = []): void;
    public function warning(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
    public function critical(string $message, array $context = []): void;

    public static function getTail(string $logFile, int $lines = 100): array;
    public static function search(string $logFile, array $criteria, int $limit = 100): array;
}
```

#### SmartCronHealth
```php
class SmartCronHealth
{
    public function __construct(mysqli $db, SmartCronLogger $logger);

    // Run all health checks
    public function isSystemHealthy(): bool;

    // Get detected issues
    public function getIssues(): array;

    // Get system status summary
    public function getSystemStatus(): array;
}
```

#### SmartCronAlert
```php
class SmartCronAlert
{
    public function __construct(mysqli $db, ?SmartCronLogger $logger = null);

    // Create new alert
    public function createAlert(array $data): ?int;

    // Send email notification
    public function sendEmailNotification(string $email, int $alertId = null): bool;

    // Acknowledge alert
    public function acknowledgeAlert(int $alertId, int $userId): bool;

    // Resolve alert
    public function resolveAlert(int $alertId): bool;

    // Get active alerts
    public function getActiveAlerts(int $limit = 50): array;
}
```

---

## ✅ Best Practices

### Task Development

1. **Always use absolute paths**
```php
// Good
$file = '/home/master/applications/data/export.csv';

// Bad
$file = '../data/export.csv';
```

2. **Implement proper error handling**
```php
try {
    // Your task code
    processData();
    exit(0); // Success
} catch (Exception $e) {
    error_log('Task failed: ' . $e->getMessage());
    exit(1); // Failure
}
```

3. **Use exit codes correctly**
- `0` = Success
- `1` = General error
- `124` = Timeout
- `>128` = Killed by signal

4. **Log important events**
```php
error_log('Starting data import...');
// ... do work ...
error_log('Import complete: ' . $count . ' records processed');
```

5. **Handle resource cleanup**
```php
register_shutdown_function(function() {
    // Close connections, delete temp files, etc.
    if ($db) $db->close();
    if (file_exists($tempFile)) unlink($tempFile);
});
```

### Scheduling Strategy

1. **Avoid peak hours** - Schedule heavy tasks during off-peak hours (2-5 AM)
2. **Stagger similar tasks** - Don't run multiple heavy tasks at the exact same time
3. **Use appropriate priority** - Reserve priority 1-3 for critical tasks only
4. **Set realistic timeouts** - Monitor actual execution times and add 20% buffer

### Monitoring Strategy

1. **Check dashboard daily** - Review system status and alerts
2. **Monitor failure trends** - Investigate tasks with declining success rates
3. **Review logs weekly** - Look for WARNING messages that might indicate issues
4. **Test backups monthly** - Verify you can restore from backup
5. **Update documentation** - Keep task descriptions current

### Security Best Practices

1. **Restrict dashboard access** - Implement strong authentication
2. **Never expose credentials** - Use environment variables or secure config files
3. **Validate all inputs** - Especially if tasks accept parameters
4. **Regular updates** - Keep PHP, MySQL, and dependencies updated
5. **Audit access** - Review audit logs for suspicious activity

---

## 📞 Support

For issues, questions, or enhancements:

**Email:** pearce.stephens@ecigdis.co.nz
**Company:** Ecigdis Limited
**System:** Smart Cron 2.0

### Reporting Issues

When reporting an issue, include:
1. Task name and configuration
2. Recent execution logs
3. Error messages from dashboard
4. Steps to reproduce
5. Expected vs actual behavior

---

## 📄 License & Credits

**Smart Cron 2.0**
© 2025 Ecigdis Limited
Built for The Vape Shed

**Version:** 2.0
**Release Date:** November 5, 2025
**Status:** Production Ready ✅

---

**END OF DOCUMENTATION**

For installation instructions, see INSTALLATION.md
For quick reference, see QUICK_REFERENCE.md
