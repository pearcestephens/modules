# CIS Database Schema Documentation

**Updated:** October 12, 2025  
**Context:** Transfer/Receive Module Database Queries  

## Core Vend Tables for Transfer System

### 1. `vend_products` Table
**Purpose:** All products available for sale and transfer  
**Key Fields:**
- `id` (varchar(100)) - Unique product identifier
- `name` (varchar(255)) - Product name
- `sku` (varchar(200)) - Stock keeping unit
- `active` (int) - Product is available for sale (1/0)
- `is_active` (int) - Product is active in operations (1/0)
- `is_deleted` (tinyint) - Generated field: `deleted_at` <> '0000-00-00 00:00:00'
- `has_inventory` (int) - Product has stock tracking (1/0)
- `supplier_id` (varchar(200)) - Links to supplier
- `brand_id` (varchar(200)) - Links to brand
- `price_including_tax` (decimal(13,5)) - Retail price with tax
- `supply_price` (decimal(13,5)) - Cost from supplier
- `avg_weight_grams` (int) - Average weight for shipping

**CRITICAL QUERY PATTERN:**
```sql
-- CORRECT way to get active products
SELECT * FROM vend_products 
WHERE is_active = 1 AND is_deleted = 0
```

### 2. `vend_inventory` Table
**Purpose:** Stock levels at each outlet  
**Key Fields:**
- `id` (varchar(100)) - Unique inventory record
- `outlet_id` (varchar(100)) - Store location
- `product_id` (varchar(100)) - Product reference
- `inventory_level` (int) - Current stock quantity
- `current_amount` (int) - Actual available stock
- `reorder_point` (int) - Minimum before reorder
- `average_cost` (decimal(16,6)) - Average acquisition cost

**Key Query Pattern:**
```sql
-- Get stock levels for transfer
SELECT vi.product_id, vi.outlet_id, vi.current_amount, vi.inventory_level
FROM vend_inventory vi
WHERE vi.outlet_id = ? AND vi.deleted_at IS NULL
```

### 3. `vend_outlets` Table
**Purpose:** Store locations and warehouses  
**Key Fields:**
- `id` (varchar(100)) - Unique outlet identifier
- `name` (varchar(100)) - Store name
- `is_warehouse` (int) - Is warehouse (1) or store (0)
- `automatic_ordering` (int) - Auto-ordering enabled
- `deleted_at` (timestamp) - Removal timestamp

**CRITICAL SHIPPING & ADDRESS FIELDS:**
- `nz_post_api_key` (varchar(45)) - NZ Post API access key
- `nz_post_subscription_key` (varchar(45)) - NZ Post subscription identifier
- `gss_token` (varchar(100)) - Google services integration token

**PHYSICAL ADDRESS FIELDS:**
- `physical_street_number` (varchar(45)) - Street number
- `physical_street` (varchar(45)) - Street name
- `physical_address_1` (varchar(100)) - Primary address line
- `physical_address_2` (varchar(100)) - Secondary address (unit/building)
- `physical_suburb` (varchar(100)) - Suburb/district
- `physical_city` (varchar(255)) - City name
- `physical_postcode` (varchar(100)) - Postal code
- `physical_state` (varchar(100)) - State/province
- `physical_country_id` (varchar(100)) - Country code
- `physical_phone_number` (varchar(45)) - Contact phone

**CRITICAL QUERY FOR VALID OUTLETS:**
```sql
## 4. vend_outlets

**Purpose:** Retail outlet/store information  
**Primary Key:** `id`

### Complete Schema:
- `id` (varchar(100)) - Outlet identifier (PRIMARY KEY)
- `register_id` (varchar(100)) - Register identifier
- `name` (varchar(100)) - Outlet name
- `default_tax_id` (varchar(100)) - Default tax configuration
- `currency` (varchar(100)) - Currency code
- `currency_symbol` (varchar(100)) - Currency symbol
- `display_prices` (varchar(100)) - Price display setting
- `time_zone` (varchar(100)) - Outlet timezone
- `physical_street_number` (varchar(45)) - Street number
- `physical_street` (varchar(45)) - Street name
- `physical_address_1` (varchar(100)) - Primary address
- `physical_address_2` (varchar(100)) - Secondary address
- `physical_suburb` (varchar(100)) - Suburb
- `physical_city` (varchar(255)) - City
- `physical_postcode` (varchar(100)) - Postal code
- `physical_state` (varchar(100)) - State/region
- `physical_country_id` (varchar(100)) - Country identifier
- `physical_phone_number` (varchar(45)) - Phone number
- `deleted_at` (timestamp) - Soft delete flag
- `version` (bigint(20)) - Version number
- `turn_over_rate` (float) - Turnover rate
- `automatic_ordering` (int(11)) - Auto ordering flag
- `facebook_page_id` (varchar(45)) - Facebook page ID
- `gss_token` (varchar(100)) - GSS token
- `google_page_id` (varchar(100)) - Google page ID
- `total_review_count` (int(11)) - Total reviews
- `google_review_rating` (float(2,1)) - Google rating
- `store_code` (varchar(45)) - Store code
- `magento_warehouse_id` (int(11)) - Magento warehouse ID
- `google_link` (varchar(100)) - Google link
- `outlet_lat` (varchar(45)) - Latitude
- `outlet_long` (varchar(45)) - Longitude
- `website_active` (int(11)) - Website active flag
- `website_outlet_id` (int(11)) - Website outlet ID
- `deposit_card_id` (int(11)) - Deposit card ID
- `vape_hq_shipping_id` (varchar(45)) - VapeHQ shipping ID
- `banking_days_allocated` (int(11)) - Banking days
- `email` (varchar(45)) - Outlet email
- `nz_post_api_key` (varchar(45)) - NZ Post API key
- `nz_post_subscription_key` (varchar(45)) - NZ Post subscription key
- `ip_address` (varchar(45)) - IP address
- `deputy_location_id` (int(11)) - Deputy location ID
- `eftpos_merchant_id` (int(11)) - EFTPOS merchant ID
- `created_at` (timestamp) - Creation timestamp
- `is_warehouse` (int(11)) - Warehouse flag

### Query Pattern:
```sql
SELECT * FROM vend_outlets 
WHERE deleted_at = '0000-00-00 00:00:00'  -- Active outlets only
```
```

### 4. `vend_suppliers` Table
**Purpose:** Product suppliers and vendors  
**Key Fields:**
- `id` (varchar(100)) - Unique supplier identifier
- `name` (varchar(100)) - Supplier business name
- `automatic_transferring` (int) - Auto-transfer enabled
- `enable_product_returns` (int) - Returns allowed
- `show_in_system` (int) - Visible in system

### 5. `vend_brands` Table
**Purpose:** Product brands  
**Key Fields:**
- `id` (varchar(45)) - Unique brand identifier
- `name` (varchar(100)) - Brand name
- `enable_store_transfers` (int) - Transfer enabled

## Critical Product Search Queries

### Current Search Function (FIXED)
```sql
-- OLD WRONG QUERY in search_products.php
SELECT v.id AS product_id, v.sku, v.name, COALESCE(i.on_hand,0) as stock
FROM vend_products v
LEFT JOIN vend_inventory i ON i.product_id = v.id AND i.outlet_id = :outlet
WHERE v.deleted_at IS NULL
  AND (v.sku LIKE :q OR v.name LIKE :q)
ORDER BY v.name LIMIT 100

-- CORRECTED QUERY - Must use proper active flags
SELECT v.id AS product_id, v.sku, v.name, COALESCE(i.current_amount,0) as stock
FROM vend_products v
LEFT JOIN vend_inventory i ON i.product_id = v.id AND i.outlet_id = :outlet
WHERE v.is_active = 1 AND v.is_deleted = 0
  AND (v.sku LIKE :q OR v.name LIKE :q)
ORDER BY v.name LIMIT 100
```

## Transfer System Queries

### Get Transfer Details
```sql
SELECT t.*, 
       o_from.name AS from_outlet_name,
       o_to.name AS to_outlet_name,
       u.username AS created_by_username
FROM transfers t
LEFT JOIN vend_outlets o_from ON t.from_outlet_id = o_from.id
LEFT JOIN vend_outlets o_to ON t.to_outlet_id = o_to.id
LEFT JOIN users u ON t.created_by = u.id
WHERE t.id = ? AND t.transfer_mode = ?
```

### Get Transfer Items with Product Details
```sql
SELECT ti.*, p.name AS product_name, p.sku, p.avg_weight_grams
FROM transfer_items ti
LEFT JOIN vend_products p ON ti.product_id = p.id
WHERE ti.transfer_id = ?
```

### Add Transfer Line
```sql
-- Check existing line
SELECT id, qty_requested, qty_sent_total, qty_received_total 
FROM transfer_items 
WHERE transfer_id = ? AND product_id = ? AND deleted_at IS NULL LIMIT 1

-- Insert new line
INSERT INTO transfer_items (transfer_id, product_id, qty_requested, qty_sent_total, qty_received_total, confirmation_status, created_at) 
VALUES (?,?,?,?,?, 'pending', NOW())
```

### Product Search with Stock
```sql
SELECT v.id AS product_id, v.sku, v.name, v.brand, v.supplier_id,
       COALESCE(i.current_amount, 0) as stock_level,
       COALESCE(i.inventory_level, 0) as inventory_level,
       v.price_including_tax, v.avg_weight_grams
FROM vend_products v
LEFT JOIN vend_inventory i ON i.product_id = v.id AND i.outlet_id = ?
WHERE v.is_active = 1 AND v.is_deleted = 0
  AND v.has_inventory = 1
  AND (v.sku LIKE ? OR v.name LIKE ?)
ORDER BY v.name
LIMIT 100
```

## Performance Indexes

### Key Indexes for Transfer Queries
- `vend_products`: `idx_vend_products_active_inventory` (active, has_inventory, is_active, supplier_id, brand_id)
- `vend_inventory`: `idx_prod_outlet` (product_id, outlet_id)
- `vend_outlets`: `ix_vend_outlets_warehouse` (is_warehouse)
- `transfer_items`: Primary and foreign keys for transfer_id, product_id

## Query Optimization Notes

1. **Always filter by active flags first**: `is_active = 1 AND is_deleted = 0`
2. **Use proper field names**: `current_amount` not `on_hand` in vend_inventory
3. **Include weight for shipping**: `avg_weight_grams` for transfer calculations
4. **Filter by outlet early**: Join with outlet_id in WHERE clause
5. **Limit results**: Always use LIMIT for search queries

## Common Mistakes to Avoid

❌ `WHERE deleted_at IS NULL` - Use `is_deleted = 0` instead  
❌ `i.on_hand` - Should be `i.current_amount`  
❌ Missing `is_active = 1` filter  
❌ Not checking `has_inventory = 1` for stock items  
❌ Forgetting to ORDER BY name for user experience  

## Schema Validation

All tables use:
- `utf8mb4_unicode_ci` charset for proper Unicode support
- Proper foreign key relationships where applicable
- Appropriate indexes for performance
- Consistent timestamp handling