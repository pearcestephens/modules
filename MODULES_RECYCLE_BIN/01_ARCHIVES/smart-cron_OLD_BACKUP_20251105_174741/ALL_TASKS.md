# 📋 SMART CRON - ALL REGISTERED TASKS

## Complete Task Registry

This document lists **all 29 cron tasks** registered in the Smart Cron system across the entire CIS application.

---

## 📊 Task Categories

| Category | Tasks | Priority Range | Description |
|----------|-------|----------------|-------------|
| **Flagged Products** | 5 | 1-4 | Product generation, leaderboards, AI insights |
| **Payroll** | 4 | 1-2 | Deputy sync, reviews, dashboard updates |
| **Consignments** | 2 | 2-3 | Transfer processing, analytics |
| **Banking** | 2 | 2-3 | Transaction sync, auto-categorization |
| **Staff Accounts** | 2 | 1-3 | Payment processing, reminders |
| **System** | 4 | 1-3 | Backups, cleanup, maintenance |
| **Vend Sync** | 3 | 2-3 | Product/inventory/sales sync |
| **Monitoring** | 3 | 2-3 | Reports, disk checks, error summaries |

**Total: 29 tasks**

---

## 🎯 Flagged Products Module (5 tasks)

### 1. Daily Product Generation
- **Task:** `flagged_products_generate_daily`
- **Schedule:** `5 7 * * *` (Daily at 7:05 AM)
- **Script:** `/modules/flagged_products/cron/generate_daily_products.php`
- **Priority:** 1 (Critical)
- **Timeout:** 600s (10 minutes)
- **Alerts:** ✅ Notify on failure
- **Purpose:** Generate 20 smart-selected products per outlet per day

### 2. Leaderboard Refresh
- **Task:** `flagged_products_refresh_leaderboard`
- **Schedule:** `0 2 * * *` (Daily at 2:00 AM)
- **Script:** `/modules/flagged_products/cron/refresh_leaderboard.php`
- **Priority:** 3 (Medium)
- **Timeout:** 300s (5 minutes)
- **Alerts:** ✅ Notify on failure
- **Purpose:** Refresh leaderboard rankings and cache

### 3. AI Insights Generation
- **Task:** `flagged_products_generate_ai_insights`
- **Schedule:** `0 * * * *` (Every hour)
- **Script:** `/modules/flagged_products/cron/generate_ai_insights.php`
- **Priority:** 4 (Low)
- **Timeout:** 600s (10 minutes)
- **Alerts:** ❌ Silent failures
- **Purpose:** Generate AI insights using ChatGPT

### 4. Achievement Checks
- **Task:** `flagged_products_check_achievements`
- **Schedule:** `0 */6 * * *` (Every 6 hours)
- **Script:** `/modules/flagged_products/cron/check_achievements.php`
- **Priority:** 3 (Medium)
- **Timeout:** 300s (5 minutes)
- **Alerts:** ❌ Silent failures
- **Purpose:** Check and award achievements/badges

### 5. Store Stats Refresh
- **Task:** `flagged_products_refresh_store_stats`
- **Schedule:** `*/30 * * * *` (Every 30 minutes)
- **Script:** `/modules/flagged_products/cron/refresh_store_stats.php`
- **Priority:** 2 (High)
- **Timeout:** 180s (3 minutes)
- **Alerts:** ❌ Silent failures
- **Purpose:** Cache store statistics for dashboards

---

## 💰 Payroll Module (4 tasks)

### 6. Deputy Timesheet Sync
- **Task:** `payroll_sync_deputy`
- **Schedule:** `0 * * * *` (Every hour)
- **Script:** `/modules/human_resources/payroll/cron/sync_deputy.php`
- **Priority:** 2 (High)
- **Timeout:** 300s (5 minutes)
- **Alerts:** ✅ Notify on failure
- **Purpose:** Sync approved amendments back to Deputy

### 7. Automated Reviews
- **Task:** `payroll_process_automated_reviews`
- **Schedule:** `*/5 * * * *` (Every 5 minutes)
- **Script:** `/modules/human_resources/payroll/cron/process_automated_reviews.php`
- **Priority:** 2 (High)
- **Timeout:** 240s (4 minutes)
- **Alerts:** ✅ Notify on failure
- **Purpose:** Process pending AI reviews

### 8. Dashboard Update
- **Task:** `payroll_update_dashboard`
- **Schedule:** `*/15 * * * *` (Every 15 minutes)
- **Script:** `/modules/human_resources/payroll/cron/update_dashboard.php`
- **Priority:** 3 (Medium)
- **Timeout:** 180s (3 minutes)
- **Alerts:** ❌ Silent failures
- **Purpose:** Update payroll dashboard statistics

### 9. Auto Start Payroll
- **Task:** `payroll_auto_start`
- **Schedule:** `0 6 * * 1` (Monday at 6:00 AM)
- **Script:** `/modules/human_resources/payroll/cron/payroll_auto_start.php`
- **Priority:** 1 (Critical)
- **Timeout:** 300s (5 minutes)
- **Alerts:** ✅ Notify on failure
- **Purpose:** Automatically start payroll periods on Monday

---

## 📦 Consignments Module (2 tasks)

### 10. Process Pending
- **Task:** `consignments_process_pending`
- **Schedule:** `*/10 * * * *` (Every 10 minutes)
- **Script:** `/modules/consignments/cron/process_pending.php`
- **Priority:** 2 (High)
- **Timeout:** 300s (5 minutes)
- **Alerts:** ✅ Notify on failure
- **Purpose:** Process pending consignments and transfers

### 11. Update Analytics
- **Task:** `consignments_update_analytics`
- **Schedule:** `0 3 * * *` (Daily at 3:00 AM)
- **Script:** `/modules/consignments/cron/update_analytics.php`
- **Priority:** 3 (Medium)
- **Timeout:** 600s (10 minutes)
- **Alerts:** ❌ Silent failures
- **Purpose:** Update consignment analytics and statistics

---

## 🏦 Bank Transactions Module (2 tasks)

### 12. Fetch Transactions
- **Task:** `bank_fetch_transactions`
- **Schedule:** `0 */4 * * *` (Every 4 hours)
- **Script:** `/modules/bank-transactions/cron/fetch_transactions.php`
- **Priority:** 2 (High)
- **Timeout:** 300s (5 minutes)
- **Alerts:** ✅ Notify on failure
- **Purpose:** Fetch latest bank transactions from Xero

### 13. Auto Categorize
- **Task:** `bank_auto_categorize`
- **Schedule:** `30 */4 * * *` (Every 4 hours, offset)
- **Script:** `/modules/bank-transactions/cron/auto_categorize.php`
- **Priority:** 3 (Medium)
- **Timeout:** 240s (4 minutes)
- **Alerts:** ❌ Silent failures
- **Purpose:** Auto-categorize transactions using AI

---

## 👥 Staff Accounts Module (2 tasks)

### 14. Process Payments
- **Task:** `staff_process_pending_payments`
- **Schedule:** `0 8-18 * * *` (Hourly, 8am-6pm)
- **Script:** `/modules/staff-accounts/cron/process_payments.php`
- **Priority:** 1 (Critical)
- **Timeout:** 300s (5 minutes)
- **Alerts:** ✅ Notify on failure
- **Purpose:** Process pending staff account payments

### 15. Send Reminders
- **Task:** `staff_send_reminders`
- **Schedule:** `0 9 * * *` (Daily at 9:00 AM)
- **Script:** `/modules/staff-accounts/cron/send_reminders.php`
- **Priority:** 3 (Medium)
- **Timeout:** 180s (3 minutes)
- **Alerts:** ❌ Silent failures
- **Purpose:** Send payment reminder notifications

---

## 🛠️ System Maintenance (4 tasks)

### 16. Database Backup
- **Task:** `system_database_backup`
- **Schedule:** `0 1 * * *` (Daily at 1:00 AM)
- **Script:** `/modules/db/cron/backup_database.php`
- **Priority:** 1 (Critical)
- **Timeout:** 900s (15 minutes)
- **Alerts:** ✅ Notify on failure
- **Purpose:** Automated database backup

### 17. Log Rotation
- **Task:** `system_log_rotation`
- **Schedule:** `0 0 * * 0` (Sunday at midnight)
- **Script:** `/modules/tools/cron/rotate_logs.php`
- **Priority:** 3 (Medium)
- **Timeout:** 300s (5 minutes)
- **Alerts:** ❌ Silent failures
- **Purpose:** Rotate and compress old log files

### 18. Cache Cleanup
- **Task:** `system_cache_cleanup`
- **Schedule:** `0 4 * * *` (Daily at 4:00 AM)
- **Script:** `/modules/base/cron/cleanup_cache.php`
- **Priority:** 3 (Medium)
- **Timeout:** 180s (3 minutes)
- **Alerts:** ❌ Silent failures
- **Purpose:** Clean expired cache entries

### 19. Session Cleanup
- **Task:** `system_session_cleanup`
- **Schedule:** `0 5 * * *` (Daily at 5:00 AM)
- **Script:** `/modules/base/cron/cleanup_sessions.php`
- **Priority:** 3 (Medium)
- **Timeout:** 120s (2 minutes)
- **Alerts:** ❌ Silent failures
- **Purpose:** Clean expired sessions

---

## 🏪 Vend/Lightspeed Sync (3 tasks)

### 20. Product Sync
- **Task:** `vend_sync_products`
- **Schedule:** `0 */2 * * *` (Every 2 hours)
- **Script:** `/modules/consignments/cron/sync_vend_products.php`
- **Priority:** 2 (High)
- **Timeout:** 600s (10 minutes)
- **Alerts:** ✅ Notify on failure
- **Purpose:** Sync product data from Vend/Lightspeed

### 21. Inventory Sync
- **Task:** `vend_sync_inventory`
- **Schedule:** `*/30 * * * *` (Every 30 minutes)
- **Script:** `/modules/consignments/cron/sync_vend_inventory.php`
- **Priority:** 2 (High)
- **Timeout:** 300s (5 minutes)
- **Alerts:** ✅ Notify on failure
- **Purpose:** Sync inventory levels from Vend

### 22. Sales Sync
- **Task:** `vend_sync_sales`
- **Schedule:** `15 * * * *` (Hourly at :15)
- **Script:** `/modules/consignments/cron/sync_vend_sales.php`
- **Priority:** 3 (Medium)
- **Timeout:** 300s (5 minutes)
- **Alerts:** ❌ Silent failures
- **Purpose:** Sync sales data from Vend for analytics

---

## 📊 Monitoring & Reporting (3 tasks)

### 23. Daily Report
- **Task:** `monitoring_daily_report`
- **Schedule:** `0 7 * * *` (Daily at 7:00 AM)
- **Script:** `/modules/tools/cron/daily_report.php`
- **Priority:** 3 (Medium)
- **Timeout:** 300s (5 minutes)
- **Alerts:** ✅ Notify on failure
- **Purpose:** Generate and email daily system report

### 24. Disk Space Check
- **Task:** `monitoring_check_disk_space`
- **Schedule:** `0 */6 * * *` (Every 6 hours)
- **Script:** `/modules/tools/cron/check_disk_space.php`
- **Priority:** 2 (High)
- **Timeout:** 60s (1 minute)
- **Alerts:** ✅ Notify on failure
- **Purpose:** Check disk space and alert if low

### 25. Error Summary
- **Task:** `monitoring_error_summary`
- **Schedule:** `0 18 * * *` (Daily at 6:00 PM)
- **Script:** `/modules/tools/cron/error_summary.php`
- **Priority:** 3 (Medium)
- **Timeout:** 180s (3 minutes)
- **Alerts:** ❌ Silent failures
- **Purpose:** Compile error log summary and notify

---

## 🚀 Quick Registration

To register ALL tasks at once:

```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/smart-cron
php register_all_tasks.php
```

**Expected Output:**
```
═══════════════════════════════════════════════════════════════
          SMART CRON - COMPLETE TASK REGISTRATION
═══════════════════════════════════════════════════════════════

Total tasks to register: 29

[1/29] Processing: flagged_products_generate_daily... ✓ REGISTERED
[2/29] Processing: flagged_products_refresh_leaderboard... ✓ REGISTERED
...
[29/29] Processing: monitoring_error_summary... ✓ REGISTERED

════════════════════════════════════════════════════════════════
✓ New Tasks Registered: 29
✓ Existing Tasks Updated: 0
✓ Total Tasks: 29
```

---

## 📈 Task Execution Frequency

| Frequency | Count | Tasks |
|-----------|-------|-------|
| **Every 5 minutes** | 1 | Automated reviews |
| **Every 10 minutes** | 1 | Process pending consignments |
| **Every 15 minutes** | 1 | Dashboard updates |
| **Every 30 minutes** | 2 | Store stats, inventory sync |
| **Hourly** | 4 | AI insights, Deputy sync, sales sync, payments |
| **Every 2 hours** | 1 | Product sync |
| **Every 4 hours** | 2 | Bank transactions |
| **Every 6 hours** | 2 | Achievements, disk check |
| **Daily** | 11 | Backups, cleanups, reports |
| **Weekly** | 1 | Log rotation |

---

## ⚠️ Critical Tasks (Priority 1)

These tasks **MUST** run successfully:

1. **flagged_products_generate_daily** - Daily product generation
2. **payroll_auto_start** - Monday payroll kickoff
3. **staff_process_pending_payments** - Payment processing
4. **system_database_backup** - Daily backups

**All have failure notifications enabled!**

---

## 🔔 Notification Settings

Tasks with **failure alerts** (14 tasks):
- All Flagged Products except AI insights & achievements
- All Payroll tasks
- All Consignment tasks (process pending)
- All Banking tasks
- All Staff Account payment processing
- System database backup
- Monitoring tasks (report, disk space)

Tasks with **silent failures** (15 tasks):
- Lower priority maintenance
- Non-critical analytics
- Cache/cleanup operations

---

## 📝 Task Status Monitoring

View all task statuses in the dashboard:

```
https://staff.vapeshed.co.nz/modules/smart-cron/dashboard/
```

Or query directly:

```sql
SELECT
    task_name,
    enabled,
    last_run_at,
    last_status,
    avg_duration_ms,
    failure_count,
    success_count
FROM smart_cron_tasks_config
ORDER BY priority ASC, task_name ASC;
```

---

## 🛡️ Failsafe Configuration

The Smart Cron system includes:

1. **Automatic retries** - Failed tasks retry up to 3 times
2. **Timeout enforcement** - All tasks have strict time limits
3. **Health monitoring** - System checks every 5 minutes
4. **Alert notifications** - Critical failures trigger alerts
5. **Execution logs** - Full audit trail of all runs
6. **Resource monitoring** - Memory/CPU tracking per task
7. **Dead task detection** - Auto-kill hung processes

---

## 📚 Related Documentation

- [Smart Cron README](./README.md) - Complete system documentation
- [Installation Guide](./install.sh) - Setup instructions
- [Security Audit](../SMART_CRON_SECURITY_AUDIT.md) - Security hardening
- [Database Schema](./database/schema.sql) - Table structures

---

**Last Updated:** 2025-11-05
**System Version:** Smart Cron v2.0.0
**Total Tasks:** 29 across 8 categories
