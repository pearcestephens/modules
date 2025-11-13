#!/bin/bash
# News Aggregator Quick Check Script

echo "🔍 NEWS AGGREGATOR VERIFICATION"
echo "================================"
echo ""

# Check if database credentials are set
if [ -z "$DB_USER" ]; then
    echo "⚠️  Database credentials not set in environment"
    echo "💡 Using default: root"
    DB_USER="root"
fi

if [ -z "$DB_NAME" ]; then
    echo "⚠️  Database name not set in environment"
    echo "💡 Checking for database name in config.php..."
    # Try to extract from config
    DB_NAME=$(grep -r "mysqli_connect\|mysqli_select_db" /home/master/applications/jcepnzzkmj/public_html/assets/functions/config.php | grep -oP "[\'\"][a-zA-Z0-9_]+[\'\"]" | tail -1 | tr -d "'\"")
fi

echo ""
echo "📊 CHECKING NEWS TABLES"
echo "======================="

mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} << EOF
SELECT
    'news_sources' as table_name,
    COUNT(*) as row_count,
    SUM(is_active = 1) as active_sources
FROM news_sources
UNION ALL
SELECT
    'news_articles' as table_name,
    COUNT(*) as row_count,
    COUNT(DISTINCT source_id) as unique_sources
FROM news_articles
UNION ALL
SELECT
    'news_feed_items' as table_name,
    COUNT(*) as row_count,
    NULL as extra
FROM news_feed_items
UNION ALL
SELECT
    'news_crawl_log' as table_name,
    COUNT(*) as row_count,
    COUNT(DISTINCT source_id) as crawled_sources
FROM news_crawl_log;
EOF

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ NEWS TABLES EXIST"
else
    echo ""
    echo "❌ NEWS TABLES NOT FOUND"
    echo ""
    echo "🔧 TO FIX: Run installer"
    echo "   cd /home/master/applications/jcepnzzkmj/public_html/modules/news-aggregator"
    echo "   php installer.php"
    exit 1
fi

echo ""
echo "📰 RECENT ARTICLES (Last 5)"
echo "==========================="

mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} << EOF
SELECT
    na.title,
    ns.name as source,
    na.published_at,
    na.view_count + na.click_count as engagement
FROM news_articles na
LEFT JOIN news_sources ns ON na.source_id = ns.id
ORDER BY na.published_at DESC
LIMIT 5;
EOF

echo ""
echo "🕷️  LAST CRAWL"
echo "=============="

mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} << EOF
SELECT
    ns.name as source,
    ncl.articles_found,
    ncl.created_at,
    CASE
        WHEN ncl.articles_found > 0 THEN '✅'
        ELSE '⚠️ '
    END as status
FROM news_crawl_log ncl
LEFT JOIN news_sources ns ON ncl.source_id = ns.id
ORDER BY ncl.created_at DESC
LIMIT 5;
EOF

echo ""
echo "🎯 SUMMARY"
echo "=========="
echo ""

# Count sources
SOURCE_COUNT=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -sN -e "SELECT COUNT(*) FROM news_sources WHERE is_active = 1")
ARTICLE_COUNT=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -sN -e "SELECT COUNT(*) FROM news_articles WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")

echo "📡 Active News Sources: ${SOURCE_COUNT}"
echo "📰 Articles (Last 7 Days): ${ARTICLE_COUNT}"

if [ ${SOURCE_COUNT} -eq 0 ]; then
    echo ""
    echo "⚠️  NO NEWS SOURCES CONFIGURED"
    echo ""
    echo "💡 TO ADD SOURCES:"
    echo "   See: /modules/news-aggregator/SETUP_GUIDE.md"
    echo "   Quick: INSERT INTO news_sources (name, url, type, category, crawl_frequency, is_active, created_at)"
    echo "          VALUES ('Vaping Post', 'https://www.vapingpost.com/feed/', 'rss', 'vaping', 3600, 1, NOW());"
fi

if [ ${ARTICLE_COUNT} -eq 0 ]; then
    echo ""
    echo "⚠️  NO RECENT ARTICLES"
    echo ""
    echo "💡 TO CRAWL NOW:"
    echo "   cd /home/master/applications/jcepnzzkmj/public_html/modules/news-aggregator"
    echo "   php cron-crawler.php"
fi

echo ""
echo "✅ VERIFICATION COMPLETE"
echo ""
