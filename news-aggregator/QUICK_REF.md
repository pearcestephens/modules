# 🚀 NEWS FEED QUICK REFERENCE

## 📋 TLDR - 3 MINUTE SETUP

```bash
# 1. Add news source
mysql -u root -p your_database << EOF
INSERT INTO news_sources (name, url, type, category, crawl_frequency, is_active, created_at)
VALUES ('Vaping Post', 'https://www.vapingpost.com/feed/', 'rss', 'vaping', 3600, 1, NOW());
EOF

# 2. Run crawler
cd /home/master/applications/jcepnzzkmj/public_html/modules/news-aggregator
php cron-crawler.php

# 3. Visit dashboard
# Go to: https://staff.vapeshed.co.nz/index-ultra.php
# Click: "Feed View" button in header
```

---

## 🔄 DATA FLOW (ONE SENTENCE)

**RSS Feeds → Crawler (cron-crawler.php) → Database (news_articles) → FeedProvider → Dashboard Display**

---

## 📂 KEY FILES

| File | Purpose |
|------|---------|
| `/public_html/index-ultra.php` | Main dashboard with toggle |
| `/modules/news-aggregator/cron-crawler.php` | Crawls RSS feeds |
| `/modules/news-aggregator/AggregatorService.php` | Crawler logic |
| `/modules/news-aggregator/FeedProvider.php` | Query interface |
| `/modules/base/templates/vape-ultra/views/dashboard-feed.php` | Premium feed UI |

---

## 🗄️ DATABASE TABLES

- `news_sources` - RSS feed URLs (add feeds here)
- `news_articles` - Crawled articles (displayed in feed)
- `news_crawl_log` - Crawler execution history

---

## ⚡ COMMON COMMANDS

### Add News Source
```sql
INSERT INTO news_sources (name, url, type, category, crawl_frequency, is_active, created_at)
VALUES ('Source Name', 'https://example.com/feed/', 'rss', 'vaping', 3600, 1, NOW());
```

### Manual Crawl
```bash
cd /modules/news-aggregator
php cron-crawler.php
```

### Check Status
```bash
./check-news.sh
```

### Count Articles
```sql
SELECT COUNT(*) FROM news_articles;
```

### View Recent
```sql
SELECT title, published_at FROM news_articles ORDER BY published_at DESC LIMIT 5;
```

### Pin Article
```sql
UPDATE news_articles SET is_pinned = 1 WHERE id = 42;
```

---

## 🎯 FEED VIEW

### Toggle Button Location
**Header → "Feed View" button**

### What It Shows
- 📰 External news articles (with images)
- 🛒 Internal CIS activities (orders, transfers, POs)
- 📌 Pinned articles at top
- 🔥 Mixed and sorted by timestamp

### Layout
- **Left Sidebar:** Quick actions, store accuracy, staff online
- **Center Feed:** Activity stream (news + CIS events)
- **Right Sidebar:** Stats, low stock, top sellers

---

## 🐛 QUICK FIXES

### No articles?
```bash
php cron-crawler.php
```

### Can't toggle view?
Check: `/public_html/index-ultra.php` line 18-27

### Images not showing?
```bash
chmod 755 /cache/news-images/
```

### Crawler not running?
```bash
crontab -e
# Add: 0 * * * * cd /path/to/modules/news-aggregator && php cron-crawler.php
```

---

## 📊 MONITORING

### Crawler Logs
```bash
tail -f /tmp/news-crawler.log
```

### Latest Crawl
```sql
SELECT * FROM news_crawl_log ORDER BY created_at DESC LIMIT 5;
```

### Most Popular
```sql
SELECT title, view_count + click_count as engagement
FROM news_articles
ORDER BY engagement DESC
LIMIT 10;
```

---

## 🔗 FULL DOCS

- **Setup Guide:** `/modules/news-aggregator/SETUP_GUIDE.md`
- **How It Works:** `/modules/news-aggregator/HOW_IT_WORKS.md`
- **Feature Docs:** `/modules/news-aggregator/README.md`

---

## 🎉 YOU'RE DONE!

Your dashboard now has a **Facebook-style feed** mixing:
- ✅ Vape industry news (auto-crawled from RSS)
- ✅ Internal CIS activities (orders, transfers, POs)
- ✅ Engagement tracking (views, clicks)
- ✅ Pinned articles, categories, filtering
- ✅ Beautiful 3-column layout

**Click "Feed View" to see it!** 🚀
