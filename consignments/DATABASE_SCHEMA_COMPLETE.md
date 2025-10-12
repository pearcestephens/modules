# COMPLETE DATABASE SCHEMA REFERENCE - CIS CONSIGNMENTS MODULE

**Updated:** October 12, 2025  
**Purpose:** Complete reference for all database tables and correct query patterns  
**CRITICAL:** Always use real column names - NO ALIASES unless 100% needed  

---

## 1. TRANSFERS Table

**Table Name:** `transfers`

### Primary Key
- `id` int(11) AUTO_INCREMENT PRIMARY KEY

### Core Fields
- `public_id` varchar(40) NOT NULL
- `vend_transfer_id` char(36)
- `consignment_id` bigint(20) unsigned
- `transfer_category` enum('STOCK','JUICE','STAFF','RETURN','PURCHASE_ORDER') NOT NULL
- `creation_method` enum('MANUAL','AUTOMATED') NOT NULL DEFAULT 'MANUAL'
- `vend_number` varchar(64)
- `vend_url` varchar(255)
- `vend_origin` enum('CONSIGNMENT','PURCHASE_ORDER','TRANSFER')

### **CRITICAL OUTLET FIELDS** ⚠️
- `outlet_from` varchar(100) NOT NULL - **NOT from_outlet_id**
- `outlet_to` varchar(100) NOT NULL - **NOT to_outlet_id**

### User & Tracking
- `created_by` int(11) NOT NULL
- `staff_transfer_id` int(10) unsigned
- `customer_id` varchar(45)

### **CRITICAL DELETION FIELDS** ⚠️
- `deleted_by` int(11) - **Use deleted_by IS NULL OR deleted_by = 0 for active transfers**
- `deleted_at` timestamp - **Use deleted_at IS NULL for active transfers**

### **CRITICAL STATE FIELD** ⚠️
- `state` enum('DRAFT','OPEN','PACKING','PACKAGED','SENT','RECEIVING','PARTIAL','RECEIVED','CLOSED','CANCELLED','ARCHIVED') NOT NULL DEFAULT 'DRAFT'

### Metadata
- `total_boxes` int(10) unsigned NOT NULL DEFAULT 0
- `total_weight_g` bigint(20) unsigned NOT NULL DEFAULT 0
- `draft_data` longtext
- `created_at` timestamp NOT NULL DEFAULT current_timestamp()
- `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
- `draft_updated_at` timestamp

### **CORRECT QUERY PATTERNS**
```sql
-- ✅ CORRECT: Use outlet_from and outlet_to with proper deletion filters
SELECT t.*, 
       o_from.name AS from_outlet_name,
       o_to.name AS to_outlet_name
FROM transfers t
LEFT JOIN vend_outlets o_from ON t.outlet_from = o_from.id AND o_from.deleted_at = '0000-00-00 00:00:00'
LEFT JOIN vend_outlets o_to ON t.outlet_to = o_to.id AND o_to.deleted_at = '0000-00-00 00:00:00'
WHERE t.id = ? 
  AND t.deleted_at IS NULL AND (t.deleted_by IS NULL OR t.deleted_by = 0)

-- ✅ CORRECT: Latest 200 transfers (any category)
SELECT *
FROM transfers
WHERE deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
ORDER BY created_at DESC
LIMIT 200

-- ✅ CORRECT: In-flight work queue (needs action)
SELECT id, public_id, transfer_category, state, outlet_from, outlet_to, updated_at
FROM transfers
WHERE state IN ('OPEN','PACKING','PACKAGED','SENT','RECEIVING','PARTIAL')
  AND deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
ORDER BY updated_at DESC

-- ❌ WRONG: Do NOT use from_outlet_id/to_outlet_id or status column
-- LEFT JOIN vend_outlets o_from ON t.from_outlet_id = o_from.id
-- WHERE t.status = 'open'  -- Use t.state instead
```

---

## 2. TRANSFER_ITEMS Table

### **CRITICAL DELETION FIELD** ⚠️
- `deleted_by` int(11) - **Use deleted_by IS NULL for active items**

### **CORRECT QUERY PATTERNS**
```sql
-- ✅ CORRECT: Use deleted_by IS NULL
SELECT ti.*, p.name AS product_name, p.sku
FROM transfer_items ti
LEFT JOIN vend_products p ON ti.product_id = p.id AND p.is_active = 1 AND p.is_deleted = 0
WHERE ti.transfer_id = ? AND ti.deleted_by IS NULL

-- ❌ WRONG: Do NOT use deleted_at
-- WHERE ti.deleted_at IS NULL
```

---

## 3. VEND_OUTLETS Table

**Table Name:** `vend_outlets` (NOT outlet, NOT outlets)

### Primary Key
- `id` varchar(100) PK

### Core Fields
- `name` varchar(100) - Outlet name
- `register_id` varchar(100)
- `default_tax_id` varchar(100)
- `currency` varchar(100)
- `currency_symbol` varchar(100)
- `display_prices` varchar(100)
- `time_zone` varchar(100)

### Physical Address
- `physical_street_number` varchar(45)
- `physical_street` varchar(45)
- `physical_address_1` varchar(100)
- `physical_address_2` varchar(100)
- `physical_suburb` varchar(100)
- `physical_city` varchar(255)
- `physical_postcode` varchar(100)
- `physical_state` varchar(100)
- `physical_country_id` varchar(100)
- `physical_phone_number` varchar(45)

### **CRITICAL STATUS FIELD** ⚠️
- `deleted_at` timestamp - **Use deleted_at = '0000-00-00 00:00:00' for active outlets**

### Business Fields
- `version` bigint(20)
- `created_at` timestamp
- `turn_over_rate` float
- `automatic_ordering` int(11)
- `store_code` varchar(45)
- `website_active` int(11)
- `website_outlet_id` int(11)
- `banking_days_allocated` int(11)
- `email` varchar(45)
- `is_warehouse` int(11)

### Integration & Shipping
- `magento_warehouse_id` int(11)
- `deputy_location_id` int(11)
- `eftpos_merchant_id` int(11)
- `deposit_card_id` int(11)
- `vape_hq_shipping_id` varchar(45)
- `nz_post_api_key` varchar(45)
- `nz_post_subscription_key` varchar(45)
- `gss_token` varchar(100)

### Social & Location
- `facebook_page_id` varchar(45)
- `google_page_id` varchar(100)
- `google_link` varchar(100)
- `total_review_count` int(11)
- `google_review_rating` float(2,1)
- `outlet_lat` varchar(45)
- `outlet_long` varchar(45)
- `ip_address` varchar(45)

### **CORRECT QUERY PATTERNS**
```sql
-- ✅ CORRECT: Check for active outlets
SELECT * FROM vend_outlets WHERE deleted_at = '0000-00-00 00:00:00'

-- ✅ CORRECT: Join pattern
LEFT JOIN vend_outlets o ON t.outlet_from = o.id AND o.deleted_at = '0000-00-00 00:00:00'
```

---

## 4. VEND_PRODUCTS Table

### **CRITICAL STATUS FIELDS** ⚠️
- `is_active` tinyint(1) - **Use is_active = 1 for active products**
- `is_deleted` tinyint(1) - **Use is_deleted = 0 for non-deleted products**
- `deleted_at` timestamp - **Use deleted_at = '0000-00-00 00:00:00' for active products**

### **CORRECT QUERY PATTERNS**
```sql
-- ✅ CORRECT: Filter active products with ALL deletion filters
SELECT * FROM vend_products 
WHERE is_active = 1 
  AND is_deleted = 0 
  AND deleted_at = '0000-00-00 00:00:00'

-- ✅ CORRECT: INNER JOIN pattern for transfer items
SELECT ti.*, p.name AS product_name, p.sku
FROM transfer_items ti
INNER JOIN vend_products p ON ti.product_id = p.id 
    AND p.is_active = 1 
    AND p.is_deleted = 0 
    AND p.deleted_at = '0000-00-00 00:00:00'
WHERE ti.transfer_id = ? AND ti.deleted_by IS NULL
```

---

## 5. USERS Table

### Primary Key
- `id` int(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY

### Personal Information
- `first_name` mediumtext
- `last_name` mediumtext
- `email` mediumtext
- `phone` mediumtext
- `image` mediumtext
- `nicknames` mediumtext

### Authentication & Security
- `password` mediumtext
- `account_locked` int(11)
- `last_active` timestamp

### **CRITICAL STATUS FIELD** ⚠️
- `staff_active` int(11) - **Use staff_active = 1 for active staff**

### Role & Permissions
- `role_id` int(11)
- `default_outlet` mediumtext
- `stored_dashboard_view` mediumtext

### External System Integration
- `xero_id` varchar(45)
- `vend_id` varchar(45)
- `vend_sync_at` timestamp
- `deputy_id` varchar(45)
- `vend_customer_account` varchar(45)

### AI Access
- `gpt_access` tinyint(1)
- `gpt_admin` tinyint(1)

### **CORRECT QUERY PATTERNS**
```sql
-- ✅ CORRECT: Get active staff only
SELECT * FROM users WHERE staff_active = 1 AND account_locked = 0

-- ✅ CORRECT: Get users with GPT access
SELECT * FROM users WHERE gpt_access = 1 AND staff_active = 1
```

---

## 🚨 CRITICAL COMPANY RULES

### 1. NO ALIASES RULE
- **NEVER** use column aliases unless 100% necessary
- Use real column names: `outlet_from`, `outlet_to`, `deleted_by`, `staff_active`
- **WRONG:** `from_outlet_id`, `to_outlet_id`, `deleted_at` for transfer_items

### 2. ACTIVE RECORD PATTERNS
- **Transfers:** Use `outlet_from` and `outlet_to` columns
- **Transfer Items:** Use `deleted_by IS NULL` for active items
- **Vend Outlets:** Use `deleted_at = '0000-00-00 00:00:00'` for active outlets
- **Vend Products:** Use `is_active = 1 AND is_deleted = 0` for active products
- **Users:** Use `staff_active = 1` for active staff

### 3. JOIN PATTERNS
```sql
-- ✅ CORRECT TRANSFER QUERY
SELECT t.*, 
       o_from.name AS from_outlet_name,
       o_to.name AS to_outlet_name
FROM transfers t
LEFT JOIN vend_outlets o_from ON t.outlet_from = o_from.id 
    AND o_from.deleted_at = '0000-00-00 00:00:00'
LEFT JOIN vend_outlets o_to ON t.outlet_to = o_to.id 
    AND o_to.deleted_at = '0000-00-00 00:00:00'
WHERE t.id = ?

-- ✅ CORRECT TRANSFER ITEMS QUERY
SELECT ti.*, p.name AS product_name, p.sku
FROM transfer_items ti
LEFT JOIN vend_products p ON ti.product_id = p.id 
    AND p.is_active = 1 AND p.is_deleted = 0
WHERE ti.transfer_id = ? AND ti.deleted_by IS NULL
```

---

## 📝 FILES TO UPDATE

### Priority 1: API Endpoints
- `/api/receive_autosave.php` - Fix outlet column names
- `/api/autosave.php` - Fix outlet column names  
- `/api/search_products.php` - Verified correct

### Priority 2: Test Files
- `smart_transfer_test.php` - Fix outlet column names
- `quick_transfer_check.php` - Fix outlet column names

### Priority 3: All Queries Must Use
1. `t.outlet_from` and `t.outlet_to` (NOT from_outlet_id/to_outlet_id)
2. `ti.deleted_by IS NULL` for transfer_items (NOT deleted_at)
3. `o.deleted_at = '0000-00-00 00:00:00'` for vend_outlets
4. `p.is_active = 1 AND p.is_deleted = 0` for vend_products
5. `u.staff_active = 1` for users

---

**THIS DOCUMENTATION IS AUTHORITATIVE - USE THESE PATTERNS FOR ALL DATABASE OPERATIONS**