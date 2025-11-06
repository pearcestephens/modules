# 🔧 Staff Performance Module - Installation Guide

## Quick Start Installation

### 1. Database Setup (5 minutes)

```bash
# Connect to database
mysql -u jcepnzzkmj -p jcepnzzkmj

# Run schema
source /path/to/modules/staff-performance/database/schema.sql;

# Verify tables created
SHOW TABLES LIKE '%performance%';
SHOW TABLES LIKE '%competition%';
SHOW TABLES LIKE '%achievement%';
```

Expected output:
```
staff_performance_stats
competitions
competition_participants
achievements
staff_achievements
leaderboard_history
```

### 2. Test Database Connection

Visit: `https://staff.vapeshed.co.nz/modules/staff-performance/`

If you see the dashboard, installation is successful!

### 3. Initial Data Population

Run these service methods to populate initial data:

```php
// In a PHP script or directly in browser
require_once 'bootstrap.php';

// Process existing reviews
$gamification = new StaffPerformance\Services\GoogleReviewsGamification($db);
$result = $gamification->processReviews();
print_r($result);

// Calculate monthly stats
$tracker = new StaffPerformance\Services\StaffPerformanceTracker($db);
$result = $tracker->updateMonthlyStats();
print_r($result);

// Check achievements
$engine = new StaffPerformance\Services\AchievementEngine($db);
$staffList = [/* array of staff IDs */];
foreach ($staffList as $staffId) {
    $unlocked = $engine->checkAchievements($staffId);
    echo "Staff $staffId unlocked: " . implode(', ', $unlocked) . "\n";
}
```

### 4. Create First Competition

```sql
INSERT INTO competitions (
    name, description, competition_type, metric,
    start_date, end_date, status,
    prize_amount_first, prize_amount_second, prize_amount_third
) VALUES (
    'November Review Challenge',
    'Most Google Reviews this week wins cash prizes!',
    'weekly',
    'google_reviews',
    NOW(),
    DATE_ADD(NOW(), INTERVAL 7 DAY),
    'active',
    100.00,
    50.00,
    25.00
);
```

### 5. Setup Automated Processing (Optional)

Create cron jobs for automatic updates:

```bash
# Edit crontab
crontab -e

# Add these lines:
0 */6 * * * cd /path/to/modules/staff-performance && php cron/process-reviews.php
0 1 * * * cd /path/to/modules/staff-performance && php cron/update-stats.php
0 2 * * * cd /path/to/modules/staff-performance && php cron/check-achievements.php
```

Create the cron scripts:

**cron/process-reviews.php:**
```php
<?php
require_once __DIR__ . '/../bootstrap.php';
$service = new StaffPerformance\Services\GoogleReviewsGamification($db);
$result = $service->processReviews();
echo json_encode($result);
```

**cron/update-stats.php:**
```php
<?php
require_once __DIR__ . '/../bootstrap.php';
$service = new StaffPerformance\Services\StaffPerformanceTracker($db);
$result = $service->updateMonthlyStats();
echo json_encode($result);
```

**cron/check-achievements.php:**
```php
<?php
require_once __DIR__ . '/../bootstrap.php';
$engine = new StaffPerformance\Services\AchievementEngine($db);
$stmt = $db->query("SELECT staff_id FROM staff_accounts WHERE is_active = 1");
$staffList = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($staffList as $staffId) {
    $engine->checkAchievements($staffId);
}
echo "Checked " . count($staffList) . " staff members\n";
```

## Verification Checklist

- [ ] All database tables created
- [ ] Dashboard loads without errors
- [ ] Personal stats show correct numbers
- [ ] Leaderboard displays rankings
- [ ] Achievements page shows 9 seed achievements
- [ ] API endpoints return valid JSON
- [ ] JavaScript auto-refresh works
- [ ] CSS styles load correctly

## Troubleshooting

**Problem:** Dashboard shows "Database connection failed"
**Solution:** Check database credentials in bootstrap.php

**Problem:** Stats show 0 for everything
**Solution:** Run processReviews() and updateMonthlyStats()

**Problem:** 404 on assets (CSS/JS)
**Solution:** Verify path constants in bootstrap.php

**Problem:** Permission denied errors
**Solution:** Run `chmod 755` on directories, `chmod 644` on PHP files

## Next Steps

1. **Customize achievements** - Add your own badges
2. **Create competitions** - Weekly/monthly challenges
3. **Setup notifications** - Email/SMS for achievements
4. **Add custom metrics** - Track additional KPIs
5. **Build admin panel** - Manage competitions/achievements

## Support

For help: helpdesk@vapeshed.co.nz
Documentation: `/modules/staff-performance/README.md`
