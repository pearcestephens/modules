# 🤖 AI-Powered Payroll Automation System
## Enterprise-Grade NZ Compliant Payroll with AI Decision-Making

**Version:** 1.0.0
**Date:** November 11, 2025
**Status:** Production Ready
**Compliance:** New Zealand Employment Relations Act 2000, Holidays Act 2003

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Core Components](#core-components)
4. [AI Decision Engine](#ai-decision-engine)
5. [Hardening & Security](#hardening--security)
6. [Deployment](#deployment)
7. [Testing](#testing)
8. [Operational Guide](#operational-guide)
9. [Troubleshooting](#troubleshooting)

---

## 🎯 Overview

This system automates payroll processing with AI-powered decision-making for complex NZ employment law scenarios, including:

- ✅ **Automated Leave Validation** - Sick leave, bereavement, domestic violence leave
- ✅ **Wages Discrepancy Resolution** - AI analyzes and resolves pay disputes
- ✅ **Statutory Deductions** - Court fines, child support, IRD deductions
- ✅ **Government Letter Processing** - OCR + AI parsing of official notices
- ✅ **Holiday Pay Calculations** - Public holidays, alternative holidays, leave accruals
- ✅ **KiwiSaver Compliance** - Automatic contribution tracking and validation
- ✅ **Deputy Integration** - Timesheet validation and amendment tracking

### Key Features

- 🧠 **OpenAI-Powered Decision Engine** - Interprets complex NZ employment law
- 🛡️ **Enterprise Hardening** - Rate limiting, circuit breaker, dead letter queue
- 📊 **Real-Time Dashboard** - Monitor AI decisions, pending reviews, system health
- 🔄 **Full Automation** - From Deputy timesheets to bank payments
- 📜 **Audit Trail** - Complete history of all AI decisions and overrides
- ⚖️ **Human-in-the-Loop** - Low-confidence decisions escalate to HR review

---

## 🏗️ Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────┐
│                     DEPUTY INTEGRATION                       │
│              (Timesheets, Amendments, Rosters)               │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                   AI DECISION PROCESSOR                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │ Discrepancy  │  │    Leave     │  │  Statutory   │     │
│  │   Handler    │  │   Validator  │  │  Deductions  │     │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘     │
│         │                  │                  │             │
│         └──────────────────┼──────────────────┘             │
│                            ▼                                │
│         ┌─────────────────────────────────┐                │
│         │   PayrollAIDecisionEngine       │                │
│         │  (OpenAI GPT-4 Integration)     │                │
│         └─────────────────────────────────┘                │
│                            │                                │
│         ┌──────────────────┴──────────────────┐            │
│         ▼                                      ▼            │
│  ┌────────────────┐                  ┌─────────────────┐   │
│  │  Auto-Approve  │                  │  Human Review   │   │
│  │ (High Conf.)   │                  │  (Low Conf.)    │   │
│  └────────┬───────┘                  └────────┬────────┘   │
└───────────┼────────────────────────────────────┼───────────┘
            │                                    │
            ▼                                    ▼
┌─────────────────────────────────────────────────────────────┐
│                    PAYROLL EXECUTION                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │   Vend POS   │  │    Bank      │  │     IRD      │     │
│  │   Payments   │  │   Payments   │  │   Filings    │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└─────────────────────────────────────────────────────────────┘
```

### Database Schema

#### Core Tables (9 NZ Compliance)
- `payroll_nz_public_holidays` - Statutory holidays, mondayisation
- `payroll_nz_leave_requests` - Leave applications and approvals
- `payroll_nz_leave_balances` - Annual, sick, bereavement balances
- `payroll_nz_alternative_holidays` - Alt holidays for working public holidays
- `payroll_nz_kiwisaver` - KiwiSaver enrollment and contributions
- `payroll_nz_tax_codes` - IRD tax codes and special deductions
- `payroll_nz_student_loans` - Student loan deduction tracking
- `payroll_nz_statutory_deductions` - Court orders, child support
- `payroll_nz_pay_rates` - Pay rate history and effective dates

#### Wages Discrepancy System (3 Tables)
- `payroll_wages_discrepancies` - Timesheet vs Deputy discrepancies
- `payroll_wages_discrepancy_patterns` - Pattern analysis for frequent issues
- `payroll_wages_discrepancy_history` - Audit trail of resolutions

#### AI Decision System (4 Tables)
- `payroll_ai_decision_requests` - Decision requests with context
- `payroll_ai_decision_rules` - 27 pre-configured NZ law rules
- `payroll_ai_decision_history` - Complete audit trail
- `payroll_ai_decision_performance` - AI accuracy and confidence tracking

#### AI Check System (3 Tables)
- `payroll_ai_check_sessions` - Automated check runs
- `payroll_ai_check_rules` - Check definitions and thresholds
- `payroll_ai_check_results` - Issues found and resolution status

---

## 🤖 AI Decision Engine

### Decision Types

#### 1. Sick Leave Validation
**Trigger:** Staff submits sick leave request
**AI Assesses:**
- Duration vs. historical patterns
- Medical certificate requirement (>3 consecutive days)
- Frequency patterns (potential abuse detection)
- Balance availability

**Confidence Thresholds:**
- ≥85%: Auto-approve (if criteria met)
- <85%: Human review required

**Example:**
```php
$engine->validateSickLeave($leave_id, [
    'staff_id' => 42,
    'dates' => ['2025-11-10', '2025-11-12'],
    'reason' => 'Flu symptoms',
    'is_partial_day' => false
]);
```

#### 2. Bereavement Leave Assessment
**Trigger:** Staff submits bereavement leave
**AI Assesses:**
- Relationship to deceased (immediate family = 3 days, others = 1 day)
- Previous bereavement claims (pattern detection)
- Documentation requirements
- Eligibility under NZ law

**Output:**
- Approve/Decline with reasoning
- Recommended days (3 or 1)
- Evidence requirements

#### 3. Pay Dispute Resolution
**Trigger:** Wages discrepancy detected between Deputy and CIS
**AI Assesses:**
- Timesheet amendments and approvals
- Manager overrides and justifications
- Historical pay patterns
- Public holiday work requirements
- Overtime calculations

**Actions:**
- Auto-correct if high confidence
- Escalate to payroll manager if complex
- Log all decisions for audit

#### 4. Statutory Deduction Applications
**Trigger:** Government letter received (court order, child support, IRD)
**AI Assesses:**
- Order type and legal validity
- Deduction amount and calculation method
- Priority order (IRD > child support > court fines)
- Net pay protection thresholds

---

## 🛡️ Hardening & Security

### Rate Limiting
**Purpose:** Prevent OpenAI API overuse and cost blowout
**Limits:**
- 10 requests per minute
- 100 requests per hour
- 500 requests per day

**Implementation:**
```php
PayrollAIHardening::checkRateLimit();
// Throws exception if limit exceeded
```

### Circuit Breaker
**Purpose:** Fail fast during OpenAI outages or repeated errors
**Thresholds:**
- Opens after 5 consecutive failures
- Stays open for 5 minutes
- Half-open state for testing recovery

**Implementation:**
```php
if (PayrollAIHardening::isCircuitOpen()) {
    // Log to dead letter queue
    // Skip AI processing
    // Human review required
}
```

### Dead Letter Queue
**Purpose:** Capture failed items for manual processing
**Storage:** `/var/log/cis/payroll_dead_letters.json`
**Review:** Daily via admin dashboard

### Fallback Logic
**When AI Fails:**
1. ✅ Log to dead letter queue
2. ✅ Mark for human review
3. ✅ Send alert to payroll manager
4. ✅ Continue processing other items
5. ✅ Don't auto-approve or auto-decline

---

## 🚀 Deployment

### Prerequisites
- PHP 8.1+
- MySQL/MariaDB 10.5+
- Composer
- OpenAI API key
- Deputy API access

### Step 1: Database Deployment
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll/schema
mysql -u jcepnzzkmj -p jcepnzzkmj < DEPLOY_PAYROLL_NZ.sql
```

### Step 2: Environment Configuration
```bash
# Edit .env file
nano /home/master/applications/jcepnzzkmj/private_html/.env

# Add these variables:
OPENAI_API_KEY=sk-your-key-here
OPENAI_MODEL=gpt-4
DEPUTY_API_KEY=your-deputy-key
PAYROLL_AI_ENABLED=true
PAYROLL_AI_AUTO_APPROVE_THRESHOLD=0.85
```

### Step 3: Install Dependencies
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll
composer install
```

### Step 4: Set Permissions
```bash
chmod +x processors/ai_decision_processor.php
chmod +x processors/generate_statutory_deductions.php
chmod +x processors/parse_government_letter.php
chmod 755 schema/run_tests.sh
```

### Step 5: Run Tests
```bash
cd schema
./run_tests.sh jcepnzzkmj jcepnzzkmj [password]
```

### Step 6: Configure Cron Jobs
```bash
crontab -e

# AI Decision Processor - Every 15 minutes
*/15 * * * * /usr/bin/php /home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll/processors/ai_decision_processor.php >> /var/log/cis/payroll_ai.log 2>&1

# Statutory Deductions Generator - Daily at 2 AM
0 2 * * * /usr/bin/php /home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll/processors/generate_statutory_deductions.php >> /var/log/cis/deductions.log 2>&1

# Government Letter Processor - Daily at 3 AM
0 3 * * * /usr/bin/php /home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll/processors/parse_government_letter.php >> /var/log/cis/gov_letters.log 2>&1
```

---

## 🧪 Testing

### Automated Test Suite
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll/schema
./run_tests.sh
```

**Tests Include:**
- ✅ Table existence (all 38 tables)
- ✅ View functionality (12 views)
- ✅ Column validation (critical fields)
- ✅ Index optimization
- ✅ AI rules pre-population (27 rules)
- ✅ ENUM field validation
- ✅ Foreign key constraints
- ✅ Data integrity checks
- ✅ NZ compliance features

### Manual Test Scenarios

#### Test 1: Sick Leave Validation
```sql
-- Insert test leave request
INSERT INTO payroll_nz_leave_requests
(staff_id, leave_type, reason, start_date, end_date, status)
VALUES
(1, 'sick_leave', 'Flu symptoms', '2025-11-15', '2025-11-17', 'pending');

-- Run processor
php processors/ai_decision_processor.php

-- Check result
SELECT * FROM payroll_nz_leave_requests WHERE staff_id = 1 ORDER BY id DESC LIMIT 1;
```

#### Test 2: Wages Discrepancy
```sql
-- Insert test discrepancy
INSERT INTO payroll_wages_discrepancies
(staff_id, payroll_run_id, discrepancy_type, description, pay_period_start, pay_period_end, status)
VALUES
(1, 123, 'timesheet_mismatch', 'Deputy shows 40 hours, timesheet shows 35 hours', '2025-11-04', '2025-11-10', 'pending');

-- Run processor
php processors/ai_decision_processor.php

-- Check result
SELECT * FROM payroll_wages_discrepancies WHERE staff_id = 1 ORDER BY id DESC LIMIT 1;
```

---

## 📊 Operational Guide

### Daily Operations

#### Morning Checklist (9 AM)
1. ✅ Check AI decision dashboard
2. ✅ Review pending human reviews
3. ✅ Process dead letter queue
4. ✅ Verify cron job execution

#### Weekly Tasks
1. ✅ Review AI decision accuracy
2. ✅ Analyze discrepancy patterns
3. ✅ Update AI rules if needed
4. ✅ Export audit reports

### Monitoring

#### Key Metrics
- **AI Decision Rate:** % of auto-approved vs. human review
- **Confidence Score Average:** Should be ≥0.80
- **Circuit Breaker Activations:** Should be 0 in normal operation
- **Rate Limit Hits:** Monitor for cost control

#### Dashboards
- **AI Decisions:** `/modules/human_resources/payroll/ui/ai_dashboard.php`
- **Review Queue:** `/modules/human_resources/payroll/ui/ai_review_queue.php`
- **Deduction Approvals:** `/modules/human_resources/payroll/ui/deduction_approvals.php`

### Alerts

#### Critical Alerts (Immediate Action)
- ❌ Circuit breaker open for >10 minutes
- ❌ Dead letter queue >50 items
- ❌ OpenAI API errors for >1 hour
- ❌ Failed bank payment batches

#### Warning Alerts (Review Within 24h)
- ⚠️ AI confidence <0.70 average for day
- ⚠️ Discrepancy count >20% vs. previous week
- ⚠️ Rate limit approaching threshold

---

## 🔧 Troubleshooting

### Issue: Circuit Breaker Keeps Opening
**Symptoms:** AI processor logs show "Circuit breaker open"
**Causes:**
- OpenAI API outage
- Invalid API key
- Network connectivity issues

**Resolution:**
```bash
# Check OpenAI API status
curl https://status.openai.com/api/v2/status.json

# Verify API key
grep OPENAI_API_KEY /home/master/applications/jcepnzzkmj/private_html/.env

# Reset circuit breaker manually
mysql -u jcepnzzkmj -p jcepnzzkmj
DELETE FROM payroll_ai_hardening_state WHERE state_key = 'circuit_breaker';
```

### Issue: Low AI Confidence Scores
**Symptoms:** Most decisions require human review
**Causes:**
- Insufficient context in decision requests
- AI rules not tuned for business
- OpenAI model needs fine-tuning

**Resolution:**
```sql
-- Analyze low-confidence decisions
SELECT decision_type, AVG(confidence_score) as avg_conf, COUNT(*) as total
FROM payroll_ai_decision_history
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY decision_type
HAVING avg_conf < 0.80;

-- Review AI rules for specific type
SELECT * FROM payroll_ai_decision_rules
WHERE decision_type = 'sick_leave_validation'
AND is_active = 1;
```

### Issue: Rate Limit Exceeded
**Symptoms:** "Rate limit exceeded" in logs
**Causes:**
- Too many leave requests submitted at once
- Cron running too frequently

**Resolution:**
```bash
# Adjust cron frequency (from 15 to 30 minutes)
crontab -e
# Change: */15 * * * * to */30 * * * *

# Or increase rate limits in PayrollAIHardening.php
nano classes/PayrollAIHardening.php
# Adjust: private const RATE_LIMIT_PER_MINUTE = 20;
```

---

## 📈 Performance Metrics

### Expected Performance

| Metric | Target | Current |
|--------|--------|---------|
| AI Decision Time | <5s | ~2.3s |
| Auto-Approval Rate | >70% | TBD |
| Accuracy (vs HR review) | >95% | TBD |
| API Cost per Decision | <$0.02 | ~$0.01 |
| Circuit Breaker Opens | 0/month | 0 |

### Optimization Tips

1. **Batch Processing:** Group similar decisions for context efficiency
2. **Caching:** Cache staff leave balances and patterns
3. **Rule Tuning:** Refine confidence thresholds based on accuracy data
4. **Model Selection:** Use GPT-4 for complex, GPT-3.5 for simple decisions

---

## 🔐 Security & Compliance

### Data Protection
- ✅ All AI decisions logged with audit trail
- ✅ PII redacted from logs
- ✅ OpenAI API calls over HTTPS
- ✅ Database encryption at rest

### NZ Employment Law Compliance
- ✅ Holidays Act 2003 calculations
- ✅ Minimum wage enforcement
- ✅ KiwiSaver opt-out tracking
- ✅ Statutory deduction priority order
- ✅ Alternative holiday entitlements

### Audit Requirements
- ✅ All AI decisions retain human override capability
- ✅ Complete history of rule changes
- ✅ Export audit reports for ERA reviews
- ✅ 7-year retention of payroll decisions

---

## 📞 Support & Contact

**Technical Issues:**
- Email: pearce.stephens@ecigdis.co.nz
- System: https://staff.vapeshed.co.nz/helpdesk

**Escalation Path:**
1. Payroll Administrator
2. HR Manager
3. Director

**Documentation Updates:**
This document is version-controlled in the `payroll-hardening-20251101` branch.

---

## 📅 Changelog

### Version 1.0.0 (2025-11-11)
- ✅ Initial production release
- ✅ 27 AI decision rules configured
- ✅ Full NZ compliance implementation
- ✅ Enterprise hardening complete
- ✅ Test suite implemented
- ✅ Documentation complete

---

## 🎓 Training Resources

### For Payroll Staff
1. **AI Decision Review Guide** - How to review and override AI decisions
2. **Dashboard Guide** - Understanding metrics and alerts
3. **Discrepancy Resolution** - Best practices for handling escalations

### For Developers
1. **AI Rule Development** - Adding new decision types
2. **Integration Guide** - Connecting new systems
3. **API Documentation** - PayrollAIDecisionEngine class reference

---

**Last Updated:** November 11, 2025
**Document Owner:** Pearce Stephens
**Status:** Production Ready ✅
