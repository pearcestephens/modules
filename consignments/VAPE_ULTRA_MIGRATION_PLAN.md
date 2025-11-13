# 🚀 Vape Ultra Migration Plan - Consignments Module

**Date:** 2025-11-11
**Status:** EXECUTING FULL MIGRATION
**Target:** 150% Production-Ready Integration

## 📋 CANONICAL FILE NAMING SCHEME

### Old → New Canonical Names:

| Old Filename | New Canonical Name | Route | Purpose |
|--------------|-------------------|-------|---------|
| `home-CLEAN.php` | `dashboard.php` | `home` | Main dashboard/landing page |
| `transfer-manager-v5.php` | `transfer-manager.php` | `transfer-manager` | Transfer creation/management |
| `freight-WORKING.php` | `freight.php` | `freight` | Freight management |
| `queue-status-SIMPLE.php` | `queue-status.php` | `queue-status` | Queue monitoring |
| `purchase-orders.php` | `purchase-orders.php` | `purchase-orders` | ✅ Already canonical |
| `stock-transfers.php` | `stock-transfers.php` | `stock-transfers` | ✅ Already canonical |
| `receiving.php` | `receiving.php` | `receiving` | ✅ Already canonical |
| `control-panel.php` | `control-panel.php` | `control-panel` | ✅ Already canonical |
| `admin-controls.php` | `admin-controls.php` | `admin-controls` | ✅ Already canonical |
| `ai-insights.php` | `ai-insights.php` | `ai-insights` | ✅ Already canonical |
| `buttons-preview.php` | `buttons-preview.php` | `buttons-preview` | ✅ Already canonical |

### Files to Archive (Move to backups/):
- `home-CLEAN.php` → Archive after creating `dashboard.php`
- `transfer-manager-v5.php` → Archive after creating canonical `transfer-manager.php`
- `freight-WORKING.php` → Archive after creating canonical `freight.php`
- `queue-status-SIMPLE.php` → Archive after creating canonical `queue-status.php`
- All `*.BS4_BACKUP_*` files → Keep in backups/ (already archived)
- All `*.OLD_*` files → Keep in backups/ (already archived)

## 🎨 VAPE ULTRA TEMPLATE INTEGRATION

### Template Path Changes:

**OLD (Broken):**
```php
require_once dirname(dirname(__DIR__)) . '/base/_templates/layouts/dashboard.php';
```

**NEW (Vape Ultra):**
```php
require_once dirname(dirname(__DIR__)) . '/base/templates/vape-ultra/layouts/main.php';
```

### Template Variable Requirements:

All views must set:
```php
$moduleContent = ob_get_clean(); // Capture module output

// Optional but recommended:
$pageTitle = 'Page Title';
$breadcrumbs = [...];
$hideRightSidebar = false; // Set true to hide right sidebar
$rightSidebarContent = '...'; // Custom right sidebar content
```

### Available Vape Ultra Features:

**CSS/Design Tokens:**
- `/modules/base/templates/vape-ultra/assets/css/variables.css` (design tokens)
- `/modules/base/templates/vape-ultra/assets/css/components.css` (pre-built components)
- `/modules/base/templates/vape-ultra/assets/css/animations.css` (smooth transitions)

**JavaScript Libraries (Auto-loaded):**
- ✅ jQuery 3.7.1
- ✅ Bootstrap 5.3.2
- ✅ Chart.js 4.4.0 (for dashboards)
- ✅ Axios 1.6.0 (HTTP client)
- ✅ Lodash 4.17.21 (utilities)
- ✅ Moment.js 2.29.4 (date formatting)
- ✅ SweetAlert2 (beautiful notifications)

**Custom JS Modules:**
- `VapeUltra.API` - HTTP client with auth/retries
- `VapeUltra.Notifications` - Toast notifications
- `VapeUltra.Charts` - Chart.js helpers
- `VapeUltra.Utils` - Currency, dates, clipboard, etc

## 🔧 MIGRATION PROCESS

### Step 1: Rename Files to Canonical Names ✅
- Copy best variant to canonical name
- Update with Vape Ultra template path
- Archive old variants

### Step 2: Update Router (index.php) ✅
- Point routes to canonical filenames
- Remove variant suffixes

### Step 3: Polish Each View 150% ✅
- Update template path to Vape Ultra
- Ensure proper `$moduleContent` capture
- Add page metadata ($pageTitle, $breadcrumbs)
- Leverage Vape Ultra JS utilities where beneficial
- Add inline scripts using `$inlineScripts` if needed

### Step 4: Cleanup & Archive ✅
- Move old variants to backups/archived/
- Keep only canonical files in views/
- Update documentation

### Step 5: Validation ✅
- Syntax check all files
- Test each route loads correctly
- Verify Vape Ultra assets load
- Check responsive layout
- Validate authentication

## 📊 SUCCESS CRITERIA (150% Standard)

✅ All views use canonical filenames
✅ All views use Vape Ultra template system
✅ All routes updated in router
✅ Zero syntax errors
✅ All pages load and render correctly
✅ Authentication enforced on all routes
✅ Breadcrumbs functional
✅ Responsive design working
✅ JavaScript features operational
✅ No console errors
✅ Documentation updated
✅ Old variants archived safely

## 🎯 COMPLETION CHECKLIST

- [ ] Rename `home-CLEAN.php` → `dashboard.php`
- [ ] Rename `transfer-manager-v5.php` → `transfer-manager.php`
- [ ] Rename `freight-WORKING.php` → `freight.php`
- [ ] Rename `queue-status-SIMPLE.php` → `queue-status.php`
- [ ] Update all 11 views to Vape Ultra template
- [ ] Update router to point to canonical names
- [ ] Archive old variant files
- [ ] Syntax validation pass
- [ ] Integration test all routes
- [ ] Update PAGE_STATUS_REPORT.md
- [ ] Update ASSEMBLED_BUNDLE_PLAN.md

---

**Next Action:** Execute full migration with 150% polish level! 🚀
