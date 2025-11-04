# Action Bar Quick Reference Card 🎯

**CIS Classic Theme - Page Subtitle Feature**

---

## 🚀 Quick Start (30 Seconds)

```php
<?php
require_once __DIR__ . '/theme.php';
$theme = new \CIS\Theme\CISClassic();

// Add page subtitle
$theme->setPageSubtitle('Inventory Dashboard');
?>
```

**That's it!** The subtitle will appear in the action bar.

---

## 📋 Common Patterns

### Pattern 1: Subtitle Only
```php
$theme->setPageSubtitle('Sales Dashboard');
```

### Pattern 2: Subtitle + Breadcrumbs
```php
$theme->setPageSubtitle('Active Orders');
$theme->addBreadcrumb('Home', '/');
$theme->addBreadcrumb('Orders');
```

### Pattern 3: Subtitle + Action Button
```php
$theme->setPageSubtitle('Customer List');
$theme->addHeaderButton('New Customer', '/customers/new', 'primary', 'fas fa-plus');
```

### Pattern 4: Subtitle + Timestamp
```php
$theme->setPageSubtitle('Live Dashboard');
$theme->showTimestamps(true);
```

### Pattern 5: Full Featured
```php
$theme->setPageSubtitle('Order Management');
$theme->addBreadcrumb('Home', '/');
$theme->addBreadcrumb('Orders');
$theme->addHeaderButton('New', '/orders/new', 'primary', 'fas fa-plus');
$theme->addHeaderButton('Export', '/export', 'secondary', 'fas fa-download');
$theme->showTimestamps(true);
```

---

## 🎨 Button Colors

| Color | Use Case | Example |
|-------|----------|---------|
| `primary` | Main action | New, Create, Save |
| `success` | Positive action | Approve, Confirm, Complete |
| `danger` | Destructive | Delete, Remove, Cancel |
| `warning` | Caution | Archive, Suspend |
| `secondary` | Support action | Export, Settings |
| `info` | Information | View Details, Help |
| `purple` | Custom brand | Special features |
| `lime` | Custom brand | Special features |

---

## 🎯 Best Practices

### ✅ DO
- Keep subtitles short (2-5 words)
- Use title case: "Sales Dashboard"
- Be descriptive: "Active Consignments" not just "List"
- Add icons to buttons for clarity
- Use appropriate button colors

### ❌ DON'T
- Don't use long sentences as subtitle
- Don't use all lowercase: "sales dashboard"
- Don't be vague: "Page" or "Items"
- Don't overload with too many buttons (max 4)
- Don't use red buttons for non-destructive actions

---

## 📐 Layout Preview

```
┌─────────────────────────────────────────────────────────────────┐
│  Subtitle  >  Breadcrumb  >  Path    [Button] [Button]  📅     │
└─────────────────────────────────────────────────────────────────┘
     ↑              ↑                        ↑              ↑
  setPageSubtitle  addBreadcrumb   addHeaderButton  showTimestamps
```

---

## 🔍 Real-World Examples

### Dashboard Page
```php
$theme->setPageSubtitle('Sales Dashboard');
$theme->showTimestamps(true);
```
**Result:** `Sales Dashboard ................................. 📅 11/04/2025 2:30 PM`

---

### List/Index Page
```php
$theme->setPageSubtitle('Active Consignments');
$theme->addBreadcrumb('Home', '/');
$theme->addBreadcrumb('Consignments');
$theme->addHeaderButton('New', '/consignments/new', 'primary', 'fas fa-plus');
```
**Result:** `Active Consignments > Home > Consignments ......... [+ New]`

---

### Detail/View Page
```php
$theme->setPageSubtitle('Consignment #CS-12345');
$theme->addBreadcrumb('Home', '/');
$theme->addBreadcrumb('Consignments', '/consignments/');
$theme->addBreadcrumb('#CS-12345');
$theme->addHeaderButton('Edit', '/edit?id=12345', 'primary', 'fas fa-edit');
```
**Result:** `Consignment #CS-12345 > Home > Consignments > #CS-12345 ... [✏️ Edit]`

---

### Form Page
```php
$theme->setPageSubtitle('Create New Transfer');
$theme->addBreadcrumb('Home', '/');
$theme->addBreadcrumb('Transfers', '/transfers/');
$theme->addBreadcrumb('Create');
$theme->addHeaderButton('Cancel', '/transfers/', 'secondary');
```
**Result:** `Create New Transfer > Home > Transfers > Create ... [Cancel]`

---

### Report Page
```php
$theme->setPageSubtitle('Monthly Sales Report');
$theme->addBreadcrumb('Home', '/');
$theme->addBreadcrumb('Reports', '/reports/');
$theme->addBreadcrumb('Sales');
$theme->addHeaderButton('PDF', '/export?pdf', 'primary', 'fas fa-file-pdf');
$theme->addHeaderButton('Excel', '/export?xlsx', 'success', 'fas fa-file-excel');
$theme->showTimestamps(true);
```
**Result:** `Monthly Sales Report > Home > Reports > Sales ... [📄 PDF] [📊 Excel] 📅`

---

## 🛠 Troubleshooting

### Subtitle not showing?
```php
// Make sure you're using the method correctly
$theme->setPageSubtitle('Your Title Here');

// Check you rendered the header
$theme->renderHeader();
```

### Timestamp showing twice?
✅ **FIXED** - This bug was resolved. Update to latest version.

### Buttons not aligned right?
✅ **FIXED** - Buttons now auto-align. Update to latest version.

### Spacing looks wrong?
Make sure you're using the latest `header.php`:
```bash
# Check file modification date
ls -la components/header.php
# Should be Nov 4, 2025 or later
```

---

## 📚 More Information

- **Full Documentation:** README.md
- **Deep Dive:** ACTION_BAR_ENHANCEMENTS.md
- **Visual Guide:** ACTION_BAR_VISUAL_GUIDE.md
- **Live Demo:** examples/subtitle-demo.php

---

## 💡 Pro Tips

1. **Dynamic Subtitles:**
   ```php
   $theme->setPageSubtitle("Order #" . $orderId);
   ```

2. **Conditional Buttons:**
   ```php
   if ($canEdit) {
       $theme->addHeaderButton('Edit', '/edit', 'primary', 'fas fa-edit');
   }
   ```

3. **Icon Library:** Use FontAwesome 6.7.1
   - Search icons: https://fontawesome.com/icons
   - Example: `fas fa-plus`, `fas fa-download`, `fas fa-edit`

4. **Button Consistency:** Use same icons for same actions across all pages
   - Create/New: `fas fa-plus`
   - Edit: `fas fa-edit`
   - Delete: `fas fa-trash`
   - Export: `fas fa-download`
   - Settings: `fas fa-cog`

---

## ⚡ Performance Notes

- **Zero HTTP Requests** - Pure PHP rendering
- **No JavaScript Required** - Server-side only
- **Fast Rendering** - Simple conditional logic
- **Mobile Optimized** - Responsive by default

---

## ✅ Checklist for New Pages

- [ ] Add page subtitle for context
- [ ] Add breadcrumbs if page is deep in hierarchy
- [ ] Add primary action button if page has main action
- [ ] Add timestamp if page shows time-sensitive data
- [ ] Test on mobile (timestamp auto-hides)
- [ ] Use appropriate button colors
- [ ] Keep subtitle concise
- [ ] Use icons on buttons

---

**Version:** 2.0.0
**Last Updated:** November 4, 2025
**Status:** Production Ready ✅
