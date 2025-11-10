#!/bin/bash
# Competitive Intelligence System - Quick Install
# Run: bash install-competitive-intel.sh

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║  COMPETITIVE INTELLIGENCE SYSTEM - INSTALLATION               ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Set paths
BASE_PATH="/home/129337.cloudwaysapps.com/hdgwrzntwa/public_html"
PRIVATE_PATH="/home/129337.cloudwaysapps.com/hdgwrzntwa/private_html"

echo "✓ Base path: $BASE_PATH"
echo "✓ Private path: $PRIVATE_PATH"
echo ""

# Check Chrome installation
echo "Checking Chrome installation..."
if command -v google-chrome &> /dev/null; then
    echo "✓ Chrome installed: $(google-chrome --version)"
elif command -v chromium-browser &> /dev/null; then
    echo "✓ Chromium installed: $(chromium-browser --version)"
else
    echo "⚠️  Chrome/Chromium NOT found!"
    echo "   Install with: sudo apt-get install -y google-chrome-stable"
    echo "   OR: sudo apt-get install -y chromium-browser"
fi
echo ""

# Create directories
echo "Creating directories..."
mkdir -p "$PRIVATE_PATH/logs/crawlers"
mkdir -p "$PRIVATE_PATH/chrome-profiles"
chmod 755 "$PRIVATE_PATH/logs/crawlers"
chmod 755 "$PRIVATE_PATH/chrome-profiles"
echo "✓ Log directory: $PRIVATE_PATH/logs/crawlers"
echo "✓ Profile directory: $PRIVATE_PATH/chrome-profiles"
echo ""

# Set permissions
echo "Setting permissions..."
chmod +x "$BASE_PATH/assets/services/crawlers/cron-competitive.php"
echo "✓ Cron script executable"
echo ""

# Database check
echo "Checking database tables..."
TABLES=$(mysql -u hdgwrzntwa -p'bFUdRjh4Jx' hdgwrzntwa -se "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'hdgwrzntwa' AND TABLE_NAME IN ('crawler_sessions', 'crawler_logs', 'competitive_prices', 'competitive_specials', 'chrome_sessions', 'dynamic_pricing_recommendations', 'crawler_metrics')")

if [ "$TABLES" -eq 7 ]; then
    echo "✓ All 7 tables exist"
else
    echo "⚠️  Found $TABLES/7 tables. Running schema install..."
    mysql -u hdgwrzntwa -p'bFUdRjh4Jx' hdgwrzntwa < "$BASE_PATH/assets/services/crawlers/schema.sql"
    echo "✓ Schema installed"
fi
echo ""

# Cron check
echo "Checking cron job..."
if crontab -l 2>/dev/null | grep -q "cron-competitive.php"; then
    echo "✓ Cron job already installed"
else
    echo "⚠️  Cron job NOT installed"
    echo ""
    echo "Add to crontab manually:"
    echo "   crontab -e"
    echo ""
    echo "   # Competitive Intelligence - Daily at 2:00 AM NZT"
    echo "   0 2 * * * /usr/bin/php $BASE_PATH/assets/services/crawlers/cron-competitive.php >> $PRIVATE_PATH/logs/crawlers/cron.log 2>&1"
fi
echo ""

# File verification
echo "Verifying files..."
FILES=(
    "modules/competitive-intel/admin.php"
    "modules/competitive-intel/price-monitor.php"
    "modules/competitive-intel/dynamic-pricing.php"
    "modules/competitive-intel/crawler-logs.php"
    "assets/services/crawlers/CentralLogger.php"
    "assets/services/crawlers/CompetitiveIntelCrawler.php"
    "assets/services/crawlers/ChromeSessionManager.php"
    "assets/services/crawlers/DynamicPricingEngine.php"
    "assets/services/crawlers/cron-competitive.php"
)

MISSING=0
for file in "${FILES[@]}"; do
    if [ -f "$BASE_PATH/$file" ]; then
        echo "✓ $file"
    else
        echo "✗ $file (MISSING)"
        MISSING=$((MISSING+1))
    fi
done
echo ""

# Summary
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║  INSTALLATION SUMMARY                                         ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "Files: $((9-MISSING))/9 installed"
echo "Tables: $TABLES/7 created"
echo ""

if [ $MISSING -eq 0 ] && [ "$TABLES" -eq 7 ]; then
    echo "✅ INSTALLATION COMPLETE!"
    echo ""
    echo "🌐 Access dashboards:"
    echo "   https://staff.vapeshed.co.nz/modules/competitive-intel/admin.php"
    echo ""
    echo "🧪 Test crawler:"
    echo "   php $BASE_PATH/assets/services/crawlers/cron-competitive.php"
else
    echo "⚠️  INSTALLATION INCOMPLETE"
    echo "   Missing files: $MISSING"
    echo "   Missing tables: $((7-TABLES))"
fi
echo ""
