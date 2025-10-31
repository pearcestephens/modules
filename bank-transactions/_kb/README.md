# 🏦 Bank Transactions Module

**Version:** 1.0.0
**Date:** October 28, 2025
**Status:** ✅ Production Ready
**Purpose:** Comprehensive bank transaction management system

---

## 📋 OVERVIEW

The Bank Transactions Module is a **complete solution** for managing all types of bank transactions in the CIS system:

- 🏪 **Store Deposits** - Cash banking from retail locations
- 🛒 **Customer Retail Payments** - Online order payments
- 🏢 **Wholesale Payments** - B2B customer payments
- 💳 **EFTPOS Settlements** - Daily EFTPOS reconciliation

### Key Features

✅ **Advanced Matching** - 300-point confidence scoring with fuzzy name matching
✅ **Manual Review Queue** - Efficiently review and match unmatched transactions
✅ **Management Tools** - Reassign payments, change outlets, bulk operations
✅ **Comprehensive Reports** - Daily summaries, discrepancies, outstanding payments
✅ **Modern UI** - Bootstrap 5, AJAX updates, responsive design
✅ **Complete Audit Trail** - Track all changes and manual interventions

---

## 🚀 QUICK START

### Access the Module

Navigate to: `https://staff.vapeshed.co.nz/modules/bank-transactions/`

### Module Structure

```
modules/bank-transactions/
├── bootstrap.php              # Module initialization (inherits from base)
├── index.php                  # Main router and entry point
├── controllers/               # Business logic controllers
├── views/                     # UI templates
├── api/                       # JSON API endpoints
├── lib/                       # Core services
├── assets/                    # CSS and JavaScript
└── _kb/                       # Knowledge bank (documentation)
```

---

## 🎯 MAIN VIEWS

### 1. Dashboard (`/modules/bank-transactions/`)
**Purpose:** Overview of all transaction activity

**Features:**
- Total transactions, matched/unmatched counts, outstanding amounts
- Transaction type breakdown (pie chart)
- Daily volume trends (line graph)
- Recent activity feed
- Quick action buttons

### 2. All Transactions (`/modules/bank-transactions/index.php?view=all`)
**Purpose:** Comprehensive filterable list

**Features:**
- Advanced filters (date range, type, status, outlet, amount)
- Search by order #, name, reference
- Sortable columns
- Pagination
- Bulk selection and operations
- Export to CSV/Excel

### 3. Manual Review Queue (`/modules/bank-transactions/index.php?view=manual-review`)
**Purpose:** Review and manually match unmatched transactions

**Features:**
- Unmatched transaction list
- AI-powered match suggestions with confidence scores
- Score breakdown (why each match scored the way it did)
- Manual assignment interface
- Batch review mode
- Notes and context

### 4. Transaction Detail (`/modules/bank-transactions/index.php?view=detail&id=123`)
**Purpose:** Deep dive into a single transaction

**Features:**
- Full transaction details
- Matched record information
- Confidence score breakdown
- Audit trail (who matched it, when, changes)
- Reassignment tools
- Add notes

### 5. Reports (`/modules/bank-transactions/index.php?view=reports`)
**Purpose:** Generate and view reports

**Reports Available:**
- Daily reconciliation summary
- Outstanding payments (> 7 days)
- Discrepancy analysis
- Monthly trends

---

## 🔧 CORE SERVICES

### TransactionService
**Location:** `lib/TransactionService.php`
**Purpose:** CRUD operations for transactions

**Methods:**
```php
getAllTransactions($filters = [])     // Get filtered list
getTransaction($id)                   // Get single transaction
updateTransaction($id, $data)         // Update transaction
getUnmatchedTransactions()            // Get manual review queue
getTransactionsByDateRange($from, $to) // Date range query
```

### MatchingEngine
**Location:** `lib/MatchingEngine.php`
**Purpose:** Advanced transaction matching algorithms

**Methods:**
```php
findMatches($transaction)             // Find candidate matches
matchTransaction($transaction)        // Auto-match with best candidate
extractOrderNumbers($reference)       // Extract order # from reference
fuzzyNameMatch($name1, $name2)        // Calculate name similarity
isPotentialWholesaleOrder($transaction) // Detect wholesale
```

**Features:**
- 6 order extraction strategies
- Fuzzy name matching (Levenshtein + token matching)
- Wholesale detection (amount > $1000 + business keywords)
- Duplicate payment prevention

### ConfidenceScorer
**Location:** `lib/ConfidenceScorer.php`
**Purpose:** Calculate 300-point confidence scores

**Scoring Breakdown:**
- Amount Match: 100 points (exact match gets full points)
- Date Match: 50 points (same day = 50, next day = 40, etc.)
- Name Match: 50 points (fuzzy matching score)
- Reference Match: 40 points (contains order #)
- Type Match: 30 points (transaction type matches)
- Outlet Match: 20 points (same outlet or nearby)
- Timing Match: 10 points (within expected time window)

**Thresholds:**
- **>= 250**: Auto-match with high confidence
- **200-249**: Auto-match with medium confidence
- **150-199**: Send to manual review
- **< 150**: Leave unmatched for investigation

### PaymentProcessor
**Location:** `lib/PaymentProcessor.php`
**Purpose:** Handle payment assignment and reassignment

**Methods:**
```php
assignPayment($transactionId, $orderId, $orderType, $userId, $reason)
reassignPayment($transactionId, $newOrderId, $userId, $reason)
reassignOutlet($transactionId, $newOutletId, $userId, $reason)
bulkReassign($transactionIds, $action, $params, $userId)
```

---

## 📡 API ENDPOINTS

### Base URL: `/modules/bank-transactions/api/`

### GET /get-transactions.php
**Purpose:** Get paginated, filtered transaction list

**Parameters:**
```
page (int): Page number (default: 1)
limit (int): Per page (default: 50, max: 200)
type (string): store_deposit|retail|wholesale|eftpos|all
status (string): matched|unmatched|manual_review|all
outlet_id (int): Filter by outlet
date_from (date): Start date (Y-m-d)
date_to (date): End date (Y-m-d)
search (string): Search term
min_amount (float): Minimum amount
max_amount (float): Maximum amount
```

**Response:**
```json
{
    "success": true,
    "data": {
        "transactions": [...],
        "pagination": {
            "current_page": 1,
            "per_page": 50,
            "total": 1245,
            "total_pages": 25
        },
        "summary": {
            "total_amount": 45678.90,
            "matched_count": 1139,
            "unmatched_count": 106
        }
    }
}
```

### GET /get-transaction.php?id=123
**Purpose:** Get single transaction with full details

**Response:**
```json
{
    "success": true,
    "data": {
        "transaction": {...},
        "matched_record": {...},
        "confidence_breakdown": {...},
        "audit_trail": [...]
    }
}
```

### POST /reassign-payment.php
**Purpose:** Reassign payment to different order

**Body:**
```json
{
    "transaction_id": 123,
    "new_order_id": 456,
    "order_type": "retail",
    "reason": "Corrected order number",
    "notes": "Customer called to confirm"
}
```

### POST /bulk-update.php
**Purpose:** Perform bulk operations

**Body:**
```json
{
    "transaction_ids": [123, 124, 125],
    "action": "reassign_outlet",
    "outlet_id": 5,
    "reason": "Corrected bank account mapping"
}
```

### GET /export.php?format=csv&filters={...}
**Purpose:** Export transactions to CSV/Excel

---

## 🗄️ DATABASE SCHEMA

### New Tables Created

**1. bank_transaction_matches**
```sql
-- Tracks matching history and confidence scores
id, transaction_id, matched_record_type, matched_record_id,
confidence_score, matched_by_user_id, matched_at, notes
```

**2. bank_transaction_manual_review**
```sql
-- Manual review queue
id, transaction_id, reason, assigned_to_user_id, status,
created_at, resolved_at, resolution_notes
```

**3. bank_transaction_reassignments**
```sql
-- Audit trail for reassignments
id, transaction_id, old_outlet_id, new_outlet_id,
old_matched_id, new_matched_id, reassigned_by_user_id,
reassigned_at, reason
```

**4. bank_transaction_filter_presets**
```sql
-- Saved filter configurations
id, user_id, preset_name, filters (JSON), is_default, created_at
```

---

## 🔐 SECURITY

### Authentication
- ✅ All pages require login
- ✅ Session validation via base module

### Authorization (Role-Based)
- **View Only**: Can view transactions and reports
- **Reviewer**: Can manually match transactions
- **Manager**: Can reassign payments and outlets
- **Admin**: Full access including bulk operations

### CSRF Protection
- ✅ All POST/PUT/DELETE requests require CSRF token
- ✅ Tokens generated via base module SecurityMiddleware

### Rate Limiting
- ✅ API endpoints: 100 requests/minute per user
- ✅ Bulk operations: Limited to 50 transactions at once

### Input Validation
- ✅ All inputs validated and sanitized
- ✅ Prepared statements for all database queries
- ✅ XSS prevention via output escaping

### Audit Trail
- ✅ All manual changes logged
- ✅ Track who, when, what, why
- ✅ Immutable audit records

---

## 📊 SUCCESS METRICS

### Efficiency
- ⏱️ Auto-match rate: Target > 90%
- 🎯 Manual review queue: Target < 50 transactions
- ⚡ Page load time: Target < 2 seconds

### Accuracy
- ✅ Match accuracy: Target > 98%
- 🔍 False positive rate: Target < 2%

### Business Impact
- 💰 Outstanding payment reduction: Target 50% in 3 months
- 📉 Discrepancy rate: Target < 1%
- ⏰ Reconciliation time: Target < 1 hour daily

---

## 🐛 TROUBLESHOOTING

### Issue: Transactions not appearing in list
**Solution:**
1. Check filters - reset to "All"
2. Verify date range includes transactions
3. Check database connection in logs

### Issue: Low confidence scores
**Solution:**
1. Review reference format (should contain order #)
2. Check name format (first name + last name vs reversed)
3. Verify amount matches exactly

### Issue: Manual review queue growing
**Solution:**
1. Run batch review mode
2. Check for systematic issues (wrong outlet, missing data)
3. Review confidence threshold settings

---

## 📚 ADDITIONAL DOCUMENTATION

- **API Reference:** `_kb/API_REFERENCE.md`
- **Matching Algorithm:** `_kb/MATCHING_ALGORITHM.md`
- **User Guide:** `_kb/USER_GUIDE.md`
- **Admin Guide:** `_kb/ADMIN_GUIDE.md`
- **Changelog:** `_kb/CHANGELOG.md`

---

## 🆘 SUPPORT

For issues or questions:
- **Developer:** Check logs in `/modules/base/` and `/logs/`
- **User:** Contact IT support
- **Bug Reports:** Create ticket in support system

---

**Module Status:** ✅ Production Ready
**Last Updated:** October 28, 2025
**Maintained By:** CIS Development Team
