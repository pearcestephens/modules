# 📰 NEWS AGGREGATOR SETUP GUIDE

## 🎯 HOW IT WORKS (DATA FLOW)

```
┌─────────────────┐
│  RSS Feed URLs  │ ← Add news sources (vape industry, tech, etc)
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ AggregatorService│ ← Crawls feeds every X hours
│  crawlRSS()     │    Parses RSS 2.0 / Atom
└────────┬────────┘    Extracts: title, content, url, images
         │
         ▼
┌─────────────────┐
│ news_articles   │ ← Stores articles with metadata
│   (database)    │    Tracks: views, clicks, pinned status
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  FeedProvider   │ ← Query interface for dashboard
│ getUnifiedFeed()│    Mixes internal + external content
└────────┬────────┘    Sorts by pinned → timestamp
         │
         ▼
┌─────────────────┐
│ Premium Feed UI │ ← Displays mixed activity stream
│ dashboard-feed  │    News cards with images
└─────────────────┘    Internal CIS activities
```

## 🚀 QUICK START (5 STEPS)

### STEP 1: Verify Database Tables Exist

```bash
mysql -u root -p
USE your_database_name;
SHOW TABLES LIKE 'news_%';
```

**Expected output:**
```
news_articles
news_crawl_log
news_feed_items
news_sources
```

**If tables don't exist:**
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/news-aggregator
php installer.php
```

---

### STEP 2: Add Your First News Source

**Option A: Via SQL**
```sql
INSERT INTO news_sources (
    name,
    url,
    type,
    category,
    crawl_frequency,
    is_active,
    created_at
) VALUES (
    'Vaping Post',
    'https://www.vapingpost.com/feed/',
    'rss',
    'vaping',
    3600,
    1,
    NOW()
);
```

**Option B: Via PHP Admin Panel** *(Coming Soon)*
- Navigate to `/modules/news-aggregator/admin.php`
- Click "Add News Source"
- Fill in form

**Recommended Vape Industry Sources:**
```sql
-- ECigIntelligence
INSERT INTO news_sources (name, url, type, category, crawl_frequency, is_active, created_at)
VALUES ('ECigIntelligence', 'https://ecigintelligence.com/feed/', 'rss', 'vaping', 7200, 1, NOW());

-- Vaping360
INSERT INTO news_sources (name, url, type, category, crawl_frequency, is_active, created_at)
VALUES ('Vaping360', 'https://vaping360.com/feed/', 'rss', 'vaping', 3600, 1, NOW());

-- Vapouround
INSERT INTO news_sources (name, url, type, category, crawl_frequency, is_active, created_at)
VALUES ('Vapouround', 'https://vapouround.co.uk/feed/', 'rss', 'vaping', 7200, 1, NOW());
```

---

### STEP 3: Run Your First Crawl

**Manual crawl (test):**
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/news-aggregator
php cron-crawler.php
```

**Expected output:**
```
🕷️  News Aggregator Crawler Starting...
✅ Crawled: Vaping Post (15 articles)
✅ Crawled: ECigIntelligence (8 articles)
✅ Crawled: Vaping360 (12 articles)
✅ Crawl Complete: 35 articles processed
```

**Verify articles in database:**
```sql
SELECT COUNT(*) FROM news_articles;
SELECT title, source_id, published_at FROM news_articles ORDER BY published_at DESC LIMIT 10;
```

---

### STEP 4: Set Up Automated Crawling (Cron Job)

**Edit crontab:**
```bash
crontab -e
```

**Add this line (crawl every hour):**
```bash
0 * * * * cd /home/master/applications/jcepnzzkmj/public_html/modules/news-aggregator && php cron-crawler.php >> /tmp/news-crawler.log 2>&1
```

**Other frequency options:**
```bash
# Every 30 minutes
*/30 * * * * php cron-crawler.php

# Every 4 hours
0 */4 * * * php cron-crawler.php

# Daily at 6am
0 6 * * * php cron-crawler.php
```

**Verify cron is running:**
```bash
tail -f /tmp/news-crawler.log
```

---

### STEP 5: Enable Premium Feed in Dashboard

**Edit `/public_html/index-ultra.php`**

Find this section (around line 50):
```php
// Load configuration
require_once __DIR__ . '/config.php';
```

Add AFTER config:
```php
// Check if user wants premium feed view
$viewMode = $_SESSION['dashboard_view'] ?? 'default';

// Handle view toggle
if (isset($_GET['toggle_view'])) {
    $_SESSION['dashboard_view'] = ($_SESSION['dashboard_view'] ?? 'default') === 'default' ? 'feed' : 'default';
    header('Location: index-ultra.php');
    exit;
}
```

Find the main content area (around line 150):
```php
<div class="container-fluid">
    <!-- Existing dashboard content -->
```

Replace with:
```php
<div class="container-fluid">
    <?php if ($viewMode === 'feed'): ?>
        <?php require_once __DIR__ . '/modules/base/templates/vape-ultra/views/dashboard-feed.php'; ?>
    <?php else: ?>
        <!-- Existing dashboard content -->
        ...
    <?php endif; ?>
</div>
```

Add toggle button to header (in `/modules/base/templates/vape-ultra/components/header.php`):
```php
<div class="header-actions">
    <a href="index-ultra.php?toggle_view=1" class="btn btn-outline-primary">
        <i class="bi bi-grid-3x3-gap-fill"></i>
        Switch to <?= $viewMode === 'default' ? 'Feed View' : 'Dashboard View' ?>
    </a>
</div>
```

---

## 🎨 CUSTOMIZATION

### Pin Important Articles
```sql
UPDATE news_articles SET is_pinned = 1 WHERE id = 42;
```

### Change Article Category
```sql
UPDATE news_articles SET category = 'regulation' WHERE id = 42;
```

### Hide Specific Source
```sql
UPDATE news_sources SET is_active = 0 WHERE name = 'Vaping Post';
```

### Adjust Crawl Frequency (in seconds)
```sql
UPDATE news_sources SET crawl_frequency = 7200 WHERE name = 'Vaping Post'; -- 2 hours
```

---

## 🐛 TROUBLESHOOTING

### No Articles Showing in Feed?

**1. Check if articles exist:**
```sql
SELECT COUNT(*) FROM news_articles WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY);
```

**2. Check last crawl:**
```sql
SELECT * FROM news_crawl_log ORDER BY created_at DESC LIMIT 5;
```

**3. Manually run crawler:**
```bash
php cron-crawler.php
```

---

### RSS Feed Not Crawling?

**1. Check feed is valid:**
```bash
curl -I https://www.vapingpost.com/feed/
# Should return: HTTP/1.1 200 OK
```

**2. Check source is active:**
```sql
SELECT * FROM news_sources WHERE is_active = 1;
```

**3. Check PHP has curl enabled:**
```bash
php -m | grep curl
```

---

### Images Not Showing?

**1. Check image cache directory exists:**
```bash
ls -la /home/master/applications/jcepnzzkmj/public_html/cache/news-images/
```

**2. Check permissions:**
```bash
chmod 755 /home/master/applications/jcepnzzkmj/public_html/cache/news-images/
```

**3. Check image URLs are valid:**
```sql
SELECT image_url FROM news_articles WHERE image_url IS NOT NULL LIMIT 5;
```

---

## 📊 MONITORING

### View Crawl Statistics
```sql
SELECT
    ns.name,
    COUNT(na.id) as article_count,
    MAX(na.published_at) as latest_article,
    SUM(na.view_count) as total_views,
    SUM(na.click_count) as total_clicks
FROM news_sources ns
LEFT JOIN news_articles na ON ns.id = na.source_id
GROUP BY ns.id
ORDER BY article_count DESC;
```

### Most Popular Articles (Last 7 Days)
```sql
SELECT
    title,
    view_count + click_count as engagement,
    published_at
FROM news_articles
WHERE published_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY engagement DESC
LIMIT 10;
```

### Crawler Health Check
```sql
SELECT
    DATE(created_at) as date,
    COUNT(*) as crawls,
    SUM(articles_found) as total_articles,
    AVG(articles_found) as avg_per_crawl
FROM news_crawl_log
WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;
```

---

## 🔥 ADVANCED FEATURES

### Add Custom Metadata to Articles
```php
// In AggregatorService.php, modify parseRSSItem():
$metadata = [
    'tags' => ['vaping', 'news', 'industry'],
    'priority' => 5,
    'custom_field' => 'value'
];
$article->metadata = json_encode($metadata);
```

### Filter by Multiple Categories
```php
$feed = $feedProvider->getUnifiedFeed([
    'category' => ['vaping', 'regulation', 'technology'],
    'limit' => 20
]);
```

### Track User Engagement
```php
// When user clicks article
$feedProvider->recordClick($articleId);

// When user views article
$feedProvider->recordView($articleId);
```

---

## 🎯 INTEGRATION CHECKLIST

- [ ] Database tables created
- [ ] Added 3+ news sources
- [ ] Ran first manual crawl successfully
- [ ] Verified articles in database
- [ ] Set up cron job
- [ ] Added toggle button to dashboard
- [ ] Tested feed view display
- [ ] Verified news cards render correctly
- [ ] Tested pinned articles appear first
- [ ] Monitored first automated crawl

---

## 📞 SUPPORT

**Files to Check:**
- `/modules/news-aggregator/AggregatorService.php` - Crawler logic
- `/modules/news-aggregator/FeedProvider.php` - Query interface
- `/modules/base/templates/vape-ultra/views/dashboard-feed.php` - Display
- `/modules/news-aggregator/cron-crawler.php` - Cron entry point

**Logs:**
```bash
# Crawler logs
tail -f /tmp/news-crawler.log

# Apache error logs
tail -f /var/log/apache2/error.log

# Database query logs
SELECT * FROM news_crawl_log ORDER BY created_at DESC LIMIT 20;
```

**Common Issues:**
- `Class not found`: Run composer autoload or add manual require
- `Connection timeout`: Increase `crawl_frequency` in news_sources
- `No articles`: Check RSS feed URL is valid with `curl`
- `Images 404`: Verify cache directory exists and is writable

---

## 🚀 YOU'RE DONE!

Your news aggregator is now:
✅ Crawling RSS feeds automatically
✅ Storing articles in database
✅ Displaying in premium feed
✅ Tracking engagement metrics
✅ Ready to scale with more sources

**Next Steps:**
1. Add more vape industry RSS feeds
2. Configure crawl frequencies per source
3. Pin important regulatory news
4. Monitor engagement metrics
5. Customize feed display styling
