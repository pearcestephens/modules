#!/bin/bash
# =============================================================================
# CIS News Aggregator - Installation Script
# =============================================================================

echo "╔═══════════════════════════════════════════════════════════╗"
echo "║                                                           ║"
echo "║   📰 CIS News Aggregator Installation                    ║"
echo "║                                                           ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""

# Configuration
DB_NAME="hdgwrzntwa"
DB_USER="hdgwrzntwa"
DB_PASS="bFUdRjh4Jx"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "📍 Script location: $SCRIPT_DIR"
echo ""

# Step 1: Create database tables
echo "▶ Step 1: Creating database tables..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SCRIPT_DIR/schema.sql"

if [ $? -eq 0 ]; then
    echo "✅ Database tables created successfully"
else
    echo "❌ Failed to create database tables"
    exit 1
fi
echo ""

# Step 2: Create image cache directory
echo "▶ Step 2: Creating image cache directory..."
IMAGE_DIR="/home/129337.cloudwaysapps.com/hdgwrzntwa/public_html/uploads/news-images"
mkdir -p "$IMAGE_DIR"
chmod 755 "$IMAGE_DIR"

if [ -d "$IMAGE_DIR" ]; then
    echo "✅ Image cache directory created: $IMAGE_DIR"
else
    echo "⚠️  Warning: Could not create image directory"
fi
echo ""

# Step 3: Make cron script executable
echo "▶ Step 3: Setting up cron script..."
chmod +x "$SCRIPT_DIR/cron-crawler.php"

if [ -x "$SCRIPT_DIR/cron-crawler.php" ]; then
    echo "✅ Cron script is executable"
else
    echo "❌ Failed to make cron script executable"
    exit 1
fi
echo ""

# Step 4: Test database connection
echo "▶ Step 4: Testing database connection..."
TEST_RESULT=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT COUNT(*) FROM news_sources;" 2>&1)

if [ $? -eq 0 ]; then
    echo "✅ Database connection successful"
    SOURCE_COUNT=$(echo "$TEST_RESULT" | tail -n 1)
    echo "   📊 News sources in database: $SOURCE_COUNT"
else
    echo "❌ Database connection failed"
    echo "   Error: $TEST_RESULT"
    exit 1
fi
echo ""

# Step 5: Display cron setup instructions
echo "▶ Step 5: Cron Job Setup"
echo ""
echo "Add this to your crontab to run crawls automatically:"
echo ""
echo "   # Crawl every hour"
echo "   0 * * * * /usr/bin/php $SCRIPT_DIR/cron-crawler.php >> /var/log/cis-news-crawler.log 2>&1"
echo ""
echo "   # Crawl every 30 minutes (recommended)"
echo "   */30 * * * * /usr/bin/php $SCRIPT_DIR/cron-crawler.php >> /var/log/cis-news-crawler.log 2>&1"
echo ""
echo "To add to crontab:"
echo "   1. Run: crontab -e"
echo "   2. Add one of the above lines"
echo "   3. Save and exit"
echo ""

# Step 6: Display admin access info
echo "▶ Step 6: Admin Panel Access"
echo ""
echo "Admin dashboard URL:"
echo "   https://staff.vapeshed.co.nz/cis-themes/modules/news-aggregator/admin.php"
echo ""

# Step 7: Run test crawl
echo "▶ Step 7: Running test crawl..."
echo "Would you like to run a test crawl now? (y/n)"
read -r RESPONSE

if [ "$RESPONSE" = "y" ] || [ "$RESPONSE" = "Y" ]; then
    echo ""
    echo "🚀 Starting test crawl..."
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    /usr/bin/php "$SCRIPT_DIR/cron-crawler.php"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
else
    echo "⏭️  Skipping test crawl"
fi
echo ""

# Final summary
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║                                                           ║"
echo "║   ✅ Installation Complete!                              ║"
echo "║                                                           ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""
echo "📋 Next Steps:"
echo ""
echo "1. Set up cron job (see instructions above)"
echo "2. Access admin panel to configure sources"
echo "3. Moderate incoming content"
echo "4. Integrate with Facebook Feed layout"
echo ""
echo "📚 Documentation:"
echo "   $SCRIPT_DIR/README.md"
echo ""
echo "🎉 Done!"
