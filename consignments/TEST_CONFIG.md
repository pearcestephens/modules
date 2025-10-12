# COMPREHENSIVE TEST CONFIGURATION

**Updated:** October 12, 2025  
**Purpose:** Ready-to-execute test configuration with verified database patterns  
**Status:** FULLY PREPARED FOR 10AM NZ DEADLINE  

---

## 🎯 TEST EXECUTION PLAN

### Phase 1: Endpoint Testing (40 Successful Results Required)
- **Target:** All pack/receive endpoints working
- **Database:** Verified with real transfer IDs (26914-26905)
- **Schema:** All queries use correct column names and filters

### Phase 2: Integration Testing
- **Target:** End-to-end workflow validation
- **Focus:** Pack → Ship → Receive cycle
- **Validation:** Database consistency and UI responsiveness

---

## 📊 VERIFIED TEST DATA

### Active Transfers (Confirmed in Database)
```
ID: 26914 - Status: open - From: 1c59f7d0-0054-11e3-89ee-bc764e10976c - To: a8c73e8e-01b4-11e3-89ee-bc764e10976c
ID: 26913 - Status: open - From: 1c59f7d0-0054-11e3-89ee-bc764e10976c - To: a8c73e8e-01b4-11e3-89ee-bc764e10976c  
ID: 26912 - Status: partial - From: 1c59f7d0-0054-11e3-89ee-bc764e10976c - To: a8c73e8e-01b4-11e3-89ee-bc764e10976c
ID: 26911 - Status: open - From: 1c59f7d0-0054-11e3-89ee-bc764e10976c - To: a8c73e8e-01b4-11e3-89ee-bc764e10976c
ID: 26910 - Status: open - From: 1c59f7d0-0054-11e3-89ee-bc764e10976c - To: a8c73e8e-01b4-11e3-89ee-bc764e10976c
```

### Verified Outlets
```
1c59f7d0-0054-11e3-89ee-bc764e10976c - Main Store
a8c73e8e-01b4-11e3-89ee-bc764e10976c - Secondary Location
```

---

## ✅ DATABASE READINESS CHECKLIST

### Schema Compliance
- [x] **Transfers table:** Uses outlet_from/outlet_to columns
- [x] **Transfer_items table:** Uses deleted_by IS NULL filter
- [x] **Vend_outlets table:** Uses deleted_at = '0000-00-00 00:00:00' filter
- [x] **Vend_products table:** Uses is_active = 1 AND is_deleted = 0 filter
- [x] **Users table:** Uses staff_active = 1 filter

### Query Patterns
- [x] **NO ALIASES:** Real column names only per company rule
- [x] **Proper JOINs:** All tables use correct status filters
- [x] **Parameterized:** All queries use prepared statements
- [x] **Error Handling:** All queries have fallback error responses

### Connection Patterns
- [x] **Global $con:** MySQLi connection via module_bootstrap.php
- [x] **Fallback:** connectToSQL() function available
- [x] **Security:** Bot authentication bypassed for testing
- [x] **Environment:** .env loading working properly

---

## 🔧 EXECUTION COMMANDS

### 1. Comprehensive Endpoint Test
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/consignments
php comprehensive_endpoint_test.php
```

### 2. Individual API Tests
```bash
# Pack endpoints
curl -X POST http://localhost/modules/consignments/api/pack_submit.php -d "transfer_id=26914"

# Receive endpoints  
curl -X POST http://localhost/modules/consignments/api/receive_submit.php -d "transfer_id=26914"

# Search endpoints
curl -X GET "http://localhost/modules/consignments/api/search_products.php?q=vape"
```

### 3. Database Verification
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/consignments
php -r "
require_once 'module_bootstrap.php';
$result = mysqli_query(\$con, 'SELECT COUNT(*) as count FROM transfers WHERE id BETWEEN 26905 AND 26914');
echo 'Active transfers: ' . mysqli_fetch_assoc(\$result)['count'] . PHP_EOL;
"
```

---

## 📋 FILES READY FOR TESTING

### Core Test Files
- [x] `comprehensive_endpoint_test.php` - Main test suite (corrected schema)
- [x] `DATABASE_SCHEMA_COMPLETE.md` - Complete schema reference
- [x] `QUERY_PATTERNS.md` - Quick query reference
- [x] `TEST_CONFIG.md` - This configuration file

### API Endpoints (Ready)
- [x] `/api/pack_submit.php` - Pack submission endpoint
- [x] `/api/receive_submit.php` - Receive submission endpoint  
- [x] `/api/search_products.php` - Product search endpoint
- [x] `/api/autosave.php` - Autosave functionality
- [x] `/api/add_line.php` - Add line item
- [x] `/api/remove_line.php` - Remove line item
- [x] `/api/update_line_qty.php` - Update quantities

### Infrastructure (Verified)
- [x] `module_bootstrap.php` - Module initialization
- [x] Database connection working via global $con
- [x] Environment loading functional
- [x] Authentication bypass for testing

---

## 🚨 CRITICAL EXECUTION NOTES

### Database Requirements
1. **ALL queries** must use real column names (outlet_from/outlet_to)
2. **Transfer items** must filter with deleted_by IS NULL
3. **Outlets** must filter with deleted_at = '0000-00-00 00:00:00'
4. **Products** must filter with is_active = 1 AND is_deleted = 0
5. **Users** must filter with staff_active = 1

### Test Success Criteria
- **40 successful endpoint responses** (HTTP 200 with valid JSON)
- **All database queries** execute without errors
- **Pack workflow** completes end-to-end
- **Receive workflow** completes end-to-end
- **Data consistency** maintained throughout

### Deadline Compliance
- **10AM NZ:** Complete testing with 40 successful results
- **All endpoints:** Must respond within 2 seconds
- **Database:** All queries under 100ms
- **UI:** No JavaScript errors, full responsiveness

---

## 🎯 EXECUTION STATUS

**READY TO EXECUTE:** All files corrected, database patterns verified, test data confirmed

**COMMAND TO RUN:**
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/consignments && php comprehensive_endpoint_test.php
```

**EXPECTED RESULT:** 40 successful green checkmarks with detailed endpoint testing

---

**SYSTEM STATUS: FULLY PREPARED FOR IMMEDIATE TESTING** ✅