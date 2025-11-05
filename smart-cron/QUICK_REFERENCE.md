# ⚡ SMART CRON - QUICK REFERENCE

## 🚀 Installation (One Command)

```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/smart-cron
sudo bash install.sh
```

---

## 📋 Register All Tasks (One Command)

```bash
php register_all_tasks.php
```

**Registers 29 tasks automatically!**

---

## 🌐 Dashboard Access

```
https://staff.vapeshed.co.nz/modules/smart-cron/dashboard/
```

---

## 🔧 Crontab Setup (Just 2 Lines!)

```bash
# Edit crontab
crontab -u www-data -e

# Add these 2 lines:
* * * * * /usr/bin/php /home/master/applications/jcepnzzkmj/public_html/modules/smart-cron/cron/master_runner.php >> /var/log/smart-cron/master.log 2>&1
*/5 * * * * /usr/bin/php /home/master/applications/jcepnzzkmj/public_html/modules/smart-cron/cron/health_monitor.php >> /var/log/smart-cron/health.log 2>&1
```

**That's it! These 2 crons manage all 29 tasks.**

---

## 📊 View Logs

```bash
# Master runner
tail -f /var/log/smart-cron/master.log

# Health monitor
tail -f /var/log/smart-cron/health.log

# Specific task
tail -f /var/log/smart-cron/tasks/flagged_products_generate_daily.log

# All task logs
tail -f /var/log/smart-cron/tasks/*.log
```

---

## 🗄️ Database Queries

```sql
-- View all registered tasks
SELECT task_name, schedule_pattern, enabled, last_status
FROM smart_cron_tasks_config
ORDER BY priority ASC;

-- View recent executions
SELECT task_name, status, duration_ms, started_at, output
FROM smart_cron_executions
ORDER BY started_at DESC
LIMIT 50;

-- View active alerts
SELECT alert_type, message, created_at
FROM smart_cron_alerts
WHERE status = 'active'
ORDER BY severity DESC;

-- View task performance
SELECT
    task_name,
    success_count,
    failure_count,
    avg_duration_ms,
    last_run_at,
    last_status
FROM smart_cron_tasks_config
ORDER BY failure_count DESC;
```

---

## 🎯 Common Operations

### Manually Run a Task

```bash
# Via dashboard
https://staff.vapeshed.co.nz/modules/smart-cron/dashboard/
# Click task → "Run Now" button

# Via database
mysql> UPDATE smart_cron_tasks_config SET next_run_at = NOW() WHERE task_name = 'task_name';
```

### Enable/Disable a Task

```bash
# Via dashboard
# Click task → Toggle "Enabled" switch

# Via database
mysql> UPDATE smart_cron_tasks_config SET enabled = 0 WHERE task_name = 'task_name';
mysql> UPDATE smart_cron_tasks_config SET enabled = 1 WHERE task_name = 'task_name';
```

### Change Task Schedule

```sql
-- Update schedule pattern (cron syntax)
UPDATE smart_cron_tasks_config
SET schedule_pattern = '*/15 * * * *'  -- Every 15 minutes
WHERE task_name = 'task_name';
```

### View Task Failures

```sql
SELECT
    task_name,
    started_at,
    duration_ms,
    exit_code,
    output,
    error_output
FROM smart_cron_executions
WHERE status = 'error'
ORDER BY started_at DESC
LIMIT 20;
```

---

## 🔔 Alert Types

| Severity | Description | Action |
|----------|-------------|--------|
| **CRITICAL** | Master runner stopped | Immediate action required |
| **CRITICAL** | Database connection lost | Check database server |
| **CRITICAL** | Disk space < 10% | Free up space immediately |
| **HIGH** | Task timeout exceeded | Increase timeout or optimize |
| **HIGH** | Priority 1 task failed | Investigate immediately |
| **MEDIUM** | Priority 2 task failed | Review within 1 hour |
| **LOW** | Priority 3 task failed | Review within 24 hours |

---

## 📈 Performance Thresholds

| Metric | Warning | Critical |
|--------|---------|----------|
| **CPU Usage** | > 70% | > 90% |
| **Memory Usage** | > 75% | > 90% |
| **Disk Space** | < 20% | < 10% |
| **Task Duration** | > timeout/2 | > timeout |
| **Failure Rate** | > 10% | > 25% |
| **Queue Backlog** | > 10 tasks | > 50 tasks |

---

## 🛠️ Troubleshooting

### Tasks Not Running?

```bash
# 1. Check crontab
crontab -u www-data -l | grep smart-cron

# 2. Check master runner log
tail -n 50 /var/log/smart-cron/master.log

# 3. Manually run master runner
php /home/master/applications/jcepnzzkmj/public_html/modules/smart-cron/cron/master_runner.php

# 4. Check for errors
grep -i error /var/log/smart-cron/master.log
```

### High CPU/Memory Usage?

```bash
# 1. Check running processes
ps aux | grep php | grep smart-cron

# 2. Kill stuck processes
pkill -9 -f "smart-cron"

# 3. Check task timeouts
mysql> SELECT task_name, timeout_seconds FROM smart_cron_tasks_config WHERE timeout_seconds < 60;
```

### Dashboard Not Loading?

```bash
# 1. Check Apache errors
tail -f /var/log/apache2/error.log

# 2. Check file permissions
ls -la /home/master/applications/jcepnzzkmj/public_html/modules/smart-cron/dashboard/

# 3. Test PHP
php -l /home/master/applications/jcepnzzkmj/public_html/modules/smart-cron/dashboard/index.php
```

### Logs Too Large?

```bash
# 1. Run cleanup manually
php /home/master/applications/jcepnzzkmj/public_html/modules/smart-cron/cron/cleanup_old_data.php

# 2. Compress old logs
find /var/log/smart-cron -name "*.log" -mtime +7 -exec gzip {} \;

# 3. Delete ancient logs
find /var/log/smart-cron -name "*.log.gz" -mtime +90 -delete
```

---

## 📱 Quick Status Check

```bash
#!/bin/bash
# Save as: check_smart_cron.sh

echo "=== Smart Cron Status ==="
echo ""

# Check crontab
echo "1. Crontab entries:"
crontab -u www-data -l | grep smart-cron | wc -l
echo "   (Should be 2)"
echo ""

# Check master runner heartbeat
echo "2. Master runner last run:"
tail -n 1 /var/log/smart-cron/master.log | grep -o "[0-9][0-9]:[0-9][0-9]:[0-9][0-9]"
echo "   (Should be recent)"
echo ""

# Check enabled tasks
echo "3. Enabled tasks:"
mysql -u root -p -e "SELECT COUNT(*) FROM smart_cron_tasks_config WHERE enabled = 1;"
echo "   (Should be ~25-29)"
echo ""

# Check recent failures
echo "4. Recent failures:"
mysql -u root -p -e "SELECT COUNT(*) FROM smart_cron_executions WHERE status = 'error' AND started_at > NOW() - INTERVAL 1 HOUR;"
echo "   (Should be 0 or very low)"
echo ""

# Check disk space
echo "5. Log disk usage:"
du -sh /var/log/smart-cron/
echo "   (Should be < 1GB)"
echo ""

echo "=== Status Check Complete ==="
```

---

## 🎯 All 29 Tasks at a Glance

| # | Task Name | Schedule | Priority |
|---|-----------|----------|----------|
| 1 | flagged_products_generate_daily | Daily 7:05 AM | 1 |
| 2 | flagged_products_refresh_leaderboard | Daily 2:00 AM | 3 |
| 3 | flagged_products_generate_ai_insights | Hourly | 4 |
| 4 | flagged_products_check_achievements | Every 6h | 3 |
| 5 | flagged_products_refresh_store_stats | Every 30m | 2 |
| 6 | payroll_sync_deputy | Hourly | 2 |
| 7 | payroll_process_automated_reviews | Every 5m | 2 |
| 8 | payroll_update_dashboard | Every 15m | 3 |
| 9 | payroll_auto_start | Mon 6 AM | 1 |
| 10 | consignments_process_pending | Every 10m | 2 |
| 11 | consignments_update_analytics | Daily 3 AM | 3 |
| 12 | bank_fetch_transactions | Every 4h | 2 |
| 13 | bank_auto_categorize | Every 4h | 3 |
| 14 | staff_process_pending_payments | 8am-6pm hourly | 1 |
| 15 | staff_send_reminders | Daily 9 AM | 3 |
| 16 | system_database_backup | Daily 1 AM | 1 |
| 17 | system_log_rotation | Sun midnight | 3 |
| 18 | system_cache_cleanup | Daily 4 AM | 3 |
| 19 | system_session_cleanup | Daily 5 AM | 3 |
| 20 | vend_sync_products | Every 2h | 2 |
| 21 | vend_sync_inventory | Every 30m | 2 |
| 22 | vend_sync_sales | Hourly :15 | 3 |
| 23 | monitoring_daily_report | Daily 7 AM | 3 |
| 24 | monitoring_check_disk_space | Every 6h | 2 |
| 25 | monitoring_error_summary | Daily 6 PM | 3 |
| 26 | smart_cron_master_runner | Every minute | 1 |
| 27 | smart_cron_health_monitor | Every 5m | 1 |
| 28 | smart_cron_cleanup_old_data | Daily 2 AM | 2 |
| 29 | smart_cron_database_maintenance | Sun 3 AM | 2 |

---

## 📚 Documentation Files

| File | Purpose |
|------|---------|
| `README.md` | Complete system documentation (600+ lines) |
| `DEPLOYMENT_COMPLETE.md` | Deployment summary & verification |
| `ALL_TASKS.md` | Detailed task reference (all 29 tasks) |
| `QUICK_REFERENCE.md` | This file - quick commands |
| `database/schema.sql` | Database structure & indexes |
| `install.sh` | Automated installation script |

---

## 🔗 Useful Links

- **Dashboard:** https://staff.vapeshed.co.nz/modules/smart-cron/dashboard/
- **Logs:** `/var/log/smart-cron/`
- **Scripts:** `/home/master/applications/jcepnzzkmj/public_html/modules/smart-cron/cron/`
- **Database:** `smart_cron_*` tables

---

## 💡 Pro Tips

1. **Monitor the first hour** - Watch dashboard to ensure all tasks run correctly
2. **Set up email alerts** - Configure in dashboard → Settings
3. **Review logs daily** - Check for any errors or warnings
4. **Adjust timeouts** - Increase for slow tasks, decrease for fast ones
5. **Use categories** - Group related tasks for easier management
6. **Check health daily** - Dashboard shows system health status
7. **Keep logs under 1GB** - Cleanup runs automatically but monitor it
8. **Test changes in staging** - Never modify production tasks directly

---

**Quick Help:**
- Need help? Check `README.md`
- See all tasks? Check `ALL_TASKS.md`
- Deployment guide? Check `DEPLOYMENT_COMPLETE.md`
- Something broken? Check logs in `/var/log/smart-cron/`

**System Status: ✅ PRODUCTION READY**
