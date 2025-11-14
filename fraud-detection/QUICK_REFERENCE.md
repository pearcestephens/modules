# Quick Reference Card
## Behavioral Fraud Detection System - At-a-Glance Guide

---

## System Access

```
Dashboard:        http://[domain]/admin/fraud-detection/dashboard
API Base URL:     http://[domain]/api/fraud-detection/
Admin Panel:      http://[domain]/admin/fraud-detection/
Logs Location:    /var/log/fraud-detection/
Config File:      /modules/fraud-detection/config/fraud-detection.config.php
```

---

## Critical Commands

### Manual Analysis
```bash
php /modules/fraud-detection/bootstrap.php daily-analysis
```
**Use when:** You need analysis outside of scheduled time (2 AM)
**Duration:** 15-45 minutes depending on staff count
**Output:** Results saved to database, alerts sent

### Check System Health
```bash
php /modules/fraud-detection/bootstrap.php health-check
```
**Checks:** Database connectivity, camera network, data sources
**Output:** Status report, any issues highlighted

### Export Report
```bash
php /modules/fraud-detection/bootstrap.php report [days]
```
**Examples:**
- `bootstrap.php report 7` → Last 7 days
- `bootstrap.php report 30` → Last 30 days
- `bootstrap.php report 365` → Last year

**Output:** Summary statistics, risk trends, incident counts

### Emergency Stop (Deactivate All Targeting)
```bash
php /modules/fraud-detection/bootstrap.php stop-all-targeting
```
**Use when:** System malfunction or false alarm
**Effect:** Immediately deactivates all active camera targeting
**Cameras:** Revert to normal recording mode

---

## API Quick Commands

### Get Dashboard Data
```bash
curl http://[domain]/api/fraud-detection/dashboard
```
**Response:** Real-time metrics, critical alerts, active targets
**Refresh Rate:** Live (5 seconds)

### Analyze Single Staff Member
```bash
curl -X POST http://[domain]/api/fraud-detection/analyze \
  -d '{"staff_id": 45}'
```
**Response:** Risk score, factors, recommendations

### Get Critical Alerts
```bash
curl http://[domain]/api/fraud-detection/alerts?severity=CRITICAL
```
**Response:** Current high-risk individuals, reasons, recommendations

### Manually Activate Camera Targeting
```bash
curl -X POST http://[domain]/api/fraud-detection/targeting/activate \
  -d '{"staff_id": 45, "duration": 120}'
```
**Parameters:**
- `staff_id` (required): Staff member to target
- `duration` (optional): Minutes to maintain targeting (default 60)

### Deactivate Targeting
```bash
curl -X POST http://[domain]/api/fraud-detection/targeting/deactivate \
  -d '{"staff_id": 45}'
```
**Effect:** Resets cameras to normal mode, stops recording for this individual

### Get Staff Profile
```bash
curl http://[domain]/api/fraud-detection/staff-profile/45
```
**Response:** Full profile with history, trends, incident record

---

## Daily Alerts

### Alert Severity Levels

| Level | Description | Action Required |
|-------|-------------|-----------------|
| **CRITICAL** | Risk score ≥0.75, multiple factors | Immediate investigation, camera targeting activated |
| **HIGH** | Risk score 0.50-0.75, trending upward | Review within 1 hour, consider investigation |
| **MEDIUM** | Risk score 0.25-0.50, watch for pattern | Monitor for trend, review weekly |
| **LOW** | Risk score <0.25, isolated incident | Log for reference, no action needed |

### What to Do With Alerts

1. **CRITICAL Alert Received:**
   - ✅ Check dashboard for details
   - ✅ Review staff profile and history
   - ✅ Check which cameras are targeting
   - ✅ Notify store manager
   - ✅ Review video evidence
   - ✅ Document findings

2. **HIGH Alert Received:**
   - ✅ Review dashboard
   - ✅ Check trend (new or escalating?)
   - ✅ Plan investigation
   - ✅ Monitor next 24-48 hours

3. **MEDIUM Alert Received:**
   - ✅ Add to investigation queue
   - ✅ Monitor in weekly report
   - ✅ Review if pattern emerges

4. **FALSE POSITIVE Identified:**
   - ✅ Document in incident notes
   - ✅ Contact development team
   - ✅ May need threshold adjustment

---

## Risk Score Breakdown

```
Risk Score Calculation:

Raw Scores (0-1 each):
├── Discount Anomalies      (15% weight) = 0-1.0
├── Void Transactions       (18% weight) = 0-1.0
├── Refund Patterns         (15% weight) = 0-1.0
├── Inventory Anomalies     (20% weight) = 0-1.0
├── After-Hours Activity    (12% weight) = 0-1.0
├── Time Fraud              (10% weight) = 0-1.0
├── Peer Comparison         (5% weight)  = 0-1.0
└── Repeat Offender         (5% weight)  = 0-1.0

Final Score = Weighted Sum of All Factors

Classification:
├── CRITICAL ≥ 0.75  → Immediate targeting
├── HIGH    0.50-0.75 → Review & monitor
├── MEDIUM  0.25-0.50 → Watch for pattern
└── LOW     < 0.25   → No action needed
```

---

## Common Issues & Quick Fixes

### "Analysis Not Running" (No 2 AM Results)

**Check:**
```bash
# 1. Verify cron job exists
crontab -l | grep fraud

# 2. Check if it ran last night
tail -50 /var/log/fraud-detection/behavioral-analytics.log

# 3. Run manually
php /modules/fraud-detection/bootstrap.php daily-analysis

# 4. Check database
mysql -u cis_user -p cis_database -e "SELECT COUNT(*) FROM behavioral_analysis_results WHERE created_at >= CURDATE();"
```

**Fix:**
- Add cron job if missing: `0 2 * * * php /path/to/bootstrap.php daily-analysis`
- Check PHP-FPM running: `systemctl status php-fpm`
- Check database connection

### "Cameras Not Moving" (Targeting Activated But No PTZ)

**Check:**
```bash
# 1. Test camera connectivity
ping [camera_ip]

# 2. Check API endpoint
curl -v http://[camera_ip]/api/status

# 3. View recent camera commands
grep "CAMERA_COMMAND" /var/log/fraud-detection/camera-targeting.log | tail -20

# 4. Check authentication
echo "Camera API Secret: [check .env]"
```

**Fix:**
- Verify camera IP address in camera_presets table
- Verify camera API secret in .env
- Restart camera if offline
- Check network firewall allowing camera API port

### "Alerts Not Sending" (No Email/SMS Received)

**Check:**
```bash
# 1. Check SMTP configuration
grep SMTP .env

# 2. Test SMTP connection
telnet mail.company.com 587

# 3. Check alert recipients
grep ALERT_EMAIL /modules/fraud-detection/config/fraud-detection.config.php

# 4. View recent alerts sent
tail -50 /var/log/fraud-detection/api.log | grep "alert"
```

**Fix:**
- Verify SMTP credentials in .env
- Add test email address: `ALERT_EMAIL_RECIPIENTS=test@company.com`
- Run manual alert test: `php /modules/fraud-detection/bootstrap.php test-alerts`
- Check email provider (Outlook, Gmail) for app password requirements

---

## Database Queries (For Manual Checking)

### View Top 10 High-Risk Staff Today
```sql
SELECT
    staff_id,
    MAX(risk_score) as max_risk,
    risk_level,
    COUNT(*) as alert_count
FROM behavioral_analysis_results
WHERE created_at >= CURDATE()
GROUP BY staff_id
ORDER BY max_risk DESC
LIMIT 10;
```

### Get Currently Active Camera Targets
```sql
SELECT
    staff_id,
    activated_at,
    expires_at,
    TIMESTAMPDIFF(MINUTE, NOW(), expires_at) as minutes_remaining
FROM camera_targeting_records
WHERE status = 'ACTIVE'
ORDER BY expires_at ASC;
```

### View Recent Fraud Incidents
```sql
SELECT
    id,
    staff_id,
    incident_type,
    severity,
    status,
    created_at
FROM fraud_incidents
WHERE status IN ('OPEN', 'IN_PROGRESS')
ORDER BY created_at DESC;
```

### Get Yesterday's Fraud Report
```sql
SELECT
    DATE(created_at) as date,
    COUNT(*) as total_alerts,
    SUM(CASE WHEN risk_level = 'CRITICAL' THEN 1 ELSE 0 END) as critical_count,
    SUM(CASE WHEN risk_level = 'HIGH' THEN 1 ELSE 0 END) as high_count,
    AVG(risk_score) as avg_risk
FROM behavioral_analysis_results
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
AND created_at < CURDATE()
GROUP BY DATE(created_at);
```

---

## Phone Tree (Escalation)

```
Issue Identified
    ↓
Check Dashboard & Logs
    ├─ "It's a false positive"     → Log in system, adjust threshold
    ├─ "Staff confirmed suspicious" → Document, escalate to manager
    └─ "System error"              → See "Common Issues & Quick Fixes"
                                      ↓
                                    Still broken?
                                      ↓
                              Contact Development
```

---

## Dashboard Color Codes

```
CRITICAL (Dark Red):   Risk ≥ 0.75  – Immediate action needed
HIGH     (Orange):     Risk 0.50-0.75 – Review within 1 hour
MEDIUM   (Yellow):     Risk 0.25-0.50 – Monitor over next week
LOW      (Green):      Risk < 0.25   – No action needed

Camera Status:
🟢 Green:   Online and functioning
🟡 Yellow:  Offline or slow response
🔴 Red:     Not responding
🟠 Orange:  Command failed
```

---

## Monthly Checklist

- [ ] Review last 30 days of alerts (any patterns?)
- [ ] Check false positive rate (aim for <5%)
- [ ] Verify all cameras online (102/102)
- [ ] Test camera PTZ functionality
- [ ] Review high-risk staff individually
- [ ] Adjust risk thresholds if needed
- [ ] Run system health check
- [ ] Generate and review monthly report
- [ ] Check log file sizes (rotate if needed)
- [ ] Backup database to external storage

---

## Emergency Contacts

| Issue | Contact | Response Time |
|-------|---------|---|
| System Down | IT Manager | 15 min |
| Security Incident | Security Lead | 5 min |
| False Positive | Development | 1 hour |
| Camera Issue | Camera Support | 30 min |
| Database Issue | Database Admin | 15 min |

---

## Keyboard Shortcuts (Dashboard)

| Shortcut | Action |
|----------|--------|
| `R` | Refresh dashboard |
| `C` | Clear alerts |
| `A` | Analyze now |
| `S` | Staff search |
| `?` | Help menu |

---

## Performance Benchmarks

```
Expected System Performance:

Analysis Speed:
├── Per staff member: < 1 second
├── All 500 staff: 5-10 minutes
└── Peak hours: Queued, run at 2 AM

API Response:
├── Dashboard: < 500ms
├── Analyze: < 2s
└── Alerts: < 1s

Camera Commands:
├── PTZ move: < 2s
├── Recording start: < 1s
└── Alert send: < 5s

Database:
├── Query time: < 100ms
├── Availability: > 99.5%
└── Disk space: Monitor monthly
```

---

## Configuration Quick Changes

```env
# .env file - Common adjustments

# More aggressive fraud detection (lower thresholds)
FRAUD_DETECTION_HIGH_RISK_THRESHOLD=0.65
FRAUD_DETECTION_MEDIUM_RISK_THRESHOLD=0.40

# Less aggressive (reduce false positives)
FRAUD_DETECTION_HIGH_RISK_THRESHOLD=0.85
FRAUD_DETECTION_MEDIUM_RISK_THRESHOLD=0.60

# Longer camera tracking (from 60 to 120 minutes)
FRAUD_DETECTION_TRACKING_DURATION=120

# More alert channels
ALERT_CHANNELS=email,sms,push

# Restrict to specific staff
FRAUD_DETECTION_EXCLUDE_STAFF_IDS=1,2,3
```

**After any changes:**
```bash
php /modules/fraud-detection/bootstrap.php health-check
```

---

## One-Page System Status

```
┌─────────────────────────────────────────────────┐
│        SYSTEM STATUS DASHBOARD SNAPSHOT          │
├─────────────────────────────────────────────────┤
│ Database:           ✅ Connected (4.2s query)  │
│ Cameras:            ✅ 102/102 Online         │
│ Analysis Engine:    ✅ Last run 2:00 AM       │
│ API Endpoints:      ✅ All responding         │
│ Alerts:             ✅ Delivery 100%          │
├─────────────────────────────────────────────────┤
│ CRITICAL Alerts:    3                          │
│ HIGH Alerts:        7                          │
│ Active Targets:     2                          │
│ Investigation:      1 open                     │
├─────────────────────────────────────────────────┤
│ Next Analysis:      Tomorrow 2:00 AM           │
│ Last Backup:        Today 3:00 AM              │
│ Storage Used:       45% (18GB/40GB)           │
└─────────────────────────────────────────────────┘
```

---

## Last Resort: Full System Reset

```bash
# ⚠️ ONLY IF SYSTEM COMPLETELY BROKEN ⚠️

# 1. Stop all analysis
php /modules/fraud-detection/bootstrap.php stop-all-targeting

# 2. Back up current database
mysqldump -u cis_user -p cis_database > backup_before_reset.sql

# 3. Reinitialize database
php /modules/fraud-detection/bootstrap.php init --force

# 4. Restart services
systemctl restart php-fpm
systemctl restart mysql

# 5. Run health check
php /modules/fraud-detection/bootstrap.php health-check

# 6. Test manual analysis
php /modules/fraud-detection/bootstrap.php daily-analysis

# 7. Review logs for any errors
tail -100 /var/log/fraud-detection/behavioral-analytics.log
```

**After reset, contact development team to investigate root cause.**

---

**Keep this card handy. Laminate it. Post it in the security office.**

*Last Updated: November 14, 2025*
