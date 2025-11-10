#!/bin/bash
#
# CIS Modules - Verification Script
# Verifies all files and database tables are present
#

echo "=========================================="
echo "CIS MODULES VERIFICATION SCRIPT"
echo "Date: $(date)"
echo "=========================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

ERRORS=0
WARNINGS=0
SUCCESS=0

# Function to check file exists
check_file() {
    if [ -f "$1" ]; then
        echo -e "${GREEN}✓${NC} Found: $1"
        ((SUCCESS++))
    else
        echo -e "${RED}✗${NC} Missing: $1"
        ((ERRORS++))
    fi
}

# Function to check directory exists
check_dir() {
    if [ -d "$1" ]; then
        echo -e "${GREEN}✓${NC} Directory: $1"
        ((SUCCESS++))
    else
        echo -e "${RED}✗${NC} Missing directory: $1"
        ((ERRORS++))
    fi
}

echo "1. CHECKING DIRECTORY STRUCTURE..."
echo "-----------------------------------"
check_dir "CIS_MODULES"
check_dir "CIS_MODULES/stock_transfer_engine"
check_dir "CIS_MODULES/stock_transfer_engine/services"
check_dir "CIS_MODULES/stock_transfer_engine/config"
check_dir "CIS_MODULES/stock_transfer_engine/database"
check_dir "CIS_MODULES/human_behavior_engine"
check_dir "CIS_MODULES/crawlers"
check_dir "CIS_MODULES/dynamic_pricing"
check_dir "CIS_MODULES/ai_intelligence"
check_dir "CIS_MODULES/content_aggregation"
check_dir "CIS_MODULES/social_feeds"
check_dir "CIS_MODULES/courier_integration"
check_dir "CIS_MODULES/staff_ordering"
echo ""

echo "2. CHECKING STOCK TRANSFER ENGINE FILES..."
echo "-------------------------------------------"
check_file "CIS_MODULES/stock_transfer_engine/services/VendTransferAPI.php"
check_file "CIS_MODULES/stock_transfer_engine/services/WarehouseManager.php"
check_file "CIS_MODULES/stock_transfer_engine/services/ExcessDetectionEngine.php"
check_file "CIS_MODULES/stock_transfer_engine/config/warehouses.php"
check_file "CIS_MODULES/stock_transfer_engine/database/stock_transfer_engine_schema.sql"
check_file "CIS_MODULES/stock_transfer_engine/database/current_database_schema.sql"
echo ""

echo "3. CHECKING HUMAN BEHAVIOR ENGINE FILES..."
echo "-------------------------------------------"
check_file "CIS_MODULES/human_behavior_engine/HumanBehaviorEngine.php"
check_file "CIS_MODULES/human_behavior_engine/test_chaotic_simple.php"
check_file "CIS_MODULES/human_behavior_engine/test_chaotic_boundaries.php"
check_file "CIS_MODULES/human_behavior_engine/test_human_behavior_engine.php"
echo ""

echo "4. CHECKING CRAWLER SYSTEM FILES..."
echo "------------------------------------"
check_file "CIS_MODULES/crawlers/CompetitiveIntelCrawler.php"
check_file "CIS_MODULES/crawlers/CentralLogger.php"
check_file "CIS_MODULES/crawlers/ChromeSessionManager.php"
check_file "CIS_MODULES/crawlers/StubLogger.php"
check_file "CIS_MODULES/crawlers/cron-competitive.php"
check_file "CIS_MODULES/crawlers/CrawlerTool.php"
check_file "CIS_MODULES/crawlers/CrawlerTools.php"
check_file "CIS_MODULES/crawlers/test_crawler_tool.php"
check_file "CIS_MODULES/crawlers/crawler-monitor.php"
check_file "CIS_MODULES/crawlers/crawler-chat.js"
check_file "CIS_MODULES/crawlers/deep-crawler.js"
check_file "CIS_MODULES/crawlers/interactive-crawler.js"
check_file "CIS_MODULES/crawlers/crawl-staff-portal.js"
check_file "CIS_MODULES/crawlers/database_schema.sql"
echo ""

echo "5. CHECKING DYNAMIC PRICING FILES..."
echo "-------------------------------------"
check_file "CIS_MODULES/dynamic_pricing/DynamicPricingEngine.php"
check_file "CIS_MODULES/dynamic_pricing/database_schema.sql"
echo ""

echo "6. CHECKING AI INTELLIGENCE FILES..."
echo "-------------------------------------"
check_file "CIS_MODULES/ai_intelligence/AdvancedIntelligenceEngine.php"
check_file "CIS_MODULES/ai_intelligence/api/IntelligenceAPIClient.php"
check_file "CIS_MODULES/ai_intelligence/api/neural_intelligence_processor.php"
echo ""

echo "7. CHECKING DOCUMENTATION..."
echo "-----------------------------"
check_file "CIS_MODULES/INDEX.md"
check_file "CIS_MODULES/MIGRATION_GUIDE.md"
check_file "CIS_MODULES/VERIFICATION.sh"
echo ""

echo "8. CHECKING PHP SYNTAX..."
echo "-------------------------"
cd CIS_MODULES

# Check stock transfer engine
php -l stock_transfer_engine/services/VendTransferAPI.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} VendTransferAPI.php syntax OK"
    ((SUCCESS++))
else
    echo -e "${RED}✗${NC} VendTransferAPI.php has syntax errors"
    ((ERRORS++))
fi

php -l stock_transfer_engine/services/WarehouseManager.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} WarehouseManager.php syntax OK"
    ((SUCCESS++))
else
    echo -e "${RED}✗${NC} WarehouseManager.php has syntax errors"
    ((ERRORS++))
fi

php -l stock_transfer_engine/services/ExcessDetectionEngine.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} ExcessDetectionEngine.php syntax OK"
    ((SUCCESS++))
else
    echo -e "${RED}✗${NC} ExcessDetectionEngine.php has syntax errors"
    ((ERRORS++))
fi

# Check human behavior
php -l human_behavior_engine/HumanBehaviorEngine.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} HumanBehaviorEngine.php syntax OK"
    ((SUCCESS++))
else
    echo -e "${RED}✗${NC} HumanBehaviorEngine.php has syntax errors"
    ((ERRORS++))
fi

# Check crawlers
php -l crawlers/CompetitiveIntelCrawler.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} CompetitiveIntelCrawler.php syntax OK"
    ((SUCCESS++))
else
    echo -e "${RED}✗${NC} CompetitiveIntelCrawler.php has syntax errors"
    ((ERRORS++))
fi

cd ..
echo ""

echo "9. CHECKING DATABASE TABLES..."
echo "-------------------------------"
# Check if we can connect to database
mysql -u hdgwrzntwa -p'bFUdRjh4Jx' hdgwrzntwa -e "SELECT 1" > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Database connection OK"
    ((SUCCESS++))

    # Check stock transfer tables
    TABLES=("stock_transfers" "stock_transfer_items" "excess_stock_alerts" "stock_velocity_tracking"
            "freight_costs" "outlet_freight_zones" "transfer_routes" "transfer_boxes"
            "transfer_rejections" "transfer_tracking_events")

    for table in "${TABLES[@]}"; do
        mysql -u hdgwrzntwa -p'bFUdRjh4Jx' hdgwrzntwa -e "DESCRIBE $table" > /dev/null 2>&1
        if [ $? -eq 0 ]; then
            echo -e "${GREEN}✓${NC} Table exists: $table"
            ((SUCCESS++))
        else
            echo -e "${YELLOW}!${NC} Table missing: $table (may need migration)"
            ((WARNINGS++))
        fi
    done

    # Check crawler tables
    CRAWLER_TABLES=("crawler_logs" "crawler_metrics" "crawler_sessions")
    for table in "${CRAWLER_TABLES[@]}"; do
        mysql -u hdgwrzntwa -p'bFUdRjh4Jx' hdgwrzntwa -e "DESCRIBE $table" > /dev/null 2>&1
        if [ $? -eq 0 ]; then
            echo -e "${GREEN}✓${NC} Table exists: $table"
            ((SUCCESS++))
        else
            echo -e "${YELLOW}!${NC} Table missing: $table (may need migration)"
            ((WARNINGS++))
        fi
    done

    # Check pricing table
    mysql -u hdgwrzntwa -p'bFUdRjh4Jx' hdgwrzntwa -e "DESCRIBE dynamic_pricing_recommendations" > /dev/null 2>&1
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓${NC} Table exists: dynamic_pricing_recommendations"
        ((SUCCESS++))
    else
        echo -e "${YELLOW}!${NC} Table missing: dynamic_pricing_recommendations (may need migration)"
        ((WARNINGS++))
    fi
else
    echo -e "${RED}✗${NC} Cannot connect to database"
    ((ERRORS++))
fi
echo ""

echo "10. FILE SIZE AND LINE COUNT SUMMARY..."
echo "----------------------------------------"
echo "Total PHP files:"
find CIS_MODULES -name "*.php" -type f | wc -l
echo ""
echo "Total JavaScript files:"
find CIS_MODULES -name "*.js" -type f | wc -l
echo ""
echo "Total SQL files:"
find CIS_MODULES -name "*.sql" -type f | wc -l
echo ""
echo "Total lines of PHP code:"
find CIS_MODULES -name "*.php" -type f -exec wc -l {} + | tail -1
echo ""
echo "Total lines of JavaScript code:"
find CIS_MODULES -name "*.js" -type f -exec wc -l {} + | tail -1
echo ""
echo "Total directory size:"
du -sh CIS_MODULES
echo ""

echo "=========================================="
echo "VERIFICATION SUMMARY"
echo "=========================================="
echo -e "${GREEN}Success:${NC} $SUCCESS checks passed"
echo -e "${YELLOW}Warnings:${NC} $WARNINGS items need attention"
echo -e "${RED}Errors:${NC} $ERRORS critical issues"
echo ""

if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}✓ ALL CRITICAL CHECKS PASSED${NC}"
    echo "Ready for migration to CIS Staff Portal!"
    exit 0
else
    echo -e "${RED}✗ CRITICAL ISSUES FOUND${NC}"
    echo "Please fix errors before migration."
    exit 1
fi
