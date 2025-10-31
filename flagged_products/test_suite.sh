#!/bin/bash
###############################################################################
# Flagged Products v2.0 - Automated Test Suite
#
# Tests all components and generates report
# Usage: ./test_suite.sh
###############################################################################

echo "🧪 Flagged Products v2.0 - Automated Test Suite"
echo "================================================"
echo ""

# Configuration
BASE_URL="https://staff.vapeshed.co.nz"
TEST_OUTLET="02dcd191-ae2b-11e6-f485-8eceed6eeafb"  # Hamilton East
DOC_ROOT="/home/master/applications/jcepnzzkmj/public_html"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counters
PASSED=0
FAILED=0
WARNINGS=0

# Helper functions
pass() {
    echo -e "${GREEN}✅ PASS${NC} - $1"
    ((PASSED++))
}

fail() {
    echo -e "${RED}❌ FAIL${NC} - $1"
    ((FAILED++))
}

warn() {
    echo -e "${YELLOW}⚠️  WARN${NC} - $1"
    ((WARNINGS++))
}

test_section() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "📋 $1"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
}

###############################################################################
# Test 1: Database Tables
###############################################################################
test_section "Database Tables"

echo "Checking required tables exist..."

tables=(
    "flagged_products"
    "flagged_products_completion_attempts"
    "flagged_products_user_stats"
    "flagged_products_violations"
    "flagged_products_achievements"
    "flagged_products_ai_insights"
)

for table in "${tables[@]}"; do
    result=$(mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "SHOW TABLES LIKE '$table';" -sN 2>/dev/null)
    if [ "$result" == "$table" ]; then
        pass "Table '$table' exists"
    else
        fail "Table '$table' missing"
    fi
done

###############################################################################
# Test 2: File Structure
###############################################################################
test_section "File Structure"

echo "Checking required files exist..."

files=(
    "$DOC_ROOT/flagged-products-v2.php"
    "$DOC_ROOT/modules/flagged_products/bootstrap.php"
    "$DOC_ROOT/modules/flagged_products/models/FlaggedProductsRepository.php"
    "$DOC_ROOT/modules/flagged_products/lib/AntiCheat.php"
    "$DOC_ROOT/modules/flagged_products/api/complete-product.php"
    "$DOC_ROOT/modules/flagged_products/assets/css/flagged-products.css"
    "$DOC_ROOT/modules/flagged_products/assets/js/anti-cheat.js"
    "$DOC_ROOT/modules/flagged_products/views/summary.php"
    "$DOC_ROOT/modules/flagged_products/views/dashboard.php"
    "$DOC_ROOT/modules/flagged_products/views/leaderboard.php"
    "$DOC_ROOT/modules/flagged_products/cron/refresh_leaderboard.php"
    "$DOC_ROOT/modules/flagged_products/cron/generate_ai_insights.php"
    "$DOC_ROOT/modules/flagged_products/cron/check_achievements.php"
    "$DOC_ROOT/modules/flagged_products/cron/refresh_store_stats.php"
)

for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        pass "File exists: $(basename $file)"
    else
        fail "File missing: $file"
    fi
done

###############################################################################
# Test 3: PHP Syntax
###############################################################################
test_section "PHP Syntax Check"

echo "Checking PHP files for syntax errors..."

php_files=$(find "$DOC_ROOT/modules/flagged_products" -name "*.php")
syntax_errors=0

for file in $php_files; do
    if php -l "$file" > /dev/null 2>&1; then
        # Silent pass
        :
    else
        fail "Syntax error in: $(basename $file)"
        ((syntax_errors++))
    fi
done

if [ $syntax_errors -eq 0 ]; then
    pass "All PHP files have valid syntax"
else
    fail "Found $syntax_errors syntax errors"
fi

###############################################################################
# Test 4: HTTP Endpoints
###############################################################################
test_section "HTTP Endpoints"

echo "Testing URL accessibility..."

urls=(
    "$BASE_URL/flagged-products-v2.php?outlet_id=$TEST_OUTLET&bypass_security=1&bot=1"
    "$BASE_URL/modules/flagged_products/views/summary.php?outlet_id=$TEST_OUTLET"
    "$BASE_URL/modules/flagged_products/views/dashboard.php"
    "$BASE_URL/modules/flagged_products/views/leaderboard.php"
)

for url in "${urls[@]}"; do
    http_code=$(curl -s -o /dev/null -w "%{http_code}" "$url" --max-time 10 2>/dev/null)
    
    if [ "$http_code" == "200" ]; then
        pass "URL accessible: $(basename $url .php)"
    elif [ "$http_code" == "302" ]; then
        warn "URL redirects (login required): $(basename $url .php)"
    else
        fail "URL returned $http_code: $(basename $url .php)"
    fi
done

###############################################################################
# Test 5: Smart-Cron Tasks
###############################################################################
test_section "Smart-Cron Tasks"

echo "Checking Smart-Cron task registration..."

tasks=(
    "flagged_products_refresh_leaderboard"
    "flagged_products_generate_ai_insights"
    "flagged_products_check_achievements"
    "flagged_products_refresh_store_stats"
)

for task in "${tasks[@]}"; do
    result=$(mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "SELECT enabled FROM smart_cron_tasks_config WHERE task_name='$task';" -sN 2>/dev/null)
    
    if [ "$result" == "1" ]; then
        pass "Task registered and enabled: $task"
    elif [ "$result" == "0" ]; then
        warn "Task registered but disabled: $task"
    else
        fail "Task not registered: $task"
    fi
done

###############################################################################
# Test 6: Cron Script Execution
###############################################################################
test_section "Cron Script Execution"

echo "Testing cron scripts run without errors..."

cron_scripts=(
    "$DOC_ROOT/modules/flagged_products/cron/refresh_leaderboard.php"
    "$DOC_ROOT/modules/flagged_products/cron/check_achievements.php"
    "$DOC_ROOT/modules/flagged_products/cron/refresh_store_stats.php"
)

for script in "${cron_scripts[@]}"; do
    output=$(php "$script" 2>&1)
    
    if echo "$output" | grep -q '"success":true'; then
        pass "Cron script runs successfully: $(basename $script)"
    else
        fail "Cron script error: $(basename $script)"
        echo "   Output: $output"
    fi
done

###############################################################################
# Test 7: JavaScript Assets
###############################################################################
test_section "JavaScript Assets"

echo "Checking JavaScript files..."

if [ -f "$DOC_ROOT/modules/flagged_products/assets/js/anti-cheat.js" ]; then
    # Check for key classes/functions
    if grep -q "class AntiCheatDetector" "$DOC_ROOT/modules/flagged_products/assets/js/anti-cheat.js"; then
        pass "AntiCheatDetector class found"
    else
        fail "AntiCheatDetector class missing"
    fi
    
    if grep -q "startCountdown()" "$DOC_ROOT/modules/flagged_products/assets/js/anti-cheat.js"; then
        pass "startCountdown() function found"
    else
        fail "startCountdown() function missing"
    fi
else
    fail "anti-cheat.js file missing"
fi

###############################################################################
# Test 8: CSS Assets
###############################################################################
test_section "CSS Assets"

echo "Checking CSS files..."

if [ -f "$DOC_ROOT/modules/flagged_products/assets/css/flagged-products.css" ]; then
    # Check for key classes
    if grep -q ".blur-overlay" "$DOC_ROOT/modules/flagged_products/assets/css/flagged-products.css"; then
        pass "blur-overlay CSS found"
    else
        fail "blur-overlay CSS missing"
    fi
    
    if grep -q ".stock-color" "$DOC_ROOT/modules/flagged_products/assets/css/flagged-products.css"; then
        pass "stock-color CSS found"
    else
        warn "stock-color CSS not found (may be inline)"
    fi
else
    fail "flagged-products.css file missing"
fi

###############################################################################
# Test 9: Database Data
###############################################################################
test_section "Database Data"

echo "Checking for sample data..."

# Check for flagged products
product_count=$(mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "SELECT COUNT(*) FROM flagged_products;" -sN 2>/dev/null)
if [ "$product_count" -gt 0 ]; then
    pass "Found $product_count flagged products"
else
    warn "No flagged products in database (expected for new install)"
fi

# Check for user stats
stats_count=$(mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "SELECT COUNT(*) FROM flagged_products_user_stats;" -sN 2>/dev/null)
if [ "$stats_count" -gt 0 ]; then
    pass "Found $stats_count user stats records"
else
    warn "No user stats yet (expected until first completion)"
fi

###############################################################################
# Test 10: Permissions
###############################################################################
test_section "File Permissions"

echo "Checking file permissions..."

# Check if cron scripts are readable
for script in "$DOC_ROOT/modules/flagged_products/cron"/*.php; do
    if [ -r "$script" ]; then
        # Silent pass
        :
    else
        fail "Cannot read: $(basename $script)"
    fi
done

pass "All cron scripts readable"

# Check if assets are readable
if [ -r "$DOC_ROOT/modules/flagged_products/assets/css/flagged-products.css" ]; then
    pass "CSS assets readable"
else
    fail "CSS assets not readable"
fi

if [ -r "$DOC_ROOT/modules/flagged_products/assets/js/anti-cheat.js" ]; then
    pass "JS assets readable"
else
    fail "JS assets not readable"
fi

###############################################################################
# Test Summary
###############################################################################
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 Test Summary"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo -e "${GREEN}Passed:${NC}   $PASSED"
echo -e "${RED}Failed:${NC}   $FAILED"
echo -e "${YELLOW}Warnings:${NC} $WARNINGS"
echo ""

TOTAL=$((PASSED + FAILED))
if [ $TOTAL -gt 0 ]; then
    SUCCESS_RATE=$(awk "BEGIN {printf \"%.1f\", ($PASSED / $TOTAL) * 100}")
    echo "Success Rate: $SUCCESS_RATE%"
fi

echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✅ All critical tests passed!${NC}"
    echo ""
    echo "🚀 System ready for deployment!"
    exit 0
else
    echo -e "${RED}❌ Some tests failed${NC}"
    echo ""
    echo "⚠️  Please review failures before deployment"
    exit 1
fi
