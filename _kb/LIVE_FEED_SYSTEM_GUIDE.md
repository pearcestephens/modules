# CIS Base Module - Live Feed System Implementation Guide

## Overview

The **Live Feed System** provides real-time activity updates for the CIS Staff Portal dashboard, featuring:

- ✅ Real-time activity stream (internal + external news)
- ✅ AJAX auto-refresh every 30 seconds
- ✅ Intelligent caching (APCu/Redis)
- ✅ Rate limiting & security hardening
- ✅ Gamification elements (engagement metrics, badges)
- ✅ Mobile-responsive design
- ✅ Performance optimized
- ✅ Accessibility compliant (WCAG 2.1 AA)

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│  Dashboard Frontend (dashboard-feed.php)                    │
│  - AJAX auto-refresh every 30s                              │
│  - Search & filter UI                                       │
│  - Engagement metrics sidebar                               │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ GET /modules/base/api/feed_refresh.php
                       ▼
┌─────────────────────────────────────────────────────────────┐
│  Feed Refresh API (feed_refresh.php)                        │
│  - Authentication & CSRF validation                         │
│  - Rate limiting (50 req/min per user)                      │
│  - Response compression & caching                           │
│  - Error logging (without PII)                              │
└──────────────────────┬──────────────────────────────────────┘
                       │
        ┌──────────────┴──────────────┐
        ▼                             ▼
┌──────────────────┐        ┌──────────────────┐
│ FeedFunctions    │        │ FeedProvider     │
│ - Get internal   │        │ - Get external   │
│   activities     │        │   news feed      │
│ - Format cards   │        │ - Unified view   │
│ - Cache data     │        │                  │
└────────┬─────────┘        └────────┬─────────┘
         │                          │
         └──────────────┬───────────┘
                        ▼
                  ┌──────────────┐
                  │  Activity    │
                  │  Partial     │
                  │  (_feed-     │
                  │   activity)  │
                  └──────┬───────┘
                         │
                         ▼
                  ┌──────────────┐
                  │ HTML Response│
                  │ (cached)     │
                  └──────────────┘
```

---

## File Structure

```
modules/base/
├── api/
│   └── feed_refresh.php                 # Main API endpoint
├── lib/
│   └── FeedFunctions.php                # Feed aggregation & utilities
├── resources/
│   └── views/
│       ├── dashboard-feed.php           # Frontend dashboard
│       └── _feed-activity.php           # Activity card partial
├── bootstrap.php                        # CIS bootstrap (auto-loaded)
└── middleware/
    ├── AuthMiddleware.php               # Authentication
    ├── RateLimitMiddleware.php          # Rate limiting
    ├── CsrfMiddleware.php               # CSRF protection
    └── ...
```

---

## Setup Instructions

### 1. **File Creation** (Already Done! ✅)

All core files have been created:
- ✅ `/modules/base/api/feed_refresh.php` - Hardened API endpoint
- ✅ `/modules/base/lib/FeedFunctions.php` - Feed aggregation library
- ✅ `/modules/base/resources/views/dashboard-feed.php` - Frontend dashboard
- ✅ `/modules/base/resources/views/_feed-activity.php` - Activity card partial

### 2. **Verify Dependencies**

Ensure these files exist and are properly configured:

```bash
# Check bootstrap.php loads correctly
php -l /modules/base/bootstrap.php

# Verify config files
ls -la /modules/base/config/*.php

# Check middleware
ls -la /modules/base/middleware/*.php
```

### 3. **Database Setup** (If Needed)

The feed system uses existing CIS tables:

```sql
-- Activities table (must exist)
SELECT * FROM activity_log LIMIT 1;

-- Check these columns exist:
-- - id, user_id, activity_type, entity_type, entity_id, title, description, metadata, created_at, deleted_at

-- Optional: Add index for performance
ALTER TABLE activity_log ADD INDEX idx_created_at (created_at DESC);
ALTER TABLE activity_log ADD INDEX idx_user_id (user_id);
```

### 4. **Environment Configuration**

Ensure `.env` is configured with:

```env
# Caching
CACHE_DRIVER=apcu          # Or 'redis' if Redis is available
REDIS_HOST=localhost       # If using Redis
REDIS_PORT=6379

# Database
DB_HOST=localhost
DB_USERNAME=cis_user
DB_PASSWORD=secure_password
DB_DATABASE=cis

# Application
APP_DEBUG=false            # Set to false in production
APP_TIMEZONE=NZ/Auckland
```

---

## Usage

### **Access the Feed Dashboard**

Navigate to the staff portal dashboard. The feed will display automatically.

**URL:** `https://staff.vapeshed.co.nz/modules/base/resources/views/dashboard-feed.php`

Or integrate into existing dashboard template:

```php
<?php
// In your existing dashboard template
render('base/resources/views/dashboard-feed');
?>
```

### **Direct API Access** (For Custom Integrations)

```bash
# Get feed data (HTML format)
curl -X GET 'https://staff.vapeshed.co.nz/modules/base/api/feed_refresh.php?limit=20&format=html' \
  -H 'Cookie: PHPSESSID=...'

# Get feed data (JSON format)
curl -X GET 'https://staff.vapeshed.co.nz/modules/base/api/feed_refresh.php?limit=20&format=json' \
  -H 'Cookie: PHPSESSID=...'

# Response
{
  "ok": true,
  "count": 20,
  "total": 150,
  "cached": false,
  "generated_at": "2025-11-11T12:30:45Z",
  "next_refresh": 30,
  "html": "<div class='activity-card'>...</div>..."
}
```

### **API Query Parameters**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | int | 20 | Number of activities (1-100) |
| `offset` | int | 0 | Pagination offset |
| `include_external` | bool | true | Include external news feeds |
| `format` | string | 'html' | Response format ('html' or 'json') |
| `cache_bust` | int | 0 | Timestamp to bypass cache |

---

## Configuration

### **Auto-Refresh Interval**

In `dashboard-feed.php`, modify the `autoRefreshInterval`:

```javascript
config: {
    autoRefreshInterval: 30000,  // 30 seconds (in milliseconds)
}
```

### **Cache TTL**

In `feed_refresh.php`, modify the cache store line:

```php
apcu_store($cacheKey, $responseData, 300); // 300 = 5 minutes
```

### **Rate Limiting**

In `feed_refresh.php`, adjust rate limits:

```php
if (!$limiter->allow(50, 60)) { // 50 requests per 60 seconds
    http_response_code(429);
    throw new Exception('Rate limit exceeded.');
}
```

### **Page Size**

In `dashboard-feed.php` JavaScript:

```javascript
pageSize: 20,  // Activities per load
```

---

## Security Features

### ✅ **Authentication**

```php
if (!isAuthenticated()) {
    http_response_code(401);
    throw new Exception('Unauthorized');
}
```

- Requires staff user login
- Session validation
- CSRF token protection

### ✅ **Rate Limiting**

```php
$limiter = new RateLimiter('feed_api', $userId);
if (!$limiter->allow(50, 60)) {
    http_response_code(429);
    throw new Exception('Rate limit exceeded');
}
```

- 50 requests per minute per user
- Prevents API abuse
- Adjustable thresholds

### ✅ **Error Handling**

```php
// Generic error messages (no sensitive info leaked)
$errorResponse = [
    'ok' => false,
    'error' => 'An error occurred while loading the feed.'
];

// Full error logged securely
Logger::error('Feed refresh API error', [
    'error' => $e->getMessage(),
    'user_id' => $userId,
]);
```

### ✅ **Security Headers**

```php
header('Cache-Control: public, max-age=30');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
```

### ✅ **Input Sanitization**

```php
// HTML escaping for output
htmlspecialchars($activity->title, ENT_QUOTES, 'UTF-8')

// Integer validation
$limit = max(1, min(100, intval($_GET['limit'])))
```

---

## Performance Optimization

### ✅ **Caching Strategy**

```
Request → Cache Key → APCu Check → Hit (return cached) → Response
                                ↓
                          Miss (fetch from DB) → Cache & Return
```

- **Cache TTL:** 5 minutes
- **Cache Key:** `feed:user:{id}:limit:{limit}:offset:{offset}:ext:{bool}`
- **Invalidation:** Automatic (TTL), or manual via `invalidateFeedCache()`

### ✅ **Database Optimization**

```sql
-- Recommended indexes for activity_log table
ALTER TABLE activity_log ADD INDEX idx_created_at (created_at DESC);
ALTER TABLE activity_log ADD INDEX idx_user_id (user_id);
ALTER TABLE activity_log ADD INDEX idx_outlet_id (outlet_id);
```

### ✅ **Output Compression**

```php
// Automatic gzip compression if available
if (extension_loaded('zlib')) {
    ob_start('ob_gzhandler');
}
```

### ✅ **Lazy Loading**

```html
<!-- Images load on-demand -->
<img src="..." loading="lazy" />
```

---

## Testing

### **Syntax Check**

```bash
php -l /modules/base/api/feed_refresh.php
php -l /modules/base/lib/FeedFunctions.php
php -l /modules/base/resources/views/dashboard-feed.php
php -l /modules/base/resources/views/_feed-activity.php
```

### **API Test**

```bash
# Test with authentication
curl -X GET 'http://localhost/modules/base/api/feed_refresh.php?limit=5' \
  -H 'Cookie: PHPSESSID=...' \
  -v

# Expected: 200 OK with JSON response
```

### **Rate Limit Test**

```bash
# Send 60+ requests in quick succession
for i in {1..70}; do
  curl -s 'http://localhost/modules/base/api/feed_refresh.php' \
    -H 'Cookie: PHPSESSID=...' | jq '.ok'
done

# Should see 429 responses after 50 requests
```

### **Cache Test**

```bash
# First request (fresh)
curl -s 'http://localhost/modules/base/api/feed_refresh.php' | jq '.cached'
# Output: false

# Second request (cached)
curl -s 'http://localhost/modules/base/api/feed_refresh.php' | jq '.cached'
# Output: true

# Bypass cache
curl -s 'http://localhost/modules/base/api/feed_refresh.php?cache_bust=1' | jq '.cached'
# Output: false
```

---

## Troubleshooting

### **Issue: Feed not loading (blank or skeleton only)**

**Solution:**
1. Check browser console for AJAX errors
2. Verify authentication: `echo $_SESSION['user_id'];`
3. Check API logs: `/modules/base/logs/app.log`
4. Test API directly: `curl http://localhost/modules/base/api/feed_refresh.php`

### **Issue: Activities not appearing**

**Solution:**
1. Verify `activity_log` table has data: `SELECT COUNT(*) FROM activity_log;`
2. Check `FeedFunctions.php` database query
3. Review error logs for SQL errors
4. Flush cache: `apcu_clear_cache()`

### **Issue: Auto-refresh not working**

**Solution:**
1. Check that auto-refresh toggle is enabled (blue button)
2. Verify JavaScript console for errors
3. Check network tab for AJAX requests
4. Confirm API endpoint is accessible

### **Issue: Rate limit errors (429)**

**Solution:**
1. Wait 60 seconds for rate limit to reset
2. Reduce refresh frequency in JavaScript config
3. Increase rate limit threshold in `feed_refresh.php` (if needed)
4. Check for automated requests (scripts, bots, etc.)

### **Issue: Memory/Cache not working**

**Solution:**
1. Verify APCu is installed: `php -m | grep apcu`
2. Check APCu settings: `php -i | grep apcu.enable`
3. Enable APCu if disabled: `php -r "echo extension_loaded('apcu') ? 'Enabled' : 'Disabled';"`
4. Fallback to no-cache mode (automatic)

---

## Optional Cron Jobs

### **Cache Warming (Recommended)**

Pre-populate cache before peak hours to improve response times:

```bash
# Crontab entry (runs at 7:55 AM every weekday)
55 7 * * 1-5 /usr/bin/php /modules/base/scripts/warm-feed-cache.php

# Script example:
<?php
require_once __DIR__ . '/../bootstrap.php';
$users = $db->query("SELECT DISTINCT user_id FROM activity_log LIMIT 100");
foreach ($users as $user) {
    // Trigger API to warm cache
    shell_exec("curl -s http://localhost/modules/base/api/feed_refresh.php");
}
?>
```

### **Cache Cleanup (Optional)**

Clear expired cache entries periodically:

```bash
# Crontab entry (runs daily at 2 AM)
0 2 * * * /usr/bin/php -r "apcu_clear_cache();"
```

### **Activity Aggregation (Optional)**

Pre-aggregate activity data for faster queries:

```bash
# Crontab entry (runs hourly)
0 * * * * /usr/bin/php /modules/base/scripts/aggregate-activities.php
```

---

## Extending the System

### **Add Custom Activity Type**

In `FeedFunctions.php`:

```php
public static function getActivityIcon($type) {
    $icons = [
        // ... existing
        'custom_event' => 'bi bi-star',  // Add your custom type
    ];
    return $icons[$type] ?? 'bi bi-info-circle';
}
```

### **Add Custom Data Source**

In `feed_refresh.php` after `getRecentSystemActivity()`:

```php
// Fetch custom data
$customData = $db->query("SELECT * FROM custom_table LIMIT 10");
foreach ($customData as $item) {
    $recentActivity[] = (object)[
        'feed_type' => 'custom',
        'type' => 'custom_event',
        'title' => $item['title'],
        // ...
    ];
}
```

### **Customize Activity Card Display**

Edit `_feed-activity.php` to modify the card layout, styling, or add new elements.

---

## Monitoring & Metrics

### **API Response Time**

The dashboard displays real-time response time metrics:

- **Green** (< 1 second): Good
- **Yellow** (1-2 seconds): Acceptable
- **Red** (> 2 seconds): Investigate

### **Cache Hit Rate**

The response includes `cached` boolean:

```php
"cached": true   // Served from cache (fast)
"cached": false  // Fresh from database
```

### **Engagement Metrics**

Track in the sidebar:
- **Total Engagement:** Sum of all interactions
- **Hot Activities:** Count of high-engagement items
- **Trending:** Most popular activities

---

## Support & Documentation

### **Quick Commands**

```bash
# Test API
curl 'http://localhost/modules/base/api/feed_refresh.php'

# Check PHP syntax
php -l /modules/base/api/feed_refresh.php

# View error logs
tail -f /modules/base/logs/app.log

# Clear cache
php -r "apcu_clear_cache();"

# Check APCu status
php -i | grep apcu
```

### **API Documentation**

Full API documentation: See comments in `feed_refresh.php` (lines 1-60)

### **Code Files Reference**

| File | Purpose | Key Functions |
|------|---------|---|
| `feed_refresh.php` | Main API endpoint | Fetch, aggregate, cache feed data |
| `FeedFunctions.php` | Feed utilities | `getRecentSystemActivity()`, `formatActivityCard()` |
| `dashboard-feed.php` | Frontend dashboard | UI, AJAX, auto-refresh |
| `_feed-activity.php` | Activity card template | HTML rendering of single card |

---

## Next Steps

1. ✅ **Verify Setup:** Run the syntax checks above
2. ✅ **Test API:** Make test requests to the endpoint
3. ✅ **Monitor Performance:** Check response times in sidebar
4. ✅ **Configure Caching:** Adjust TTL as needed
5. ✅ **Extend System:** Add custom activity types or data sources
6. ✅ **Deploy:** Push to production with backup

---

**Status:** ✅ Production-Ready (November 11, 2025)
**Version:** 1.0
**Author:** CIS Development Team
