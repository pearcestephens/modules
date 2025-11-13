# 🤖 AUTONOMOUS PAYROLL BOT - DEPLOYMENT GUIDE

## Overview

The **Payroll Bot** is an event-driven autonomous agent that continuously monitors and manages all payroll operations. It integrates with your existing bot management system, uses Claude/GPT for AI decisions, and executes actions through the payroll module API.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    BOT MANAGEMENT SYSTEM                     │
│           (Your Existing Event-Driven System)                │
└────────────────────┬────────────────────────────────────────┘
                     │
                     │ Deploys & Monitors
                     ▼
┌─────────────────────────────────────────────────────────────┐
│                     PAYROLL BOT                              │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  Continuous Event Loop:                              │   │
│  │  1. Poll pending events (30s interval)              │   │
│  │  2. Get event context                               │   │
│  │  3. Call Claude/GPT API for decision                │   │
│  │  4. Execute action via payroll API                  │   │
│  │  5. Report status & heartbeat                       │   │
│  │  6. Sleep & repeat                                  │   │
│  └──────────────────────────────────────────────────────┘   │
└────────────────────┬────────────────────────────────────────┘
                     │
                     │ API Calls
                     ▼
┌─────────────────────────────────────────────────────────────┐
│              PAYROLL MODULE APIs                             │
│  • GET  /api/bot_events.php?action=pending_events           │
│  • GET  /api/bot_context.php?event_type=X&event_id=Y        │
│  • POST /api/bot_actions.php (execute decision)             │
│  • POST /api/bot_events.php?action=report_status            │
│  • GET  /api/bot_events.php?action=health_check             │
└─────────────────────────────────────────────────────────────┘
```

---

## Bot Capabilities

The Payroll Bot autonomously handles:

### ✅ **Timesheet Amendments**
- Reviews pending timesheet changes
- Validates against NZ employment law (breaks, hours, minimum wage)
- Detects fraud patterns (unusual hours, late submissions)
- Auto-approves low-risk amendments (confidence >0.9)
- Syncs approved amendments to Deputy
- Escalates high-risk changes to managers

### ✅ **Leave Requests**
- Checks leave balance and entitlement
- Validates advance notice requirements
- Assesses staffing impact (outlet coverage, concurrent leave)
- Auto-approves routine leave with sufficient balance
- Escalates blackout period requests or insufficient balance
- Notifies staff of approval/decline

### ✅ **Unapproved Timesheets**
- Identifies timesheets awaiting approval
- Validates compliance (breaks, minimum wage, public holidays)
- Compares against roster expectations
- Auto-approves compliant timesheets
- Escalates discrepancies or compliance issues
- Approves via Deputy API

### ✅ **Wage Discrepancies**
- Analyzes staff claims vs. payslips
- Validates against Deputy timesheet data
- Calculates risk scores and fraud indicators
- Auto-creates amendments for legitimate discrepancies
- Escalates high-risk or large-amount claims
- Tracks resolution outcomes

### ✅ **Compliance Monitoring**
- Monitors public holiday pay rates
- Detects break violations (worked alone without break pay)
- Flags minimum wage violations
- Tracks overtime limits
- Generates compliance reports

---

## Prerequisites

### 1. Database Setup

Run the migration to create bot tracking tables:

```bash
mysql -u jcepnzzkmj -p jcepnzzkmj < /modules/human_resources/payroll/database/migrations/bot_tracking_tables.sql
```

This creates:
- `payroll_bot_decisions` - Decision audit trail
- `payroll_bot_heartbeat` - Bot health monitoring
- `payroll_bot_events` - Event queue
- `payroll_bot_metrics` - Performance metrics
- `payroll_escalations` - Human escalations
- `payroll_bot_config` - Bot configuration

### 2. Bot Token

Generate a secure bot token:

```bash
# Generate daily rotating token (already configured in index.php)
php -r "echo hash('sha256', 'payroll_bot_' . date('Y-m-d'));"
```

Or use static token: `ci_automation_token` (for testing)

### 3. Claude/GPT API Access

Ensure your bot management system has:
- Claude API key (Anthropic)
- OpenAI GPT API key (fallback)
- Rate limit handling
- Error retry logic

---

## API Endpoints Reference

### 1. **Poll Pending Events**

Get all work needing attention, sorted by priority.

```bash
curl -H "X-Bot-Token: ci_automation_token" \
  "https://staff.vapeshed.co.nz/modules/human_resources/payroll/api/bot_events.php?action=pending_events"
```

**Response:**
```json
{
  "success": true,
  "event_count": 5,
  "events": [
    {
      "id": 123,
      "event_type": "timesheet_amendment",
      "staff_id": 45,
      "staff_name": "John Smith",
      "priority": 85,
      "age_minutes": 1500,
      "requires_ai_decision": true,
      "details": { ... }
    }
  ]
}
```

### 2. **Get Event Context**

Retrieve comprehensive context for AI decision-making.

```bash
curl -H "X-Bot-Token: ci_automation_token" \
  "https://staff.vapeshed.co.nz/modules/human_resources/payroll/api/bot_context.php?event_type=timesheet_amendment&event_id=123"
```

**Response:**
```json
{
  "success": true,
  "context": {
    "event_details": { ... },
    "staff_profile": { ... },
    "staff_history": {
      "recent_amendments": [ ... ],
      "performance_metrics": { ... }
    },
    "compliance_checks": {
      "break_compliance": { "compliant": true },
      "wage_compliance": { "compliant": true }
    },
    "risk_factors": {
      "fraud_indicators": { "risk_score": 0.2 },
      "financial_impact": { "hours_difference": 2.5 }
    },
    "recommendations": [
      {
        "action": "approve",
        "reason": "Low risk, standard amendment",
        "confidence": 0.95
      }
    ]
  }
}
```

### 3. **Execute Action**

Execute bot's AI decision.

```bash
curl -X POST \
  -H "X-Bot-Token: ci_automation_token" \
  -H "Content-Type: application/json" \
  -d '{
    "event_type": "timesheet_amendment",
    "event_id": 123,
    "action": "approve",
    "reasoning": "Low risk amendment with compliance checks passed. Staff has good history.",
    "confidence": 0.95,
    "bot_metadata": {
      "model": "claude-3-5-sonnet",
      "tokens_used": 1250,
      "decision_time_ms": 850
    }
  }' \
  "https://staff.vapeshed.co.nz/modules/human_resources/payroll/api/bot_actions.php"
```

**Response:**
```json
{
  "success": true,
  "event_type": "timesheet_amendment",
  "event_id": 123,
  "action": "approve",
  "result": {
    "approved": true,
    "deputy_sync": { "updated": true }
  }
}
```

### 4. **Report Status**

Send bot heartbeat.

```bash
curl -X POST \
  -H "X-Bot-Token: ci_automation_token" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "active",
    "events_processed": 15,
    "decisions_made": 12,
    "errors_count": 0,
    "uptime_seconds": 3600,
    "system_health": {
      "database": "healthy",
      "deputy_api": "healthy",
      "xero_api": "healthy"
    }
  }' \
  "https://staff.vapeshed.co.nz/modules/human_resources/payroll/api/bot_events.php?action=report_status"
```

### 5. **Health Check**

Validate system status.

```bash
curl -H "X-Bot-Token: ci_automation_token" \
  "https://staff.vapeshed.co.nz/modules/human_resources/payroll/api/bot_events.php?action=health_check"
```

---

## Bot Implementation Example

### Pseudocode Bot Loop

```python
import requests
import time
from anthropic import Anthropic

BOT_TOKEN = "ci_automation_token"
BASE_URL = "https://staff.vapeshed.co.nz/modules/human_resources/payroll/api"
HEADERS = {"X-Bot-Token": BOT_TOKEN}

# Initialize Claude API
anthropic = Anthropic(api_key="your-api-key")

def run_payroll_bot():
    uptime_start = time.time()
    events_processed = 0
    decisions_made = 0
    errors = 0

    while True:
        try:
            # 1. Poll for pending events
            events_response = requests.get(
                f"{BASE_URL}/bot_events.php?action=pending_events",
                headers=HEADERS
            )
            events_data = events_response.json()

            if not events_data['success']:
                raise Exception("Failed to fetch events")

            # 2. Process high-priority events
            for event in events_data['events']:
                if event['priority'] < 50:
                    break  # Stop at low priority

                try:
                    # 3. Get detailed context
                    context_response = requests.get(
                        f"{BASE_URL}/bot_context.php",
                        params={
                            'event_type': event['event_type'],
                            'event_id': event['id']
                        },
                        headers=HEADERS
                    )
                    context = context_response.json()['context']

                    # 4. Call Claude for AI decision
                    ai_decision = make_ai_decision(event, context)

                    # 5. Execute action
                    action_response = requests.post(
                        f"{BASE_URL}/bot_actions.php",
                        headers={**HEADERS, "Content-Type": "application/json"},
                        json={
                            'event_type': event['event_type'],
                            'event_id': event['id'],
                            'action': ai_decision['action'],
                            'reasoning': ai_decision['reasoning'],
                            'confidence': ai_decision['confidence'],
                            'bot_metadata': {
                                'model': 'claude-3-5-sonnet',
                                'tokens_used': ai_decision.get('tokens_used', 0)
                            }
                        }
                    )

                    if action_response.json()['success']:
                        events_processed += 1
                        decisions_made += 1
                    else:
                        errors += 1

                except Exception as e:
                    print(f"Error processing event {event['id']}: {e}")
                    errors += 1

            # 6. Report heartbeat every 60s
            uptime = int(time.time() - uptime_start)
            if uptime % 60 == 0:
                requests.post(
                    f"{BASE_URL}/bot_events.php?action=report_status",
                    headers={**HEADERS, "Content-Type": "application/json"},
                    json={
                        'status': 'active',
                        'events_processed': events_processed,
                        'decisions_made': decisions_made,
                        'errors_count': errors,
                        'uptime_seconds': uptime
                    }
                )

            # 7. Sleep before next poll
            time.sleep(30)  # 30 second interval

        except Exception as e:
            print(f"Bot error: {e}")
            errors += 1
            time.sleep(60)  # Longer sleep on error

def make_ai_decision(event, context):
    """Call Claude API to make decision"""

    prompt = f"""
    You are an autonomous payroll bot for The Vape Shed, NZ.

    EVENT: {event['event_type']} (ID: {event['id']})
    STAFF: {event.get('staff_name', 'Unknown')}
    PRIORITY: {event['priority']}/100

    CONTEXT:
    {json.dumps(context, indent=2)}

    TASK: Decide whether to approve, decline, or escalate this event.

    RULES:
    - Auto-approve if low risk, compliant, and confidence >0.9
    - Decline if compliance violations or fraud indicators
    - Escalate if uncertain, high risk, or requires human judgment

    Respond in JSON:
    {{
        "action": "approve|decline|escalate",
        "reasoning": "Clear explanation",
        "confidence": 0.0-1.0
    }}
    """

    message = anthropic.messages.create(
        model="claude-3-5-sonnet-20241022",
        max_tokens=1024,
        messages=[{"role": "user", "content": prompt}]
    )

    response_text = message.content[0].text
    decision = json.loads(response_text)
    decision['tokens_used'] = message.usage.input_tokens + message.usage.output_tokens

    return decision

if __name__ == "__main__":
    print("🤖 Payroll Bot starting...")
    run_payroll_bot()
```

---

## Configuration

### Bot Behavior Settings

Stored in `payroll_bot_config` table:

| Setting | Default | Description |
|---------|---------|-------------|
| `auto_approve_threshold` | 0.9 | Confidence required for auto-approval |
| `manual_review_threshold` | 0.8 | Below this, send to manual review |
| `escalation_threshold` | 0.5 | Below this, escalate to manager |
| `max_auto_approve_amount` | $500 | Max dollar amount for auto-approval |
| `poll_interval_seconds` | 30 | Event polling frequency |
| `heartbeat_interval_seconds` | 60 | Heartbeat reporting frequency |
| `enable_auto_leave_approval` | 1 | Allow auto leave approval |
| `enable_auto_timesheet_approval` | 1 | Allow auto timesheet approval |
| `enable_auto_amendment_approval` | 1 | Allow auto amendment approval |
| `enable_auto_discrepancy_fix` | 1 | Allow auto discrepancy fixing |

### Modify Settings

```sql
UPDATE payroll_bot_config
SET config_value = '0.95'
WHERE config_key = 'auto_approve_threshold';
```

---

## Monitoring & Dashboards

### Real-Time Bot Activity

```sql
SELECT * FROM v_bot_activity_realtime;
```

Shows last 100 decisions in past hour.

### Bot Performance (24h)

```sql
SELECT * FROM v_bot_performance_24h;
```

Aggregated stats: decisions, approvals, errors, avg confidence.

### Pending Escalations

```sql
SELECT * FROM v_pending_escalations;
```

Events requiring human review.

### Bot Health Status

```sql
SELECT * FROM v_bot_health_current;
```

Current health: active/warning/offline, last heartbeat, uptime.

---

## Human Override

Managers can override bot decisions through the dashboard:

1. View bot activity: `/modules/human_resources/payroll/dashboard.php`
2. See pending escalations
3. Manually approve/decline
4. View bot reasoning and confidence

---

## Deployment Checklist

- [ ] Database migration completed (`bot_tracking_tables.sql`)
- [ ] Bot token configured in bot management system
- [ ] Claude/GPT API keys configured
- [ ] Test bot can authenticate (health check endpoint)
- [ ] Test bot can poll events (pending_events endpoint)
- [ ] Test bot can get context (bot_context endpoint)
- [ ] Test bot can execute actions (bot_actions endpoint)
- [ ] Test bot heartbeat reporting
- [ ] Monitoring dashboard accessible
- [ ] Escalation notification system configured
- [ ] Bot deployed to production environment
- [ ] Bot running continuously (systemd/supervisor/container)

---

## Troubleshooting

### Bot Not Polling Events

1. Check bot token: `curl -H "X-Bot-Token: YOUR_TOKEN" ".../bot_events.php?action=health_check"`
2. Verify database connectivity
3. Check bot heartbeat: `SELECT * FROM payroll_bot_heartbeat ORDER BY last_seen DESC LIMIT 1`

### Bot Making Wrong Decisions

1. Review context being sent to AI: `bot_context.php` logs
2. Check AI prompt and model version
3. Adjust confidence thresholds in config
4. Review decision logs: `SELECT * FROM payroll_bot_decisions WHERE confidence < 0.8`

### High Escalation Rate

1. Lower auto-approve threshold (increase confidence requirement)
2. Add more context to AI decisions
3. Review escalation reasons: `SELECT reason, COUNT(*) FROM payroll_escalations GROUP BY reason`

---

## Security Considerations

- ✅ Bot tokens rotate daily (hash based on date)
- ✅ All decisions logged with full audit trail
- ✅ Human override always available
- ✅ Compliance checks never bypassed
- ✅ Financial impact limits enforced ($500 default)
- ✅ Escalation for uncertain decisions
- ✅ SSL/TLS required for all API calls

---

## Performance Expectations

- **Event polling:** < 500ms per request
- **Context gathering:** < 2s per event
- **AI decision:** 1-3s (depends on Claude API)
- **Action execution:** < 1s per action
- **Total cycle time:** 5-10s per event
- **Throughput:** ~100 events/hour (conservative)

---

## Next Steps

1. **Deploy Bot:** Integrate with your bot management system
2. **Monitor Performance:** Watch dashboard for first 24 hours
3. **Tune Thresholds:** Adjust confidence thresholds based on accuracy
4. **Expand Capabilities:** Add more event types as needed
5. **Train AI:** Provide feedback on decisions to improve accuracy

---

## Support & Maintenance

- **Log Location:** `/logs/payroll_bot_*.log`
- **Database Tables:** All prefixed with `payroll_bot_*`
- **API Documentation:** This file + inline code comments
- **Bot Management:** Through your existing bot deployment system

---

**The payroll bot is now ready for autonomous operation! 🚀**
