# TABLE NAMING CONVENTION STANDARD

**Date:** 2025-11-09
**Status:** ✅ IMPLEMENTED IN INSTALLER
**Scope:** New modules only (no legacy table changes)

## Convention: Prefix-Based Namespacing

All new CIS module tables use a short prefix to identify their module ownership.

### Format
```
{prefix}_{entity}_{subtype}
```

## Module Prefixes

| Module | Prefix | Example Tables |
|--------|--------|---------------|
| **Admin UI** | `theme_` | theme_themes, theme_settings, theme_ai_configs, theme_analytics |
| **Control Panel** | `cp_` | cp_backups, cp_config, cp_logs, cp_registry, cp_maintenance |
| **HR Portal** | `hr_` | hr_review_questions, hr_review_responses, hr_tracking_defs, hr_tracking_entries |
| **E-Commerce Ops** | `ecom_` | ecom_orders, ecom_order_items, ecom_inventory_sync, ecom_age_verify, ecom_site_sync_log |
| **Bank Transactions** | `bank_` | bank_transactions, bank_matches |

## Legacy/Pre-Existing Tables (UNCHANGED)

These tables remain as-is to avoid breaking existing code:

- **Employee Onboarding:** users, roles, permissions, role_permissions, user_roles
- **Outlets:** outlets, outlet_photos, outlet_operating_hours, etc.
- **Business Intelligence:** financial_snapshots, revenue_by_category, forecasts
- **Store Reports:** store_reports, store_report_*
- **Staff Performance:** staff_performance_stats, staff_achievements
- **Consignments:** vend_consignments, vend_consignment_line_items (Vend system)
- **Flagged Products:** flagged_products, product_flags, flagged_products_*
- **Staff Accounts:** staff_account_*, staff_payment_*
- **Human Resources:** payroll_runs, payroll_*
- **Stock Transfer Engine:** stock_transfers, stock_transfer_*
- **Crawler Engine:** crawler_sessions, crawler_*

## External System Prefixes (Unchanged)

- `vend_` - Lightspeed/Vend POS tables
- `deputy_` - Deputy workforce management tables
- `xero_` - Xero accounting tables
- `stripe_` - Stripe payment tables

## Benefits

1. **Module Identification:** `SHOW TABLES LIKE 'cp_%'` instantly shows all Control Panel tables
2. **No Conflicts:** Each module has its own namespace
3. **Professional:** Clear, concise, industry-standard approach
4. **Scalable:** Easy to add new modules without naming collisions
5. **Legacy Safe:** Doesn't touch working production tables

## Implementation Status

✅ **Installer Updated:** All 5 new modules updated with correct prefixes
✅ **Schema Files:** To be created with new naming when modules are installed
📋 **Migration:** Not needed (tables don't exist yet)
🚀 **Next:** Install modules via installer dashboard - tables will be created with correct names

## Finding Module Tables

```sql
-- All Control Panel tables
SHOW TABLES LIKE 'cp_%';

-- All HR Portal tables
SHOW TABLES LIKE 'hr_%';

-- All E-commerce tables
SHOW TABLES LIKE 'ecom_%';

-- All theme/admin tables
SHOW TABLES LIKE 'theme_%';

-- All CIS module tables (future: mod_ convention)
SHOW TABLES LIKE 'mod_%';
```

## Future Convention (Optional)

For future modules, consider using `mod_` prefix:
- `mod_admin_*` instead of `theme_*`
- `mod_sys_*` instead of `cp_*`
- `mod_hr_*` instead of `hr_*`
- `mod_ecom_*` instead of `ecom_*`

This provides even clearer module ownership: `SHOW TABLES LIKE 'mod_%'`

---

**Last Updated:** 2025-11-09
**Updated By:** AI Agent
**Approved By:** Pearce Stephens
