# TRANSFERS TABLE SCHEMA - SAVED FOR REFERENCE

**Table Name:** `transfers`

## Key Fields (from DESCRIBE transfers):
- `id` - int(11) - PRIMARY KEY
- `outlet_from` - varchar(100) - Source outlet ID
- `outlet_to` - varchar(100) - Destination outlet ID
- `status` - enum('draft','open','sent','partial','received','cancelled','archived')
- `state` - enum('OPEN','PACKING','PACKAGED','SENT','RECEIVING','RECEIVED','CLOSED','CANCELLED')
- `transfer_type` - enum('GENERAL','JUICE','STAFF','AUTOMATED')
- `type` - enum('stock','juice','staff','purchase_order','return')
- `vend_transfer_id` - char(36)
- `vend_resource` - enum('consignment','purchase_order','transfer')
- `created_at` - timestamp
- `updated_at` - timestamp

## CRITICAL JOIN PATTERN:
```sql
-- CORRECT outlet references
LEFT JOIN vend_outlets o_from ON t.outlet_from = o_from.id
LEFT JOIN vend_outlets o_to ON t.outlet_to = o_to.id
```

**COMPANY RULE: NO ALIASES - use real column names: outlet_from, outlet_to**