# CIS Database - Bank Transaction Tables

**Database:** `jcepnzzkmj`
**Host:** 127.0.0.1
**User:** jcepnzzkmj
**Password:** wprKh9Jq63

---

## ALL TABLES WITH `bank_` PREFIX

### 1. **bank_deposits** ✅ PRIMARY TABLE
- **Rows:** 39,292
- **Size:** 77.50 MB
- **Purpose:** Bank transaction deposits with AI matching capabilities
- **Status:** ACTIVE - Contains live deposit data

### 2. **bank_reconciliation_manual_reviews** ✅
- **Rows:** 72
- **Size:** 0.09 MB
- **Purpose:** Queue for transactions requiring manual review due to ambiguity
- **Status:** ACTIVE - Used for problematic matches

### 3. **bank_reconciliation_transactions** ✅
- **Rows:** TBD
- **Size:** TBD
- **Purpose:** Reconciliation tracking
- **Status:** Check if exists

### 4. **bank_transaction_matches** ✅
- **Rows:** TBD
- **Size:** TBD
- **Purpose:** Stores match results
- **Status:** Check if exists

---

## RELATED TABLES (Deposit/Transaction)

### 5. **deposit_transactions** 📦
- **Rows:** 12,889
- **Size:** 1.52 MB
- **Purpose:** Sentence: This table records deposit transactions made by customers, which are used by the business to track and manage customer deposits for purchases or services.
- **Tags:** customer deposits, transaction history, deposit tracking, financial reports, customer payment records, deposit reconciliation, store deposits, transaction analysis

### 6. **deposit_transactions_new** 📦
- **Rows:** 39,018
- **Size:** 67.31 MB
- **Purpose:** This table records deposit transactions for The Vape Shed, used to track and reconcile financial deposits made by customers or stores.
- **Tags:** deposit reconciliation, transaction history, financial reports, customer deposits, store deposits, transaction tracking, deposit audits, financial discrepancies, daily deposit summary, monthly deposit report

---

## AUDIT & TRACKING TABLES

### 7. **audit_trail** 📋
- **Rows:** 0
- **Size:** 0.08 MB
- **Purpose:** Audit trail for all bank transaction actions
- **Status:** EMPTY - Ready for logging

### 8. **decision_audit_trail** 📋
- **Rows:** TBD
- **Size:** TBD
- **Purpose:** Records AI decision history
- **Status:** Check if exists

---

## PAYROLL RELATED TABLES

### 9. **payroll_bank_exports** 💰
- **Purpose:** Exported payroll data to bank
- **Status:** Check if exists

### 10. **payroll_bank_payment_batches** 💰
- **Purpose:** Payment batch tracking
- **Status:** Check if exists

### 11. **payroll_bank_payments** 💰
- **Purpose:** Individual payment records
- **Status:** Check if exists

---

## REGISTER/CLOSURE RELATED

### 12. **register_closure_bank_deposits** 📊
- **Purpose:** Bank deposits associated with register closures
- **Status:** Check if exists

---

## AI RELATED TABLES

### 13. **enterprise_ai_memory_bank** 🤖
- **Purpose:** AI memory/knowledge storage
- **Status:** Check if exists

---

## COMPLETE DATABASE CONNECTION REFERENCE

### PRIMARY (CIS)
```php
$host = '127.0.0.1';
$database = 'jcepnzzkmj';
$username = 'jcepnzzkmj';
$password = 'wprKh9Jq63';
$charset = 'utf8mb4';

$pdo = new PDO(
  "mysql:host=$host;dbname=$database;charset=$charset",
  $username,
  $password
);
```

### SECONDARY (VapeShed)
```php
$host = '127.0.0.1';
$database = 'dvaxgvsxmz';
$username = 'dvaxgvsxmz';
$password = 'WDtP6sH4c8';
$charset = 'utf8mb4';

$pdo = new PDO(
  "mysql:host=$host;dbname=$database;charset=$charset",
  $username,
  $password
);
```

---

## QUICK REFERENCE - VERIFIED TABLES

| Table Name | Prefix | Rows | MB | Status |
|-----------|--------|------|-----|--------|
| audit_trail | bank_ | 0 | 0.08 | ✅ EXISTS |
| bank_deposits | bank_ | 39,292 | 77.50 | ✅ EXISTS |
| bank_reconciliation_manual_reviews | bank_ | 72 | 0.09 | ✅ EXISTS |
| deposit_transactions | (related) | 12,889 | 1.52 | ✅ EXISTS |
| deposit_transactions_new | (related) | 39,018 | 67.31 | ✅ EXISTS |

---

## MYSQL COMMANDS

### Connect to Database
```bash
mysql -h 127.0.0.1 -u jcepnzzkmj -pwprKh9Jq63 jcepnzzkmj
```

### List All Tables
```bash
mysql -h 127.0.0.1 -u jcepnzzkmj -pwprKh9Jq63 jcepnzzkmj -e "SHOW TABLES;"
```

### List All bank_ Tables
```bash
mysql -h 127.0.0.1 -u jcepnzzkmj -pwprKh9Jq63 jcepnzzkmj -e "SHOW TABLES LIKE 'bank_%';"
```

### View Table Structure
```bash
mysql -h 127.0.0.1 -u jcepnzzkmj -pwprKh9Jq63 jcepnzzkmj -e "DESCRIBE bank_deposits;"
```

### Count Rows
```bash
mysql -h 127.0.0.1 -u jcepnzzkmj -pwprKh9Jq63 jcepnzzkmj -e "SELECT COUNT(*) FROM bank_deposits;"
```

### View Sample Data
```bash
mysql -h 127.0.0.1 -u jcepnzzkmj -pwprKh9Jq63 jcepnzzkmj -e "SELECT * FROM bank_deposits LIMIT 5;"
```

### Get Detailed Info
```bash
mysql -h 127.0.0.1 -u jcepnzzkmj -pwprKh9Jq63 jcepnzzkmj -e "
SELECT
  TABLE_NAME,
  TABLE_ROWS,
  ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as 'Size (MB)'
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = 'jcepnzzkmj'
  AND TABLE_NAME LIKE 'bank_%'
ORDER BY TABLE_NAME;
"
```

---

## AVAILABLE FOR USE

✅ **bank_deposits** (39,292 rows) - Primary transaction table
✅ **bank_reconciliation_manual_reviews** (72 rows) - Queue for review
✅ **audit_trail** (0 rows) - Ready for logging
✅ **deposit_transactions** (12,889 rows) - Historical data
✅ **deposit_transactions_new** (39,018 rows) - New format data

---

**Last Updated:** 2025-10-30
**Source:** CIS Database `jcepnzzkmj`
