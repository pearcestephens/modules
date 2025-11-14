#!/bin/bash
###############################################################################
# FLAGGED PRODUCTS MODULE - COMPREHENSIVE TEST SUITE
# Tests all endpoints, files, and generates detailed report
###############################################################################

echo "════════════════════════════════════════════════════════════════════════"
echo "  FLAGGED PRODUCTS MODULE - COMPREHENSIVE TEST SUITE"
echo "════════════════════════════════════════════════════════════════════════"
echo ""
date
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

PASS=0
FAIL=0
WARN=0

MODULE_PATH="/home/master/applications/jcepnzzkmj/public_html/modules/flagged_products"
cd "$MODULE_PATH" || exit 1

echo "📂 Module Path: $MODULE_PATH"
echo ""

# ===========================================================================
# 1. PHP SYNTAX CHECK
# ===========================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "1️⃣  PHP SYNTAX VALIDATION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

PHP_FILES=$(find . -name "*.php" -type f)
PHP_COUNT=$(echo "$PHP_FILES" | wc -l)
echo "Found $PHP_COUNT PHP files to check..."
echo ""

while IFS= read -r file; do
    if php -l "$file" > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC} $file"
        ((PASS++))
    else
        echo -e "${RED}✗${NC} $file"
        php -l "$file" 2>&1 | grep -v "No syntax errors"
        ((FAIL++))
    fi
done <<< "$PHP_FILES"

echo ""
echo "PHP Syntax: $PASS passed, $FAIL failed"
echo ""

# ===========================================================================
# 2. FILE STRUCTURE VALIDATION
# ===========================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "2️⃣  FILE STRUCTURE VALIDATION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

REQUIRED_FILES=(
    "index.php"
    "bootstrap.php"
    "controllers/FlaggedProductController.php"
    "views/index.php"
    "views/outlet.php"
    "views/cron-dashboard.php"
    "models/FlaggedProductModel.php"
    "config/module.php"
    "cron/bootstrap.php"
    "cron/FlaggedProductsCronWrapper.php"
    "database/cron_metrics_schema.sql"
)

for file in "${REQUIRED_FILES[@]}"; do
    if [[ -f "$MODULE_PATH/$file" ]]; then
        echo -e "${GREEN}✓${NC} $file exists"
        ((PASS++))
    else
        echo -e "${RED}✗${NC} $file MISSING"
        ((FAIL++))
    fi
done

echo ""

REQUIRED_DIRS=(
    "api"
    "controllers"
    "views"
    "models"
    "config"
    "cron"
    "database"
    "lib"
    "assets"
    "logs"
)

for dir in "${REQUIRED_DIRS[@]}"; do
    if [[ -d "$MODULE_PATH/$dir" ]]; then
        echo -e "${GREEN}✓${NC} Directory: $dir/"
        ((PASS++))
    else
        echo -e "${YELLOW}⚠${NC} Directory missing: $dir/ (may be optional)"
        ((WARN++))
    fi
done

echo ""

# ===========================================================================
# 3. DATABASE CONNECTION TEST
# ===========================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "3️⃣  DATABASE CONNECTION & TABLE VALIDATION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

DB_USER="jcepnzzkmj"
DB_PASS="wprKh9Jq63"
DB_NAME="jcepnzzkmj"

# Check if database tables exist
TABLES=(
    "flagged_products_cron_metrics"
    "vend_outlets"
)

for table in "${TABLES[@]}"; do
    if mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SHOW TABLES LIKE '$table'" 2>/dev/null | grep -q "$table"; then
        COUNT=$(mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -se "SELECT COUNT(*) FROM $table" 2>/dev/null)
        echo -e "${GREEN}✓${NC} Table: $table ($COUNT rows)"
        ((PASS++))
    else
        echo -e "${RED}✗${NC} Table MISSING: $table"
        ((FAIL++))
    fi
done

echo ""

# Check if views exist
VIEWS=(
    "vw_flagged_products_cron_performance"
    "vw_flagged_products_cron_daily_trends"
    "vw_flagged_products_cron_health"
)

for view in "${VIEWS[@]}"; do
    if mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SHOW FULL TABLES WHERE Table_type = 'VIEW' AND Tables_in_$DB_NAME = '$view'" 2>/dev/null | grep -q "$view"; then
        echo -e "${GREEN}✓${NC} View: $view"
        ((PASS++))
    else
        echo -e "${YELLOW}⚠${NC} View not found: $view (may need creation)"
        ((WARN++))
    fi
done

echo ""

# ===========================================================================
# 4. CRON JOB FILE VALIDATION
# ===========================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "4️⃣  CRON JOB FILES VALIDATION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

CRON_FILES=(
    "cron/generate_daily_products_wrapped.php"
    "cron/refresh_leaderboard_wrapped.php"
    "cron/generate_ai_insights_wrapped.php"
    "cron/check_achievements_wrapped.php"
    "cron/refresh_store_stats_wrapped.php"
)

for cron_file in "${CRON_FILES[@]}"; do
    if [[ -f "$MODULE_PATH/$cron_file" ]]; then
        if [[ -x "$MODULE_PATH/$cron_file" ]]; then
            echo -e "${GREEN}✓${NC} $cron_file (executable)"
            ((PASS++))
        else
            echo -e "${YELLOW}⚠${NC} $cron_file (NOT executable)"
            ((WARN++))
        fi
    else
        echo -e "${RED}✗${NC} $cron_file MISSING"
        ((FAIL++))
    fi
done

echo ""

# Check if cron jobs are in crontab
echo "Checking system crontab..."
if crontab -l 2>/dev/null | grep -q "flagged_products"; then
    COUNT=$(crontab -l 2>/dev/null | grep "flagged_products" | grep -v "^#" | wc -l)
    echo -e "${GREEN}✓${NC} Found $COUNT flagged_products cron entries"
    ((PASS++))
else
    echo -e "${YELLOW}⚠${NC} No flagged_products cron jobs found in crontab"
    ((WARN++))
fi

echo ""

# ===========================================================================
# 5. ENDPOINT ACCESSIBILITY TEST
# ===========================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "5️⃣  ENDPOINT ACCESSIBILITY TEST"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

BASE_URL="https://staff.vapeshed.co.nz/modules/flagged_products"

ENDPOINTS=(
    "/?action=index"
    "/?action=cron-dashboard"
    "/?action=cron"
)

echo "Testing endpoints (HTTP HEAD requests)..."
echo ""

for endpoint in "${ENDPOINTS[@]}"; do
    URL="$BASE_URL$endpoint"
    HTTP_CODE=$(curl -k -s -o /dev/null -w "%{http_code}" --max-time 10 "$URL" 2>/dev/null)

    if [[ "$HTTP_CODE" == "200" ]]; then
        echo -e "${GREEN}✓${NC} $endpoint (HTTP $HTTP_CODE)"
        ((PASS++))
    elif [[ "$HTTP_CODE" == "302" ]] || [[ "$HTTP_CODE" == "301" ]]; then
        echo -e "${YELLOW}⚠${NC} $endpoint (HTTP $HTTP_CODE - Redirect)"
        ((WARN++))
    elif [[ -z "$HTTP_CODE" ]]; then
        echo -e "${YELLOW}⚠${NC} $endpoint (Connection timeout or network error)"
        ((WARN++))
    else
        echo -e "${RED}✗${NC} $endpoint (HTTP $HTTP_CODE)"
        ((FAIL++))
    fi
done

echo ""

# ===========================================================================
# 6. LOG DIRECTORY CHECK
# ===========================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "6️⃣  LOG DIRECTORY & PERMISSIONS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

LOG_DIR="$MODULE_PATH/logs"

if [[ -d "$LOG_DIR" ]]; then
    if [[ -w "$LOG_DIR" ]]; then
        LOG_COUNT=$(find "$LOG_DIR" -name "*.log" 2>/dev/null | wc -l)
        echo -e "${GREEN}✓${NC} Logs directory exists and is writable ($LOG_COUNT log files)"
        ((PASS++))
    else
        echo -e "${RED}✗${NC} Logs directory is NOT writable"
        ((FAIL++))
    fi
else
    echo -e "${YELLOW}⚠${NC} Logs directory does not exist"
    ((WARN++))
fi

echo ""

# ===========================================================================
# 7. RECENT CRON EXECUTION CHECK
# ===========================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "7️⃣  RECENT CRON EXECUTIONS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

RECENT_RUNS=$(mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -se "
SELECT COUNT(*) FROM flagged_products_cron_metrics
WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
" 2>/dev/null)

if [[ -n "$RECENT_RUNS" ]] && [[ "$RECENT_RUNS" -gt 0 ]]; then
    echo -e "${GREEN}✓${NC} Found $RECENT_RUNS cron executions in last 24 hours"
    ((PASS++))

    echo ""
    echo "Recent execution summary:"
    mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -t -e "
    SELECT
        task_name,
        COUNT(*) as runs,
        SUM(success) as successes,
        COUNT(*) - SUM(success) as failures
    FROM flagged_products_cron_metrics
    WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY task_name
    " 2>/dev/null
else
    echo -e "${YELLOW}⚠${NC} No cron executions found in last 24 hours (may not have run yet)"
    ((WARN++))
fi

echo ""

# ===========================================================================
# 8. DOCUMENTATION CHECK
# ===========================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "8️⃣  DOCUMENTATION FILES"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

DOC_FILES=(
    "README.md"
    "README_CRON_V2.md"
    "ACTIVATION_COMPLETE.md"
    "DEPLOYMENT_STATUS.md"
    "SMART_CRON_CONTROL_PANEL_GUIDE.md"
    "CRON_DASHBOARD_READY.md"
)

for doc in "${DOC_FILES[@]}"; do
    if [[ -f "$MODULE_PATH/$doc" ]]; then
        SIZE=$(du -h "$MODULE_PATH/$doc" | cut -f1)
        echo -e "${GREEN}✓${NC} $doc ($SIZE)"
        ((PASS++))
    else
        echo -e "${YELLOW}⚠${NC} $doc not found (optional)"
        ((WARN++))
    fi
done

echo ""

# ===========================================================================
# SUMMARY
# ===========================================================================
echo "════════════════════════════════════════════════════════════════════════"
echo "  TEST SUMMARY"
echo "════════════════════════════════════════════════════════════════════════"
echo ""

TOTAL=$((PASS + FAIL + WARN))
PASS_PERCENT=$(awk "BEGIN {printf \"%.1f\", ($PASS / $TOTAL) * 100}")

echo -e "${GREEN}✓ PASSED:${NC}  $PASS"
echo -e "${RED}✗ FAILED:${NC}  $FAIL"
echo -e "${YELLOW}⚠ WARNINGS:${NC} $WARN"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "TOTAL TESTS: $TOTAL"
echo "SUCCESS RATE: ${PASS_PERCENT}%"
echo ""

if [[ $FAIL -eq 0 ]]; then
    echo -e "${GREEN}🎉 ALL CRITICAL TESTS PASSED!${NC}"
    EXIT_CODE=0
elif [[ $FAIL -le 3 ]]; then
    echo -e "${YELLOW}⚠️  MINOR ISSUES DETECTED (review warnings)${NC}"
    EXIT_CODE=1
else
    echo -e "${RED}❌ CRITICAL FAILURES DETECTED (review immediately)${NC}"
    EXIT_CODE=2
fi

echo ""
echo "════════════════════════════════════════════════════════════════════════"
echo ""

# Generate detailed report file
REPORT_FILE="$MODULE_PATH/TEST_REPORT_$(date +%Y%m%d_%H%M%S).txt"
{
    echo "FLAGGED PRODUCTS MODULE - TEST REPORT"
    echo "Generated: $(date)"
    echo ""
    echo "SUMMARY:"
    echo "  Passed: $PASS"
    echo "  Failed: $FAIL"
    echo "  Warnings: $WARN"
    echo "  Success Rate: ${PASS_PERCENT}%"
    echo ""
    echo "For detailed results, review the test output above."
} > "$REPORT_FILE"

echo "📄 Detailed report saved: $REPORT_FILE"
echo ""

exit $EXIT_CODE
