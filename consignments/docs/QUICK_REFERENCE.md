# 🚀 Quick Reference Card - Pack Page Features

## 🎯 Quick Commands

### Test the Page
```bash
# URL format
https://staff.vapeshed.co.nz/modules/consignments/stock-transfers/pack.php?id=TRANSFER_ID

# Check database schema
php /modules/consignments/migrations/check-autosave-schema.php

# View auto-save errors in console
F12 → Console tab
```

### Common Issues & Fixes

| Issue | Fix |
|-------|-----|
| "isNumberKey not defined" | Added to `window` scope in pack.js line 704 |
| Auto-save red error | Run database migration script |
| Toast not showing | Load `/assets/js/cis-toast.js` first |
| Spinner arrows visible | Check pack.css loaded correctly |
| Print shows colors | Clear cache (Ctrl+Shift+R) |

---

## 💻 JavaScript API

### Toast Notifications
```javascript
CIS.Toast.success('Item added!');
CIS.Toast.error('Failed to save');
CIS.Toast.warning('Check quantity');
CIS.Toast.info('Processing...');
```

### Validation
```javascript
isNumberKey(event)              // Blocks decimals
validateCountedQty(input)       // Validates & colors row
detectUnusualNumber(val, plan, stock)  // Fraud check
```

---

## 🎨 CSS Classes

### Row Validation
```css
.table-success  /* Green - perfect match */
.table-warning  /* Yellow - under count */
.table-danger   /* Red - over count */
```

### Input Styling
```css
.counted-qty    /* Compact, no spinners */
```

---

## 🗄️ Database Columns

```sql
transfers.draft_data          -- TEXT (JSON)
transfers.draft_updated_at    -- DATETIME
```

---

## 📱 Fraud Patterns Detected

1. **Repeating digits** - 111, 222, 999
2. **Sequential digits** - 123, 456, 789
3. **Round numbers** - 100, 500, 1000 (when planned is small)
4. **Typing errors** - 50 when planned is 5
5. **Extreme overage** - 300%+ over planned
6. **Exceeds stock** - 50%+ over available stock

---

## 🖨️ Print Features

- ✅ Tick boxes replace product images
- ✅ Manual write lines for quantities
- ✅ Number of boxes field
- ✅ Packed By / Received By signatures
- ✅ Transfer barcode
- ✅ Summary section with totals

---

## ⚡ Performance Targets

- Page load: < 2s
- Auto-save: < 500ms
- Toast display: < 100ms
- Validation: Real-time (0ms)

---

## 🔧 Quick Edits

### Change Auto-Save Delay
```javascript
// pack.js line 20
const AUTO_SAVE_DEBOUNCE = 2000; // milliseconds
```

### Change Toast Duration
```javascript
// cis-toast.js line 22
delay: 4000, // milliseconds
```

### Change Max Simultaneous Toasts
```javascript
// cis-toast.js line 24
maxToasts: 5, // number
```

---

## 📞 Support

**Documentation:** `/modules/consignments/PACK_PAGE_ENHANCEMENTS.md`  
**Schema Check:** `php check-autosave-schema.php`  
**Error Logs:** `/logs/apache_phpstack-*.error.log`  
**Browser Console:** F12 → Console

---

**Version:** 2.0.0 | **Status:** ✅ Production Ready
