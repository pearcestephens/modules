# Flagged Products Module v2.0

**Complete Anti-Cheat Stock Verification System with Gamification**

---

## 📋 Overview

The Flagged Products module is a secure, gamified stock verification system for retail stores. It helps staff quickly verify and correct inventory discrepancies while preventing cheating through advanced anti-cheat measures.

### Key Features

- ✅ **Anti-Cheat Security** - 15-second countdown penalty for tab switching, silent violation logging
- ✅ **Lightspeed Integration** - Automatic queue entries for inventory updates
- ✅ **Gamification** - Points, streaks, achievements, leaderboards
- ✅ **AI Insights** - ChatGPT-powered performance analysis
- ✅ **Manager Dashboard** - Multi-store comparison, trend analysis
- ✅ **Beautiful UI** - Compact, professional, mobile-responsive

---

## 📁 File Structure

```
modules/flagged_products/
├── models/
│   └── FlaggedProductsRepository.php    # Database access layer
├── lib/
│   └── AntiCheat.php                    # Anti-cheat detection system
├── api/
│   ├── complete-product.php             # Complete product endpoint
│   └── get-summary-stats.php            # Summary stats endpoint
├── views/
│   ├── summary.php                      # Completion summary page
│   ├── dashboard.php                    # Manager dashboard
│   └── leaderboard.php                  # Standalone leaderboard
├── assets/
│   ├── css/
│   │   └── flagged-products.css         # Module styles
│   └── js/
│       └── anti-cheat.js                # Anti-cheat client-side
├── cron/
│   ├── refresh_leaderboard.php          # Daily leaderboard refresh
│   ├── generate_ai_insights.php         # AI insight generation
│   ├── check_achievements.php           # Achievement awarding
│   ├── refresh_store_stats.php          # Stats caching
│   └── register_tasks.php               # Smart-Cron registration
└── bootstrap.php                        # Module initialization
```

**Main Entry Point:**
- `/flagged-products-v2.php` - Primary stock verification interface

---

## 🚀 Installation

### 1. Database Setup

Run these SQL commands to create required tables:

```sql
-- Achievements table
CREATE TABLE IF NOT EXISTS flagged_products_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    achievement_key VARCHAR(50) NOT NULL,
    achievement_name VARCHAR(100) NOT NULL,
    achievement_description TEXT,
    achievement_icon VARCHAR(10),
    awarded_at DATETIME NOT NULL,
    UNIQUE KEY unique_achievement (user_id, achievement_key),
    INDEX idx_user (user_id),
    INDEX idx_awarded (awarded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI Insights table
CREATE TABLE IF NOT EXISTS flagged_products_ai_insights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    outlet_id VARCHAR(50) NOT NULL,
    insight_text TEXT NOT NULL,
    stats_snapshot JSON,
    generated_at DATETIME NOT NULL,
    INDEX idx_user (user_id),
    INDEX idx_outlet (outlet_id),
    INDEX idx_generated (generated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Violations tracking (already exists, verify)
CREATE TABLE IF NOT EXISTS flagged_products_violations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    outlet_id VARCHAR(50),
    violation_type VARCHAR(50) NOT NULL,
    violation_data JSON,
    severity ENUM('low', 'medium', 'high') DEFAULT 'medium',
    created_at DATETIME NOT NULL,
    INDEX idx_user (user_id),
    INDEX idx_type (violation_type),
    INDEX idx_severity (severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2. Register Smart-Cron Tasks

Run the registration script once:

```bash
php /home/master/applications/jcepnzzkmj/public_html/modules/flagged_products/cron/register_tasks.php
```

This registers:
- **Daily Leaderboard Refresh** (2:00 AM)
- **Hourly AI Insight Generation** (Every hour)
- **Achievement Checks** (Every 6 hours)
- **Store Stats Caching** (Every 30 minutes)

### 3. Configure Environment Variables (Optional)

For ChatGPT integration, set:
```bash
export OPENAI_API_KEY="sk-your-api-key-here"
```

Or add to `.env` file if using dotenv.

---

## 📖 Usage Guide

### For Staff Members

#### Accessing the System
```
https://staff.vapeshed.co.nz/flagged-products-v2.php?outlet_id=YOUR_OUTLET_ID
```

#### Workflow
1. **Load Products** - Page shows flagged products for your store
2. **Verify Stock** - Enter actual stock count in the input field
3. **Complete** - Click green "Complete" button
4. **Anti-Cheat** - If you switch tabs, 15-second countdown penalty applies
5. **Summary** - After all products, redirected to summary page with stats

#### Scoring System
- **10 points** - Perfect accuracy (qty matches)
- **5 points** - Within 10% accuracy
- **1 point** - Completion (even if inaccurate)
- **+5 bonus** - Speed bonus (under 30 seconds)
- **+10 bonus** - Perfect streak bonus

### For Managers

#### Dashboard Access
```
https://staff.vapeshed.co.nz/modules/flagged_products/views/dashboard.php
```

**Features:**
- Multi-store performance comparison
- Historical trend charts (Chart.js)
- Top performers list
- Violation pattern detection
- Date range filters

#### Leaderboard Access
```
https://staff.vapeshed.co.nz/modules/flagged_products/views/leaderboard.php
```

**Features:**
- Daily, weekly, monthly, all-time rankings
- Achievement badges display
- Store filtering
- User position highlighting

### Bot Bypass for Testing

Add these parameters to URL:
```
?bypass_security=1&bot=1
```

Or login as user ID 18.

---

## 🎮 Gamification Features

### Points System

| Action | Points | Conditions |
|--------|--------|------------|
| Perfect Match | 10 | Qty exactly matches |
| Good Accuracy | 5 | Within 10% of actual |
| Completion | 1 | Any completion |
| Speed Bonus | +5 | Under 30 seconds |
| Streak Bonus | +10 | 7+ day streak |

### Achievements

| Achievement | Criteria | Icon | Bonus |
|-------------|----------|------|-------|
| First Step | Complete 1 product | 🎯 | 50 pts |
| Perfect 10 | 10 products @ 100% | 💯 | 50 pts |
| Speed Demon | Avg < 30s (10+ products) | ⚡ | 50 pts |
| Week Warrior | 7-day streak | 🔥 | 50 pts |
| Century Club | 100 products | 💯 | 50 pts |
| Accuracy Master | 98%+ accuracy (50+ products) | 🎯 | 50 pts |
| Point Millionaire | 1,000 points | 💰 | 50 pts |

### Leaderboard Periods

- **Daily** - Today's rankings
- **Weekly** - This week (Mon-Sun)
- **Monthly** - Current month
- **All-Time** - Historical best

---

## 🔒 Anti-Cheat System

### Detection Methods

1. **Tab Switch Detection**
   - Monitors `visibilitychange` and `blur` events
   - Shows blur modal with 15-second countdown
   - Button disabled until countdown completes
   - Violations logged to database

2. **DevTools Detection**
   - Monitors window size changes
   - Detects console panel opening
   - Flags suspicious behavior

3. **Timing Analysis**
   - Too fast = suspicious
   - Too slow = distracted
   - Patterns flagged for review

4. **Mouse Movement Tracking**
   - Monitors interaction patterns
   - Detects bot-like behavior

### Violation Logging

All violations stored silently in `flagged_products_violations`:
```php
{
    "user_id": 123,
    "violation_type": "tab_switch",
    "severity": "high",
    "timestamp": "2025-10-26 14:30:00",
    "context": {...}
}
```

No scary visual warnings - just professional countdown penalty.

---

## 🤖 AI Integration

### ChatGPT Insights

**Generated For:**
- Completion summary page
- Manager dashboard
- Weekly email reports

**Insight Types:**
- Performance highlights
- Improvement suggestions
- Motivational messages
- Trend analysis

**Fallback:**
If ChatGPT unavailable, system uses rule-based insights:
- Accuracy-based feedback
- Streak recognition
- Speed optimization tips
- Volume achievements

### API Configuration

```php
// In generate_ai_insights.php
$apiKey = getenv('OPENAI_API_KEY');

// Prompt structure
$prompt = "Analyze retail employee performance...";
$model = "gpt-4";
$maxTokens = 150;
```

---

## 📊 Analytics & Reporting

### Available Metrics

**User Level:**
- Products completed
- Accuracy percentage
- Current streak
- Total points
- Average time per product
- Security score

**Store Level:**
- Total completions
- Store accuracy
- Active users
- Completion rate
- Violation counts

**Company Level:**
- Multi-store comparison
- Historical trends
- Top performers
- Achievement distribution

### Data Export

All stats available via `FlaggedProductsRepository`:
```php
// Get user stats
$stats = FlaggedProductsRepository::getUserStats($userId);

// Get store stats with date range
$stats = FlaggedProductsRepository::getStoreStats($outletId, $startDate, $endDate);

// Get leaderboard
$leaderboard = FlaggedProductsRepository::getLeaderboard('weekly', 50, $outletId);

// Get historical trends
$trends = FlaggedProductsRepository::getHistoricalTrends($outletId, $startDate, $endDate);
```

---

## 🎨 UI Design

### Design System

**Colors:**
- Primary: `#667eea` (Purple gradient)
- Accent: `#fbbf24` (Gold)
- Success: `#10b981` (Green)
- Danger: `#ef4444` (Red)

**Stock Level Colors:**
- Red `#ef4444`: 0-4 units (Critical)
- Orange `#f59e0b`: 5-9 units (Low)
- Blue `#3b82f6`: 10-19 units (Medium)
- Green `#10b981`: 20+ units (Good)

**Typography:**
- Headers: 16-18px, weight 700
- Body: 13-14px, weight 400
- Stats: 24-32px, weight 700
- Labels: 10-12px, weight 600

**Spacing:**
- Compact: 8-10px padding
- Standard: 15-20px padding
- Generous: 25-30px padding

### Responsive Design

- **Desktop** (> 992px): Full layout with sidebar
- **Tablet** (768-992px): Stacked layout
- **Mobile** (< 768px): Card-based layout

---

## 🔧 Maintenance

### Cron Jobs

| Task | Frequency | Purpose |
|------|-----------|---------|
| Leaderboard Refresh | Daily @ 2 AM | Update rankings cache |
| AI Insights | Hourly | Generate ChatGPT insights |
| Achievement Checks | Every 6 hours | Award badges |
| Stats Caching | Every 30 min | Cache dashboard data |

### Monitoring

Check logs in:
```
/home/master/applications/jcepnzzkmj/public_html/logs/cis.log
```

Filter by module:
```bash
grep "flagged_products" /path/to/logs/cis.log
```

### Performance Optimization

**Cache Strategy:**
- Leaderboards: 24 hours
- Store stats: 2 hours
- User stats: 1 hour
- AI insights: Until next completion

**Database Indexes:**
```sql
-- Essential indexes
CREATE INDEX idx_outlet_completed ON flagged_products(outlet, date_completed_stocktake);
CREATE INDEX idx_user_stats ON flagged_products_user_stats(user_id, points_earned);
CREATE INDEX idx_violations ON flagged_products_violations(user_id, created_at);
```

---

## 🐛 Troubleshooting

### Common Issues

#### 1. "Missing Outlet ID" Error
**Solution:** Always access with `?outlet_id=YOUR_OUTLET_ID` parameter

#### 2. Anti-Cheat Countdown Doesn't Work
**Check:**
- JavaScript enabled in browser
- No console errors
- anti-cheat.js loaded properly

#### 3. Achievements Not Awarded
**Check:**
- Cron job running (register_tasks.php)
- check_achievements.php executing
- Database table exists

#### 4. AI Insights Not Generating
**Check:**
- OpenAI API key configured
- generate_ai_insights.php cron running
- Fallback insights working

#### 5. Leaderboard Empty
**Check:**
- Users have completed products
- refresh_leaderboard.php cron running
- Date range filters correct

### Debug Mode

Enable verbose logging:
```php
// In bootstrap.php
define('FLAGGED_PRODUCTS_DEBUG', true);
```

---

## 🚦 Testing Checklist

### Pre-Production Testing

- [ ] Load page with valid outlet_id
- [ ] Verify product list displays correctly
- [ ] Test stock level colors (red/orange/blue/green)
- [ ] Complete a product successfully
- [ ] Test tab switching triggers countdown
- [ ] Verify countdown blocks button for 15 seconds
- [ ] Check Lightspeed queue entry created
- [ ] Verify CIS inventory updated
- [ ] Test summary page redirect
- [ ] Check points awarded correctly
- [ ] Test achievement unlocking
- [ ] Verify leaderboard rankings
- [ ] Test manager dashboard loads
- [ ] Check historical charts display
- [ ] Test violation logging (silent)
- [ ] Verify AI insights generate

### Performance Testing

- [ ] Page load < 2 seconds
- [ ] Product completion < 500ms API response
- [ ] Dashboard charts render smoothly
- [ ] Leaderboard loads < 1 second
- [ ] No memory leaks during long sessions

### Security Testing

- [ ] Bot bypass only works for authorized users
- [ ] Anti-cheat cannot be disabled client-side
- [ ] SQL injection prevention on all queries
- [ ] XSS protection on all outputs
- [ ] CSRF tokens on all forms

---

## 📝 API Documentation

### Complete Product
```http
POST /modules/flagged_products/api/complete-product.php
Content-Type: application/json

{
    "product_id": "1164404",
    "outlet_id": "02dcd191-ae2b-11e6-f485-8eceed6eeafb",
    "qty_after": 10,
    "qty_before": 8,
    "time_spent": 25,
    "security_context": {...}
}

Response:
{
    "success": true,
    "points_earned": 15,
    "new_total_points": 250,
    "streak_updated": true,
    "achievements_unlocked": ["perfect_10"]
}
```

### Get Summary Stats
```http
GET /modules/flagged_products/api/get-summary-stats.php?user_id=123&outlet_id=abc

Response:
{
    "success": true,
    "user_stats": {...},
    "store_stats": {...},
    "leaderboard_position": 5,
    "ai_insight": "Great accuracy! Keep the momentum..."
}
```

---

## 🎯 Future Enhancements

### Planned Features

- [ ] Mobile app (React Native)
- [ ] Voice input for stock counts
- [ ] Barcode scanner integration
- [ ] Photo verification option
- [ ] Team challenges/competitions
- [ ] Weekly email reports
- [ ] Push notifications for achievements
- [ ] Advanced analytics dashboard
- [ ] Machine learning fraud detection
- [ ] Integration with staff scheduling

### Ideas for v3.0

- Real-time multiplayer leaderboard updates (WebSocket)
- Augmented reality stock counting (AR.js)
- Predictive analytics for flagged products
- Integration with store CCTV for verification
- Gamification tournaments (store vs store)

---

## 📞 Support

### Contact Information

**Developer:** CIS Development Team  
**Email:** dev@vapeshed.co.nz  
**Documentation:** https://staff.vapeshed.co.nz/docs/flagged-products

### Reporting Issues

Please include:
1. User ID and outlet ID
2. Browser and device info
3. Steps to reproduce
4. Screenshots if applicable
5. Console errors (F12 → Console)

---

## 📄 License

Proprietary - The Vape Shed / Ecigdis Limited  
© 2025 All Rights Reserved

---

**Version:** 2.0.0  
**Last Updated:** October 26, 2025  
**Status:** ✅ Production Ready
