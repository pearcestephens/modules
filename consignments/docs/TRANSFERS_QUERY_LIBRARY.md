# TRANSFERS QUERY LIBRARY - COMPLETE COLLECTION

**Updated:** October 12, 2025  
**Purpose:** Complete collection of all transfer queries with proper deletion filters  
**Critical Rule:** ALL queries must include: `deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)`  

---

## 🔍 BASIC QUERIES

### 1. Latest 200 Transfers (Any Category)
```sql
SELECT *
FROM transfers
WHERE deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
ORDER BY created_at DESC
LIMIT 200;
```

### 2. Pagination (Page N, Size M)
```sql
SELECT *
FROM transfers
WHERE deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
ORDER BY created_at DESC
LIMIT :limit OFFSET :offset;
```

### 3. Date Range (Inclusive Start, Exclusive End)
```sql
SELECT *
FROM transfers
WHERE created_at >= :from AND created_at < :to
  AND deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
ORDER BY created_at DESC;
```

---

## 📂 CATEGORY & METHOD QUERIES

### 4. By Category (WHAT)
```sql
SELECT *
FROM transfers
WHERE transfer_category = 'JUICE'
  AND deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
ORDER BY created_at DESC;
```

### 5. By Creation Method (HOW)
```sql
SELECT *
FROM transfers
WHERE creation_method = 'AUTOMATED'
  AND deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
ORDER BY created_at DESC;
```

---

## 🚛 WORKFLOW QUERIES

### 6. In-Flight Work Queue (Needs Action)
```sql
SELECT id, public_id, transfer_category, state, outlet_from, outlet_to, updated_at
FROM transfers
WHERE state IN ('OPEN','PACKING','PACKAGED','SENT','RECEIVING','PARTIAL')
  AND deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
ORDER BY updated_at DESC;
```

### 7. Completed vs Cancelled (Ops Reporting)
```sql
SELECT state, COUNT(*) AS n
FROM transfers
WHERE state IN ('RECEIVED','CLOSED','CANCELLED','ARCHIVED')
  AND deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
GROUP BY state
ORDER BY n DESC;
```

### 8. Work to PACK (Origin Outlet) - Last 14 Days
```sql
SELECT id, public_id, transfer_category, state, outlet_from, outlet_to, created_at
FROM transfers
WHERE outlet_from = :from_outlet
  AND state IN ('OPEN','PACKING','PACKAGED')
  AND created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
  AND deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
ORDER BY created_at DESC;
```

### 9. Work to RECEIVE (Destination Outlet) - Last 14 Days
```sql
SELECT id, public_id, transfer_category, state, outlet_from, outlet_to, created_at
FROM transfers
WHERE outlet_to = :to_outlet
  AND state IN ('SENT','RECEIVING','PARTIAL')
  AND created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
  AND deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
ORDER BY created_at DESC;
```

### 10. Pipe from X → Y: Current Status Mix
```sql
SELECT state, COUNT(*) AS n
FROM transfers
WHERE outlet_from = :from_outlet
  AND outlet_to   = :to_outlet
  AND deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
GROUP BY state
ORDER BY n DESC;
```

---

## 🧹 MAINTENANCE QUERIES

### 11. Stale DRAFTS Older Than 7 Days (Clean-up)
```sql
SELECT id, public_id, transfer_category, created_at
FROM transfers
WHERE state = 'DRAFT'
  AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
  AND deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
ORDER BY created_at ASC;
```

### 12. SENT but Not RECEIVED After 3 Days (Chase)
```sql
SELECT id, public_id, outlet_from, outlet_to, created_at, updated_at
FROM transfers
WHERE state = 'SENT'
  AND updated_at < DATE_SUB(NOW(), INTERVAL 3 DAY)
  AND deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
ORDER BY updated_at ASC;
```

### 13. PARTIAL for More Than 48h (Investigate)
```sql
SELECT id, public_id, outlet_from, outlet_to, created_at, updated_at
FROM transfers
WHERE state = 'PARTIAL'
  AND updated_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)
  AND deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
ORDER BY updated_at ASC;
```

### 14. Missing/Zero Weights (Data Quality)
```sql
SELECT id, public_id, transfer_category, total_boxes, total_weight_g
FROM transfers
WHERE (total_weight_g IS NULL OR total_weight_g = 0)
  AND deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
ORDER BY created_at DESC;
```

---

## 🔎 SEARCH QUERIES

### 19. Quick Search by Public ID or Vend Transfer ID
```sql
SELECT *
FROM transfers
WHERE (public_id = :id OR vend_transfer_id = :id)
  AND deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0);
```

### 20. Text Search Across Common Fields (Basic)
```sql
SELECT *
FROM transfers
WHERE CONCAT_WS(' ', public_id, outlet_from, outlet_to, transfer_category) LIKE CONCAT('%', :q, '%')
  AND deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
ORDER BY created_at DESC
LIMIT 200;
```

### 21. Possible Dupes by Vend Transfer ID (Should Be Unique)
```sql
SELECT vend_transfer_id, COUNT(*) AS n, MIN(id) AS first_id, MAX(id) AS last_id
FROM transfers
WHERE vend_transfer_id IS NOT NULL AND vend_transfer_id <> ''
GROUP BY vend_transfer_id
HAVING n > 1
ORDER BY n DESC;
```

---

## 📊 ANALYTICS QUERIES

### 22. Current In-Flight by Category
```sql
SELECT transfer_category, COUNT(*) AS n
FROM transfers
WHERE state IN ('OPEN','PACKING','PACKAGED','SENT','RECEIVING','PARTIAL')
  AND deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
GROUP BY transfer_category
ORDER BY n DESC;
```

### 23. Top 10 Lanes (From→To) Last 30 Days
```sql
SELECT outlet_from, outlet_to, COUNT(*) AS n
FROM transfers
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  AND deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
GROUP BY outlet_from, outlet_to
ORDER BY n DESC
LIMIT 10;
```

### 24. Heavy Consignments Last 30 Days (> 20kg)
```sql
SELECT id, public_id, outlet_from, outlet_to, total_weight_g, created_at
FROM transfers
WHERE total_weight_g >= 20000
  AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  AND deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
ORDER BY total_weight_g DESC;
```

---

## 🔗 JOIN PATTERNS WITH CORRECT DELETION FILTERS

### Transfer with Outlets
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

### Transfer with Items and Products
```sql
SELECT t.*, ti.*, p.name AS product_name, p.sku
FROM transfers t
LEFT JOIN transfer_items ti ON t.id = ti.transfer_id 
    AND ti.deleted_by IS NULL
LEFT JOIN vend_products p ON ti.product_id = p.id 
    AND p.is_active = 1 AND p.is_deleted = 0
WHERE t.id = ?
  AND t.deleted_at IS NULL AND (t.deleted_by IS NULL OR t.deleted_by = 0)
```

---

## 🚨 CRITICAL RULES

### 1. MANDATORY DELETION FILTER
**EVERY transfers query MUST include:**
```sql
WHERE deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)
```

### 2. COLUMN NAMES
- Use `outlet_from` and `outlet_to` (NOT from_outlet_id/to_outlet_id)
- Use `deleted_by` for transfer_items (NOT deleted_at)
- Use `deleted_at = '0000-00-00 00:00:00'` for vend_outlets

### 3. PERFORMANCE
- Always include proper indexes on filtered columns
- Use LIMIT for large result sets
- Order by indexed columns when possible

---

**AUTHORITY:** This is the complete and correct query library for all transfer operations