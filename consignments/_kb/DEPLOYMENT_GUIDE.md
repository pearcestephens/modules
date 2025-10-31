# Deployment Guide: PO Logging & Instrumentation System

**Version:** 1.0.0
**Last Updated:** October 31, 2025
**Target Environment:** CIS Production

Complete deployment checklist for Purchase Order logging infrastructure, client-side instrumentation, and transfer review system.

---

## Table of Contents

1. [Pre-Deployment Checklist](#pre-deployment-checklist)
2. [File Deployment](#file-deployment)
3. [Database Setup](#database-setup)
4. [Configuration](#configuration)
5. [Testing](#testing)
6. [Cron Jobs](#cron-jobs)
7. [Monitoring](#monitoring)
8. [Rollback Procedures](#rollback-procedures)
9. [Post-Deployment Validation](#post-deployment-validation)

---

## Pre-Deployment Checklist

### Prerequisites

- [ ] PHP 8.0+ installed and configured
- [ ] MySQL/MariaDB 10.5+ accessible
- [ ] Existing CISLogger core service operational
- [ ] Write access to `/modules/consignments/`
- [ ] Cron/systemd timer access for scheduled tasks
- [ ] Git repository access for version control

### Backups

```bash
# 1. Backup database
mysqldump -u jcepnzzkmj -p jcepnzzkmj \
  vend_consignments \
  vend_consignment_line_items \
  consignment_ai_insights \
  cis_action_log \
  cis_ai_context \
  cis_security_log \
  > backup_$(date +%Y%m%d_%H%M%S).sql

# 2. Backup purchase-orders directory
tar -czf purchase_orders_backup_$(date +%Y%m%d_%H%M%S).tar.gz \
  /modules/consignments/purchase-orders/

# 3. Backup API directory
tar -czf api_backup_$(date +%Y%m%d_%H%M%S).tar.gz \
  /modules/consignments/api/purchase-orders/
```

### Dependencies Check

```bash
# Check PHP version
php -v  # Should be 8.0+

# Check required PHP extensions
php -m | grep -E 'pdo|mysql|json|mbstring'

# Check CISLogger exists
test -f /modules/base/lib/Services/CISLogger.php && echo "CISLogger found" || echo "CISLogger missing"

# Check database connectivity
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "SELECT 1;"
```

---

## File Deployment

### Step 1: Deploy Core Logger

```bash
# Deploy PurchaseOrderLogger.php
cp -v PurchaseOrderLogger.php \
  /modules/consignments/lib/PurchaseOrderLogger.php

# Set permissions
chmod 644 /modules/consignments/lib/PurchaseOrderLogger.php

# Verify syntax
php -l /modules/consignments/lib/PurchaseOrderLogger.php
```

**Expected Output:**
```
No syntax errors detected in /modules/consignments/lib/PurchaseOrderLogger.php
```

### Step 2: Deploy Transfer Review Service

```bash
# Create Services directory if not exists
mkdir -p /modules/consignments/lib/Services

# Deploy TransferReviewService.php
cp -v TransferReviewService.php \
  /modules/consignments/lib/Services/TransferReviewService.php

# Set permissions
chmod 644 /modules/consignments/lib/Services/TransferReviewService.php

# Verify syntax
php -l /modules/consignments/lib/Services/TransferReviewService.php
```

### Step 3: Deploy CLI Scripts

```bash
# Create cli directory if not exists
mkdir -p /modules/consignments/cli

# Deploy review generation script
cp -v generate_transfer_review.php \
  /modules/consignments/cli/generate_transfer_review.php

# Deploy weekly report script
cp -v send_weekly_transfer_reports.php \
  /modules/consignments/cli/send_weekly_transfer_reports.php

# Make executable
chmod 755 /modules/consignments/cli/generate_transfer_review.php
chmod 755 /modules/consignments/cli/send_weekly_transfer_reports.php

# Verify syntax
php -l /modules/consignments/cli/generate_transfer_review.php
php -l /modules/consignments/cli/send_weekly_transfer_reports.php
```

### Step 4: Deploy Client-Side Scripts

```bash
# Deploy interaction logger
cp -v interaction-logger.js \
  /modules/consignments/purchase-orders/js/interaction-logger.js

# Deploy security monitor
cp -v security-monitor.js \
  /modules/consignments/purchase-orders/js/security-monitor.js

# Set permissions
chmod 644 /modules/consignments/purchase-orders/js/*.js

# Verify no syntax errors (if node available)
node -c /modules/consignments/purchase-orders/js/interaction-logger.js || echo "Node not available, skipping JS validation"
node -c /modules/consignments/purchase-orders/js/security-monitor.js || echo "Node not available, skipping JS validation"
```

### Step 5: Deploy API Endpoints

```bash
# Deploy log interaction endpoint
cp -v log-interaction.php \
  /modules/consignments/api/purchase-orders/log-interaction.php

# Deploy AI insight endpoints
cp -v accept-ai-insight.php \
  /modules/consignments/api/purchase-orders/accept-ai-insight.php

cp -v dismiss-ai-insight.php \
  /modules/consignments/api/purchase-orders/dismiss-ai-insight.php

cp -v bulk-accept-ai-insights.php \
  /modules/consignments/api/purchase-orders/bulk-accept-ai-insights.php

cp -v bulk-dismiss-ai-insights.php \
  /modules/consignments/api/purchase-orders/bulk-dismiss-ai-insights.php

# Set permissions
chmod 644 /modules/consignments/api/purchase-orders/*.php

# Verify syntax
for file in /modules/consignments/api/purchase-orders/*.php; do
    php -l "$file"
done
```

### Step 6: Update View Files

```bash
# Backup originals
cp /modules/consignments/purchase-orders/view.php \
   /modules/consignments/purchase-orders/view.php.backup_$(date +%Y%m%d)

cp /modules/consignments/purchase-orders/ai-insights.php \
   /modules/consignments/purchase-orders/ai-insights.php.backup_$(date +%Y%m%d)

cp /modules/consignments/purchase-orders/freight-quote.php \
   /modules/consignments/purchase-orders/freight-quote.php.backup_$(date +%Y%m%d)

# Deploy updated views
cp -v view.php /modules/consignments/purchase-orders/view.php
cp -v ai-insights.php /modules/consignments/purchase-orders/ai-insights.php
cp -v freight-quote.php /modules/consignments/purchase-orders/freight-quote.php

# Verify syntax
php -l /modules/consignments/purchase-orders/view.php
php -l /modules/consignments/purchase-orders/ai-insights.php
php -l /modules/consignments/purchase-orders/freight-quote.php
```

### Step 7: Deploy Documentation

```bash
# Create _kb directory if not exists
mkdir -p /modules/consignments/_kb

# Deploy documentation
cp -v CLIENT_INSTRUMENTATION.md \
  /modules/consignments/_kb/CLIENT_INSTRUMENTATION.md

cp -v PURCHASEORDERLOGGER_API_REFERENCE.md \
  /modules/consignments/_kb/PURCHASEORDERLOGGER_API_REFERENCE.md

# Set permissions
chmod 644 /modules/consignments/_kb/*.md
```

---

## Database Setup

### Step 1: Create Transfer Reviews Table

```sql
-- Connect to database
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj

-- Create transfer_reviews table
CREATE TABLE IF NOT EXISTS transfer_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT NOT NULL,
    outlet_id INT NOT NULL,
    user_id INT NOT NULL,

    -- Metrics
    accuracy_score DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Percentage accuracy',
    completion_time_minutes INT DEFAULT 0,
    discrepancy_count INT DEFAULT 0,

    -- Coaching
    coaching_text TEXT,
    coaching_category VARCHAR(50) COMMENT 'excellent, good, needs_improvement, critical',

    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_transfer_id (transfer_id),
    INDEX idx_outlet_id (outlet_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),

    CONSTRAINT fk_transfer_reviews_transfer
        FOREIGN KEY (transfer_id)
        REFERENCES vend_consignments(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Step 2: Create Gamification Events Table

```sql
CREATE TABLE IF NOT EXISTS gamification_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    outlet_id INT,

    -- Event details
    event_type VARCHAR(50) NOT NULL COMMENT 'transfer_completed, accuracy_milestone, speed_bonus',
    points INT DEFAULT 0,
    badge VARCHAR(50),

    -- Context
    related_id INT COMMENT 'transfer_id or other entity',
    metadata JSON COMMENT 'Additional event data',

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user_id (user_id),
    INDEX idx_outlet_id (outlet_id),
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Step 3: Verify Table Creation

```sql
-- Check tables exist
SHOW TABLES LIKE 'transfer_reviews';
SHOW TABLES LIKE 'gamification_events';

-- Check table structure
DESCRIBE transfer_reviews;
DESCRIBE gamification_events;

-- Test insert (will rollback)
START TRANSACTION;
INSERT INTO transfer_reviews (transfer_id, outlet_id, user_id, accuracy_score)
VALUES (1, 1, 1, 95.5);
SELECT * FROM transfer_reviews WHERE id = LAST_INSERT_ID();
ROLLBACK;
```

---

## Configuration

### Step 1: Security Monitor Thresholds

Edit `/modules/consignments/purchase-orders/js/security-monitor.js`:

```javascript
// Production thresholds
const config = {
    rapidKeyboardThreshold: 8,      // keys/second
    copyPasteThreshold: 3,           // paste events
    focusLossThreshold: 3,           // focus losses
    devtoolsCheckInterval: 1000      // milliseconds
};
```

**Staging/Dev:**
```javascript
// More lenient for development
const config = {
    rapidKeyboardThreshold: 15,      // keys/second
    copyPasteThreshold: 10,          // paste events
    focusLossThreshold: 10,          // focus losses
    devtoolsCheckInterval: 5000      // milliseconds
};
```

### Step 2: Transfer Review Scoring

Edit `/modules/consignments/lib/Services/TransferReviewService.php`:

```php
// Accuracy thresholds
const EXCELLENT_THRESHOLD = 98.0;  // >= 98% accuracy
const GOOD_THRESHOLD = 90.0;       // >= 90% accuracy
const NEEDS_IMPROVEMENT_THRESHOLD = 75.0;  // >= 75% accuracy
// Below 75% = critical

// Timing percentiles
const P25_MINUTES = 15;  // Fast quartile
const P50_MINUTES = 25;  // Median
const P75_MINUTES = 40;  // Slow quartile
```

### Step 3: Logging Rate Limits

Edit `/modules/consignments/api/purchase-orders/log-interaction.php`:

```php
// Rate limiting (optional)
$maxEventsPerMinute = 60;  // Adjust based on load testing
$maxBatchSize = 10;        // Maximum events per request
```

---

## Testing

### Phase 1: Unit Tests

```bash
# Test PurchaseOrderLogger
php -r "
require_once '/modules/consignments/lib/PurchaseOrderLogger.php';
\CIS\Consignments\PurchaseOrderLogger::poCreated(999999, 'Test Supplier', 'Test Outlet', 100.0);
echo 'PurchaseOrderLogger test passed\n';
"

# Test TransferReviewService
php -r "
require_once '/modules/consignments/lib/Services/TransferReviewService.php';
\$service = new \CIS\Consignments\Services\TransferReviewService(\$db);
echo 'TransferReviewService instantiated\n';
"
```

### Phase 2: API Endpoint Tests

```bash
# Test log-interaction endpoint
curl -X POST https://staff.vapeshed.co.nz/modules/consignments/api/purchase-orders/log-interaction.php \
  -H "Content-Type: application/json" \
  -d '{
    "events": [{
      "event_type": "button_clicked",
      "event_data": {"button_id": "test", "po_id": 999999},
      "page": "test",
      "timestamp": 1698765432000
    }]
  }' \
  -b cookies.txt

# Test accept-ai-insight endpoint
curl -X POST https://staff.vapeshed.co.nz/modules/consignments/api/purchase-orders/accept-ai-insight.php \
  -H "Content-Type: application/json" \
  -d '{
    "insight_id": 1,
    "po_id": 999999,
    "review_time_seconds": 30
  }' \
  -b cookies.txt
```

### Phase 3: Client-Side Tests

```bash
# Run automated test suite
cd /modules/consignments/purchase-orders
chmod +x test-instrumentation.sh
./test-instrumentation.sh
```

**Expected Output:**
```
========================================
CLIENT INSTRUMENTATION TEST SUITE
========================================

[TEST 1] JavaScript files existence
  ℹ Found: js/interaction-logger.js
  ℹ Found: js/security-monitor.js
  ℹ Found: js/ai.js
✓ PASS All JavaScript files exist

[TEST 2] API endpoint files existence
  ℹ Found: ../api/purchase-orders/log-interaction.php
  ℹ Found: ../api/purchase-orders/accept-ai-insight.php
  ...
✓ PASS All API endpoint files exist

...

========================================
TEST RESULTS
========================================
Total:  10
Passed: 10
Failed: 0

✓ ALL TESTS PASSED
```

### Phase 4: Integration Tests

```bash
# Test full PO lifecycle logging
php /modules/consignments/tests/integration/test_po_logging_lifecycle.php

# Test transfer review generation
php /modules/consignments/cli/generate_transfer_review.php 12345  # Use real transfer ID

# Test weekly reports (dry run)
php /modules/consignments/cli/send_weekly_transfer_reports.php --dry-run
```

---

## Cron Jobs

### Step 1: Add Transfer Review Generator

```bash
# Edit crontab
crontab -e

# Add entry: Run 5 minutes after transfer completion
*/5 * * * * cd /modules/consignments/cli && php generate_transfer_review.php --process-pending >> /logs/transfer_reviews.log 2>&1
```

### Step 2: Add Weekly Report Sender

```bash
# Add entry: Every Monday 8 AM NZT
0 8 * * 1 cd /modules/consignments/cli && php send_weekly_transfer_reports.php >> /logs/weekly_reports.log 2>&1
```

### Step 3: Verify Cron Entries

```bash
# List all cron jobs
crontab -l | grep -E 'transfer_review|weekly_report'

# Test manual execution
php /modules/consignments/cli/generate_transfer_review.php --help
php /modules/consignments/cli/send_weekly_transfer_reports.php --dry-run
```

---

## Monitoring

### Step 1: Log File Monitoring

```bash
# Create log directory if not exists
mkdir -p /logs

# Set up log rotation (logrotate config)
cat > /etc/logrotate.d/cis_po_logging <<'EOF'
/logs/transfer_reviews.log
/logs/weekly_reports.log
{
    daily
    rotate 30
    compress
    missingok
    notifempty
    create 0644 www-data www-data
}
EOF
```

### Step 2: Database Monitoring

```sql
-- Monitor log table growth
SELECT
    COUNT(*) as total_logs,
    DATE(created_at) as log_date
FROM cis_action_log
WHERE action LIKE 'purchase_order.%'
GROUP BY DATE(created_at)
ORDER BY log_date DESC
LIMIT 7;

-- Monitor security events
SELECT
    action,
    COUNT(*) as event_count
FROM cis_security_log
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY action
ORDER BY event_count DESC;

-- Monitor transfer reviews
SELECT
    coaching_category,
    COUNT(*) as review_count,
    AVG(accuracy_score) as avg_accuracy
FROM transfer_reviews
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY coaching_category;
```

### Step 3: Performance Monitoring

```sql
-- Monitor API call performance
SELECT
    endpoint,
    AVG(duration_seconds) as avg_duration,
    MAX(duration_seconds) as max_duration,
    COUNT(*) as call_count
FROM cis_performance_metrics
WHERE action = 'performance.api_call'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY endpoint
ORDER BY avg_duration DESC;
```

### Step 4: Alert Configuration

Create `/modules/consignments/monitoring/check_security_alerts.sh`:

```bash
#!/bin/bash
# Check for security anomalies

THRESHOLD=10  # DevTools detections per hour

COUNT=$(mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -sN -e "
    SELECT COUNT(*)
    FROM cis_security_log
    WHERE action = 'security.devtools_detected'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
")

if [ "$COUNT" -gt "$THRESHOLD" ]; then
    echo "ALERT: $COUNT DevTools detections in last hour (threshold: $THRESHOLD)"
    # Send email/Slack notification here
fi
```

---

## Rollback Procedures

### Complete Rollback

```bash
# 1. Stop cron jobs
crontab -l | grep -v 'transfer_review\|weekly_report' | crontab -

# 2. Restore backed up files
tar -xzf purchase_orders_backup_YYYYMMDD_HHMMSS.tar.gz -C /
tar -xzf api_backup_YYYYMMDD_HHMMSS.tar.gz -C /

# 3. Restore database
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj < backup_YYYYMMDD_HHMMSS.sql

# 4. Clear cache (if applicable)
rm -rf /modules/consignments/cache/*

# 5. Verify rollback
curl -I https://staff.vapeshed.co.nz/modules/consignments/purchase-orders/view.php?id=1
```

### Partial Rollback (Client-Side Only)

```bash
# Remove client-side instrumentation from views
for file in view.php ai-insights.php freight-quote.php; do
    cp /modules/consignments/purchase-orders/${file}.backup_YYYYMMDD \
       /modules/consignments/purchase-orders/${file}
done

# Verify
php -l /modules/consignments/purchase-orders/view.php
```

---

## Post-Deployment Validation

### Checklist

- [ ] All files deployed successfully
- [ ] Database tables created (transfer_reviews, gamification_events)
- [ ] Syntax checks passed
- [ ] Unit tests passed
- [ ] API endpoints responding
- [ ] Client-side scripts loading
- [ ] Cron jobs scheduled
- [ ] Logs being written
- [ ] No PHP errors in logs
- [ ] No JavaScript console errors
- [ ] Security monitoring active
- [ ] Documentation accessible

### Final Tests

```bash
# 1. Create test PO and verify logging
# (Manual test in browser)

# 2. Check recent logs
tail -f /logs/apache*.error.log | grep -i 'purchaseorderlogger\|transfer.*review'

# 3. Verify database writes
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "
    SELECT COUNT(*) as recent_logs
    FROM cis_action_log
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR);
"

# 4. Test security monitoring
# (Open view.php in browser, open DevTools, verify event logged)

# 5. Check documentation URLs
curl -I https://staff.vapeshed.co.nz/modules/consignments/_kb/CLIENT_INSTRUMENTATION.md
curl -I https://staff.vapeshed.co.nz/modules/consignments/_kb/PURCHASEORDERLOGGER_API_REFERENCE.md
```

### Success Criteria

✅ **Deployment successful if:**
- All tests pass
- No errors in logs
- Security events being captured
- Transfer reviews generating
- AI insights functional
- Performance metrics within budget
- Documentation accessible
- Rollback procedures tested

---

## Support & Troubleshooting

### Common Issues

**Issue:** PurchaseOrderLogger not logging

**Solution:**
```bash
# Check class exists
php -r "
require_once '/modules/consignments/lib/PurchaseOrderLogger.php';
var_dump(class_exists('\\CIS\\Consignments\\PurchaseOrderLogger'));
"

# Check CISLogger dependency
php -r "
require_once '/modules/base/lib/Services/CISLogger.php';
var_dump(class_exists('CISLogger'));
"
```

**Issue:** Client-side events not reaching server

**Solution:**
```javascript
// Check browser console for errors
// Verify InteractionLogger is loaded:
console.log(typeof InteractionLogger);  // Should be 'object'

// Manually trigger test event:
InteractionLogger.track({
    event_type: 'test',
    event_data: {test: true},
    page: 'manual_test'
});

// Check network tab for POST to log-interaction.php
```

**Issue:** Transfer reviews not generating

**Solution:**
```bash
# Check CLI script syntax
php -l /modules/consignments/cli/generate_transfer_review.php

# Test manual execution
php /modules/consignments/cli/generate_transfer_review.php 12345

# Check cron execution
grep 'generate_transfer_review' /var/log/syslog
```

---

## Contact

**Technical Support:** IT Department
**Documentation:** `/modules/consignments/_kb/`
**Emergency Rollback:** Use backup files + database dump

---

**Deployment Status:** [  ] Staged  [  ] Production
**Deployed By:** _________________
**Deployment Date:** _________________
**Validated By:** _________________
**Validation Date:** _________________

---

**Version:** 1.0.0
**Last Updated:** October 31, 2025
