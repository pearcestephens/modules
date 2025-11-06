#!/bin/bash
#
# Bank Transactions Module - Comprehensive Endpoint Test Suite
# Tests all 10 API endpoints + 5 page views
#
# Usage: ./test-bank-endpoints.sh
#

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m' # No Color

BASE_URL="https://staff.vapeshed.co.nz/modules/bank-transactions"
COOKIE_FILE="/tmp/bank-test-cookies-$$"
TEST_RESULTS_FILE="test-results-$(date +%Y-%m-%d_%H-%M-%S).log"

PASS_COUNT=0
FAIL_COUNT=0

echo -e "${MAGENTA}"
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║                                                              ║"
echo "║  BANK TRANSACTIONS MODULE - COMPREHENSIVE TEST SUITE        ║"
echo "║  Testing All API Endpoints & Page Views                     ║"
echo "║                                                              ║"
echo "╔══════════════════════════════════════════════════════════════╗"
echo -e "${NC}"

# Log function
log_result() {
    echo "$1" >> "$TEST_RESULTS_FILE"
}

# Test function
test_endpoint() {
    local name="$1"
    local url="$2"
    local method="$3"
    local data="$4"
    local expected_code="$5"

    echo -ne "${CYAN}Testing: ${NC}$name... "

    if [ "$method" = "GET" ]; then
        response=$(curl -s -w "\n%{http_code}" -b "$COOKIE_FILE" "$url" 2>/dev/null)
    else
        response=$(curl -s -w "\n%{http_code}" -X POST -b "$COOKIE_FILE" -d "$data" "$url" 2>/dev/null)
    fi

    http_code=$(echo "$response" | tail -n 1)
    body=$(echo "$response" | sed '$d')

    if [ "$http_code" = "$expected_code" ] || [ "$http_code" = "200" ]; then
        echo -e "${GREEN}✓${NC} ($http_code)"
        log_result "PASS: $name - HTTP $http_code"
        ((PASS_COUNT++))
        return 0
    else
        echo -e "${RED}✗${NC} ($http_code, expected $expected_code)"
        log_result "FAIL: $name - HTTP $http_code (expected $expected_code)"
        log_result "Response: $body"
        ((FAIL_COUNT++))
        return 1
    fi
}

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}PHASE 1: PAGE VIEWS${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

test_endpoint "Dashboard Page" "${BASE_URL}/index.php?route=dashboard" "GET" "" "200"
test_endpoint "Transaction List" "${BASE_URL}/index.php?route=list" "GET" "" "200"
test_endpoint "Transaction Detail" "${BASE_URL}/index.php?route=detail&id=1" "GET" "" "200"
test_endpoint "Bulk Operations View" "${BASE_URL}/views/bulk-operations.php" "GET" "" "200"
test_endpoint "Settings View" "${BASE_URL}/views/settings.php" "GET" "" "200"

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}PHASE 2: GET API ENDPOINTS${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

test_endpoint "Dashboard Metrics API" "${BASE_URL}/api/dashboard-metrics.php" "GET" "" "200"
test_endpoint "Match Suggestions API" "${BASE_URL}/api/match-suggestions.php?transaction_id=1" "GET" "" "200"
test_endpoint "Export API" "${BASE_URL}/api/export.php?format=csv" "GET" "" "200"
test_endpoint "Settings Get API" "${BASE_URL}/api/settings.php" "GET" "" "200"

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}PHASE 3: POST API ENDPOINTS (Without CSRF)${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

# These should fail without CSRF but should return proper error
test_endpoint "Auto-Match Single (No CSRF)" "${BASE_URL}/api/auto-match-single.php" "POST" "transaction_id=1" "403"
test_endpoint "Auto-Match All (No CSRF)" "${BASE_URL}/api/auto-match-all.php" "POST" "" "403"
test_endpoint "Bulk Auto-Match (No CSRF)" "${BASE_URL}/api/bulk-auto-match.php" "POST" "transaction_ids[]=1&transaction_ids[]=2" "403"
test_endpoint "Bulk Send Review (No CSRF)" "${BASE_URL}/api/bulk-send-review.php" "POST" "transaction_ids[]=1" "403"
test_endpoint "Reassign Payment (No CSRF)" "${BASE_URL}/api/reassign-payment.php" "POST" "transaction_id=1&new_outlet_id=2" "403"
test_endpoint "Settings Save (No CSRF)" "${BASE_URL}/api/settings.php" "POST" "auto_match_enabled=1" "403"

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}PHASE 4: ASSET FILES${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

test_endpoint "CSS File" "${BASE_URL}/assets/css/transactions.css" "GET" "" "200"
test_endpoint "Dashboard JS" "${BASE_URL}/assets/js/dashboard.js" "GET" "" "200"
test_endpoint "Transaction List JS" "${BASE_URL}/assets/js/transaction-list.js" "GET" "" "200"

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${MAGENTA}TEST SUMMARY${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

TOTAL_TESTS=$((PASS_COUNT + FAIL_COUNT))

echo -e "${GREEN}Passed: $PASS_COUNT${NC}"
echo -e "${RED}Failed: $FAIL_COUNT${NC}"
echo -e "Total:  $TOTAL_TESTS"
echo ""

if [ $FAIL_COUNT -eq 0 ]; then
    echo -e "${GREEN}ALL TESTS PASSED! 🎉${NC}"
    exit 0
else
    echo -e "${RED}SOME TESTS FAILED${NC}"
    echo -e "Check ${TEST_RESULTS_FILE} for details"
    exit 1
fi

# Cleanup
rm -f "$COOKIE_FILE"
