# 🚀 Flagged Products v2.0 - Quick Deployment Guide

## ✅ Pre-Deployment Checklist

### 1. Database Tables
Run these SQL commands in your database:

```sql
-- Achievements
CREATE TABLE IF NOT EXISTS flagged_products_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    achievement_key VARCHAR(50) NOT NULL,
    achievement_name VARCHAR(100) NOT NULL,
    achievement_description TEXT,
    achievement_icon VARCHAR(10),
    awarded_at DATETIME NOT NULL,
    UNIQUE KEY unique_achievement (user_id, achievement_key),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI Insights
CREATE TABLE IF NOT EXISTS flagged_products_ai_insights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    outlet_id VARCHAR(50) NOT NULL,
    insight_text TEXT NOT NULL,
    stats_snapshot JSON,
    generated_at DATETIME NOT NULL,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Violations (if not exists)
CREATE TABLE IF NOT EXISTS flagged_products_violations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    outlet_id VARCHAR(50),
    violation_type VARCHAR(50) NOT NULL,
    violation_data JSON,
    severity ENUM('low', 'medium', 'high') DEFAULT 'medium',
    created_at DATETIME NOT NULL,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Smart-Cron Cache (if not exists)
CREATE TABLE IF NOT EXISTS smart_cron_cache (
    cache_key VARCHAR(100) PRIMARY KEY,
    cache_data LONGTEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Smart-Cron Tasks Config (if not exists)
CREATE TABLE IF NOT EXISTS smart_cron_tasks_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_name VARCHAR(100) UNIQUE NOT NULL,
    task_description TEXT,
    task_script VARCHAR(255) NOT NULL,
    schedule_pattern VARCHAR(50) NOT NULL,
    priority INT DEFAULT 5,
    timeout_seconds INT DEFAULT 300,
    enabled TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Status Check:**
```sql
-- Verify tables exist
SHOW TABLES LIKE 'flagged_products_%';

-- Expected results:
-- flagged_products
-- flagged_products_completion_attempts
-- flagged_products_user_stats
-- flagged_products_violations
-- flagged_products_achievements
-- flagged_products_ai_insights
```

---

### 2. Register Smart-Cron Tasks

**Run once:**
```bash
cd /home/master/applications/jcepnzzkmj/public_html
php modules/flagged_products/cron/register_tasks.php
```

**Expected Output:**
```json
{
    "success": true,
    "tasks_registered": 4,
    "tasks_updated": 0,
    "total_tasks": 4
}
```

**Verify Registration:**
```sql
SELECT task_name, schedule_pattern, enabled 
FROM smart_cron_tasks_config 
WHERE task_name LIKE 'flagged_products%';
```

---

### 3. Configure Environment (Optional)

For ChatGPT AI insights:
```bash
# Add to .env or export
export OPENAI_API_KEY="sk-your-api-key-here"
```

**Without API key:** System uses rule-based fallback insights (still works great!)

---

### 4. File Permissions

```bash
# Ensure cron scripts executable
chmod +x modules/flagged_products/cron/*.php

# Verify web server can read assets
chown -R www-data:www-data modules/flagged_products/assets/
chmod -R 755 modules/flagged_products/assets/
```

---

## 🧪 Testing Guide

### Test 1: Basic Load
```
URL: https://staff.vapeshed.co.nz/flagged-products-v2.php?outlet_id=YOUR_OUTLET_ID&bypass_security=1&bot=1

✅ Page loads without errors
✅ Header shows outlet name
✅ Product list displays
✅ Stock colors show (red/orange/blue/green)
✅ No console errors
```

### Test 2: Product Completion
```
1. Enter quantity in input field
2. Click "Complete" button
3. Watch for success message
4. Check product removed from list

✅ API responds 200 OK
✅ Points awarded
✅ Product removed
✅ Lightspeed queue entry created
```

### Test 3: Anti-Cheat Countdown
```
1. Switch to another tab
2. Switch back
3. Blur modal should appear
4. Countdown starts at 15
5. Button disabled (gray)
6. After 15 seconds, button enables (green)
7. Click button to close

✅ Countdown works
✅ Button stays disabled
✅ No premature closing
✅ Violation logged silently
```

### Test 4: Summary Page
```
URL: https://staff.vapeshed.co.nz/modules/flagged_products/views/summary.php?outlet_id=YOUR_OUTLET_ID

✅ Stats display correctly
✅ AI insight shows
✅ Leaderboard position shown
✅ Achievement badges display
```

### Test 5: Manager Dashboard
```
URL: https://staff.vapeshed.co.nz/modules/flagged_products/views/dashboard.php

✅ Multi-store comparison loads
✅ Chart.js trends display
✅ Date filters work
✅ Store filters work
✅ Violation stats show
```

### Test 6: Leaderboard
```
URL: https://staff.vapeshed.co.nz/modules/flagged_products/views/leaderboard.php

✅ Rankings display
✅ Period filters work (daily/weekly/monthly/all-time)
✅ Store filters work
✅ Your position highlighted
✅ Badges show correctly
```

### Test 7: Smart-Cron Tasks
```bash
# Manually trigger each task
php modules/flagged_products/cron/refresh_leaderboard.php
php modules/flagged_products/cron/generate_ai_insights.php
php modules/flagged_products/cron/check_achievements.php
php modules/flagged_products/cron/refresh_store_stats.php

# Check for success JSON response
```

---

## 📊 Monitoring

### Log Files to Watch
```bash
# CIS module log
tail -f /home/master/applications/jcepnzzkmj/public_html/logs/cis.log | grep flagged_products

# Apache error log
tail -f /home/master/applications/jcepnzzkmj/logs/apache_*.error.log

# PHP error log
tail -f /var/log/php_errors.log
```

### Database Queries to Monitor

**Check completions:**
```sql
SELECT COUNT(*) as completed_today
FROM flagged_products
WHERE date_completed_stocktake >= CURDATE();
```

**Check violations:**
```sql
SELECT violation_type, COUNT(*) as count
FROM flagged_products_violations
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY violation_type;
```

**Check achievements:**
```sql
SELECT achievement_name, COUNT(*) as awarded
FROM flagged_products_achievements
WHERE awarded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY achievement_name;
```

**Check cron tasks:**
```sql
SELECT task_name, status, completed_at
FROM smart_cron_tasks_config
WHERE task_name LIKE 'flagged_products%';
```

---

## 🐛 Troubleshooting

### Issue: "Missing Outlet ID"
**Fix:** Always include `?outlet_id=YOUR_OUTLET_ID` parameter

### Issue: Countdown doesn't work
**Check:**
```javascript
// Browser console
console.log(window.antiCheatDetector);
// Should show object with methods
```

**Fix:** Clear browser cache, reload page

### Issue: No leaderboard data
**Check:**
```sql
SELECT * FROM flagged_products_user_stats LIMIT 5;
```

**Fix:** Complete some products first, then run:
```bash
php modules/flagged_products/cron/refresh_leaderboard.php
```

### Issue: Achievements not awarded
**Check:**
```bash
php modules/flagged_products/cron/check_achievements.php
```

**Look for:** JSON response with `achievements_awarded > 0`

### Issue: Chart.js not loading
**Check browser console for:**
```
Failed to load Chart.js from CDN
```

**Fix:** Check CDN availability or switch to local Chart.js

---

## 📈 Performance Optimization

### Database Indexes
```sql
-- Add these for faster queries
CREATE INDEX idx_outlet_completed ON flagged_products(outlet, date_completed_stocktake);
CREATE INDEX idx_user_points ON flagged_products_user_stats(user_id, points_earned);
CREATE INDEX idx_violations_user_date ON flagged_products_violations(user_id, created_at);
```

### Caching Strategy
```sql
-- Check cache hit rate
SELECT 
    COUNT(*) as total_cache_entries,
    SUM(CASE WHEN expires_at > NOW() THEN 1 ELSE 0 END) as valid_entries,
    SUM(CASE WHEN expires_at <= NOW() THEN 1 ELSE 0 END) as expired_entries
FROM smart_cron_cache
WHERE cache_key LIKE 'flagged_products%';
```

### Clean Up Old Data
```sql
-- Remove old violations (keep 90 days)
DELETE FROM flagged_products_violations
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Remove expired cache
DELETE FROM smart_cron_cache
WHERE expires_at < NOW();
```

---

## ✅ Go-Live Checklist

Before enabling for all users:

- [ ] All database tables created
- [ ] Smart-Cron tasks registered and enabled
- [ ] Test user completed full workflow successfully
- [ ] Anti-cheat countdown tested and working
- [ ] Manager dashboard loads without errors
- [ ] Leaderboard displays correctly
- [ ] AI insights generating (or fallback working)
- [ ] Achievements being awarded
- [ ] Lightspeed queue integration verified
- [ ] CIS inventory updates confirmed
- [ ] Logs show no errors
- [ ] Performance acceptable (< 2s page load)
- [ ] Mobile responsive tested
- [ ] Cross-browser tested (Chrome, Firefox, Safari)
- [ ] Documentation reviewed by team

---

## 🚀 Deployment Commands

**One-line deployment:**
```bash
cd /home/master/applications/jcepnzzkmj/public_html && \
php modules/flagged_products/cron/register_tasks.php && \
echo "✅ Deployment complete - Ready for testing!"
```

---

## 📞 Post-Deployment Support

### Week 1: Monitor Daily
- Check logs for errors
- Verify cron jobs running
- Watch completion rates
- Monitor violation patterns

### Week 2: Analyze Performance
- Review leaderboard engagement
- Check achievement unlock rates
- Analyze accuracy improvements
- Survey staff feedback

### Week 3: Optimize
- Adjust point values if needed
- Fine-tune anti-cheat sensitivity
- Update AI insight prompts
- Add new achievements based on data

---

## 🎯 Success Metrics

**Target Goals:**
- 80%+ staff adoption in first month
- 95%+ accuracy average across stores
- <5% violation rate
- 90%+ user satisfaction

**Tracking:**
```sql
-- Daily metrics query
SELECT 
    DATE(date_completed_stocktake) as date,
    COUNT(*) as products_completed,
    AVG(CASE WHEN qty_before = qty_after THEN 100 ELSE 0 END) as accuracy,
    COUNT(DISTINCT completed_by_staff) as active_users
FROM flagged_products
WHERE date_completed_stocktake >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(date_completed_stocktake)
ORDER BY date DESC;
```

---

**System Ready! 🎉**

For questions or issues:
- Check README.md for full documentation
- Review logs for error details
- Contact development team

**Good luck with your deployment!** 🚀
