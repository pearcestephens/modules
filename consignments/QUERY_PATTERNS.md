# DATABASE QUERY PATTERNS REFERENCE

**Updated:** October 12, 2025  
**Purpose:** Quick reference for correct database query patterns  
**Company Rule:** NO ALIASES unless 100% needed - use real column names  

---

## ✅ CORRECT QUERY PATTERNS

### 1. Get Transfer with Outlets
```sql
SELECT t.*, 
       o_from.name AS from_outlet_name,
       o_to.name AS to_outlet_name
FROM transfers t
LEFT JOIN vend_outlets o_from ON t.outlet_from = o_from.id 
    AND o_from.deleted_at = '0000-00-00 00:00:00'
LEFT JOIN vend_outlets o_to ON t.outlet_to = o_to.id 
    AND o_to.deleted_at = '0000-00-00 00:00:00'
WHERE t.id = ?
  AND t.deleted_at IS NULL AND (t.deleted_by IS NULL OR t.deleted_by = 0)
```

### 2. Latest 200 Transfers
```sql
SELECT *
FROM transfers
WHERE deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
ORDER BY created_at DESC
LIMIT 200
```

### 3. In-Flight Work Queue (Needs Action)
```sql
SELECT id, public_id, transfer_category, state, outlet_from, outlet_to, updated_at
FROM transfers
WHERE state IN ('OPEN','PACKING','PACKAGED','SENT','RECEIVING','PARTIAL')
  AND deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
ORDER BY updated_at DESC
```

### 4. Work to PACK (Origin Outlet) - Last 14 Days
```sql
SELECT id, public_id, transfer_category, state, outlet_from, outlet_to, created_at
FROM transfers
WHERE outlet_from = ?
  AND state IN ('OPEN','PACKING','PACKAGED')
  AND created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
  AND deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
ORDER BY created_at DESC
```

### 5. Work to RECEIVE (Destination Outlet) - Last 14 Days
```sql
SELECT id, public_id, transfer_category, state, outlet_from, outlet_to, created_at
FROM transfers
WHERE outlet_to = ?
  AND state IN ('SENT','RECEIVING','PARTIAL')
  AND created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
  AND deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
ORDER BY created_at DESC
```

### 2. Get Transfer Items (Active Only)
```sql
SELECT ti.*, p.name AS product_name, p.sku
FROM transfer_items ti
LEFT JOIN vend_products p ON ti.product_id = p.id 
    AND p.is_active = 1 AND p.is_deleted = 0
WHERE ti.transfer_id = ? AND ti.deleted_by IS NULL
ORDER BY ti.id ASC
```

### 3. Get Active Outlets Only
```sql
SELECT id, name, physical_city, is_warehouse
FROM vend_outlets 
WHERE deleted_at = '0000-00-00 00:00:00'
ORDER BY name ASC
```

### 4. Get Active Staff Only
```sql
SELECT id, first_name, last_name, email, role_id
FROM users 
WHERE staff_active = 1 AND account_locked = 0
ORDER BY first_name ASC
```

### 5. Search Active Products
```sql
SELECT id, name, sku, handle, brand_name, supply_price
FROM vend_products 
WHERE is_active = 1 AND is_deleted = 0
  AND (name LIKE ? OR sku LIKE ? OR handle LIKE ?)
ORDER BY name ASC
LIMIT 50
```

---

## ❌ INCORRECT PATTERNS (DO NOT USE)

### Wrong Column Names
```sql
-- ❌ WRONG: from_outlet_id/to_outlet_id do not exist
LEFT JOIN vend_outlets o ON t.from_outlet_id = o.id

-- ❌ WRONG: deleted_at for transfer_items (use deleted_by)
WHERE ti.deleted_at IS NULL

-- ❌ WRONG: outlet/outlets table name (use vend_outlets)
FROM outlet o
FROM outlets o
```

### Wrong Status Checks
```sql
-- ❌ WRONG: Missing active checks
FROM vend_products p  -- Missing is_active = 1 AND is_deleted = 0

-- ❌ WRONG: Wrong user status field
WHERE users.active = 1  -- Use staff_active = 1

-- ❌ WRONG: Wrong outlet deletion check
WHERE vend_outlets.deleted = 0  -- Use deleted_at = '0000-00-00 00:00:00'
```

---

## 🔍 QUICK VERIFICATION QUERIES

### Check Transfer Exists
```sql
SELECT id, status, outlet_from, outlet_to, created_at
FROM transfers 
WHERE id = ?
```

### Check Transfer Items Count
```sql
SELECT COUNT(*) as item_count
FROM transfer_items 
WHERE transfer_id = ? AND deleted_by IS NULL
```

### Check Outlets Exist
```sql
SELECT id, name 
FROM vend_outlets 
WHERE id IN (?, ?) AND deleted_at = '0000-00-00 00:00:00'
```

### Check User Permissions
```sql
SELECT id, first_name, last_name, gpt_access, gpt_admin
FROM users 
WHERE id = ? AND staff_active = 1
```

---

## 🚨 COMPANY COMPLIANCE CHECKLIST

Before any database query:
- [ ] Uses real column names (outlet_from/outlet_to)
- [ ] Includes proper active/deleted filters
- [ ] No unnecessary aliases
- [ ] Proper JOIN conditions with status checks
- [ ] Parameterized queries (no SQL injection)

---

**REFERENCE AUTHORITY: DATABASE_SCHEMA_COMPLETE.md**