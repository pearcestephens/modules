# 🚀 Competitive Intelligence System

**COMPLETE COMPETITIVE INTELLIGENCE PLATFORM FOR DAILY PRICE MONITORING & DYNAMIC PRICING**

---

## 🎯 WHAT IS THIS?

A sophisticated competitive intelligence system that:
- **Daily price monitoring** of ALL NZ vape competitors
- **Chrome headless** crawling with anti-detection
- **AI-powered dynamic pricing** recommendations
- **Automated special offer detection** → feeds into News Aggregator
- **Real-time dashboards** for monitoring and control

---

## 📁 ARCHITECTURE

```
/public_html/
├── modules/competitive-intel/        ← CONTROL PANELS (Admin UI)
│   ├── admin.php                     Master dashboard
│   ├── price-monitor.php             Real-time price tracking
│   ├── dynamic-pricing.php           Pricing recommendations
│   └── crawler-logs.php              Unified log viewer
│
└── assets/services/crawlers/        ← CRAWLERS (Backend Engines)
    ├── CentralLogger.php             Universal logging system
    ├── CompetitiveIntelCrawler.php   Main competitive crawler
    ├── ChromeSessionManager.php      Profile & session management
    ├── DynamicPricingEngine.php      AI pricing optimization
    ├── cron-competitive.php          Daily automation script
    └── schema.sql                    Database tables
```

---

## 🗄️ DATABASE SCHEMA

**7 Tables Created:**

1. **crawler_sessions** - Crawler execution sessions
2. **crawler_logs** - Unified logs for ALL crawlers
3. **competitive_prices** - Daily price tracking from competitors
4. **competitive_specials** - Special offers detected
5. **chrome_sessions** - Chrome profile management
6. **dynamic_pricing_recommendations** - AI price recommendations
7. **crawler_metrics** - Performance tracking

---

## 🎛️ FEATURES

### 1. Central Logger
- **Universal logging** for all crawlers (News + Competitive + Transfers)
- Database + file fallback
- Severity levels: debug, info, warning, error, critical
- Context tagging and correlation IDs
- Real-time monitoring
- Alert integration
- Performance metrics

### 2. Competitive Intelligence Crawler
- **8 NZ competitors** tracked:
  - Shosha, Vapo, VapeStore, Cloudix, VaporEmpire
  - Alt New Zealand, Cosmic, NZVAPOR
- Chrome headless with stealth profiles
- Anti-detection features:
  - Random user agents & viewports
  - Fingerprint rotation
  - Intelligent delays
  - Session management
- **Extracts:**
  - Product names
  - Prices (current + original)
  - Stock availability
  - Special offers
- **Sends specials → News Feed**

### 3. Chrome Session Manager
- Profile creation & rotation
- Random fingerprints (UA, viewport, timezone)
- Success rate tracking
- Auto-ban low-performing profiles
- Profile cleanup (30+ day old)
- Anti-automation flags

### 4. Dynamic Pricing Engine
- **4 Pricing Strategies:**
  - Match (lowest competitor)
  - Undercut (beat by X%)
  - Premium (above market)
  - Margin Optimize (AI-powered)
- Confidence scoring
- Margin constraints (15-60%)
- Auto-approve small changes (<5%)
- Manual review workflow
- Vend API integration ready

### 5. Admin Control Panels
- **Master Dashboard** (`admin.php`)
  - System stats (crawler, prices, recommendations)
  - Quick actions (run scan, generate pricing, apply)
  - Real-time activity feed

- **Price Monitor** (`price-monitor.php`)
  - Competitor overview (last 24h)
  - Special offers feed
  - Price history charts

- **Dynamic Pricing** (`dynamic-pricing.php`)
  - Pending recommendations
  - Approve/reject workflow
  - Applied price history

- **Crawler Logs** (`crawler-logs.php`)
  - Session history
  - Error summary
  - Real-time log viewer

---

## ⏰ AUTOMATION

**Cron Job:** Runs daily at 2:00 AM NZT

```bash
0 2 * * * /usr/bin/php /home/129337.cloudwaysapps.com/hdgwrzntwa/public_html/assets/services/crawlers/cron-competitive.php >> /home/129337.cloudwaysapps.com/hdgwrzntwa/private_html/logs/crawlers/cron.log 2>&1
```

**What it does:**
1. Scans ALL 8 competitors with Chrome headless
2. Extracts prices, products, specials
3. Saves to database
4. Detects special offers
5. Sends specials → News Aggregator
6. Generates pricing recommendations
7. Auto-approves small changes
8. Applies approved prices (optional)

---

## 🔧 INSTALLATION

### Prerequisites
- PHP 7.4+
- MySQL 5.7+
- Chrome/Chromium installed
- cURL enabled
- DOM/XML extensions

### Install Chrome (if not installed)
```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install -y google-chrome-stable

# OR Chromium
sudo apt-get install -y chromium-browser
```

### Database Setup
```bash
cd /home/129337.cloudwaysapps.com/hdgwrzntwa/public_html
mysql -u hdgwrzntwa -p'bFUdRjh4Jx' hdgwrzntwa < assets/services/crawlers/schema.sql
```

### Permissions
```bash
chmod +x assets/services/crawlers/cron-competitive.php
mkdir -p /home/129337.cloudwaysapps.com/hdgwrzntwa/private_html/logs/crawlers
mkdir -p /home/129337.cloudwaysapps.com/hdgwrzntwa/private_html/chrome-profiles
chmod 755 /home/129337.cloudwaysapps.com/hdgwrzntwa/private_html/logs/crawlers
chmod 755 /home/129337.cloudwaysapps.com/hdgwrzntwa/private_html/chrome-profiles
```

### Add to Crontab
```bash
crontab -e
# Add this line:
0 2 * * * /usr/bin/php /home/129337.cloudwaysapps.com/hdgwrzntwa/public_html/assets/services/crawlers/cron-competitive.php >> /home/129337.cloudwaysapps.com/hdgwrzntwa/private_html/logs/crawlers/cron.log 2>&1
```

---

## 🚀 USAGE

### Access Control Panels

**Master Dashboard:**
```
https://staff.vapeshed.co.nz/modules/competitive-intel/admin.php
```

**Price Monitor:**
```
https://staff.vapeshed.co.nz/modules/competitive-intel/price-monitor.php
```

**Dynamic Pricing:**
```
https://staff.vapeshed.co.nz/modules/competitive-intel/dynamic-pricing.php
```

**Crawler Logs:**
```
https://staff.vapeshed.co.nz/modules/competitive-intel/crawler-logs.php
```

### Manual Execution

**Run competitive scan:**
```bash
php /home/129337.cloudwaysapps.com/hdgwrzntwa/public_html/assets/services/crawlers/cron-competitive.php
```

**Test Chrome session:**
```php
require_once 'assets/services/crawlers/ChromeSessionManager.php';
$manager = new CIS\Crawlers\ChromeSessionManager($db, $logger);
$profile = $manager->getProfile();
print_r($profile);
```

**Generate pricing recommendations:**
```php
require_once 'assets/services/crawlers/DynamicPricingEngine.php';
$engine = new CIS\Crawlers\DynamicPricingEngine($db);
$recommendations = $engine->generateRecommendations();
print_r($recommendations);
```

---

## 📊 MONITORING

### Check Logs
```bash
# Cron logs
tail -f /home/129337.cloudwaysapps.com/hdgwrzntwa/private_html/logs/crawlers/cron.log

# Crawler logs (today)
tail -f /home/129337.cloudwaysapps.com/hdgwrzntwa/private_html/logs/crawlers/competitive_intel_$(date +%Y-%m-%d).log

# Session stats
mysql -u hdgwrzntwa -p'bFUdRjh4Jx' hdgwrzntwa -e "SELECT * FROM crawler_sessions ORDER BY started_at DESC LIMIT 10;"
```

### Database Queries

**Recent prices:**
```sql
SELECT competitor_name, COUNT(*) as products, MAX(scraped_at) as last_scan
FROM competitive_prices
WHERE scraped_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY competitor_name;
```

**Special offers:**
```sql
SELECT * FROM competitive_specials
WHERE detected_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY discount_percent DESC
LIMIT 20;
```

**Pending recommendations:**
```sql
SELECT product_name, current_price, recommended_price,
       price_change_percent, confidence_score
FROM dynamic_pricing_recommendations
WHERE status = 'pending'
ORDER BY confidence_score DESC;
```

---

## 🔐 SECURITY

- Chrome profiles isolated per session
- No secrets in code (use environment variables)
- Rate limiting on competitor sites
- Proxy rotation available
- Session rotation every 100 uses
- Profile ban on low success rate (<50%)
- Anti-detection headers
- Intelligent delays (2-8 seconds)

---

## 🛠️ CONFIGURATION

Edit in code or use `.env`:

```php
// CompetitiveIntelCrawler config
[
    'enable_chrome' => true,
    'max_concurrent' => 3,
    'request_delay_min' => 2000,
    'request_delay_max' => 8000,
    'special_discount_threshold' => 10,
    'send_specials_to_news' => true,
]

// DynamicPricingEngine config
[
    'default_strategy' => 'margin_optimize',
    'target_margin_percent' => 35,
    'min_margin_percent' => 15,
    'auto_approve_under' => 5,
    'vend_api_enabled' => false,
]

// ChromeSessionManager config
[
    'profile_rotation_after' => 100,
    'profile_ban_threshold' => 0.5,
    'headless' => true,
]
```

---

## 🎯 ROADMAP

- [ ] Pagination support (scrape all pages)
- [ ] Category-specific scraping
- [ ] Image extraction
- [ ] Product matching (fuzzy match)
- [ ] Price history charts
- [ ] Email alerts on price drops
- [ ] Slack integration
- [ ] API endpoints
- [ ] Bulk approve/reject
- [ ] Profit forecasting
- [ ] A/B testing pricing

---

## 🐛 TROUBLESHOOTING

**Chrome not found:**
```bash
which google-chrome
which chromium-browser
# Update path in ChromeSessionManager.php
```

**Permission denied:**
```bash
chmod +x assets/services/crawlers/cron-competitive.php
chmod 755 /home/129337.cloudwaysapps.com/hdgwrzntwa/private_html/chrome-profiles
```

**Database errors:**
```bash
# Re-run schema
mysql -u hdgwrzntwa -p'bFUdRjh4Jx' hdgwrzntwa < assets/services/crawlers/schema.sql
```

**Cron not running:**
```bash
# Check crontab
crontab -l

# Test manually
php assets/services/crawlers/cron-competitive.php
```

---

## 📞 SUPPORT

- **Logs:** `/modules/competitive-intel/crawler-logs.php`
- **Database:** `crawler_logs`, `crawler_sessions` tables
- **Cron log:** `/private_html/logs/crawlers/cron.log`

---

## 🏆 SUCCESS METRICS

After 30 days, you should see:
- **900+ price points tracked** (30 products × 8 competitors × daily)
- **50-100 special offers** detected
- **20-40 pricing recommendations** per week
- **5-15% margin optimization** on applied prices
- **99%+ crawler success rate**

---

**Built with 🔥 by CIS Intelligence Team**
**Version:** 1.0.0
**Last Updated:** November 5, 2025
