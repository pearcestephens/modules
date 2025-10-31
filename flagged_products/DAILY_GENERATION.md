# 🎯 Daily Product Generation - Smart Selection Algorithm

**Critical Missing Task - NOW ADDED!**

This is the **MOST IMPORTANT** cron task - it populates the `flagged_products` table with 20 intelligently selected products per outlet every day.

---

## 📋 Task Details

**File:** `/modules/flagged_products/cron/generate_daily_products.php`  
**Schedule:** Daily at 1:00 AM (runs BEFORE leaderboard refresh)  
**Duration:** ~10 minutes (depending on outlets/inventory)  
**Priority:** 1 (HIGHEST - this must run first)

---

## 🧠 Smart Selection Algorithm

The task selects **exactly 20 products per outlet** using this intelligent priority system:

### 1. 🔴 **Critical Stock (0-4 units)** - Priority 1
- **Quantity:** Up to 5 products
- **Logic:** Items that are nearly out of stock
- **Order:** Lowest stock first, then highest price
- **Why:** Prevent stockouts on valuable items

### 2. 🟠 **Low Stock (5-9 units)** - Priority 2
- **Quantity:** Up to 3 products
- **Logic:** Items approaching low stock
- **Order:** Highest price first
- **Why:** Catch issues before they become critical

### 3. 💰 **High-Value Products (>$100)** - Priority 3
- **Quantity:** Up to 4 products
- **Logic:** Expensive items need accurate counts
- **Order:** Highest price first, then lowest stock
- **Why:** High dollar value = high impact if wrong

### 4. 🔥 **Fast-Moving Products** - Priority 4
- **Quantity:** Up to 3 products
- **Logic:** Items with 5+ sales in last 7 days
- **Order:** Most sales first, then lowest stock
- **Why:** High turnover items need frequent checks

### 5. 💵 **Recently Price Changed** - Priority 5
- **Quantity:** Up to 2 products
- **Logic:** Products updated in last 7 days
- **Order:** Most recent changes first
- **Why:** Verify price changes are correct

### 6. ⭐ **Manually Flagged** - Priority 0 (HIGHEST!)
- **Quantity:** Up to 3 products
- **Logic:** Manager-flagged from dashboard
- **Order:** Oldest flag first (FIFO)
- **Why:** Manager knows best - their requests come first

### 7. 🎲 **Random Selection** - Priority 6
- **Quantity:** Remaining slots (to reach 20 total)
- **Logic:** Random products with stock > 0
- **Order:** Random
- **Why:** Catch unexpected issues, keep staff alert

---

## 🔄 Daily Process Flow

```
1:00 AM - Cron triggers
    ↓
Get all active outlets (Hamilton East, Hamilton Central, etc.)
    ↓
For each outlet:
    ↓
    1. Find 5 critical stock items (0-4 units)
    2. Find 3 low stock items (5-9 units)
    3. Find 4 high-value items (>$100)
    4. Find 3 fast-moving items (5+ sales in 7 days)
    5. Find 2 recently price-changed items
    6. Find up to 3 manually flagged items
    7. Fill remaining slots with random items
    ↓
    Clear yesterday's auto-flagged products
    ↓
    Insert 20 new flagged products with 24-hour expiry
    ↓
Log results to CISLogger
    ↓
Move to next outlet
    ↓
Complete - Staff see new products when they login
```

---

## 📊 Example Output

For **Hamilton East** outlet:
```
✅ 5 critical stock products (e.g., Vuse Alto Green 1.8% - 2 units left)
✅ 3 low stock products (e.g., IGET Legend Grape - 7 units)
✅ 4 high-value products (e.g., Vaporesso GEN 200 Kit - $145.00)
✅ 3 fast-moving products (e.g., Lost Mary BM600 Blue Razz - 18 sales)
✅ 2 price-changed products (e.g., Vuse ePod Crisp Mint - price updated yesterday)
✅ 1 manually flagged (e.g., Manager flagged Aspire Nautilus Coils)
✅ 2 random products (variety)
─────────────────
Total: 20 products ready for verification
```

---

## 🛡️ Safety Features

### Deduplication
- Each product can only appear once per outlet
- Uses `NOT IN` clauses to exclude already-selected products
- Ensures no duplicate work for staff

### Expiry System
- Products expire after 24 hours
- Expired products automatically hidden from UI
- Fresh set generated daily

### Auto-Flag Tracking
- `auto_flagged = 1` for system-generated
- `auto_flagged = 0` for manager-flagged
- Allows different handling/priority

### Cleanup
- Old auto-flagged products deleted before insert
- Keeps table clean
- Manually flagged products preserved until completed

---

## 🎯 Why This Matters

**Without this task:**
- ❌ No products appear in the flagged products page
- ❌ Staff have nothing to verify
- ❌ System is useless

**With this task:**
- ✅ 20 smart products per outlet daily
- ✅ Critical items prioritized
- ✅ High-value items protected
- ✅ Fast-movers monitored
- ✅ Manager requests honored
- ✅ Random variety for thorough checking

---

## 🚀 Registration

This task is now included in `register_tasks.php`:

```php
[
    'task_name' => 'flagged_products_generate_daily_products',
    'task_description' => 'Generate 20 smart-selected products per outlet per day',
    'task_script' => '/modules/flagged_products/cron/generate_daily_products.php',
    'schedule_pattern' => '0 1 * * *', // Daily at 1 AM
    'priority' => 1,
    'timeout_seconds' => 600,
    'enabled' => 1
]
```

Run registration:
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/flagged_products/cron
php register_tasks.php
```

---

## 📈 Expected Results

### For 1 Outlet:
- 20 products generated
- ~1 minute execution time
- ~1KB database insert

### For 17 Outlets (all stores):
- 340 products generated (20 × 17)
- ~10 minutes execution time
- ~17KB database insert

### Daily:
- Fresh products every morning
- Yesterday's completed products cleared
- Staff see new list when they login

---

## 🔍 Monitoring

### Check if it ran:
```bash
tail -100 /home/master/applications/jcepnzzkmj/public_html/logs/cis.log | grep "flagged_products_cron"
```

### Verify products generated:
```sql
SELECT outlet_id, COUNT(*) as product_count, 
       GROUP_CONCAT(DISTINCT reason) as reasons
FROM flagged_products
WHERE completed = 0
GROUP BY outlet_id;
```

Should show 20 products per outlet with mixed reasons.

---

## ✅ Updated Cron Schedule

| Task | Time | Purpose | Priority |
|------|------|---------|----------|
| **Generate Products** | 1:00 AM | Create 20 smart products/outlet | **1 (CRITICAL)** |
| Leaderboard | 2:00 AM | Cache rankings | 3 |
| AI Insights | Hourly | ChatGPT coaching | 4 |
| Achievements | Every 6h | Award badges | 3 |
| Store Stats | Every 30m | Dashboard cache | 2 |

---

## 🎉 Status: COMPLETE!

The critical daily product generation task is now:
- ✅ Created (`generate_daily_products.php`)
- ✅ Registered in Smart-Cron system
- ✅ Syntax validated (no errors)
- ✅ Documented
- ✅ Ready to run

**This was the missing piece!** Without it, staff would have nothing to verify. Now the system is truly complete.
