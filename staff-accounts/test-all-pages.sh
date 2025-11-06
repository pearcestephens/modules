#!/bin/bash
#
# Staff Accounts Module - Page Health Check Script
# Tests all pages for 200 OK status and basic functionality
#
# Usage: ./test-all-pages.sh
#

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

BASE_URL="https://staff.vapeshed.co.nz/modules/staff-accounts"

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║  STAFF ACCOUNTS MODULE - PAGE HEALTH CHECK                 ║${NC}"
echo -e "${BLUE}║  Testing all pages for 200 OK status                       ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Test function
test_page() {
    local url=$1
    local name=$2
    local requires_auth=${3:-true}

    echo -n "Testing: ${name}... "

    # Make request (with cookies if auth required)
    if [ "$requires_auth" = true ]; then
        response=$(curl -s -o /dev/null -w "%{http_code}" -L --cookie-jar /tmp/cis-cookies --cookie /tmp/cis-cookies "${url}" 2>&1)
    else
        response=$(curl -s -o /dev/null -w "%{http_code}" -L "${url}" 2>&1)
    fi

    if [ "$response" = "200" ]; then
        echo -e "${GREEN}✓ 200 OK${NC}"
        return 0
    elif [ "$response" = "302" ] || [ "$response" = "301" ]; then
        echo -e "${YELLOW}⚠ $response REDIRECT (likely auth required)${NC}"
        return 1
    else
        echo -e "${RED}✗ $response ERROR${NC}"
        return 1
    fi
}

# Test function with content verification
test_page_content() {
    local url=$1
    local name=$2
    local search_string=$3

    echo -n "Testing: ${name} (with content check)... "

    response=$(curl -s -L --cookie-jar /tmp/cis-cookies --cookie /tmp/cis-cookies "${url}")
    http_code=$(curl -s -o /dev/null -w "%{http_code}" -L --cookie-jar /tmp/cis-cookies --cookie /tmp/cis-cookies "${url}")

    if [ "$http_code" = "200" ] && [[ "$response" =~ "$search_string" ]]; then
        echo -e "${GREEN}✓ 200 OK + Content Found${NC}"
        return 0
    elif [ "$http_code" = "200" ]; then
        echo -e "${YELLOW}⚠ 200 OK but content missing: '$search_string'${NC}"
        return 1
    else
        echo -e "${RED}✗ $http_code ERROR${NC}"
        return 1
    fi
}

# Counter
total_tests=0
passed_tests=0

echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}CORE PAGES${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"

# Test main dashboard
((total_tests++))
test_page "${BASE_URL}/index.php" "Main Dashboard" && ((passed_tests++))

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}VIEW PAGES${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"

# Test all view pages
((total_tests++))
test_page "${BASE_URL}/views/my-account.php" "My Account" && ((passed_tests++))

((total_tests++))
test_page "${BASE_URL}/views/make-payment.php" "Make Payment" && ((passed_tests++))

((total_tests++))
test_page "${BASE_URL}/views/staff-list.php" "Staff List (Manager)" && ((passed_tests++))

# Payment success requires parameters
echo -n "Testing: Payment Success... "
echo -e "${YELLOW}⚠ SKIPPED (requires payment parameters)${NC}"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}API ENDPOINTS${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"

# Test API endpoints (expect 401/403 without auth)
echo -n "Testing: Payment API... "
response=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/api/payment.php")
if [ "$response" = "401" ] || [ "$response" = "403" ] || [ "$response" = "200" ]; then
    echo -e "${GREEN}✓ $response (Expected)${NC}"
    ((passed_tests++))
else
    echo -e "${RED}✗ $response ERROR${NC}"
fi
((total_tests++))

echo -n "Testing: Customer Search API... "
response=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/api/customer-search.php")
if [ "$response" = "401" ] || [ "$response" = "403" ] || [ "$response" = "200" ]; then
    echo -e "${GREEN}✓ $response (Expected)${NC}"
    ((passed_tests++))
else
    echo -e "${RED}✗ $response ERROR${NC}"
fi
((total_tests++))

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}STATIC ASSETS${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"

# Test CSS
((total_tests++))
test_page "${BASE_URL}/css/staff-accounts.css" "CSS File" false && ((passed_tests++))

# Test JS
((total_tests++))
test_page "${BASE_URL}/js/staff-accounts.js" "JavaScript File" false && ((passed_tests++))

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}RESULTS${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"

percentage=$((passed_tests * 100 / total_tests))

echo ""
echo -e "Total Tests: ${total_tests}"
echo -e "Passed: ${GREEN}${passed_tests}${NC}"
echo -e "Failed: ${RED}$((total_tests - passed_tests))${NC}"
echo -e "Success Rate: ${percentage}%"
echo ""

if [ $percentage -ge 90 ]; then
    echo -e "${GREEN}✓ ALL SYSTEMS OPERATIONAL${NC}"
    exit 0
elif [ $percentage -ge 70 ]; then
    echo -e "${YELLOW}⚠ SOME ISSUES DETECTED${NC}"
    exit 1
else
    echo -e "${RED}✗ CRITICAL ISSUES DETECTED${NC}"
    exit 2
fi
