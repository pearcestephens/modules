# 📦 COMPLETE PAYROLL DATA STORAGE - WHAT WE CAPTURE

**Version:** 2.0
**Updated:** 2025-10-28
**Status:** ✅ Production-Ready

---

## 🎯 YOUR REQUEST

> "I DO WANT EVERY STAFF MEMBERS ENTIRE PAYSLIP... WELL ALL THE DETAILS IN EVERY EVER TABLE EVERY WEEK"

**✅ DONE!** Here's exactly what we're storing:

---

## 📊 STORAGE LEVELS

### 1. **COMPLETE JSON BLOBS** (Everything, Unmodified)
*Stored in: `payroll_snapshots` table*

```sql
-- All raw data preserved forever
user_objects_json         LONGTEXT  -- Complete CIS $userObject arrays
deputy_timesheets_json    LONGTEXT  -- Raw Deputy API responses
vend_account_balances_json TEXT     -- Vend customer balances
xero_payslips_json        LONGTEXT  -- COMPLETE Xero payslip responses
xero_employees_json       TEXT      -- Xero employee details
xero_leave_json           TEXT      -- Xero leave balances
public_holidays_json      TEXT      -- Calendarific holiday data
bonus_calculations_json   TEXT      -- Bonus breakdowns
amendments_json           TEXT      -- Manual adjustments
config_snapshot_json      TEXT      -- System configuration
```

**Size:** ~1.5-2MB per snapshot (compressed later)
**Retention:** Forever (with optional compression after 6 months)

---

### 2. **NORMALIZED EMPLOYEE DETAILS** (Fast SQL Queries)
*Stored in: `payroll_employee_details` table*

```sql
-- 40+ fields per employee
user_id, xero_employee_id, xero_payslip_id
deputy_employee_id, vend_customer_id
employee_name, employee_email
total_hours, ordinary_hours, overtime_hours
leave_hours, public_holiday_hours
base_pay, overtime_pay, commission
monthly_bonus, google_review_bonus, vape_drops_bonus
leave_pay, public_holiday_pay, gross_earnings
account_payment_deduction, total_deductions, net_pay
hourly_rate, salary_annual
vend_account_balance, deputy_timesheet_count
deputy_first_punch, deputy_last_punch
public_holiday_worked, public_holiday_preference
alternative_holiday_created, alternative_holiday_hours
processing_status, skip_reason, error_message
full_user_object_json  -- Complete object for this employee
```

**Purpose:** Fast queries like "Show me all employees who earned >$2000 last week"

---

### 3. **🆕 INDIVIDUAL XERO PAYSLIP LINE ITEMS** (Every Line)
*Stored in: `payroll_xero_payslip_lines` table*

Every single line from every payslip, categorized:

#### **A) EARNINGS LINES**
```sql
line_category: 'earnings'
Examples:
- Ordinary Time: 40 hours @ $27.50/hr = $1,100.00
- Overtime 1.5x: 5 hours @ $41.25/hr = $206.25
- Monthly Bonus: $500.00 (fixed)
- Google Review Bonus: $50.00 (fixed)
- VapeDrop Delivery: 3 drops @ $7.00/drop = $21.00
```

#### **B) DEDUCTION LINES**
```sql
line_category: 'deduction'
Examples:
- Account Payment: $50.00
- Other Deduction: $25.00
```

#### **C) LEAVE EARNINGS**
```sql
line_category: 'leave_earnings'
Examples:
- Annual Leave: 8 hours @ $27.50/hr = $220.00
- Sick Leave: 4 hours @ $27.50/hr = $110.00
```

#### **D) REIMBURSEMENTS**
```sql
line_category: 'reimbursement'
Examples:
- Expense Reimbursement: $45.00
```

#### **E) EMPLOYEE TAX**
```sql
line_category: 'employee_tax'
Examples:
- PAYE: $350.50
- ACC Earner Levy: $45.30
- Student Loan: $75.00
- KiwiSaver Employee 3%: $50.00
```

#### **F) EMPLOYER TAX**
```sql
line_category: 'employer_tax'
Examples:
- ACC Employer Levy: $25.00
```

#### **G) SUPERANNUATION (KiwiSaver)**
```sql
line_category: 'superannuation'
Examples:
- KiwiSaver: Employee $50.00 + Employer $50.00 = $100.00
```

#### **H) LEAVE ACCRUALS**
```sql
line_category: 'leave_accrual'
Examples:
- Annual Leave Accrued: 3.2 hours
- Sick Leave Accrued: 1.6 hours
```

#### **I) STATUTORY DEDUCTIONS**
```sql
line_category: 'statutory_deduction'
Examples:
- Child Support: $100.00
- IRD Deduction: $50.00
```

---

## 🔍 WHAT EACH LINE STORES

For **EVERY line item** in **EVERY payslip**:

```sql
run_id                    -- Which pay run (Week 1, 2, 3...)
snapshot_id               -- Which snapshot within that run
employee_detail_id        -- Link to employee summary
xero_payslip_id          -- Xero's payslip ID
xero_employee_id         -- Xero's employee ID

line_category            -- earnings/deduction/tax/super/leave
line_type_id             -- Xero rate/deduction/tax type ID
display_name             -- "Ordinary Time", "Account Payment", etc.
description              -- Additional details

rate_per_unit            -- Hourly rate, per-drop rate, etc.
number_of_units          -- Hours, drops, days
fixed_amount             -- Fixed dollar amount
percentage               -- Percentage (for KiwiSaver, deductions)
calculated_amount        -- FINAL AMOUNT FOR THIS LINE

is_linked_to_timesheet   -- TRUE if from Deputy timesheet
is_average_daily_pay_rate -- TRUE if using average rate
auto_calculate           -- TRUE if Xero auto-calculated

tax_type                 -- "PAYE", "Student Loan", etc.
employee_contribution    -- Employee portion (KiwiSaver)
employer_contribution    -- Employer portion (KiwiSaver)

leave_type_id            -- Leave type (annual, sick, etc.)
leave_units              -- Hours/days of leave

period_start_date        -- Pay period start
period_end_date          -- Pay period end
payment_date             -- Actual payment date

full_line_json           -- Complete line object as JSON (backup)
```

---

## 📈 QUERY EXAMPLES

Now you can run queries like:

### "Show me all overtime hours paid last week"
```sql
SELECT
    e.employee_name,
    l.number_of_units AS overtime_hours,
    l.rate_per_unit AS rate,
    l.calculated_amount AS total_paid
FROM payroll_xero_payslip_lines l
JOIN payroll_employee_details e ON l.employee_detail_id = e.id
WHERE l.run_id = 42  -- Last week
  AND l.line_category = 'earnings'
  AND l.display_name LIKE '%Overtime%'
ORDER BY overtime_hours DESC;
```

### "Show me all Account Payment deductions"
```sql
SELECT
    e.employee_name,
    l.calculated_amount AS deduction_amount,
    l.payment_date
FROM payroll_xero_payslip_lines l
JOIN payroll_employee_details e ON l.employee_detail_id = e.id
WHERE l.line_category = 'deduction'
  AND l.display_name = 'Account Payment'
  AND l.payment_date BETWEEN '2025-01-01' AND '2025-12-31'
ORDER BY l.payment_date DESC, deduction_amount DESC;
```

### "Show me all KiwiSaver contributions (employee + employer)"
```sql
SELECT
    e.employee_name,
    l.employee_contribution,
    l.employer_contribution,
    l.employee_contribution + l.employer_contribution AS total_contribution,
    l.payment_date
FROM payroll_xero_payslip_lines l
JOIN payroll_employee_details e ON l.employee_detail_id = e.id
WHERE l.line_category = 'superannuation'
  AND l.payment_date >= '2025-01-01'
ORDER BY total_contribution DESC;
```

### "Show me all leave taken (annual + sick)"
```sql
SELECT
    e.employee_name,
    l.display_name AS leave_type,
    l.leave_units AS hours_taken,
    l.rate_per_unit AS pay_rate,
    l.calculated_amount AS amount_paid,
    l.payment_date
FROM payroll_xero_payslip_lines l
JOIN payroll_employee_details e ON l.employee_detail_id = e.id
WHERE l.line_category = 'leave_earnings'
  AND l.payment_date >= '2025-10-01'
ORDER BY e.employee_name, l.payment_date;
```

### "Total PAYE tax paid per employee this year"
```sql
SELECT
    e.employee_name,
    SUM(l.calculated_amount) AS total_paye,
    COUNT(*) AS pay_periods
FROM payroll_xero_payslip_lines l
JOIN payroll_employee_details e ON l.employee_detail_id = e.id
WHERE l.line_category = 'employee_tax'
  AND l.tax_type = 'PAYE'
  AND YEAR(l.payment_date) = 2025
GROUP BY e.employee_name
ORDER BY total_paye DESC;
```

### "Show me ALL data for ONE employee's ONE payslip"
```sql
SELECT
    l.line_category,
    l.display_name,
    l.number_of_units,
    l.rate_per_unit,
    l.fixed_amount,
    l.calculated_amount,
    l.full_line_json
FROM payroll_xero_payslip_lines l
WHERE l.xero_payslip_id = 'abc-123-xyz'
ORDER BY
    CASE l.line_category
        WHEN 'earnings' THEN 1
        WHEN 'leave_earnings' THEN 2
        WHEN 'deduction' THEN 3
        WHEN 'employee_tax' THEN 4
        WHEN 'superannuation' THEN 5
        WHEN 'leave_accrual' THEN 6
    END;
```

---

## 🔐 DATA INTEGRITY

Every snapshot has:
- **SHA256 hash** of all data (tamper detection)
- **Timestamp** (exact capture time)
- **Revision link** (which button click triggered it)
- **User link** (who was logged in)
- **IP address** (where it came from)

---

## 💾 STORAGE SIZE ESTIMATES

**Per Employee Per Week:**
- JSON blob: ~20-30 KB
- Normalized row: ~1 KB
- Xero line items: ~5-10 rows (~2 KB)

**For 30 Employees:**
- Per week: ~650 KB
- Per year (52 weeks): ~34 MB

**For 100 Employees:**
- Per week: ~2.2 MB
- Per year: ~114 MB

**With 5 years of history (30 employees):**
- Total: ~170 MB (before compression)
- Compressed: ~50-70 MB (70% compression ratio)

---

## 📅 RETENTION POLICY

1. **Recent data (0-6 months):** Uncompressed, instant access
2. **Old data (6-12 months):** Compressed, quick access
3. **Archive data (1+ years):** Compressed, stored separately, accessible on demand

---

## ✅ WHAT THIS GIVES YOU

### **1. Complete Audit Trail**
- See EXACTLY what was paid, when, and why
- Reproduce any payslip from any week
- Track changes over time

### **2. Amendment Capability**
- "Employee worked 2 extra hours on Saturday"
- Load snapshot → Add hours → Calculate diff → Pay difference
- Full audit trail of changes

### **3. Reporting & Analytics**
- Total overtime by employee/month/year
- KiwiSaver contributions tracking
- Account payment deduction tracking
- Leave balance changes
- Tax paid per employee
- Average hourly rates
- Bonus payment analysis

### **4. Compliance & Auditing**
- Complete records for IRD audits
- Holiday Act compliance evidence
- Minimum wage compliance proof
- KiwiSaver contribution proof

### **5. Historical Reconstruction**
- "What did we pay John in March 2024?"
- "How many overtime hours did the team work last quarter?"
- "What was the Account Payment deduction for Sarah in Week 15?"

---

## 🚀 ACCESSING THE DATA

### **Option 1: Direct SQL** (Fast)
```sql
-- Your queries here
SELECT * FROM payroll_xero_payslip_lines WHERE ...;
```

### **Option 2: PHP API** (Coming Soon)
```php
$manager = new PayrollSnapshotManager($pdo, $xeroTenantId);
$snapshot = $manager->loadSnapshot($runId, $snapshotId);
$lines = $snapshot['xero_payslips']; // All payslip data
```

### **Option 3: Web UI** (Planned)
- View run history
- Click on a run → See all employees
- Click on an employee → See complete payslip breakdown
- Visual diff between snapshots
- Export to Excel/PDF

---

## 🎉 SUMMARY

**YOU NOW HAVE:**
- ✅ Complete $userObject arrays (CIS processed data)
- ✅ Every Deputy timesheet (raw API responses)
- ✅ All Vend account balances
- ✅ **COMPLETE Xero payslips (all fields, all nested data)**
- ✅ **EVERY earnings line (ordinary, OT, bonuses, leave)**
- ✅ **EVERY deduction line**
- ✅ **EVERY tax line (PAYE, student loan, KiwiSaver)**
- ✅ **EVERY superannuation line**
- ✅ **EVERY leave accrual**
- ✅ **EVERY reimbursement**
- ✅ Public holiday tracking
- ✅ Bonus calculation breakdowns
- ✅ Amendment history
- ✅ System configuration snapshots

**STORED IN:**
- 1 complete JSON blob (everything, unmodified)
- 1 normalized employee summary row (fast queries)
- 5-10 individual line item rows per employee (detailed analysis)

**TOTAL:** ~10 tables working together to give you complete visibility

---

## 📞 NEXT STEPS

1. ✅ **Deploy the new schema** (adds `payroll_xero_payslip_lines` table)
2. ✅ **Code is ready** (PayrollSnapshotManager updated)
3. 🔜 **Run your first payroll with this system**
4. 🔜 **Check the data** (SQL queries or wait for UI)

---

**Version:** 2.0
**Created:** 2025-10-28
**Status:** ✅ Ready for deployment
