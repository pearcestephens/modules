# Consignments Module - View Structure

## ✅ CONVERSION COMPLETE!

The Consignments module has been fully converted to use the BASE template system with proper routing.

---

## 📂 Directory Structure

```
modules/consignments/
├── index.php                          # Router entry point (NEW)
├── bootstrap.php                      # Module bootstrap
├── views/                             # All views (NEW STRUCTURE)
│   ├── transfer-manager.php          # Main Transfer Manager interface ✅
│   ├── control-panel.php             # System monitoring dashboard ✅
│   ├── purchase-orders.php           # PO list view ✅
│   ├── stock-transfers.php           # Transfer list view ✅
│   ├── queue-status.php              # Queue monitoring ✅
│   ├── freight.php                   # Freight management ✅
│   └── admin-controls.php            # Admin settings ✅ (existing)
├── TransferManager/                   # Transfer Manager components
│   ├── frontend-content.php          # UI body content
│   ├── api.php                       # API endpoint
│   ├── js/                           # 8 JavaScript modules
│   └── styles.css                    # Custom styles
├── purchase-orders/                   # PO specific pages
├── stock-transfers/                   # Transfer specific pages
└── ...
```

---

## 🎯 Available Routes

All routes use: `/modules/consignments/?endpoint=ROUTE_NAME`

| Route | View File | Purpose |
|-------|-----------|---------|
| `index` or `` | `transfer-manager.php` | Main Transfer Manager tool (DEFAULT) |
| `transfer-manager` | `transfer-manager.php` | Main Transfer Manager tool |
| `control-panel` | `control-panel.php` | System stats and monitoring |
| `purchase-orders` | `purchase-orders.php` | Browse purchase orders |
| `stock-transfers` | `stock-transfers.php` | Browse stock transfers |
| `queue-status` | `queue-status.php` | Monitor sync queue |
| `freight` | `freight.php` | Freight bookings |
| `admin-controls` | `admin-controls.php` | Admin settings |

---

## 🔗 URL Examples

```
# Main Transfer Manager (default)
https://staff.vapeshed.co.nz/modules/consignments/

# Control Panel
https://staff.vapeshed.co.nz/modules/consignments/?endpoint=control-panel

# Purchase Orders
https://staff.vapeshed.co.nz/modules/consignments/?endpoint=purchase-orders

# Stock Transfers
https://staff.vapeshed.co.nz/modules/consignments/?endpoint=stock-transfers

# Queue Status
https://staff.vapeshed.co.nz/modules/consignments/?endpoint=queue-status

# Freight Management
https://staff.vapeshed.co.nz/modules/consignments/?endpoint=freight

# Admin Controls
https://staff.vapeshed.co.nz/modules/consignments/?endpoint=admin-controls
```

---

## ✨ What Changed

### Before (Standalone):
- ❌ One monolithic index.php with full HTML structure
- ❌ No routing system
- ❌ Only Bootstrap 5.3.3 loaded
- ❌ No sidebar or navigation
- ❌ Inconsistent with rest of CIS

### After (BASE Template):
- ✅ Clean router-based architecture
- ✅ 7 properly structured views
- ✅ Full BASE template integration (sidebar, header, breadcrumbs)
- ✅ All 11 modern libraries available (jQuery, DataTables, Chart.js, etc.)
- ✅ Consistent styling with rest of CIS
- ✅ Navigation included automatically
- ✅ User authentication integrated
- ✅ Breadcrumbs for each page

---

## 🎨 Features Now Available

### From BASE Template:
- ✅ **jQuery 3.7.1** - DOM manipulation and AJAX
- ✅ **Bootstrap 5.3.2** - Full framework (grid, utilities, components)
- ✅ **DataTables 1.13.7** - Advanced tables with export (Excel, PDF, Print)
- ✅ **Chart.js 4.4.0** - Data visualization
- ✅ **Select2 4.1.0** - Enhanced dropdowns
- ✅ **Flatpickr 4.6.13** - Modern date picker
- ✅ **Moment.js 2.29.4** - Date manipulation
- ✅ **Toastr 2.1.4** - Toast notifications
- ✅ **SweetAlert2 11.10.1** - Beautiful modals
- ✅ **Axios 1.6.2** - HTTP client
- ✅ **Lodash 4.17.21** - Utility functions
- ✅ **Sidebar navigation** - Consistent UI
- ✅ **Breadcrumbs** - Clear navigation path
- ✅ **Auto tooltips/popovers** - Bootstrap components initialized

### From Transfer Manager:
- ✅ **Real-time transfer management** - Create, track, manage transfers
- ✅ **Lightspeed sync** - Two-way sync with Lightspeed Retail
- ✅ **Queue monitoring** - Track background jobs
- ✅ **Freight integration** - Book and track shipments
- ✅ **Statistics dashboard** - System health and metrics

---

## 📝 View Templates

Each view follows this pattern:

```php
<?php
declare(strict_types=1);

// Page metadata
$pageTitle = 'Page Title';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/'],
    ['label' => 'Current Page', 'url' => '', 'active' => true]
];

// Optional custom CSS/JS
$pageCSS = ['/path/to/custom.css'];
$pageJS = ['/path/to/custom.js'];

// Start output buffering
ob_start();
?>

<!-- Your HTML content here -->

<?php
// Get buffered content
$content = ob_get_clean();

// Include BASE dashboard layout
require_once dirname(dirname(__DIR__)) . '/base/_templates/layouts/dashboard.php';
```

---

## 🚀 Next Steps

### Immediate:
1. Test each route in browser
2. Verify Transfer Manager loads correctly
3. Check DataTables initialization
4. Test breadcrumb navigation

### Future Enhancements:
1. Add more views for specific workflows
2. Integrate existing purchase-orders/*.php pages
3. Integrate existing stock-transfers/*.php pages
4. Add API documentation view
5. Add webhook management view

---

## 🐛 Troubleshooting

### If Transfer Manager doesn't load:
- Check browser console for JavaScript errors
- Verify `/modules/consignments/TransferManager/js/` files exist
- Check `window.TT_CONFIG` is defined

### If DataTables don't initialize:
- Verify jQuery loads before DataTables
- Check table has `id` attribute
- Open console and check for errors

### If breadcrumbs don't show:
- Verify `$breadcrumbs` array is set
- Check BASE template includes breadcrumb rendering

### If sidebar is missing:
- Verify BASE template path is correct
- Check authentication passed
- Verify ob_start() and ob_get_clean() used correctly

---

## 📊 Success Metrics

- ✅ 7 views created (was 1)
- ✅ Router with 7+ routes (was none)
- ✅ BASE template integrated (was standalone)
- ✅ 11 modern libraries available (was 1)
- ✅ Consistent styling across module
- ✅ All views have breadcrumbs
- ✅ All views authenticated
- ✅ All syntax validated

---

**Status:** ✅ READY FOR TESTING
**Created:** 2025-11-04
**Version:** 3.0.0
