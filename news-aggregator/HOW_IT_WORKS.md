# 🎯 NEWS FEED INTEGRATION - COMPLETE EXPLANATION

## 📋 WHAT I JUST BUILT FOR YOU

I've integrated the **News Aggregator Module** into your **Premium Facebook-Style Dashboard Feed**. Here's EXACTLY how it all works:

---

## 🔄 THE COMPLETE DATA FLOW (Step-by-Step)

### 1️⃣ ADD NEWS SOURCES
**What:** Tell the system which RSS feeds to monitor
**Where:** `news_sources` table in database
**How:** Insert RSS feed URLs with these fields:
- `name` - Source name (e.g., "Vaping Post")
- `url` - RSS feed URL (e.g., "https://www.vapingpost.com/feed/")
- `type` - Feed type ("rss", "html", or "api")
- `category` - Content category ("vaping", "regulation", "technology")
- `crawl_frequency` - Seconds between crawls (3600 = hourly)
- `is_active` - Enable/disable (1 = active, 0 = disabled)

**Example SQL:**
```sql
INSERT INTO news_sources (name, url, type, category, crawl_frequency, is_active, created_at)
VALUES ('Vaping Post', 'https://www.vapingpost.com/feed/', 'rss', 'vaping', 3600, 1, NOW());
```

---

### 2️⃣ CRAWLER FETCHES ARTICLES
**What:** Automated script that downloads articles from RSS feeds
**Where:** `/modules/news-aggregator/cron-crawler.php`
**When:** Runs on cron schedule (e.g., every hour)
**How:**
1. Checks `news_sources` for sources where `next_crawl_at <= NOW()`
2. Downloads RSS feed XML
3. Parses articles using `simplexml_load_string()`
4. Extracts: title, description, url, author, published date, images
5. Saves to `news_articles` table
6. Logs results to `news_crawl_log`

**What It Does:**
```
RSS Feed XML → AggregatorService.crawlRSS() → Parse XML → Extract Data → Save to DB
```

**Key Features:**
- ✅ Supports RSS 2.0 and Atom formats
- ✅ Downloads and caches images locally
- ✅ Deduplicates articles via `external_id`
- ✅ Rate limiting (2 seconds between requests)
- ✅ Error handling and logging

**Run Manually:**
```bash
cd /modules/news-aggregator
php cron-crawler.php
```

---

### 3️⃣ ARTICLES STORED IN DATABASE
**What:** Permanent storage of crawled news articles
**Where:** `news_articles` table
**Structure:**
```sql
CREATE TABLE news_articles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    source_id INT,                    -- FK to news_sources
    external_id VARCHAR(255),         -- RSS guid (for deduplication)
    title VARCHAR(500),               -- Article headline
    content TEXT,                     -- Full article text
    summary TEXT,                     -- Short description
    external_url VARCHAR(500),        -- Link to original article
    author VARCHAR(255),              -- Article author
    published_at DATETIME,            -- Publication timestamp
    image_url VARCHAR(500),           -- Cached image path
    category VARCHAR(100),            -- vaping, regulation, tech
    view_count INT DEFAULT 0,         -- Times viewed
    click_count INT DEFAULT 0,        -- Times clicked
    is_pinned BOOLEAN DEFAULT 0,      -- Show at top?
    priority INT DEFAULT 0,           -- Sort order
    metadata JSON,                    -- Extra data
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Example Row:**
```
id: 42
title: "FDA Announces New Vape Regulations for 2025"
content: "The Food and Drug Administration has released..."
external_url: "https://www.vapingpost.com/2025/01/fda-announces..."
source_id: 1 (Vaping Post)
published_at: 2025-01-15 14:30:00
image_url: /cache/news-images/fda-regulations-2025.jpg
category: regulation
view_count: 127
click_count: 34
is_pinned: 1
```

---

### 4️⃣ FEEDDPROVIDER QUERIES ARTICLES
**What:** PHP class that provides data to the dashboard
**Where:** `/modules/news-aggregator/FeedProvider.php`
**Purpose:** Query interface between database and UI

**Key Methods:**

#### `getUnifiedFeed($options)`
Returns mixed internal + external content for dashboard

**Parameters:**
```php
$options = [
    'limit' => 15,                    // How many items
    'offset' => 0,                    // Pagination
    'category' => 'vaping',           // Filter by category
    'type' => 'external',             // 'internal', 'external', or both
];
```

**SQL Query:**
```sql
SELECT
    na.*,
    ns.name as source_name,
    ns.logo_url as source_logo
FROM news_articles na
LEFT JOIN news_sources ns ON na.source_id = ns.id
WHERE na.category = ?
ORDER BY
    na.is_pinned DESC,
    na.priority ASC,
    na.published_at DESC
LIMIT 15
```

**Returns:**
```php
[
    {
        id: 42,
        title: "FDA Announces New Vape Regulations",
        content: "The Food and Drug Administration...",
        external_url: "https://...",
        source_name: "Vaping Post",
        published_at: "2025-01-15 14:30:00",
        image_url: "/cache/news-images/fda.jpg",
        view_count: 127,
        click_count: 34,
        is_pinned: 1
    },
    ...
]
```

#### `getTrending($limit, $days)`
Returns most-viewed articles from last X days

**SQL:**
```sql
SELECT * FROM news_articles
WHERE published_at > DATE_SUB(NOW(), INTERVAL ? DAY)
ORDER BY (view_count + click_count) DESC
LIMIT ?
```

#### `recordView($articleId)` / `recordClick($articleId)`
Track engagement metrics

---

### 5️⃣ DASHBOARD-FEED.PHP DISPLAYS CONTENT
**What:** Premium Facebook-style 3-column feed layout
**Where:** `/modules/base/templates/vape-ultra/views/dashboard-feed.php`
**Purpose:** Display mixed internal + external content

**How It Works:**

#### Data Loading (Top of File)
```php
// 1. Initialize FeedProvider
$feedProvider = new FeedProvider($GLOBALS['conn']);

// 2. Get external news articles
$externalNews = $feedProvider->getUnifiedFeed(['limit' => 15]);
$trendingNews = $feedProvider->getTrending(5, 7);

// 3. Get internal CIS activities
$internalActivity = getRecentSystemActivity();

// 4. Mix them together
$recentActivity = [];
foreach ($internalActivity as $activity) {
    $recentActivity[] = $activity; // Internal: orders, transfers, POs
}
foreach ($externalNews as $article) {
    $recentActivity[] = [
        'feed_type' => 'external',
        'type' => 'news',
        'title' => $article['title'],
        'description' => $article['content'],
        'timestamp' => $article['published_at'],
        'url' => $article['external_url'],
        'image' => $article['image_url'],
        'engagement' => $article['view_count'] + $article['click_count'],
        'is_pinned' => $article['is_pinned'],
        'details' => [
            'source' => $article['source_name'],
            'category' => $article['category']
        ]
    ];
}

// 5. Sort by pinned first, then timestamp
usort($recentActivity, function($a, $b) {
    if ($a->is_pinned) return -1;
    if ($b->is_pinned) return 1;
    return strtotime($b->timestamp) - strtotime($a->timestamp);
});
```

#### Display Logic (Activity Stream)
```php
<?php foreach($recentActivity as $activity): ?>

    <?php if ($activity->feed_type === 'external'): ?>
        <!-- EXTERNAL NEWS CARD -->
        <div class="activity-card news-card">
            <?php if ($activity->image): ?>
                <div class="news-image">
                    <img src="<?= $activity->image ?>">
                    <?php if ($activity->is_pinned): ?>
                        <div class="pinned-badge">📌 Pinned</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="activity-content">
                <div class="activity-title">
                    📰 <?= htmlspecialchars($activity->title) ?>
                </div>
                <div class="activity-body">
                    <?= nl2br(htmlspecialchars($activity->description)) ?>
                </div>
                <div class="news-meta">
                    <span>🌐 <?= $activity->details['source'] ?></span>
                    <span>🏷️ <?= $activity->details['category'] ?></span>
                    <span>👁️ <?= $activity->engagement ?> views</span>
                </div>
                <a href="<?= $activity->url ?>" target="_blank" class="btn">
                    Read Full Article →
                </a>
            </div>
        </div>

    <?php else: ?>
        <!-- INTERNAL CIS ACTIVITY CARD -->
        <div class="activity-card">
            <div class="activity-icon">🛒</div>
            <div class="activity-content">
                <div class="activity-title"><?= $activity->title ?></div>
                <div class="activity-body"><?= $activity->description ?></div>
            </div>
        </div>
    <?php endif; ?>

<?php endforeach; ?>
```

---

### 6️⃣ INDEX-ULTRA.PHP VIEW TOGGLE
**What:** Switch between normal dashboard and premium feed
**Where:** `/public_html/index-ultra.php`
**How:** Session-based view mode switcher

**Session Variable:**
```php
$_SESSION['dashboard_view'] = 'default'; // or 'feed'
```

**Toggle Handler:**
```php
// Handle view toggle
if (isset($_GET['toggle_view'])) {
    $_SESSION['dashboard_view'] = ($_SESSION['dashboard_view'] ?? 'default') === 'default' ? 'feed' : 'default';
    header('Location: index-ultra.php');
    exit;
}
```

**View Conditional:**
```php
<?php if ($viewMode === 'feed'): ?>
    <!-- PREMIUM FEED VIEW -->
    <?php require_once 'modules/base/templates/vape-ultra/views/dashboard-feed.php'; ?>
<?php else: ?>
    <!-- STANDARD DASHBOARD VIEW -->
    ...existing dashboard content...
<?php endif; ?>
```

**Toggle Button (in header):**
```html
<a href="index-ultra.php?toggle_view=1" class="btn btn-outline-primary">
    <i class="bi bi-<?= $viewMode === 'default' ? 'list-ul' : 'grid-3x3-gap' ?>"></i>
    <?= $viewMode === 'default' ? 'Feed View' : 'Dashboard View' ?>
</a>
```

---

## 🎨 LAYOUT STRUCTURE (3-Column Facebook-Style)

```
┌─────────────────────────────────────────────────────────────┐
│ HEADER: CIS Ultra Dashboard + Toggle Button                 │
├──────────┬──────────────────────────────────┬───────────────┤
│          │                                  │               │
│  LEFT    │       CENTER FEED                │  RIGHT        │
│ SIDEBAR  │                                  │ SIDEBAR       │
│          │                                  │               │
│ ┌──────┐ │ ┌──────────────────────────────┐ │ ┌───────────┐│
│ │Quick │ │ │ 📰 FDA Announces Regulations │ │ │ Today's   ││
│ │Action│ │ │ [IMAGE]                      │ │ │ Stats     ││
│ │      │ │ │ The FDA has released...      │ │ │           ││
│ │📤 New│ │ │ 🌐 Vaping Post 👁️ 127 views  │ │ │ 📦 52     ││
│ │Trans │ │ │ [Read Full Article]          │ │ │ Orders    ││
│ │      │ │ └──────────────────────────────┘ │ │           ││
│ │📋 New│ │                                  │ │ 🎯 12     ││
│ │PO    │ │ ┌──────────────────────────────┐ │ │ C&C       ││
│ │      │ │ │ 🛒 New Order #12345          │ │ └───────────┘│
│ │🔍 Find│ │ │ Customer: John Smith         │ │               │
│ │Product│ │ │ Total: $85.50                │ │ ┌───────────┐│
│ └──────┘ │ │ [View Details]               │ │ │ Low Stock ││
│          │ └──────────────────────────────┘ │ │           ││
│ ┌──────┐ │                                  │ │ ⚠️ Juice X ││
│ │Store │ │ ┌──────────────────────────────┐ │ │ ⚠️ Coil Y ││
│ │Accuracy│ │ │ 📦 Transfer #89 Completed   │ │ └───────────┘│
│ │      │ │ │ From: Auckland to Chch       │ │               │
│ │█████░│ │ │ 24 items                     │ │ ┌───────────┐│
│ │95%   │ │ │ [View Details]               │ │ │ Top       ││
│ └──────┘ │ └──────────────────────────────┘ │ │ Sellers   ││
│          │                                  │ │           ││
│ ┌──────┐ │ [Load More]                      │ │ #1 Pod A  ││
│ │Staff │ │                                  │ │ #2 Juice B││
│ │Online│ │                                  │ │ #3 Kit C  ││
│ │      │ │                                  │ │           ││
│ │👤 JS │ │                                  │ │           ││
│ │👤 AB │ │                                  │ │           ││
│ └──────┘ │                                  │ └───────────┘│
│          │                                  │               │
└──────────┴──────────────────────────────────┴───────────────┘
```

---

## 📂 FILE STRUCTURE

```
/public_html/
├── index-ultra.php
│   ├── Loads config.php
│   ├── Checks $_SESSION['dashboard_view']
│   ├── Toggle handler (?toggle_view=1)
│   └── Conditional: feed view OR standard dashboard
│
├── modules/
│   ├── news-aggregator/
│   │   ├── AggregatorService.php      ← RSS crawler
│   │   ├── FeedProvider.php           ← Query interface
│   │   ├── AdminController.php        ← Admin panel
│   │   ├── cron-crawler.php           ← Cron entry point
│   │   ├── installer.php              ← Database setup
│   │   ├── SETUP_GUIDE.md             ← Full instructions
│   │   ├── README.md                  ← Feature docs
│   │   └── check-news.sh              ← Verification script
│   │
│   └── base/
│       └── templates/
│           └── vape-ultra/
│               └── views/
│                   └── dashboard-feed.php  ← Premium feed UI
│
└── cache/
    └── news-images/                    ← Cached article images
```

---

## 🗄️ DATABASE TABLES

### `news_sources`
Stores RSS feed URLs and configuration

**Columns:**
- `id` - Primary key
- `name` - Display name (e.g., "Vaping Post")
- `url` - RSS feed URL
- `type` - "rss", "html", or "api"
- `category` - Content category
- `crawl_frequency` - Seconds between crawls
- `is_active` - Enable/disable flag
- `last_crawled_at` - Last successful crawl
- `next_crawl_at` - When to crawl next
- `logo_url` - Source logo image
- `created_at` / `updated_at`

### `news_articles`
Stores crawled articles

**Columns:**
- `id` - Primary key
- `source_id` - FK to news_sources
- `external_id` - RSS guid (deduplication)
- `title` - Headline
- `content` - Full text
- `summary` - Short description
- `external_url` - Original article link
- `author` - Article author
- `published_at` - Publication date
- `image_url` - Cached image path
- `category` - vaping/regulation/tech
- `view_count` - Times viewed
- `click_count` - Times clicked
- `is_pinned` - Show at top
- `priority` - Sort order
- `metadata` - JSON extra data
- `created_at` / `updated_at`

### `news_feed_items`
Tracks feed item history (optional)

### `news_crawl_log`
Logs crawler execution

**Columns:**
- `id` - Primary key
- `source_id` - Which source was crawled
- `articles_found` - How many articles
- `articles_new` - How many were new
- `articles_updated` - How many were updated
- `duration_ms` - Crawl duration
- `status` - "success" or "error"
- `error_message` - If failed
- `created_at`

---

## 🚀 QUICK START COMMANDS

### 1. Verify Tables Exist
```bash
cd /modules/news-aggregator
./check-news.sh
```

### 2. Add News Source
```sql
INSERT INTO news_sources (name, url, type, category, crawl_frequency, is_active, created_at)
VALUES ('Vaping Post', 'https://www.vapingpost.com/feed/', 'rss', 'vaping', 3600, 1, NOW());
```

### 3. Run First Crawl
```bash
cd /modules/news-aggregator
php cron-crawler.php
```

### 4. Set Up Cron Job
```bash
crontab -e
# Add: 0 * * * * cd /path/to/modules/news-aggregator && php cron-crawler.php
```

### 5. Visit Dashboard
```
https://staff.vapeshed.co.nz/index-ultra.php
```

### 6. Click "Feed View" Button
```
Button in header → Switches to premium feed → Shows mixed content
```

---

## 🎯 WHAT HAPPENS WHEN YOU CLICK "FEED VIEW"

1. **Click Button** → Sends `?toggle_view=1` to index-ultra.php
2. **index-ultra.php** → Toggles `$_SESSION['dashboard_view']` between 'default' and 'feed'
3. **Page Reloads** → Checks session variable
4. **If 'feed'** → Requires `dashboard-feed.php` instead of standard dashboard
5. **dashboard-feed.php** → Loads FeedProvider
6. **FeedProvider** → Queries `news_articles` table
7. **Mix Data** → Combines external news + internal CIS activities
8. **Sort** → Pinned first, then by timestamp
9. **Display** → Renders 3-column Facebook-style layout
10. **User Sees** → Mixed feed of news articles + CIS activities

---

## 🔥 KEY FEATURES

### News Cards Display:
- ✅ Featured image at top (if available)
- ✅ Pinned badge for important articles
- ✅ Article title with icon
- ✅ Content preview (200 chars)
- ✅ Metadata (source, category, engagement)
- ✅ "Read Full Article" button (opens in new tab)
- ✅ Hover effects and smooth animations

### Internal Activity Cards Display:
- ✅ Icon based on type (🛒 order, 📦 transfer, 📋 PO)
- ✅ Title and description
- ✅ Timestamp ("2h ago")
- ✅ Detail fields (customer, total, items)
- ✅ Action buttons (View Details, Process, etc)

### Filtering:
- ✅ Filter buttons at top (All / Orders / Transfers / POs / News)
- ✅ Click to filter by type
- ✅ Active state on selected filter

### Auto-Refresh:
- ✅ Every 30 seconds (configurable)
- ✅ AJAX request to load new items
- ✅ Smooth insertion of new content

---

## 🐛 TROUBLESHOOTING

### "No articles showing"
1. Check database: `SELECT COUNT(*) FROM news_articles;`
2. Add source: See SETUP_GUIDE.md Step 2
3. Run crawler: `php cron-crawler.php`

### "Crawler not working"
1. Check cron: `crontab -l`
2. Check logs: `tail -f /tmp/news-crawler.log`
3. Test feed: `curl -I https://www.vapingpost.com/feed/`

### "Images not showing"
1. Check directory: `ls -la /cache/news-images/`
2. Fix permissions: `chmod 755 /cache/news-images/`
3. Check URLs: `SELECT image_url FROM news_articles LIMIT 5;`

### "Toggle button not working"
1. Check session: `print_r($_SESSION);`
2. Verify file path in require_once
3. Check for PHP errors in Apache log

---

## 📚 FULL DOCUMENTATION

See these files for complete details:
- `/modules/news-aggregator/SETUP_GUIDE.md` - Step-by-step setup
- `/modules/news-aggregator/README.md` - Feature documentation
- `/modules/news-aggregator/check-news.sh` - Verification script

---

## ✅ SUMMARY

You now have a **FULLY INTEGRATED NEWS AGGREGATOR** with:

1. ✅ RSS crawler that fetches articles automatically
2. ✅ Database storage with engagement tracking
3. ✅ FeedProvider query interface
4. ✅ Premium Facebook-style feed UI
5. ✅ Toggle button to switch views
6. ✅ Mixed internal + external content
7. ✅ Pinned articles, categories, filtering
8. ✅ Image caching, deduplication, rate limiting
9. ✅ Cron job automation
10. ✅ Comprehensive documentation

**NEXT STEPS:**
1. Run `./check-news.sh` to verify setup
2. Add 3-5 vape industry RSS feeds
3. Run `php cron-crawler.php` to populate
4. Visit dashboard and click "Feed View"
5. Enjoy your new premium feed! 🎉
