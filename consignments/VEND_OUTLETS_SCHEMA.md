# VEND_OUTLETS Table Schema - SAVED FOR REFERENCE

**Table Name:** `vend_outlets` (NOT outlet, NOT outlets - it's vend_outlets)

## Primary Key
- `id` varchar(100) PK

## Core Fields
- `name` varchar(100) - Outlet name
- `register_id` varchar(100)
- `default_tax_id` varchar(100)
- `currency` varchar(100)
- `currency_symbol` varchar(100)
- `display_prices` varchar(100)
- `time_zone` varchar(100)

## Physical Address
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

## Status & Metadata
- `deleted_at` timestamp - Use deleted_at = '0000-00-00 00:00:00' for active outlets
- `version` bigint(20)
- `created_at` timestamp

## Business Fields
- `turn_over_rate` float
- `automatic_ordering` int(11)
- `store_code` varchar(45)
- `website_active` int(11)
- `website_outlet_id` int(11)
- `banking_days_allocated` int(11)
- `email` varchar(45)
- `is_warehouse` int(11)

## Integration IDs
- `magento_warehouse_id` int(11)
- `deputy_location_id` int(11)
- `eftpos_merchant_id` int(11)
- `deposit_card_id` int(11)

## Social & Reviews
- `facebook_page_id` varchar(45)
- `google_page_id` varchar(100)
- `google_link` varchar(100)
- `total_review_count` int(11)
- `google_review_rating` float(2,1)

## Location
- `outlet_lat` varchar(45)
- `outlet_long` varchar(45)

## Shipping Integration
- `vape_hq_shipping_id` varchar(45)
- `nz_post_api_key` varchar(45)
- `nz_post_subscription_key` varchar(45)
- `gss_token` varchar(100)

## Technical
- `ip_address` varchar(45)

## CRITICAL QUERY PATTERNS
```sql
-- Get active outlets only
SELECT * FROM vend_outlets WHERE deleted_at = '0000-00-00 00:00:00'

-- Join with transfers (correct pattern)
LEFT JOIN vend_outlets o ON t.from_outlet_id = o.id AND o.deleted_at = '0000-00-00 00:00:00'
```

**COMPANY RULE: NO ALIASES unless 100% needed - use real column names**