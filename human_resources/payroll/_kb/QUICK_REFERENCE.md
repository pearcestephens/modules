# 🎯 PAYROLL MODULE - QUICK REFERENCE CARD

**Last Updated:** 2025-11-02 23:20 NZDT
**Progress:** 8% Complete (Phase 0 ✅)

---

## 📍 LOCATION
```
/home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll/
```

---

## 🗂️ KEY FILES

### Configuration
- `config.php` - Central config (week start, thresholds, caps)
- `bootstrap.php` - Environment setup (timezone, autoloader)
- `autoload.php` - PSR-4 loader for `Payroll\` namespace

### Core Libraries (`lib/`)
- `Respond.php` - JSON response helper
- `Validate.php` - Input validation
- `Idempotency.php` - Duplicate prevention keys
- `ErrorEnvelope.php` - Exception normalization
- `PayrollLogger.php` - Structured logging

### Services (`services/`)
- `PayrollDeputyService.php` ✅ PRODUCTION READY
- `PayrollXeroService.php` 🚧 STUB
- `BonusService.php` 🚧 PARTIAL
- `VendService.php` ✅ READY
- `PayslipCalculationEngine.php` ✅ READY

### Database
- Schema: `schema/*.sql`, `_schema/*.sql`
- Migrations: `migrations/` (empty - need Phase 1)

### Documentation (`_kb/`)
- `PAYROLL_DEEP_DIVE_ANALYSIS.md` - Complete system analysis
- `SESSION_SUMMARY.md` - What we built
- `QUICK_REFERENCE.md` - This file

---

## 🗄️ DATABASE

### Connection
```php
Host: 127.0.0.1:3306
Database: jcepnzzkmj
User: jcepnzzkmj
Pass: wprKh9Jq63
```

### Key Tables (Existing)
- `payroll_activity_log` - Centralized logging
- `payroll_rate_limits` - API 429 tracking
- `payroll_timesheet_amendments` - Timesheet corrections
- `payroll_ai_decisions` - AI audit trail
- `staff_identity_map` - Cross-system staff IDs

### Missing Tables (Phase 1)
- `payroll_runs` ❌
- `payroll_applications` ❌
- `payroll_dlq` ❌
- `payroll_residuals` ❌
- `staff_leave_balances` ❌
- `payroll_bonus_events` ❌

---

## 🔑 KEY CONCEPTS

### Idempotency
```php
use Payroll\Lib\Idempotency;

$key = Idempotency::keyFor('xero.apply', [
    'run' => 'PR_2025_10_27',
    'emp' => 'E123',
    'cents' => 45000
]);
// Same inputs → same key → prevents duplicates
```

### Error Handling
```php
use Payroll\Lib\ErrorEnvelope;

try {
    // operation
} catch (Throwable $e) {
    $envelope = ErrorEnvelope::from($e, ['context' => 'extra']);
    // Insert to payroll_dlq for retry
}
```

### Response Format
```php
use Payroll\Lib\Respond;

Respond::ok(['data' => $result]);
Respond::fail('INVALID_INPUT', 'Bad date format', ['field' => 'start_date']);
```

---

## 🚦 WORKFLOW

### Pay Run Processing
```
1. Deputy → Fetch timesheets
2. Map staff (staff_identity_map)
3. Calculate pay (PayslipCalculationEngine)
4. Generate Xero payslip
5. Apply Vend deductions (FIFO)
6. Track residuals
7. Log to payroll_activity_log
8. Errors → payroll_dlq
```

### FIFO Allocation
```php
// AllocationService::allocate()
// Allocates deduction to open invoices oldest-first
$result = [
    'allocated_cents' => 45000,
    'residual_cents' => 500,
    'applications' => [
        ['invoice_id' => 'INV-001', 'applied_cents' => 30000],
        ['invoice_id' => 'INV-002', 'applied_cents' => 15000]
    ]
];
```

---

## 📋 NEXT TASKS

### Immediate (Phase 1)
1. Create `migrations/2025_11_XX_core.sql`
2. Add missing tables (runs, applications, dlq, residuals, leave, bonus)
3. Run migration
4. Verify idempotency

### Short Term (Phases 2-4)
1. Extend health endpoint
2. Create PayrunIntakeService
3. Create AllocationService
4. Create VendApplyService

### Medium Term (Phases 5-8)
1. DLQ + Replay
2. Leave service
3. Bonus automation
4. Reconciliation

---

## 🔍 SEARCH COMMANDS

### Find Files
```bash
find human_resources/payroll -name "*.php" | grep service
```

### Find Classes
```bash
grep -r "class.*Service" human_resources/payroll/services/
```

### Check Logs
```bash
tail -100 human_resources/payroll/logs/*.log
```

---

## 🩺 HEALTH CHECK
```
URL: /modules/human_resources/payroll/health/
Returns: JSON with db_ping + table checks
```

---

## 📊 PROGRESS
```
Phase 0: ████████████████████ 100% ✅
Phase 1: ░░░░░░░░░░░░░░░░░░░░   0%
Overall: ██░░░░░░░░░░░░░░░░░░   8%
```

---

## 🚨 RED FLAGS
- ❌ No secrets in code (use `.env`)
- ❌ No direct SQL (use prepared statements)
- ❌ No duplicate operations (use idempotency)
- ❌ No silent errors (log everything)

---

## ✅ ACCEPTANCE CRITERIA

### Phase 1 Done When:
- All tables exist
- Migration runs idempotently
- Unique keys prevent dupes

### Production Ready When:
- All 12 phases complete
- Tests pass
- Health green
- Docs complete
- Final checklist signed

---

**Keep this handy for quick reference! 📌**
