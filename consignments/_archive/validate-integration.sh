#!/bin/bash
#
# Transfer Manager Integration Validation Script
# Tests the newly integrated index.php and verifies no regressions
#
# Usage: bash validate-integration.sh
#

set -uo pipefail

echo "========================================="
echo "Transfer Manager Integration Validator"
echo "========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Base URL
BASE_URL="https://staff.vapeshed.co.nz/modules/consignments"
MODULE_PATH="/home/master/applications/jcepnzzkmj/public_html/modules/consignments"

# Test counters
PASSED=0
FAILED=0
WARNINGS=0

# Test function
test_file_exists() {
    local file=$1
    local description=$2

    if [ -f "$file" ]; then
        echo -e "${GREEN}✓${NC} $description"
        ((PASSED++))
        return 0
    else
        echo -e "${RED}✗${NC} $description - FILE NOT FOUND"
        ((FAILED++))
        return 1
    fi
}

test_file_permissions() {
    local file=$1
    local expected=$2
    local description=$3

    if [ -f "$file" ]; then
        actual=$(stat -c "%a" "$file")
        if [ "$actual" == "$expected" ]; then
            echo -e "${GREEN}✓${NC} $description"
            ((PASSED++))
            return 0
        else
            echo -e "${YELLOW}⚠${NC} $description - Expected: $expected, Got: $actual"
            ((WARNINGS++))
            return 1
        fi
    else
        echo -e "${RED}✗${NC} $description - FILE NOT FOUND"
        ((FAILED++))
        return 1
    fi
}

test_url_accessible() {
    local url=$1
    local description=$2

    status=$(curl -s -o /dev/null -w "%{http_code}" -L "$url" 2>/dev/null || echo "000")

    if [ "$status" == "200" ]; then
        echo -e "${GREEN}✓${NC} $description - HTTP $status OK"
        ((PASSED++))
        return 0
    elif [ "$status" == "302" ] || [ "$status" == "301" ]; then
        echo -e "${YELLOW}⚠${NC} $description - HTTP $status (Redirect, likely to login)"
        ((WARNINGS++))
        return 0
    elif [ "$status" == "000" ]; then
        echo -e "${RED}✗${NC} $description - CONNECTION FAILED"
        ((FAILED++))
        return 1
    else
        echo -e "${RED}✗${NC} $description - HTTP $status ERROR"
        ((FAILED++))
        return 1
    fi
}

test_php_syntax() {
    local file=$1
    local description=$2

    if [ -f "$file" ]; then
        if php -l "$file" > /dev/null 2>&1; then
            echo -e "${GREEN}✓${NC} $description"
            ((PASSED++))
            return 0
        else
            echo -e "${RED}✗${NC} $description - SYNTAX ERROR"
            php -l "$file"
            ((FAILED++))
            return 1
        fi
    else
        echo -e "${RED}✗${NC} $description - FILE NOT FOUND"
        ((FAILED++))
        return 1
    fi
}

test_file_contains() {
    local file=$1
    local pattern=$2
    local description=$3

    if [ -f "$file" ]; then
        if grep -q "$pattern" "$file"; then
            echo -e "${GREEN}✓${NC} $description"
            ((PASSED++))
            return 0
        else
            echo -e "${RED}✗${NC} $description - PATTERN NOT FOUND"
            ((FAILED++))
            return 1
        fi
    else
        echo -e "${RED}✗${NC} $description - FILE NOT FOUND"
        ((FAILED++))
        return 1
    fi
}

# Start Tests
echo -e "${BLUE}=== File Existence Tests ===${NC}"
test_file_exists "$MODULE_PATH/index.php" "index.php exists"
test_file_exists "$MODULE_PATH/TransferManager/frontend-content.php" "frontend-content.php exists"
test_file_exists "$MODULE_PATH/TransferManager/frontend.php" "Original frontend.php preserved"
test_file_exists "$MODULE_PATH/TransferManager/backend.php" "Original backend.php preserved"
test_file_exists "$MODULE_PATH/TransferManager/styles.css" "styles.css exists"
test_file_exists "$MODULE_PATH/TransferManager/api.php" "API endpoint exists"
test_file_exists "$MODULE_PATH/bootstrap.php" "bootstrap.php exists"
test_file_exists "$MODULE_PATH/ConsignmentService.php" "ConsignmentService exists"
echo ""

echo -e "${BLUE}=== JavaScript Module Tests ===${NC}"
for i in {00..08}; do
    js_file="$MODULE_PATH/TransferManager/js/${i}-"*.js
    if ls $js_file 1> /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC} JS module $i exists"
        ((PASSED++))
    else
        echo -e "${RED}✗${NC} JS module $i NOT FOUND"
        ((FAILED++))
    fi
done
echo ""

echo -e "${BLUE}=== File Permission Tests ===${NC}"
test_file_permissions "$MODULE_PATH/index.php" "644" "index.php permissions (644)"
test_file_permissions "$MODULE_PATH/TransferManager/frontend-content.php" "644" "frontend-content.php permissions (644)"
echo ""

echo -e "${BLUE}=== PHP Syntax Tests ===${NC}"
test_php_syntax "$MODULE_PATH/index.php" "index.php syntax valid"
test_php_syntax "$MODULE_PATH/TransferManager/frontend-content.php" "frontend-content.php syntax valid"
test_php_syntax "$MODULE_PATH/bootstrap.php" "bootstrap.php syntax valid"
test_php_syntax "$MODULE_PATH/ConsignmentService.php" "ConsignmentService syntax valid"
echo ""

echo -e "${BLUE}=== Integration Content Tests ===${NC}"
test_file_contains "$MODULE_PATH/index.php" "loadTransferManagerInit" "index.php has init function"
test_file_contains "$MODULE_PATH/index.php" "require_once __DIR__ . '/bootstrap.php'" "index.php includes bootstrap"
test_file_contains "$MODULE_PATH/index.php" "frontend-content.php" "index.php includes frontend content"
test_file_contains "$MODULE_PATH/index.php" "consignments-content" "index.php has CSS scoping wrapper"
test_file_contains "$MODULE_PATH/index.php" "window.TT_CONFIG" "index.php has JavaScript config"
test_file_contains "$MODULE_PATH/TransferManager/frontend-content.php" "modalQuick" "frontend-content has detail modal"
test_file_contains "$MODULE_PATH/TransferManager/frontend-content.php" "modalCreate" "frontend-content has create modal"
test_file_contains "$MODULE_PATH/TransferManager/frontend-content.php" "modalReceiving" "frontend-content has receiving modal"
echo ""

echo -e "${BLUE}=== URL Accessibility Tests ===${NC}"
test_url_accessible "$BASE_URL/" "Main index endpoint"
test_url_accessible "$BASE_URL/TransferManager/api.php" "API endpoint"
test_url_accessible "$BASE_URL/TransferManager/frontend.php" "Original frontend (preserved)"
echo ""

echo -e "${BLUE}=== CSS Scoping Tests ===${NC}"
if grep -q "\.consignments-content" "$MODULE_PATH/index.php"; then
    echo -e "${GREEN}✓${NC} CSS scoped to .consignments-content"
    ((PASSED++))
else
    echo -e "${RED}✗${NC} CSS scoping wrapper NOT FOUND"
    ((FAILED++))
fi

if grep -q "max-width: 1600px" "$MODULE_PATH/index.php"; then
    echo -e "${GREEN}✓${NC} Container max-width preserved"
    ((PASSED++))
else
    echo -e "${YELLOW}⚠${NC} Container max-width not found in index.php"
    ((WARNINGS++))
fi
echo ""

echo -e "${BLUE}=== Database Integration Tests ===${NC}"
if grep -q "CIS\\\\Base\\\\Database::pdo()" "$MODULE_PATH/index.php"; then
    echo -e "${GREEN}✓${NC} Uses CIS database infrastructure"
    ((PASSED++))
else
    echo -e "${RED}✗${NC} Database integration NOT FOUND"
    ((FAILED++))
fi

if grep -q "vend_outlets" "$MODULE_PATH/index.php"; then
    echo -e "${GREEN}✓${NC} Loads outlets from database"
    ((PASSED++))
else
    echo -e "${RED}✗${NC} Outlet loading NOT FOUND"
    ((FAILED++))
fi

if grep -q "vend_suppliers" "$MODULE_PATH/index.php"; then
    echo -e "${GREEN}✓${NC} Loads suppliers from database"
    ((PASSED++))
else
    echo -e "${RED}✗${NC} Supplier loading NOT FOUND"
    ((FAILED++))
fi
echo ""

echo -e "${BLUE}=== Authentication Tests ===${NC}"
if grep -q "isLoggedIn()" "$MODULE_PATH/index.php"; then
    echo -e "${GREEN}✓${NC} Authentication check present"
    ((PASSED++))
else
    echo -e "${RED}✗${NC} Authentication check NOT FOUND"
    ((FAILED++))
fi

if grep -q "tt_csrf" "$MODULE_PATH/index.php"; then
    echo -e "${GREEN}✓${NC} CSRF protection implemented"
    ((PASSED++))
else
    echo -e "${RED}✗${NC} CSRF protection NOT FOUND"
    ((FAILED++))
fi
echo ""

echo -e "${BLUE}=== Log Check ===${NC}"
LOG_FILE="/home/master/applications/jcepnzzkmj/public_html/logs/apache_phpstack-129337-518184.cloudwaysapps.com.error.log"
if [ -f "$LOG_FILE" ]; then
    recent_errors=$(tail -100 "$LOG_FILE" | grep -i "consignments" | grep -E "(error|fatal|warning)" | wc -l || echo "0")
    if [ "$recent_errors" -eq 0 ]; then
        echo -e "${GREEN}✓${NC} No recent errors in Apache log (last 100 lines)"
        ((PASSED++))
    else
        echo -e "${YELLOW}⚠${NC} Found $recent_errors recent errors/warnings mentioning 'consignments'"
        echo "   Run: tail -100 $LOG_FILE | grep -i consignments"
        ((WARNINGS++))
    fi
else
    echo -e "${YELLOW}⚠${NC} Apache log file not found at expected location"
    ((WARNINGS++))
fi
echo ""

# Summary
echo "========================================="
echo -e "${BLUE}TEST SUMMARY${NC}"
echo "========================================="
echo -e "${GREEN}✓ Passed:${NC}    $PASSED"
echo -e "${YELLOW}⚠ Warnings:${NC}  $WARNINGS"
echo -e "${RED}✗ Failed:${NC}    $FAILED"
echo ""

TOTAL=$((PASSED + FAILED + WARNINGS))
if [ $TOTAL -gt 0 ]; then
    SUCCESS_RATE=$(( (PASSED * 100) / TOTAL ))
    echo "Success Rate: $SUCCESS_RATE%"
else
    SUCCESS_RATE=0
fi
echo ""

# Verdict
if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}========================================="
    echo -e "✅ INTEGRATION VALIDATION PASSED"
    echo -e "=========================================${NC}"
    echo ""
    echo "✅ The Transfer Manager has been successfully integrated!"
    echo ""
    echo "Next steps:"
    echo "1. Test in browser: $BASE_URL/"
    echo "2. Compare with original: $BASE_URL/TransferManager/frontend.php"
    echo "3. Verify all functionality works"
    echo "4. Check for CSS pollution (inspect global styles)"
    echo ""
    exit 0
else
    echo -e "${RED}========================================="
    echo -e "❌ INTEGRATION VALIDATION FAILED"
    echo -e "=========================================${NC}"
    echo ""
    echo "❌ $FAILED critical tests failed"
    echo ""
    echo "Review the errors above and fix before testing."
    echo ""
    exit 1
fi
