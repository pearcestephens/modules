# 📅 VEND SYNC MANAGER - RECOMMENDED CRON SCHEDULE

## 🎯 Quick Summary

| Frequency | Jobs | Purpose |
|-----------|------|---------|
| **Every 5 min** | Queue processing | Process webhook-triggered syncs |
| **Every 10 min** | Consignment sync | Real-time transfer updates |
| **Every 15 min** | Health check + Sales | Monitor system + critical data |
| **Every 30 min** | Inventory sync | Stock level updates |
| **Every hour** | Product sync | Catalog updates |
| **Every 6 hours** | Full sync | Complete data refresh |
| **Daily** | Cleanup + Reports | Maintenance + monitoring |

---

## 📋 PRODUCTION CRONTAB

```cron
# ═══════════════════════════════════════════════════════════════════════════
# VEND SYNC MANAGER - PRODUCTION CRON SCHEDULE
# ═══════════════════════════════════════════════════════════════════════════
# Path: /home/master/applications/jcepnzzkmj/public_html/modules/vend/cli
# User: www-data (or your PHP-FPM user)
# ═══════════════════════════════════════════════════════════════════════════

SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
VEND_CLI_PATH=/home/master/applications/jcepnzzkmj/public_html/modules/vend/cli
LOG_PATH=/var/log/vend_sync

# ┌───────────────────────────────────────────────────────────────────────────┐
# │  CRITICAL: QUEUE PROCESSING (Every 5 minutes)                             │
# │  Processes webhook-triggered jobs and failed retries                      │
# └───────────────────────────────────────────────────────────────────────────┘
*/5 * * * * cd ${VEND_CLI_PATH} && php vend-sync-manager.php queue:process --limit=100 >> ${LOG_PATH}/queue_process.log 2>&1

# ┌───────────────────────────────────────────────────────────────────────────┐
# │  HIGH PRIORITY: CONSIGNMENTS (Every 10 minutes, 7am-7pm)                  │
# │  Real-time transfer tracking during business hours                        │
# └───────────────────────────────────────────────────────────────────────────┘
*/10 7-19 * * * cd ${VEND_CLI_PATH} && php vend-sync-manager.php sync:consignments >> ${LOG_PATH}/sync_consignments.log 2>&1

# ┌───────────────────────────────────────────────────────────────────────────┐
# │  HIGH PRIORITY: SALES (Every 15 minutes, 9am-6pm)                         │
# │  Customer transactions during business hours                              │
# └───────────────────────────────────────────────────────────────────────────┘
*/15 9-18 * * * cd ${VEND_CLI_PATH} && php vend-sync-manager.php sync:sales --limit=500 >> ${LOG_PATH}/sync_sales.log 2>&1

# ┌───────────────────────────────────────────────────────────────────────────┐
# │  MEDIUM PRIORITY: INVENTORY (Every 30 minutes)                            │
# │  Stock levels across all outlets                                          │
# └───────────────────────────────────────────────────────────────────────────┘
*/30 * * * * cd ${VEND_CLI_PATH} && php vend-sync-manager.php sync:inventory >> ${LOG_PATH}/sync_inventory.log 2>&1

# ┌───────────────────────────────────────────────────────────────────────────┐
# │  MEDIUM PRIORITY: PRODUCTS (Every hour)                                   │
# │  Product catalog updates                                                  │
# └───────────────────────────────────────────────────────────────────────────┘
0 * * * * cd ${VEND_CLI_PATH} && php vend-sync-manager.php sync:products >> ${LOG_PATH}/sync_products.log 2>&1

# ┌───────────────────────────────────────────────────────────────────────────┐
# │  MEDIUM PRIORITY: CUSTOMERS (Every 2 hours)                               │
# │  Customer database updates                                                │
# └───────────────────────────────────────────────────────────────────────────┘
0 */2 * * * cd ${VEND_CLI_PATH} && php vend-sync-manager.php sync:customers >> ${LOG_PATH}/sync_customers.log 2>&1

# ┌───────────────────────────────────────────────────────────────────────────┐
# │  LOW PRIORITY: OUTLETS, CATEGORIES, BRANDS (Every 6 hours)                │
# │  Reference data (rarely changes)                                          │
# └───────────────────────────────────────────────────────────────────────────┘
0 */6 * * * cd ${VEND_CLI_PATH} && php vend-sync-manager.php sync:outlets >> ${LOG_PATH}/sync_outlets.log 2>&1
30 */6 * * * cd ${VEND_CLI_PATH} && php vend-sync-manager.php sync:categories >> ${LOG_PATH}/sync_categories.log 2>&1
0 1,7,13,19 * * * cd ${VEND_CLI_PATH} && php vend-sync-manager.php sync:brands >> ${LOG_PATH}/sync_brands.log 2>&1
0 2,8,14,20 * * * cd ${VEND_CLI_PATH} && php vend-sync-manager.php sync:suppliers >> ${LOG_PATH}/sync_suppliers.log 2>&1

# ┌───────────────────────────────────────────────────────────────────────────┐
# │  FULL SYNC: ALL ENTITIES (Every 6 hours at 00:00, 06:00, 12:00, 18:00)    │
# │  Complete data refresh - use sparingly                                    │
# └───────────────────────────────────────────────────────────────────────────┘
0 0,6,12,18 * * * cd ${VEND_CLI_PATH} && php vend-sync-manager.php sync:all >> ${LOG_PATH}/sync_all.log 2>&1

# ┌───────────────────────────────────────────────────────────────────────────┐
# │  MONITORING: HEALTH CHECK (Every 15 minutes)                              │
# │  System health monitoring and alerting                                    │
# └───────────────────────────────────────────────────────────────────────────┘
*/15 * * * * cd ${VEND_CLI_PATH} && php vend-sync-manager.php health:check --alert-on-error >> ${LOG_PATH}/health_check.log 2>&1

# ┌───────────────────────────────────────────────────────────────────────────┐
# │  MONITORING: QUEUE STATS (Every hour)                                     │
# │  Track queue performance metrics                                          │
# └───────────────────────────────────────────────────────────────────────────┘
0 * * * * cd ${VEND_CLI_PATH} && php vend-sync-manager.php queue:stats >> ${LOG_PATH}/queue_stats.log 2>&1

# ┌───────────────────────────────────────────────────────────────────────────┐
# │  MAINTENANCE: RETRY FAILED JOBS (Every hour)                              │
# │  Automatic retry of failed queue items                                    │
# └───────────────────────────────────────────────────────────────────────────┘
0 * * * * cd ${VEND_CLI_PATH} && php vend-sync-manager.php queue:process-failed >> ${LOG_PATH}/queue_retry.log 2>&1

# ┌───────────────────────────────────────────────────────────────────────────┐
# │  MAINTENANCE: CLEANUP OLD QUEUE ITEMS (Daily at 3:00 AM)                  │
# │  Remove successful items older than 30 days                               │
# └───────────────────────────────────────────────────────────────────────────┘
0 3 * * * cd ${VEND_CLI_PATH} && php vend-sync-manager.php queue:clear --days=30 >> ${LOG_PATH}/cleanup.log 2>&1

# ┌───────────────────────────────────────────────────────────────────────────┐
# │  REPORTS: DAILY SYNC STATUS (Daily at 8:00 AM)                            │
# │  Generate daily sync report                                               │
# └───────────────────────────────────────────────────────────────────────────┘
0 8 * * * cd ${VEND_CLI_PATH} && php vend-sync-manager.php audit:sync-status > ${LOG_PATH}/daily_report_$(date +\%Y\%m\%d).log 2>&1

# ┌───────────────────────────────────────────────────────────────────────────┐
# │  REPORTS: AUDIT LOGS (Daily at 8:15 AM)                                   │
# │  Export yesterday's audit logs                                            │
# └───────────────────────────────────────────────────────────────────────────┘
15 8 * * * cd ${VEND_CLI_PATH} && php vend-sync-manager.php audit:logs --limit=1000 > ${LOG_PATH}/audit_$(date +\%Y\%m\%d).log 2>&1

# ═══════════════════════════════════════════════════════════════════════════
# END OF VEND SYNC CRON SCHEDULE
# ═══════════════════════════════════════════════════════════════════════════
```

---

## 📊 SCHEDULE BREAKDOWN

### Queue Processing (CRITICAL)
```
*/5 * * * *  →  Every 5 minutes
              →  Processes webhook-triggered syncs
              →  Handles failed retries
              →  Target: < 100 items per run
```

### Consignments (HIGH)
```
*/10 7-19 * * *  →  Every 10 minutes, 7am-7pm NZT
                 →  Business hours only
                 →  Real-time transfer tracking
                 →  Critical for operations
```

### Sales (HIGH)
```
*/15 9-18 * * *  →  Every 15 minutes, 9am-6pm NZT
                 →  Peak business hours
                 →  Customer transaction data
                 →  Limit 500 per run
```

### Inventory (MEDIUM)
```
*/30 * * * *  →  Every 30 minutes
              →  Stock level synchronization
              →  All outlets
              →  Auto-update low stock alerts
```

### Products (MEDIUM)
```
0 * * * *  →  Every hour on the hour
           →  Product catalog updates
           →  Pricing changes
           →  New product additions
```

### Customers (MEDIUM)
```
0 */2 * * *  →  Every 2 hours
             →  Customer database sync
             →  Loyalty program updates
             →  Contact info changes
```

### Reference Data (LOW)
```
Outlets:     Every 6 hours (00:00, 06:00, 12:00, 18:00)
Categories:  Every 6 hours (00:30, 06:30, 12:30, 18:30)
Brands:      4× daily (01:00, 07:00, 13:00, 19:00)
Suppliers:   4× daily (02:00, 08:00, 14:00, 20:00)
```

### Full Sync (COMPREHENSIVE)
```
0 0,6,12,18 * * *  →  Every 6 hours
                    →  Complete refresh of all entities
                    →  Use sparingly (API rate limits)
                    →  Overnight (00:00) is safest
```

### Monitoring & Maintenance
```
Health Check:      Every 15 minutes
Queue Stats:       Every hour
Retry Failed:      Every hour
Cleanup:           Daily at 3:00 AM
Daily Report:      Daily at 8:00 AM
Audit Export:      Daily at 8:15 AM
```

---

## 🔧 INSTALLATION INSTRUCTIONS

### Step 1: Create Log Directory
```bash
sudo mkdir -p /var/log/vend_sync
sudo chown www-data:www-data /var/log/vend_sync
sudo chmod 755 /var/log/vend_sync
```

### Step 2: Copy Crontab
```bash
# Edit crontab for your PHP user (usually www-data or apache)
sudo crontab -e -u www-data

# Paste the schedule from above
# Save and exit
```

### Step 3: Test Single Job
```bash
# Test queue processing manually
cd /home/master/applications/jcepnzzkmj/public_html/modules/vend/cli
php vend-sync-manager.php queue:process --limit=10

# Check if it works
echo $?  # Should return 0 on success
```

### Step 4: Verify Cron Execution
```bash
# Check cron is running
sudo service cron status

# View cron logs
tail -f /var/log/syslog | grep CRON

# View vend_sync logs
tail -f /var/log/vend_sync/queue_process.log
```

### Step 5: Monitor First 24 Hours
```bash
# Watch queue processing
watch -n 10 'cd /home/master/applications/jcepnzzkmj/public_html/modules/vend/cli && php vend-sync-manager.php queue:stats'

# Check for errors
tail -f /var/log/vend_sync/*.log | grep -i error
```

---

## ⚙️ CUSTOMIZATION OPTIONS

### Adjust Frequency
```cron
# More frequent during peak hours
*/3 9-17 * * *   # Every 3 minutes, 9am-5pm

# Less frequent off-hours
*/30 0-7,19-23 * * *   # Every 30 minutes, midnight-7am and 7pm-11pm
```

### Adjust Limits
```bash
# Process more items per run (if queue builds up)
php vend-sync-manager.php queue:process --limit=500

# Process fewer items (if timeout issues)
php vend-sync-manager.php queue:process --limit=50
```

### Add Alerting
```bash
# Email on health check failure
*/15 * * * * cd ${VEND_CLI_PATH} && php vend-sync-manager.php health:check || echo "Vend Sync Health Check FAILED" | mail -s "ALERT: Vend Sync Issue" admin@example.com
```

### Log Rotation
```bash
# Add to /etc/logrotate.d/vend-sync
/var/log/vend_sync/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 0644 www-data www-data
}
```

---

## 📈 PERFORMANCE TUNING

### If Queue Backs Up (> 1000 items)
```cron
# Increase frequency
*/2 * * * *  # Every 2 minutes

# OR increase limit
php vend-sync-manager.php queue:process --limit=500
```

### If API Rate Limits Hit
```cron
# Reduce frequency
*/10 * * * *  # Every 10 minutes

# OR reduce full sync frequency
0 0,12 * * *  # Only twice daily (midnight, noon)
```

### If Database Load Too High
```cron
# Stagger job times
0 * * * *     # Products at :00
15 * * * *    # Sales at :15
30 * * * *    # Inventory at :30
45 * * * *    # Customers at :45
```

---

## 🚨 TROUBLESHOOTING

### Cron Not Running
```bash
# Check cron service
sudo systemctl status cron

# Start cron if stopped
sudo systemctl start cron

# Check crontab is loaded
sudo crontab -l -u www-data
```

### Jobs Failing
```bash
# Check PHP path
which php  # Ensure path is correct in crontab

# Check file permissions
ls -la /home/master/applications/jcepnzzkmj/public_html/modules/vend/cli/vend-sync-manager.php

# Test command manually
cd /home/master/applications/jcepnzzkmj/public_html/modules/vend/cli
php vend-sync-manager.php health:check --verbose
```

### Logs Not Writing
```bash
# Check log directory permissions
ls -la /var/log/vend_sync

# Fix permissions if needed
sudo chown -R www-data:www-data /var/log/vend_sync
sudo chmod -R 755 /var/log/vend_sync
```

### Queue Items Stuck
```bash
# View stuck items
php vend-sync-manager.php queue:view --status=pending --limit=10

# Manually retry
php vend-sync-manager.php queue:process-failed

# Clear if necessary
php vend-sync-manager.php queue:clear --status=failed --days=7
```

---

## 📝 MONITORING CHECKLIST

### Daily
- [ ] Check queue stats: `php vend-sync-manager.php queue:stats`
- [ ] Review error logs: `tail -100 /var/log/vend_sync/*.log | grep -i error`
- [ ] Verify sync status: `php vend-sync-manager.php audit:sync-status`

### Weekly
- [ ] Review daily reports in `/var/log/vend_sync/daily_report_*.log`
- [ ] Check disk space: `df -h /var/log/vend_sync`
- [ ] Verify cron execution: `grep CRON /var/log/syslog | grep vend-sync-manager`
- [ ] Test health check: `php vend-sync-manager.php health:check --verbose`

### Monthly
- [ ] Review queue success rate (target: > 99%)
- [ ] Check API rate limit usage
- [ ] Analyze slow queries
- [ ] Optimize cron schedule if needed
- [ ] Rotate/archive old logs

---

## 🎯 SUCCESS METRICS

### Queue Performance
- **Success Rate**: > 99%
- **Processing Time**: < 30 seconds per 100 items
- **Queue Size**: < 500 pending items
- **Failed Items**: < 10 in last 24 hours

### Sync Performance
- **Products**: < 2 minutes for incremental sync
- **Sales**: < 5 minutes for 500 sales
- **Inventory**: < 3 minutes for all outlets
- **Consignments**: < 1 minute for incremental sync

### System Health
- **API Errors**: < 5 per hour
- **Database Errors**: 0
- **Queue Stuck Items**: 0
- **Health Check**: Passing all tests

---

## ✅ DEPLOYMENT CHECKLIST

Before going live:

1. - [ ] API token configured in `configuration` table
2. - [ ] Database tables created (run `setup.sql`)
3. - [ ] Log directory created with correct permissions
4. - [ ] Test commands manually (success on all)
5. - [ ] Add crontab for appropriate user
6. - [ ] Verify cron is running
7. - [ ] Monitor first hour (all jobs execute)
8. - [ ] Monitor first day (no errors)
9. - [ ] Review first week (adjust schedule if needed)
10. - [ ] Document any custom changes

---

## 📞 SUPPORT

### View Help
```bash
php vend-sync-manager.php help
```

### Quick Commands
```bash
# Queue status
php vend-sync-manager.php queue:stats

# Recent errors
php vend-sync-manager.php audit:logs --status=error --limit=20

# Health check
php vend-sync-manager.php health:check --verbose

# Test connection
php vend-sync-manager.php test:connection
```

### Emergency Stop
```bash
# Disable all cron jobs temporarily
sudo crontab -e -u www-data
# Comment out all vend-sync lines with #

# Re-enable later by uncommenting
```

---

**Last Updated**: November 8, 2025
**Version**: 1.0.0
**System**: Vend Sync Manager - Production Cron Schedule
