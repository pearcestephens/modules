# 🚀 RAPID DEPLOYMENT GUIDE - Stock Transfer Packing Interfaces

## ⚡ Quick Start (5 Minutes)

### Step 1: Verify Files Exist (30 seconds)

```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/consignments/stock-transfers/

# Check PHP layouts
ls -la pack-layout-a-v2-PRODUCTION.php  # Desktop layout
ls -la pack-layout-b-v2-PRODUCTION.php  # Tablet layout
ls -la pack-layout-c-v2-PRODUCTION.php  # Mobile layout

# Check JavaScript
ls -la js/freight-engine.js      # Shared freight logic
ls -la js/pack-layout-b.js       # Tabs functionality
ls -la js/pack-layout-c.js       # Accordion functionality

# Check documentation
ls -la README_PRODUCTION_LAYOUTS.md
```

**Expected:** All 6 files present ✅

---

### Step 2: Add Carrier Logos (2 minutes)

```bash
# Create carrier images directory
mkdir -p /home/master/applications/jcepnzzkmj/public_html/assets/images/carriers/

# Option A: Download logos
cd /home/master/applications/jcepnzzkmj/public_html/assets/images/carriers/
wget https://example.com/nz-courier-logo.png
wget https://example.com/nz-post-logo.png

# Option B: Create placeholder logos (temporary)
# Use existing company logos or create simple text-based placeholders
```

**Minimum Size:** 200px × 60px (transparent PNG)

---

### Step 3: Test Each Layout (2 minutes)

**Replace `123` with an actual transfer ID from your system:**

```bash
# Open in browser:

# Layout A (Desktop)
https://staff.vapeshed.co.nz/?endpoint=consignments/stock-transfers/pack-layout-a-v2-PRODUCTION&transfer_id=123

# Layout B (Tablet)
https://staff.vapeshed.co.nz/?endpoint=consignments/stock-transfers/pack-layout-b-v2-PRODUCTION&transfer_id=123

# Layout C (Mobile)
https://staff.vapeshed.co.nz/?endpoint=consignments/stock-transfers/pack-layout-c-v2-PRODUCTION&transfer_id=123
```

**Expected Results:**
- ✅ Page loads without PHP errors
- ✅ Transfer details display (from/to outlets)
- ✅ Products list shows all items
- ✅ Quantity controls work (+/− buttons)
- ✅ Stats update when quantities change

**If JavaScript errors:** Open DevTools Console (F12) and check for missing files

---

### Step 4: Verify Freight Integration (30 seconds)

1. **Pack some items** (set qty > 0 on at least 2 products)
2. **Wait 2-3 seconds** for freight calculation
3. **Check freight console/section** updates with:
   - ✅ Total weight displayed
   - ✅ Weight sources (P/C/D legend)
   - ✅ Carrier options (NZ Courier / NZ Post)
   - ✅ AI recommendation badge on one carrier

**If freight doesn't calculate:**
- Check browser console for API errors
- Verify FreightEngine.php exists: `/assets/services/core/freight/FreightEngine.php`
- Check `.env` has `FREIGHT_API_ENABLED=true`

---

### Step 5: Production Rollout (30 seconds)

**Option 1: Replace Existing Pack Page**

```bash
# Backup current pack page
cp pack.php pack.php.backup_$(date +%Y%m%d_%H%M%S)

# Symlink to new layout (choose one)
ln -sf pack-layout-a-v2-PRODUCTION.php pack.php   # Desktop default
# OR
ln -sf pack-layout-b-v2-PRODUCTION.php pack.php   # Tablet default
# OR
ln -sf pack-layout-c-v2-PRODUCTION.php pack.php   # Mobile default
```

**Option 2: Add Layout Selector**

Let users choose their preferred layout:

```php
// In transfer list page (frontend.php)
<div class="layout-selector">
    <a href="?endpoint=consignments/stock-transfers/pack-layout-a-v2-PRODUCTION&transfer_id=<?= $id ?>">
        🖥️ Desktop Layout
    </a>
    <a href="?endpoint=consignments/stock-transfers/pack-layout-b-v2-PRODUCTION&transfer_id=<?= $id ?>">
        📱 Tablet Layout
    </a>
    <a href="?endpoint=consignments/stock-transfers/pack-layout-c-v2-PRODUCTION&transfer_id=<?= $id ?>">
        📲 Mobile Layout
    </a>
</div>
```

---

## 🔧 Configuration Checklist

### Required Environment Variables (.env)

```env
# Database (already configured)
DB_HOST=localhost
DB_NAME=cis_prod
DB_USER=cis_user
DB_PASS=your_password

# Freight API
FREIGHT_API_ENABLED=true

# Carrier API Keys (get from GoSweetSpot dashboard)
NZ_COURIER_API_KEY=your_nz_courier_key
NZ_POST_API_KEY=your_nz_post_key
GOSWEETSPOT_API_KEY=your_gosweetspot_key

# AI Recommendations (optional - uses OpenAI for carrier selection)
OPENAI_API_KEY=sk-proj-your-key-here
```

**If API keys not available yet:**
- System will use fallback: cheapest carrier auto-selected
- AI recommendation badge won't show (but layout still works)

---

## 🐛 Common Issues & Fixes

### Issue 1: "Transfer ID required" error

**Cause:** Missing or invalid `transfer_id` parameter

**Fix:**
```php
// Ensure URL has transfer_id:
?endpoint=consignments/stock-transfers/pack-layout-a-v2-PRODUCTION&transfer_id=123

// Or check database for valid transfer IDs:
SELECT id, status FROM transfers WHERE status = 'OPEN' LIMIT 10;
```

---

### Issue 2: "FreightEngine not found" error

**Cause:** FreightEngine.php missing or path incorrect

**Fix:**
```bash
# Check file exists:
ls -la /home/master/applications/jcepnzzkmj/public_html/assets/services/core/freight/FreightEngine.php

# If missing, check alternative locations:
find /home/master/applications -name "FreightEngine.php"

# Update path in layout files if needed (line ~21):
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/services/core/freight/FreightEngine.php';
```

---

### Issue 3: Carrier logos showing broken image icon

**Cause:** Logo files missing or path incorrect

**Fix:**
```bash
# Check logos exist:
ls -la /assets/images/carriers/nz-courier-logo.png
ls -la /assets/images/carriers/nz-post-logo.png

# Create temporary placeholders if logos not available:
# Use any PNG image or create simple text-based logos
```

---

### Issue 4: "No freight data yet" message persists

**Cause:** FreightEngine API not responding or JavaScript error

**Fix:**
1. Open browser DevTools (F12) → Console tab
2. Look for errors like:
   - `freight-engine.js:123 Failed to fetch`
   - `Uncaught ReferenceError: freightEngine is not defined`

3. Check freight API endpoint works:
```bash
curl -X POST https://staff.vapeshed.co.nz/assets/services/core/freight/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"resolve_weights","product_ids":[1,2,3]}'
```

Expected response: JSON with weight data

---

### Issue 5: Quantities not saving

**Cause:** Auto-save failing or CSRF token invalid

**Fix:**
1. Check browser console for save errors
2. Verify CSRF token exists:
```javascript
// In browser console:
document.querySelector('meta[name="csrf-token"]').content
// Should return: long string like "abc123def456..."
```

3. Test manual save:
```javascript
// In browser console:
saveProgress()
// Should show: "✅ Progress saved!" alert
```

---

## 📊 Health Check Commands

Run these to verify system health:

```bash
# 1. Check PHP syntax
php -l pack-layout-a-v2-PRODUCTION.php
php -l pack-layout-b-v2-PRODUCTION.php
php -l pack-layout-c-v2-PRODUCTION.php

# 2. Check JavaScript syntax
node -c js/freight-engine.js
node -c js/pack-layout-b.js
node -c js/pack-layout-c.js

# 3. Check database connectivity
mysql -u cis_user -p cis_prod -e "SELECT COUNT(*) FROM transfers;"

# 4. Check freight API endpoint
curl -I https://staff.vapeshed.co.nz/assets/services/core/freight/api.php
# Expected: HTTP/1.1 200 OK

# 5. Check file permissions
ls -la pack-layout-*.php
# Expected: -rw-r--r-- (644)
```

All checks should pass ✅

---

## 🎯 Testing Scenarios

### Scenario 1: Desktop User Workflow (Layout A)

1. Navigate to transfer list
2. Click "Pack Transfer" on any OPEN transfer
3. URL: `...pack-layout-a-v2-PRODUCTION.php?transfer_id=123`
4. Search for product: type "JUI" in search box
5. Adjust quantity using +/− buttons
6. Verify freight sidebar updates in real-time
7. Select carrier (or accept AI recommendation)
8. Click "Book Freight & Generate Labels"
9. Verify modal shows tracking numbers
10. Click "Print Labels" → PDF opens
11. Click "Mark as Sent" → redirects to list

**Expected Time:** 2-3 minutes per transfer

---

### Scenario 2: Tablet User Workflow (Layout B)

1. Same steps 1-3 as above (but use Layout B URL)
2. **Products tab:** Pack items using card grid
3. Click **Freight tab** → see carrier options
4. Click **AI Insights tab** → read recommendation reasoning
5. Click **Summary tab** → review all details
6. Click "Complete & Book Freight"
7. Same steps 9-11 as Scenario 1

**Expected Time:** 3-4 minutes per transfer

---

### Scenario 3: Mobile User Workflow (Layout C)

1. Open on mobile device (phone or tablet)
2. Use Layout C URL
3. Expand "Pack Products" accordion
4. Use large +/− buttons to pack items
5. Collapse products, expand "Freight Options"
6. Tap carrier card to select
7. Expand "Complete Transfer"
8. Tap "Complete & Book Freight"
9. View tracking in modal
10. Tap "Print Labels" (opens in new tab)

**Expected Time:** 4-5 minutes per transfer (mobile)

---

## 🎓 User Training (5-Minute Brief)

**For warehouse staff:**

1. **"We have 3 new packing layouts - choose your favorite!"**
   - Desktop → Layout A (side-by-side view)
   - Tablet → Layout B (step-by-step tabs)
   - Mobile → Layout C (touch-friendly accordion)

2. **"The system now calculates freight automatically!"**
   - Pack items → freight updates instantly
   - See NZ Courier vs NZ Post prices
   - AI recommends the best option (usually cheapest)

3. **"Barcode scanners work!"**
   - Scan barcode → product auto-filters
   - Scan multiple to find quickly

4. **"Your progress auto-saves every 30 seconds!"**
   - No need to click "Save" constantly
   - But you can manually save anytime

5. **"Book freight in one click!"**
   - Select carrier → click "Book Freight"
   - Tracking numbers appear instantly
   - Print labels and packing slip

**Demo:** Show each layout for 1 minute each (3 min total)

---

## 📈 Success Metrics (Week 1)

Track these after deployment:

- [ ] **Adoption rate:** % of transfers packed using new layouts
- [ ] **Layout preference:** Which layout gets most use?
- [ ] **Packing speed:** Average time from open to complete
- [ ] **Freight accuracy:** Quoted cost vs actual cost variance
- [ ] **AI acceptance:** % of users accepting AI recommendation
- [ ] **Error rate:** Failed bookings or JS errors
- [ ] **Mobile usage:** % packed on mobile devices
- [ ] **User feedback:** NPS score or satisfaction survey

**Target:** >80% adoption, <5% error rate, >3 min faster packing

---

## 🔒 Security Validation

Before going live, verify:

```bash
# 1. CSRF tokens present in all forms
grep -n 'csrf-token' pack-layout-*.php
# Should return: 3 matches (one per file)

# 2. SQL injection prevention
grep -n 'prepare\|bindParam' /modules/consignments/TransferManager/functions/transfer_functions.php
# Should show: prepared statements used

# 3. XSS protection
grep -n 'htmlspecialchars' pack-layout-*.php
# Should return: multiple matches on user input display

# 4. Session authentication
grep -n 'staff_id' pack-layout-*.php
# Should return: 3 matches checking if user logged in
```

All checks should pass ✅

---

## 🎉 Deployment Complete!

If you've reached this point:

- ✅ All 3 layouts deployed
- ✅ Freight integration tested
- ✅ Users trained
- ✅ Monitoring in place

**You're ready for production! 🚀**

---

## 📞 Emergency Rollback

If something goes wrong:

```bash
# Option 1: Restore backup
cp pack.php.backup_YYYYMMDD_HHMMSS pack.php

# Option 2: Disable new layouts temporarily
mv pack-layout-a-v2-PRODUCTION.php pack-layout-a-v2-PRODUCTION.php.disabled
mv pack-layout-b-v2-PRODUCTION.php pack-layout-b-v2-PRODUCTION.php.disabled
mv pack-layout-c-v2-PRODUCTION.php pack-layout-c-v2-PRODUCTION.php.disabled

# Option 3: Disable freight integration only
# Edit .env:
FREIGHT_API_ENABLED=false
```

**Rollback time:** < 2 minutes

---

## 💡 Pro Tips

1. **Start with Layout C on mobile** - easiest to test on phone
2. **Show users all 3 layouts** - let them pick favorite
3. **Monitor console logs first week** - catch JS errors early
4. **Get feedback from packers** - they know best!
5. **Track packing times** - measure actual speed improvement

---

## 📚 Additional Resources

- **Full Documentation:** `README_PRODUCTION_LAYOUTS.md`
- **Freight Algorithm:** `/_kb/knowledge-base/vend/FREIGHT_WEIGHT_ALGORITHM_COMPLETE.md`
- **API Reference:** `/assets/services/core/freight/README.md`
- **Gap Analysis:** `/modules/consignments/_kb/PACKING_RECEIVING_GAP_ANALYSIS_NOV_9.md`

---

**Deployment Date:** 2025-11-09
**Version:** 2.0.0 PRODUCTION
**Status:** READY TO GO LIVE 🚀

*Any questions? Check the full README or contact IT Manager*
