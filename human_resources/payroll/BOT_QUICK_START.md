# 🚀 PAYROLL BOT - QUICK START GUIDE

## One-Command Setup

```bash
# 1. Create database tables
mysql -u jcepnzzkmj -p jcepnzzkmj < /home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll/database/migrations/bot_tracking_tables.sql

# 2. Test API health
curl -H "X-Bot-Token: ci_automation_token" "https://staff.vapeshed.co.nz/modules/human_resources/payroll/api/bot_events.php?action=health_check"

# 3. Check for pending events
curl -H "X-Bot-Token: ci_automation_token" "https://staff.vapeshed.co.nz/modules/human_resources/payroll/api/bot_events.php?action=pending_events"
```

**✅ If all 3 succeed, you're ready to deploy the bot!**

---

## API Endpoints (Copy-Paste Ready)

### 1. Poll Events
```bash
curl -H "X-Bot-Token: ci_automation_token" \
  "https://staff.vapeshed.co.nz/modules/human_resources/payroll/api/bot_events.php?action=pending_events"
```

### 2. Get Context
```bash
curl -H "X-Bot-Token: ci_automation_token" \
  "https://staff.vapeshed.co.nz/modules/human_resources/payroll/api/bot_context.php?event_type=timesheet_amendment&event_id=123"
```

### 3. Execute Action
```bash
curl -X POST \
  -H "X-Bot-Token: ci_automation_token" \
  -H "Content-Type: application/json" \
  -d '{
    "event_type": "timesheet_amendment",
    "event_id": 123,
    "action": "approve",
    "reasoning": "Low risk, compliant amendment",
    "confidence": 0.95
  }' \
  "https://staff.vapeshed.co.nz/modules/human_resources/payroll/api/bot_actions.php"
```

### 4. Report Status
```bash
curl -X POST \
  -H "X-Bot-Token: ci_automation_token" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "active",
    "events_processed": 10,
    "decisions_made": 8,
    "errors_count": 0
  }' \
  "https://staff.vapeshed.co.nz/modules/human_resources/payroll/api/bot_events.php?action=report_status"
```

---

## Monitoring Queries (Copy-Paste Ready)

### Current Bot Status
```sql
SELECT * FROM v_bot_health_current;
```

### Recent Bot Activity (Last Hour)
```sql
SELECT * FROM v_bot_activity_realtime;
```

### Performance Last 24h
```sql
SELECT * FROM v_bot_performance_24h;
```

### Pending Escalations
```sql
SELECT * FROM v_pending_escalations;
```

### Bot Configuration
```sql
SELECT * FROM payroll_bot_config;
```

---

## File Locations

| File | Path |
|------|------|
| Event Polling | `/modules/human_resources/payroll/api/bot_events.php` |
| Context API | `/modules/human_resources/payroll/api/bot_context.php` |
| Action Execution | `/modules/human_resources/payroll/api/bot_actions.php` |
| Database Migration | `/modules/human_resources/payroll/database/migrations/bot_tracking_tables.sql` |
| Deployment Guide | `/modules/human_resources/payroll/BOT_DEPLOYMENT_GUIDE.md` |
| Complete Summary | `/modules/human_resources/payroll/AUTONOMOUS_BOT_COMPLETE.md` |

---

## What Bot Can Do

✅ Approve timesheet amendments (with Deputy sync)
✅ Approve leave requests (with balance checks)
✅ Approve Deputy timesheets (with compliance validation)
✅ Fix wage discrepancies (auto-create amendments)
✅ Detect compliance violations (breaks, minimum wage, holidays)
✅ Identify fraud patterns (unusual hours, late submissions)
✅ Escalate high-risk items (to managers)
✅ Track all decisions (full audit trail)

---

## Key Settings

| Setting | Default | What It Does |
|---------|---------|-------------|
| `auto_approve_threshold` | 0.9 | Min confidence for auto-approval |
| `manual_review_threshold` | 0.8 | Below this = manual review |
| `max_auto_approve_amount` | $500 | Max $ amount for auto-approval |
| `poll_interval_seconds` | 30 | How often bot checks for events |
| `heartbeat_interval_seconds` | 60 | How often bot reports status |

**Change settings:**
```sql
UPDATE payroll_bot_config
SET config_value = '0.95'
WHERE config_key = 'auto_approve_threshold';
```

---

## Bot Tokens

**For testing:**
```
ci_automation_token
```

**For production (daily rotating):**
```bash
php -r "echo hash('sha256', 'payroll_bot_' . date('Y-m-d'));"
```

---

## Troubleshooting

### Bot can't authenticate
```bash
# Test token
curl -H "X-Bot-Token: YOUR_TOKEN" \
  "https://staff.vapeshed.co.nz/modules/human_resources/payroll/api/bot_events.php?action=health_check"
```

### No events appearing
```sql
-- Check for pending amendments
SELECT COUNT(*) FROM payroll_timesheet_amendments WHERE status = 'pending';

-- Check for pending leave
SELECT COUNT(*) FROM leave_requests WHERE status = 0;

-- Check for unapproved timesheets
SELECT COUNT(*) FROM deputy_timesheets WHERE approved = 0;
```

### Bot offline
```sql
-- Check last heartbeat
SELECT * FROM payroll_bot_heartbeat ORDER BY last_seen DESC LIMIT 1;

-- If last_seen > 2 minutes ago, bot is offline
```

---

## Decision Flow

```
1. Bot polls /bot_events.php?action=pending_events
2. Gets event with highest priority
3. Calls /bot_context.php to get full context
4. Sends context to Claude/GPT for AI decision
5. Executes decision via /bot_actions.php
6. Logs decision to payroll_bot_decisions
7. Reports status via /bot_events.php?action=report_status
8. Sleep 30s, repeat
```

---

## Priority Scores

| Event Type | Base | Modifiers | Max |
|-----------|------|-----------|-----|
| Amendment | 50 | +30 if >24hr, +20 if multiple, +25 if big change | 100 |
| Leave | 50 | +40 if urgent (<3 days), +25 if old request | 100 |
| Timesheet | 40 | +40 if >10 days old | 100 |
| Discrepancy | 60 | +30 if high risk, +10 if confident | 100 |

---

## Compliance Checks

✅ **Break Rules:** 30min for 5+ hrs, additional for 12+ hrs
✅ **Minimum Wage:** $23.15/hr (April 2024)
✅ **Public Holidays:** Full 2025-2026 calendar
✅ **Annual Leave:** 4 weeks after 12 months
✅ **Sick Leave:** 10 days per year
✅ **Working Alone:** Break pay required

---

## Performance Targets

- **Polling:** < 500ms
- **Context:** < 2s
- **AI Decision:** 1-3s
- **Execution:** < 1s
- **Total:** 5-10s per event
- **Throughput:** ~100 events/hour

---

## Human Dashboard

View bot activity:
```
https://staff.vapeshed.co.nz/modules/human_resources/payroll/dashboard.php
```

See:
- Recent bot decisions
- Pending escalations
- Bot health status
- Performance metrics
- Manual override option

---

## Emergency Stop

```sql
-- Disable all auto-approvals
UPDATE payroll_bot_config
SET config_value = '0'
WHERE config_key LIKE 'enable_auto_%';

-- Or just stop the bot process
-- (depends on your bot management system)
```

---

## Next Steps

1. ✅ Run database migration
2. ✅ Test API endpoints
3. ✅ Configure bot in your bot management system
4. ✅ Deploy bot
5. ✅ Monitor for 24 hours
6. ✅ Tune thresholds if needed

---

**Everything is ready. Just deploy the bot and watch it work! 🚀**

For full details, see:
- `BOT_DEPLOYMENT_GUIDE.md` (comprehensive guide)
- `AUTONOMOUS_BOT_COMPLETE.md` (what's been built)
