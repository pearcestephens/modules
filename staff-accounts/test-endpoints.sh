#!/bin/bash
#
# Staff Accounts Module - API Endpoint Testing Script
# Tests all API endpoints for proper responses
#
# Usage: ./test-endpoints.sh
#

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

BASE_URL="https://staff.vapeshed.co.nz/modules/staff-accounts"
COOKIE_FILE="/tmp/cis-test-cookies-$$"

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║  STAFF ACCOUNTS - API ENDPOINT TESTING                     ║${NC}"
echo -e "${BLUE}║  Testing all API endpoints for proper responses            ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Test counters
total_tests=0
passed_tests=0
failed_tests=0

# Test function for API endpoints
test_endpoint() {
    local endpoint=$1
    local method=${2:-GET}
    local expected_codes=$3
    local description=$4

    ((total_tests++))

    echo -n "Testing: ${description}... "

    # Make request
    if [ "$method" = "POST" ]; then
        http_code=$(curl -s -o /dev/null -w "%{http_code}" -X POST \
            --cookie "$COOKIE_FILE" --cookie-jar "$COOKIE_FILE" \
            -H "Content-Type: application/json" \
            -d '{"test":"true"}' \
            "${BASE_URL}${endpoint}" 2>&1)
    else
        http_code=$(curl -s -o /dev/null -w "%{http_code}" \
            --cookie "$COOKIE_FILE" --cookie-jar "$COOKIE_FILE" \
            "${BASE_URL}${endpoint}" 2>&1)
    fi

    # Check if response matches expected codes
    if [[ " ${expected_codes} " =~ " ${http_code} " ]]; then
        echo -e "${GREEN}✓ ${http_code}${NC}"
        ((passed_tests++))
        return 0
    else
        echo -e "${RED}✗ ${http_code} (expected: ${expected_codes})${NC}"
        ((failed_tests++))
        return 1
    fi
}

# Test with detailed response
test_endpoint_verbose() {
    local endpoint=$1
    local method=${2:-GET}
    local description=$3

    ((total_tests++))

    echo -e "\n${CYAN}Testing: ${description}${NC}"
    echo -e "Endpoint: ${endpoint}"
    echo -e "Method: ${method}"

    if [ "$method" = "POST" ]; then
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X POST \
            --cookie "$COOKIE_FILE" --cookie-jar "$COOKIE_FILE" \
            -H "Content-Type: application/json" \
            -d '{"test":"true"}' \
            "${BASE_URL}${endpoint}")
    else
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
            --cookie "$COOKIE_FILE" --cookie-jar "$COOKIE_FILE" \
            "${BASE_URL}${endpoint}")
    fi

    http_code=$(echo "$response" | grep "HTTP_CODE:" | cut -d: -f2)
    body=$(echo "$response" | sed '/HTTP_CODE:/d')

    echo -e "HTTP Code: ${http_code}"
    echo -e "Response Preview: ${body:0:200}..."

    if [ "$http_code" = "200" ] || [ "$http_code" = "401" ] || [ "$http_code" = "403" ]; then
        echo -e "${GREEN}✓ Expected response${NC}"
        ((passed_tests++))
        return 0
    else
        echo -e "${RED}✗ Unexpected response${NC}"
        ((failed_tests++))
        return 1
    fi
}

echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}API ENDPOINTS - BASIC TESTS${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"

# Test API endpoints (expect 200, 401, or 403 without proper auth/data)
test_endpoint "/api/payment.php" "GET" "200 401 403 405" "Payment API"
test_endpoint "/api/process-payment.php" "POST" "200 400 401 403 405" "Process Payment"
test_endpoint "/api/customer-search.php" "GET" "200 401 403" "Customer Search"
test_endpoint "/api/staff-reconciliation.php" "GET" "200 401 403" "Staff Reconciliation"
test_endpoint "/api/manager-dashboard.php" "GET" "200 401 403" "Manager Dashboard"
test_endpoint "/api/employee-mapping.php" "GET" "200 401 403" "Employee Mapping"
test_endpoint "/api/auto-match-suggestions.php" "GET" "200 401 403" "Auto-Match Suggestions"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}VERBOSE ENDPOINT TESTS (Sample)${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"

# Test one endpoint verbosely to see actual response
test_endpoint_verbose "/api/payment.php" "GET" "Payment API (Verbose)"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}STATIC ASSETS${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"

# Test static assets (should be 200 OK)
test_endpoint "/css/staff-accounts.css" "GET" "200" "CSS File"
test_endpoint "/js/staff-accounts.js" "GET" "200" "Main JavaScript"
test_endpoint "/js/employee-mapping.js" "GET" "200" "Employee Mapping JS"
test_endpoint "/js/auto-match-review.js" "GET" "200" "Auto-Match Review JS"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}RESULTS SUMMARY${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"

percentage=$((passed_tests * 100 / total_tests))

echo ""
echo -e "Total Tests:    ${total_tests}"
echo -e "Passed:         ${GREEN}${passed_tests}${NC}"
echo -e "Failed:         ${RED}${failed_tests}${NC}"
echo -e "Success Rate:   ${percentage}%"
echo ""

# Cleanup
rm -f "$COOKIE_FILE"

if [ $percentage -ge 90 ]; then
    echo -e "${GREEN}✓ ENDPOINTS OPERATIONAL${NC}"
    exit 0
elif [ $percentage -ge 70 ]; then
    echo -e "${YELLOW}⚠ SOME ENDPOINT ISSUES${NC}"
    exit 1
else
    echo -e "${RED}✗ CRITICAL ENDPOINT ISSUES${NC}"
    exit 2
fi
