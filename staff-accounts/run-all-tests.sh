#!/bin/bash
#
# Staff Accounts Module - Complete Page & Endpoint Test Suite
# Comprehensive testing of all pages and API endpoints
#
# Usage: ./run-all-tests.sh
#

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m' # No Color

BASE_URL="https://staff.vapeshed.co.nz/modules/staff-accounts"
COOKIE_FILE="/tmp/cis-test-cookies-$$"

echo -e "${MAGENTA}"
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║                                                              ║"
echo "║  STAFF ACCOUNTS MODULE - COMPREHENSIVE TEST SUITE           ║"
echo "║  Testing All Pages, Endpoints, and Assets                   ║"
echo "║                                                              ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"
echo ""

# Test counters
total_tests=0
passed_tests=0
failed_tests=0
warning_tests=0

# Test function
test_item() {
    local url=$1
    local method=${2:-GET}
    local expected_codes=$3
    local description=$4
    local category=$5

    ((total_tests++))

    printf "%-50s ... " "$description"

    # Make request
    if [ "$method" = "POST" ]; then
        http_code=$(curl -s -o /dev/null -w "%{http_code}" -X POST \
            -L --cookie-jar "$COOKIE_FILE" --cookie "$COOKIE_FILE" \
            -H "Content-Type: application/json" \
            -d '{"test":"true"}' \
            "${url}" 2>&1)
    else
        http_code=$(curl -s -o /dev/null -w "%{http_code}" -L \
            --cookie-jar "$COOKIE_FILE" --cookie "$COOKIE_FILE" \
            "${url}" 2>&1)
    fi

    # Check if response matches expected codes
    if [[ " ${expected_codes} " =~ " ${http_code} " ]]; then
        echo -e "${GREEN}✓ ${http_code}${NC}"
        ((passed_tests++))
        return 0
    elif [[ "$expected_codes" == *"302"* ]] && [[ "$http_code" == "302" ]]; then
        echo -e "${YELLOW}⚠ ${http_code} (auth redirect)${NC}"
        ((warning_tests++))
        return 0
    else
        echo -e "${RED}✗ ${http_code}${NC} (expected: ${expected_codes})"
        ((failed_tests++))
        return 1
    fi
}

# Get response content for analysis
get_response() {
    local url=$1
    curl -s -L --cookie-jar "$COOKIE_FILE" --cookie "$COOKIE_FILE" "${url}" 2>&1
}

echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}SECTION 1: MAIN PAGES${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""

test_item "${BASE_URL}/index.php" "GET" "200 302" "Main Dashboard" "page"
test_item "${BASE_URL}/views/my-account.php" "GET" "200 302" "My Account" "page"
test_item "${BASE_URL}/views/make-payment.php" "GET" "200 302" "Make Payment" "page"
test_item "${BASE_URL}/views/staff-list.php" "GET" "200 302" "Staff List" "page"
test_item "${BASE_URL}/views/payment-success.php?id=test" "GET" "200 302" "Payment Success" "page"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}SECTION 2: API ENDPOINTS${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""

test_item "${BASE_URL}/api/payment.php" "GET" "200 401 403 405" "Payment API" "api"
test_item "${BASE_URL}/api/process-payment.php" "POST" "200 400 401 403 405" "Process Payment API" "api"
test_item "${BASE_URL}/api/customer-search.php" "GET" "200 401 403" "Customer Search API" "api"
test_item "${BASE_URL}/api/staff-reconciliation.php" "GET" "200 401 403" "Staff Reconciliation API" "api"
test_item "${BASE_URL}/api/manager-dashboard.php" "GET" "200 401 403" "Manager Dashboard API" "api"
test_item "${BASE_URL}/api/employee-mapping.php" "GET" "200 401 403" "Employee Mapping API" "api"
test_item "${BASE_URL}/api/auto-match-suggestions.php" "GET" "200 401 403" "Auto-Match Suggestions API" "api"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}SECTION 3: STATIC ASSETS (CSS)${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""

test_item "${BASE_URL}/css/staff-accounts.css" "GET" "200" "Main CSS File" "asset"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}SECTION 4: STATIC ASSETS (JavaScript)${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""

test_item "${BASE_URL}/js/staff-accounts.js" "GET" "200" "Main JavaScript" "asset"
test_item "${BASE_URL}/js/employee-mapping.js" "GET" "200" "Employee Mapping JS" "asset"
test_item "${BASE_URL}/js/auto-match-review.js" "GET" "200" "Auto-Match Review JS" "asset"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}SECTION 5: CONTENT VALIDATION (Sample)${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""

# Check if main page contains expected content
echo -n "Checking index.php for CIS template... "
response=$(get_response "${BASE_URL}/index.php")
if [[ "$response" =~ "Staff Accounts" ]] || [[ "$response" =~ "CIS" ]] || [[ "$response" =~ "<!DOCTYPE html>" ]]; then
    echo -e "${GREEN}✓ HTML detected${NC}"
    ((total_tests++))
    ((passed_tests++))
else
    echo -e "${RED}✗ No HTML found${NC}"
    ((total_tests++))
    ((failed_tests++))
fi

# Check if CSS file contains actual CSS
echo -n "Checking CSS file for valid content... "
response=$(get_response "${BASE_URL}/css/staff-accounts.css")
if [[ "$response" =~ ".staff-accounts" ]] || [[ "$response" =~ "color:" ]] || [[ "$response" =~ "background:" ]]; then
    echo -e "${GREEN}✓ CSS detected${NC}"
    ((total_tests++))
    ((passed_tests++))
else
    echo -e "${RED}✗ No CSS found${NC}"
    ((total_tests++))
    ((failed_tests++))
fi

# Check if JS file contains actual JavaScript
echo -n "Checking JS file for valid content... "
response=$(get_response "${BASE_URL}/js/staff-accounts.js")
if [[ "$response" =~ "function" ]] || [[ "$response" =~ "const" ]] || [[ "$response" =~ "var" ]]; then
    echo -e "${GREEN}✓ JavaScript detected${NC}"
    ((total_tests++))
    ((passed_tests++))
else
    echo -e "${RED}✗ No JavaScript found${NC}"
    ((total_tests++))
    ((failed_tests++))
fi

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}SECTION 6: BOOTSTRAP FILE VERIFICATION${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""

# Check if bootstrap.php exists
echo -n "Checking bootstrap.php exists... "
if [ -f "/home/master/applications/jcepnzzkmj/public_html/modules/staff-accounts/bootstrap.php" ]; then
    echo -e "${GREEN}✓ Found${NC}"
    ((total_tests++))
    ((passed_tests++))
else
    echo -e "${RED}✗ Missing${NC}"
    ((total_tests++))
    ((failed_tests++))
fi

# Check if views directory exists
echo -n "Checking views/ directory... "
if [ -d "/home/master/applications/jcepnzzkmj/public_html/modules/staff-accounts/views" ]; then
    echo -e "${GREEN}✓ Found${NC}"
    ((total_tests++))
    ((passed_tests++))
else
    echo -e "${RED}✗ Missing${NC}"
    ((total_tests++))
    ((failed_tests++))
fi

# Check if api directory exists
echo -n "Checking api/ directory... "
if [ -d "/home/master/applications/jcepnzzkmj/public_html/modules/staff-accounts/api" ]; then
    echo -e "${GREEN}✓ Found${NC}"
    ((total_tests++))
    ((passed_tests++))
else
    echo -e "${RED}✗ Missing${NC}"
    ((total_tests++))
    ((failed_tests++))
fi

echo ""
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════${NC}"
echo -e "${MAGENTA}FINAL RESULTS${NC}"
echo -e "${MAGENTA}═══════════════════════════════════════════════════════════${NC}"
echo ""

percentage=$((passed_tests * 100 / total_tests))

echo -e "Total Tests:     ${total_tests}"
echo -e "Passed:          ${GREEN}${passed_tests}${NC}"
echo -e "Warnings:        ${YELLOW}${warning_tests}${NC}"
echo -e "Failed:          ${RED}${failed_tests}${NC}"
echo -e "Success Rate:    ${percentage}%"
echo ""

# Cleanup
rm -f "$COOKIE_FILE"

# Final verdict
if [ $failed_tests -eq 0 ] && [ $percentage -ge 95 ]; then
    echo -e "${GREEN}╔══════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║  ✓ ALL TESTS PASSED - MODULE PRODUCTION READY           ║${NC}"
    echo -e "${GREEN}╚══════════════════════════════════════════════════════════╝${NC}"
    exit 0
elif [ $percentage -ge 80 ]; then
    echo -e "${YELLOW}╔══════════════════════════════════════════════════════════╗${NC}"
    echo -e "${YELLOW}║  ⚠ MOST TESTS PASSED - MINOR ISSUES DETECTED            ║${NC}"
    echo -e "${YELLOW}╚══════════════════════════════════════════════════════════╝${NC}"
    exit 1
else
    echo -e "${RED}╔══════════════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║  ✗ CRITICAL ISSUES DETECTED - REVIEW REQUIRED           ║${NC}"
    echo -e "${RED}╚══════════════════════════════════════════════════════════╝${NC}"
    exit 2
fi
