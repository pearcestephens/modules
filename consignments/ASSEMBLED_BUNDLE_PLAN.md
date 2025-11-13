# Consignments Module — Assembled Bundle Plan (2025-11-11)

This plan consolidates the most stable, modern pages for the Consignments module so it can be assembled and ready to go.

## Selected Entry Points
- Router: `index.php` (updated)
  - home → `views/home-CLEAN.php`
  - transfer-manager → `views/transfer-manager-v5.php`
  - control-panel → `views/control-panel.php`
  - purchase-orders → `views/purchase-orders.php`
  - stock-transfers → `views/stock-transfers.php`
  - receiving → `views/receiving.php`
  - freight → `views/freight-WORKING.php` (stable)
  - queue-status → `views/queue-status-SIMPLE.php` (stable)
  - admin-controls → `views/admin-controls.php`
  - ai-insights → `views/ai-insights.php`

- Ultra Template Router: `index-ultra.php` (optional, already wired to base Template)

## Core Pages (Best Variants)
- Dashboard/Home: `views/home-CLEAN.php` (selected over home.php variants)
- Transfer Manager: `views/transfer-manager-v5.php` (BS5 modern)
- Freight: `views/freight-WORKING.php` (selected over complex)
- Queue Status: `views/queue-status-SIMPLE.php` (selected over complex)
- Receiving: `views/receiving.php` (BS5 backup available)
- Purchase Orders: `views/purchase-orders.php` (BS5 backup available)
- Stock Transfers: `views/stock-transfers.php` (BS5 backup available)
- Admin Controls: `views/admin-controls.php`
- AI Insights: `views/ai-insights.php` (fixed variant exists as backup)

## Services and Supporting Files
- Transfer Manager: `TransferManager/` frontend content included by `pages/transfer-manager.php`
- Assets:
  - CSS: `/modules/consignments/assets/css/*.css`
  - JS: `/modules/consignments/assets/js/*.js`
- Bootstrap: `bootstrap.php` (auth + env)

## Hardening Checklist
- [x] Router updated to known-stable page variants
- [ ] Ensure `views/*` files require shared/module bootstrap where needed
- [ ] Validate CSRF tokens for any POST actions
- [ ] Verify session auth checks exist on all entry points
- [ ] Confirm URLs in navs use `?route=` pattern consistently

## QA Quick-Dial
- Syntax:
  - `php -l modules/consignments/index.php`
- URL probes:
  - `curl -I "/modules/consignments/"`
  - `curl -I "/modules/consignments/?route=transfer-manager"`
  - `curl -I "/modules/consignments/?route=freight"`
  - `curl -I "/modules/consignments/?route=queue-status"`

## Notes
- Many backup variants exist (BS4_BACKUP, OLD_UI_BACKUP). We’ve chosen the CLEAN/WORKING/SIMPLE and v5 variants for stability.
- The ultra template router remains available for a unified base-template experience if desired.
