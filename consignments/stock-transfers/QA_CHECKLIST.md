# ✅ PRE-DEPLOYMENT QA CHECKLIST

## 🎯 Production Readiness Verification

Use this checklist before deploying to production. Check off each item as you verify it works correctly.

---

## 📦 PART 1: File Verification (2 minutes)

### Core Files Present

```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/consignments/stock-transfers/
```

- [ ] `pack-layout-a-v2-PRODUCTION.php` exists (622 lines)
- [ ] `pack-layout-b-v2-PRODUCTION.php` exists (789 lines)
- [ ] `pack-layout-c-v2-PRODUCTION.php` exists (701 lines)
- [ ] `js/freight-engine.js` exists (678 lines)
- [ ] `js/pack-layout-b.js` exists (385 lines)
- [ ] `js/pack-layout-c.js` exists (328 lines)
- [ ] `README_PRODUCTION_LAYOUTS.md` exists (1,200+ lines)
- [ ] `DEPLOYMENT_GUIDE.md` exists (400+ lines)
- [ ] `DELIVERY_SUMMARY.md` exists (300+ lines)
- [ ] `VISUAL_COMPARISON.md` exists

### File Permissions Correct

```bash
ls -la pack-layout-*.php
ls -la js/*.js
```

- [ ] All PHP files: `-rw-r--r--` (644)
- [ ] All JS files: `-rw-r--r--` (644)
- [ ] Owned by correct user (www-data or similar)

### No Syntax Errors

```bash
php -l pack-layout-a-v2-PRODUCTION.php
php -l pack-layout-b-v2-PRODUCTION.php
php -l pack-layout-c-v2-PRODUCTION.php
node -c js/freight-engine.js
node -c js/pack-layout-b.js
node -c js/pack-layout-c.js
```

- [ ] Layout A: No syntax errors
- [ ] Layout B: No syntax errors
- [ ] Layout C: No syntax errors
- [ ] freight-engine.js: No syntax errors
- [ ] pack-layout-b.js: No syntax errors
- [ ] pack-layout-c.js: No syntax errors

---

## 🔧 PART 2: Configuration Verification (3 minutes)

### Environment Variables (.env)

- [ ] `DB_HOST` configured
- [ ] `DB_NAME` configured
- [ ] `DB_USER` configured
- [ ] `DB_PASS` configured
- [ ] `FREIGHT_API_ENABLED=true` (or false if testing without freight)
- [ ] `NZ_COURIER_API_KEY` present (if using live freight)
- [ ] `NZ_POST_API_KEY` present (if using live freight)
- [ ] `GOSWEETSPOT_API_KEY` present (if using live freight)
- [ ] `OPENAI_API_KEY` present (optional for AI recommendations)

### Database Tables Exist

```sql
SHOW TABLES LIKE 'transfers';
SHOW TABLES LIKE 'transfer_items';
SHOW TABLES LIKE 'vend_products';
SHOW TABLES LIKE 'freight_category_rules';
SHOW TABLES LIKE 'product_freight_overrides';
SHOW TABLES LIKE 'product_pack_map';
SHOW TABLES LIKE 'packaging_presets';
SHOW TABLES LIKE 'outlets';
```

- [ ] `transfers` table exists
- [ ] `transfer_items` table exists
- [ ] `vend_products` table exists (with `avg_weight_grams` column)
- [ ] `freight_category_rules` table exists
- [ ] `product_freight_overrides` table exists
- [ ] `product_pack_map` table exists
- [ ] `packaging_presets` table exists
- [ ] `outlets` table exists (with address columns)

### Backend Services Present

```bash
ls -la /home/master/applications/jcepnzzkmj/public_html/assets/services/core/freight/FreightEngine.php
ls -la /home/master/applications/jcepnzzkmj/public_html/modules/consignments/TransferManager/functions/transfer_functions.php
ls -la /home/master/applications/jcepnzzkmj/public_html/assets/themes/CISClassicTheme.php
ls -la /home/master/applications/jcepnzzkmj/private_html/app.php
```

- [ ] FreightEngine.php exists and readable
- [ ] transfer_functions.php exists and readable
- [ ] CISClassicTheme.php exists and readable
- [ ] app.php exists and readable

### Carrier Logos Present

```bash
ls -la /home/master/applications/jcepnzzkmj/public_html/assets/images/carriers/
```

- [ ] `nz-courier-logo.png` exists (or placeholder)
- [ ] `nz-post-logo.png` exists (or placeholder)
- [ ] Both images are < 100KB
- [ ] Both images are readable by web server

---

## 🌐 PART 3: Layout A Testing (5 minutes)

### Open Layout A

URL: `https://staff.vapeshed.co.nz/?endpoint=consignments/stock-transfers/pack-layout-a-v2-PRODUCTION&transfer_id=XXX`

Replace `XXX` with a valid transfer ID from your system.

### Visual Rendering

- [ ] Page loads without errors (check browser console F12)
- [ ] Header displays correctly with breadcrumbs
- [ ] Transfer info bar shows (from → to outlets)
- [ ] Stats bar displays (4 stat boxes)
- [ ] Search bar visible with magnifying glass icon
- [ ] Product table renders with all products
- [ ] Product images load correctly
- [ ] Weight badges show (P/C/D codes)
- [ ] Freight sidebar visible on right
- [ ] Sidebar stays visible during scroll (sticky)
- [ ] No horizontal scrolling on desktop

### Functionality Testing

- [ ] Search bar filters products correctly
- [ ] +/− buttons adjust quantity
- [ ] Quantity input accepts typed numbers
- [ ] Stats update when quantities change
- [ ] Packed items count updates correctly
- [ ] Total weight updates correctly
- [ ] Product rows change color when packed
- [ ] Freight console updates after packing items
- [ ] Weight summary displays in freight console
- [ ] Carrier options appear (NZ Courier + NZ Post)
- [ ] Carrier logos display correctly
- [ ] AI recommendation badge shows on one carrier
- [ ] Can select carrier by clicking card
- [ ] "Book Freight" button is clickable

### Barcode Scanner Test

- [ ] Can scan barcode into search field
- [ ] Search filters to matching product
- [ ] Can scan multiple products quickly

### Save & Complete

- [ ] "Save Progress" button works (check alert)
- [ ] "Print Packing Slip" opens new tab/window
- [ ] Auto-save triggers every 30 seconds (check console)

---

## 📱 PART 4: Layout B Testing (5 minutes)

### Open Layout B

URL: `https://staff.vapeshed.co.nz/?endpoint=consignments/stock-transfers/pack-layout-b-v2-PRODUCTION&transfer_id=XXX`

### Visual Rendering

- [ ] Page loads without errors
- [ ] Header displays correctly
- [ ] Transfer info bar shows
- [ ] Stats grid displays (4 stat boxes)
- [ ] Tab navigation visible (Products/Freight/AI/Summary)
- [ ] Products tab is active by default
- [ ] Product cards render in grid
- [ ] Product images load
- [ ] Weight badges show

### Tab Switching

- [ ] Can click "Products" tab
- [ ] Can click "Freight" tab
- [ ] Can click "AI Insights" tab
- [ ] Can click "Summary" tab
- [ ] Active tab has blue underline
- [ ] Tab content fades in smoothly
- [ ] Only one tab content visible at a time

### Products Tab

- [ ] Search bar filters products
- [ ] Product cards display in grid
- [ ] +/− buttons work
- [ ] Quantity input accepts numbers
- [ ] Cards change color when packed
- [ ] Stats update when packing

### Freight Tab

- [ ] Freight tab shows "loading" before packing
- [ ] After packing, weight summary displays
- [ ] Carrier comparison cards appear
- [ ] Logos display correctly
- [ ] AI recommendation badge shows
- [ ] Can select carrier
- [ ] "Book Freight" button visible

### AI Insights Tab

- [ ] Shows loading state before freight calc
- [ ] After freight calc, AI recommendation displays
- [ ] Insight cards show reasoning
- [ ] Content is readable and makes sense

### Summary Tab

- [ ] Shows loading state before completion
- [ ] After packing + freight selection, summary displays
- [ ] Packing summary card shows totals
- [ ] Freight summary card shows selected carrier
- [ ] Transfer details card shows from/to
- [ ] "Complete Transfer" button visible

---

## 📲 PART 5: Layout C Testing (5 minutes)

### Open Layout C on Mobile Device

URL: `https://staff.vapeshed.co.nz/?endpoint=consignments/stock-transfers/pack-layout-c-v2-PRODUCTION&transfer_id=XXX`

**Test on actual mobile device (iPhone or Android) or use Chrome DevTools mobile emulation (F12 → Toggle device toolbar)**

### Visual Rendering

- [ ] Page loads without errors
- [ ] Header card displays (from → to)
- [ ] Stats grid displays (2×2 or 4×1)
- [ ] Accordion sections visible
- [ ] "Pack Products" section open by default
- [ ] "Freight Options" section closed
- [ ] "Complete Transfer" section closed
- [ ] No horizontal scrolling

### Accordion Interaction

- [ ] Tapping section header toggles open/close
- [ ] Chevron icon rotates when opened
- [ ] Only one section can be open (or multiple allowed)
- [ ] Smooth expand/collapse animation
- [ ] Touch targets are large enough (44px buttons)

### Products Section

- [ ] Search bar is touch-friendly
- [ ] Product cards stack vertically
- [ ] Product images load
- [ ] Weight badges display
- [ ] +/− buttons are large and tappable
- [ ] Quantity input is large enough to tap
- [ ] Cards change color when packed
- [ ] Stats update when packing

### Freight Section

- [ ] Can expand freight section
- [ ] Weight summary displays
- [ ] Carrier cards stack vertically
- [ ] Logos display correctly
- [ ] AI badge shows on recommended carrier
- [ ] Can tap carrier card to select
- [ ] Selected state shows clearly

### Complete Section

- [ ] Can expand complete section
- [ ] "Print Packing Slip" button is large
- [ ] "Complete & Book Freight" button is large
- [ ] Buttons are tappable with thumb
- [ ] Button press gives visual feedback

### Mobile-Specific

- [ ] One-handed use is comfortable
- [ ] Portrait orientation works perfectly
- [ ] Landscape orientation works acceptably
- [ ] Scrolling is smooth
- [ ] No pinch-to-zoom needed (unless wanted)
- [ ] Toast notification appears at bottom-center
- [ ] Toast disappears after 2-3 seconds

---

## 🚚 PART 6: Freight Integration Testing (10 minutes)

### Weight Resolution (P→C→D)

Test with 3 different products:
1. Product with weight in `vend_products.avg_weight_grams`
2. Product without weight but has category default
3. Product with no weight data (should default to 100g)

- [ ] Product 1 shows weight badge with **(P)** source
- [ ] Product 2 shows weight badge with **(C)** source
- [ ] Product 3 shows weight badge with **(D)** source
- [ ] All three weights are reasonable (not 0, not 999999)

### Freight Calculation

Pack 5-10 items with varying weights:

- [ ] Freight console updates after packing
- [ ] Total weight displayed correctly
- [ ] Weight equals sum of (qty × unit_weight) for all packed items
- [ ] Parcel count shown (1, 2, or more)
- [ ] Volumetric weight considered if applicable

### Carrier Rate Fetching

- [ ] NZ Courier rate displays
- [ ] NZ Post rate displays
- [ ] Both show price in dollars
- [ ] Both show ETA in days
- [ ] Prices are realistic ($10-$100 range typical)
- [ ] Logos display next to carrier names

### AI Recommendation

- [ ] One carrier has "🤖 AI Recommended" badge
- [ ] Recommended carrier is usually the cheapest (verify)
- [ ] Confidence score shown (if applicable)
- [ ] Recommendation reason displayed
- [ ] Reason makes sense ("Lowest cost", "Fastest delivery", etc.)

### Carrier Selection

- [ ] Can click/tap to select carrier
- [ ] Selected carrier shows visual indication (border, background)
- [ ] Can switch selection between carriers
- [ ] "Book Freight" button enables after selection

---

## 📋 PART 7: End-to-End Workflow Test (15 minutes)

### Complete Transfer Workflow

Perform a full pack-and-ship workflow on each layout:

#### Layout A Workflow

- [ ] 1. Open transfer from list
- [ ] 2. Verify transfer details (from/to)
- [ ] 3. Search for 3 products
- [ ] 4. Pack all 3 products with full quantities
- [ ] 5. Verify stats bar updates
- [ ] 6. Verify freight sidebar calculates
- [ ] 7. Review carrier options
- [ ] 8. Select recommended carrier (or other)
- [ ] 9. Click "Book Freight & Generate Labels"
- [ ] 10. Modal appears with tracking numbers *(if freight API enabled)*
- [ ] 11. Click "Print Labels" *(opens PDF/new tab)*
- [ ] 12. Click "Mark as Sent" *(or "Complete Transfer")*
- [ ] 13. Redirects to transfer list
- [ ] 14. Transfer status updated to "SENT"

#### Layout B Workflow

Same steps as Layout A, but using tab navigation:

- [ ] Products tab: Pack items
- [ ] Freight tab: Select carrier
- [ ] Summary tab: Review totals
- [ ] Complete transfer

#### Layout C Workflow

Same steps as Layout A, but using accordion navigation:

- [ ] Pack Products section: Pack items
- [ ] Freight Options section: Select carrier
- [ ] Complete Transfer section: Finish

### Verify Data Persistence

After completing transfer:

```sql
SELECT * FROM transfers WHERE id = XXX;
SELECT * FROM transfer_items WHERE transfer_id = XXX;
```

- [ ] Transfer status = "SENT" (or appropriate status)
- [ ] Freight cost saved to transfer record
- [ ] Tracking numbers saved (if applicable)
- [ ] Carrier name saved
- [ ] Timestamp updated
- [ ] Packed quantities match database

---

## 🔒 PART 8: Security Testing (5 minutes)

### Authentication

- [ ] Logged-out user redirected to login page
- [ ] Logged-in user can access packing page
- [ ] Only authorized roles can pack transfers

### CSRF Protection

- [ ] CSRF token present in meta tag
- [ ] CSRF token sent with all POST requests
- [ ] Invalid CSRF token rejects request

### SQL Injection Prevention

Attempt SQL injection in search field:

```
' OR 1=1 --
'; DROP TABLE transfers; --
```

- [ ] Search filters safely (no SQL error)
- [ ] No database tables dropped
- [ ] No unauthorized data access

### XSS Prevention

Attempt XSS in search field:

```
<script>alert('XSS')</script>
<img src=x onerror="alert('XSS')">
```

- [ ] Script does not execute
- [ ] Alert does not appear
- [ ] Input is safely escaped

### Error Handling

- [ ] PHP errors don't expose file paths
- [ ] SQL errors don't expose query structure
- [ ] API errors return user-friendly messages
- [ ] No sensitive data in browser console

---

## 🎨 PART 9: Browser Compatibility (10 minutes)

### Desktop Browsers

Test on each:

- [ ] Chrome (latest): All layouts work
- [ ] Firefox (latest): All layouts work
- [ ] Safari (latest): All layouts work
- [ ] Edge (latest): All layouts work

### Mobile Browsers

Test Layout C on:

- [ ] iOS Safari (iPhone): Works perfectly
- [ ] Chrome Mobile (Android): Works perfectly
- [ ] Samsung Internet: Works acceptably

### Tablet Browsers

Test Layout B on:

- [ ] iPad Safari: Works well
- [ ] Android tablet Chrome: Works well

---

## 📊 PART 10: Performance Testing (5 minutes)

### Initial Page Load

Use browser DevTools (F12 → Network tab):

- [ ] Layout A loads in < 2 seconds
- [ ] Layout B loads in < 2 seconds
- [ ] Layout C loads in < 2 seconds
- [ ] Total page size < 1MB
- [ ] No 404 errors for assets

### API Response Times

Use browser DevTools (F12 → Network tab → XHR filter):

- [ ] `resolve_weights` responds in < 200ms
- [ ] `optimize_parcels` responds in < 500ms
- [ ] `get_carrier_rates` responds in < 1s
- [ ] `ai_recommend_carrier` responds in < 300ms
- [ ] `save_packing_progress` responds in < 500ms

### Lighthouse Scores

Run Lighthouse audit (F12 → Lighthouse tab):

- [ ] Performance score > 80
- [ ] Accessibility score > 90
- [ ] Best Practices score > 90
- [ ] SEO score > 80

### Core Web Vitals

- [ ] LCP (Largest Contentful Paint) < 2.5s
- [ ] CLS (Cumulative Layout Shift) < 0.1
- [ ] INP (Interaction to Next Paint) < 200ms

---

## 📱 PART 11: Mobile-Specific Testing (Layout C) (5 minutes)

### Touch Interactions

- [ ] Tap buttons works immediately (no 300ms delay)
- [ ] Scroll is smooth (60fps)
- [ ] Pinch-to-zoom works (if enabled)
- [ ] Swipe gestures don't interfere
- [ ] No text selection on button tap
- [ ] No hover states stuck after tap

### Keyboard Behavior

- [ ] Quantity input opens numeric keyboard
- [ ] Search input opens text keyboard
- [ ] Keyboard doesn't cover input fields
- [ ] Can dismiss keyboard
- [ ] Tab key works for navigation

### Orientation Changes

- [ ] Portrait → landscape works
- [ ] Landscape → portrait works
- [ ] No layout breaking
- [ ] No content cutoff

---

## 🐛 PART 12: Error Scenario Testing (5 minutes)

### No Items Packed

- [ ] Freight console shows "Pack items first" message
- [ ] No JavaScript errors
- [ ] Stats show 0 packed items

### Invalid Transfer ID

URL: `...&transfer_id=99999999`

- [ ] Shows "Transfer not found" error
- [ ] Doesn't crash with PHP error
- [ ] User redirected or shown error message

### Network Failure

Disconnect internet, then try freight calculation:

- [ ] Shows error message in freight console
- [ ] "Retry" button appears
- [ ] User can continue working after reconnection

### API Timeout

Slow API response simulation:

- [ ] Loading spinner shows
- [ ] Page remains responsive
- [ ] Timeout after 30 seconds
- [ ] Error message displayed

### No Weight Data

Product with no weight in database:

- [ ] Falls back to 100g default **(D)**
- [ ] Warning shown in freight console
- [ ] User can still complete transfer

---

## ✅ PART 13: Final Verification (2 minutes)

### Documentation

- [ ] README_PRODUCTION_LAYOUTS.md accessible
- [ ] DEPLOYMENT_GUIDE.md accessible
- [ ] DELIVERY_SUMMARY.md accessible
- [ ] VISUAL_COMPARISON.md accessible
- [ ] All docs are readable and helpful

### Backup & Rollback

- [ ] Current production pack page backed up
- [ ] Rollback procedure documented
- [ ] Can execute rollback in < 2 minutes

### Monitoring

- [ ] Error logging configured
- [ ] API call logging enabled
- [ ] User action tracking setup (optional)
- [ ] Alert thresholds configured

### Training Materials

- [ ] Quick start guide printed/available
- [ ] Staff training scheduled
- [ ] Support contact documented
- [ ] FAQ prepared for common questions

---

## 🎯 FINAL CHECKLIST SUMMARY

Count your checkmarks:

- **Total Items:** ~200 checklist items
- **Passing Grade:** 180+ items checked (90%)
- **Production Ready:** All critical sections 100% complete

### Critical Sections (Must Be 100%)

- [ ] Part 1: File Verification (100%)
- [ ] Part 2: Configuration Verification (100%)
- [ ] Part 8: Security Testing (100%)

### Important Sections (Should Be >90%)

- [ ] Part 3: Layout A Testing
- [ ] Part 4: Layout B Testing
- [ ] Part 5: Layout C Testing
- [ ] Part 6: Freight Integration Testing
- [ ] Part 7: End-to-End Workflow Test

### Nice-to-Have Sections (Target >80%)

- [ ] Part 9: Browser Compatibility
- [ ] Part 10: Performance Testing
- [ ] Part 11: Mobile-Specific Testing
- [ ] Part 12: Error Scenario Testing

---

## 📝 QA Sign-Off

**Tested By:** _______________________
**Date:** _______________________
**Environment:** [ ] Staging [ ] Production

**Total Items Checked:** _____ / 200
**Pass Rate:** _____%

**Critical Issues Found:** _______________________
**Status:** [ ] PASS - Ready for Production [ ] FAIL - Needs Fixes

**Approver:** _______________________
**Date:** _______________________
**Signature:** _______________________

---

## 🚀 Next Steps After QA Pass

1. [ ] Schedule deployment window
2. [ ] Notify users of new features
3. [ ] Deploy to production
4. [ ] Monitor for 24 hours
5. [ ] Collect user feedback
6. [ ] Document any issues
7. [ ] Plan Phase 2 enhancements

---

**QA Checklist Version:** 1.0
**Last Updated:** 2025-11-09
**Status:** Ready for Use ✅
