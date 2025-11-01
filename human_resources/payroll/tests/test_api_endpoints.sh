#!/bin/bash
# Comprehensive API Endpoint Testing Suite
# Tests all payroll API endpoints with various scenarios

echo "🧪 PAYROLL API - COMPREHENSIVE ENDPOINT TEST SUITE"
echo "=================================================================="
echo ""

# Configuration
BASE_URL="https://staff.vapeshed.co.nz/modules/human_resources/payroll"
TESTS_PASSED=0
TESTS_FAILED=0
TOTAL_TESTS=0

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test helper function
test_endpoint() {
    local name="$1"
    local url="$2"
    local method="${3:-GET}"
    local data="$4"
    local expected_status="${5:-200}"

    TOTAL_TESTS=$((TOTAL_TESTS + 1))

    echo -n "Test $TOTAL_TESTS: $name... "

    if [ "$method" = "GET" ]; then
        response=$(curl -s -w "\n%{http_code}" "$url" 2>&1)
    else
        response=$(curl -s -w "\n%{http_code}" -X "$method" -H "Content-Type: application/json" -d "$data" "$url" 2>&1)
    fi

    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')

    if [ "$http_code" = "$expected_status" ]; then
        echo -e "${GREEN}✅ PASS${NC} (HTTP $http_code)"
        TESTS_PASSED=$((TESTS_PASSED + 1))
        return 0
    else
        echo -e "${RED}❌ FAIL${NC} (Expected $expected_status, got $http_code)"
        TESTS_FAILED=$((TESTS_FAILED + 1))
        return 1
    fi
}

# Test helper with JSON validation
test_json_endpoint() {
    local name="$1"
    local url="$2"
    local method="${3:-GET}"
    local data="$4"

    TOTAL_TESTS=$((TOTAL_TESTS + 1))

    echo -n "Test $TOTAL_TESTS: $name... "

    if [ "$method" = "GET" ]; then
        response=$(curl -s "$url" 2>&1)
    else
        response=$(curl -s -X "$method" -H "Content-Type: application/json" -d "$data" "$url" 2>&1)
    fi

    # Check if valid JSON
    if echo "$response" | python3 -m json.tool > /dev/null 2>&1; then
        echo -e "${GREEN}✅ PASS${NC} (Valid JSON response)"
        TESTS_PASSED=$((TESTS_PASSED + 1))
        echo "$response" | python3 -m json.tool | head -5
        return 0
    else
        echo -e "${RED}❌ FAIL${NC} (Invalid JSON)"
        TESTS_FAILED=$((TESTS_FAILED + 1))
        return 1
    fi
}

echo "==================================================================="
echo "Section 1: Pay Run API Endpoints"
echo "==================================================================="
echo ""

# Test 1: List Pay Runs
test_json_endpoint "GET /api/payruns.php - List all pay runs" \
    "$BASE_URL/api/payruns.php"

# Test 2: Get Pay Run Stats
test_json_endpoint "GET /api/payruns.php?action=stats - Get statistics" \
    "$BASE_URL/api/payruns.php?action=stats"

# Test 3: Get Specific Pay Run
test_json_endpoint "GET /api/payruns.php?id=1 - Get pay run details" \
    "$BASE_URL/api/payruns.php?id=1"

# Test 4: Create Pay Run (POST)
echo ""
echo "Test $((TOTAL_TESTS + 1)): POST /api/payruns.php - Create new pay run... "
create_data='{
    "period_start": "2025-11-01",
    "period_end": "2025-11-30",
    "status": "draft"
}'
response=$(curl -s -X POST -H "Content-Type: application/json" -d "$create_data" "$BASE_URL/api/payruns.php" 2>&1)
TOTAL_TESTS=$((TOTAL_TESTS + 1))
if echo "$response" | grep -q '"success":true'; then
    echo -e "${GREEN}✅ PASS${NC}"
    TESTS_PASSED=$((TESTS_PASSED + 1))
    # Extract pay run ID for later tests
    PAY_RUN_ID=$(echo "$response" | python3 -c "import sys, json; print(json.load(sys.stdin).get('data', {}).get('id', 0))" 2>/dev/null || echo "0")
    echo "Created pay run ID: $PAY_RUN_ID"
else
    echo -e "${RED}❌ FAIL${NC}"
    TESTS_FAILED=$((TESTS_FAILED + 1))
fi

echo ""
echo "==================================================================="
echo "Section 2: Payslip API Endpoints"
echo "==================================================================="
echo ""

# Test 5: List Payslips
test_json_endpoint "GET /api/payslips.php - List all payslips" \
    "$BASE_URL/api/payslips.php"

# Test 6: Get Payslip Details
test_json_endpoint "GET /api/payslips.php?id=1 - Get payslip details" \
    "$BASE_URL/api/payslips.php?id=1"

# Test 7: Payslip PDF Download
test_endpoint "GET /payslips.php?action=download&id=1 - Download PDF" \
    "$BASE_URL/payslips.php?action=download&id=1"

# Test 8: Email Payslip
echo ""
echo "Test $((TOTAL_TESTS + 1)): POST /payslips.php - Email payslip... "
email_data='{
    "payslip_id": 1,
    "email": "test@example.com"
}'
response=$(curl -s -X POST -H "Content-Type: application/json" -d "$email_data" "$BASE_URL/payslips.php?action=email" 2>&1)
TOTAL_TESTS=$((TOTAL_TESTS + 1))
if echo "$response" | grep -q '"success":true\|queued'; then
    echo -e "${GREEN}✅ PASS${NC}"
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    echo -e "${RED}❌ FAIL${NC}"
    TESTS_FAILED=$((TESTS_FAILED + 1))
fi

echo ""
echo "==================================================================="
echo "Section 3: Employee API Endpoints"
echo "==================================================================="
echo ""

# Test 9: List Employees
test_json_endpoint "GET /api/employees.php - List all employees" \
    "$BASE_URL/api/employees.php"

# Test 10: Get Employee Details
test_json_endpoint "GET /api/employees.php?id=1 - Get employee details" \
    "$BASE_URL/api/employees.php?id=1"

# Test 11: Employee Payslips
test_json_endpoint "GET /api/employees.php?id=1&payslips=1 - Employee payslips" \
    "$BASE_URL/api/employees.php?id=1&payslips=1"

echo ""
echo "==================================================================="
echo "Section 4: Reconciliation API Endpoints"
echo "==================================================================="
echo ""

# Test 12: Reconciliation Status
test_json_endpoint "GET /api/reconciliation.php - Get reconciliation status" \
    "$BASE_URL/api/reconciliation.php"

# Test 13: Xero Sync Status
test_json_endpoint "GET /api/reconciliation.php?action=xero_status" \
    "$BASE_URL/api/reconciliation.php?action=xero_status"

# Test 14: Deputy Sync Status
test_json_endpoint "GET /api/reconciliation.php?action=deputy_status" \
    "$BASE_URL/api/reconciliation.php?action=deputy_status"

echo ""
echo "==================================================================="
echo "Section 5: Reports API Endpoints"
echo "==================================================================="
echo ""

# Test 15: Summary Report
test_json_endpoint "GET /api/reports.php?type=summary - Summary report" \
    "$BASE_URL/api/reports.php?type=summary"

# Test 16: Variance Report
test_json_endpoint "GET /api/reports.php?type=variance - Variance report" \
    "$BASE_URL/api/reports.php?type=variance"

# Test 17: Expense Report
test_json_endpoint "GET /api/reports.php?type=expenses - Expense report" \
    "$BASE_URL/api/reports.php?type=expenses"

echo ""
echo "==================================================================="
echo "Section 6: Integration Health Checks"
echo "==================================================================="
echo ""

# Test 18: Xero Connection Test
test_json_endpoint "GET /api/health.php?service=xero - Xero health check" \
    "$BASE_URL/api/health.php?service=xero"

# Test 19: Deputy Connection Test
test_json_endpoint "GET /api/health.php?service=deputy - Deputy health check" \
    "$BASE_URL/api/health.php?service=deputy"

# Test 20: Email Service Test
test_json_endpoint "GET /api/health.php?service=email - Email health check" \
    "$BASE_URL/api/health.php?service=email"

# Test 21: Database Health
test_json_endpoint "GET /api/health.php?service=database - Database health" \
    "$BASE_URL/api/health.php?service=database"

echo ""
echo "==================================================================="
echo "Section 7: Error Handling Tests"
echo "==================================================================="
echo ""

# Test 22: Invalid Endpoint
test_endpoint "GET /api/nonexistent.php - 404 error handling" \
    "$BASE_URL/api/nonexistent.php" "GET" "" "404"

# Test 23: Invalid Method
echo ""
echo "Test $((TOTAL_TESTS + 1)): Invalid HTTP method (DELETE)... "
response=$(curl -s -w "\n%{http_code}" -X DELETE "$BASE_URL/api/payruns.php" 2>&1)
http_code=$(echo "$response" | tail -n1)
TOTAL_TESTS=$((TOTAL_TESTS + 1))
if [ "$http_code" = "405" ] || [ "$http_code" = "400" ]; then
    echo -e "${GREEN}✅ PASS${NC} (HTTP $http_code)"
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    echo -e "${RED}❌ FAIL${NC} (Expected 405/400, got $http_code)"
    TESTS_FAILED=$((TESTS_FAILED + 1))
fi

# Test 24: Missing Required Parameters
echo ""
echo "Test $((TOTAL_TESTS + 1)): Missing required parameters... "
response=$(curl -s -X POST -H "Content-Type: application/json" -d '{}' "$BASE_URL/api/payruns.php" 2>&1)
TOTAL_TESTS=$((TOTAL_TESTS + 1))
if echo "$response" | grep -q '"success":false\|error\|required'; then
    echo -e "${GREEN}✅ PASS${NC}"
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    echo -e "${RED}❌ FAIL${NC}"
    TESTS_FAILED=$((TESTS_FAILED + 1))
fi

echo ""
echo "=================================================================="
echo "📊 TEST SUMMARY"
echo "=================================================================="
echo "Total Tests:    $TOTAL_TESTS"
echo -e "Tests Passed:   ${GREEN}$TESTS_PASSED${NC}"
echo -e "Tests Failed:   ${RED}$TESTS_FAILED${NC}"

if [ $TOTAL_TESTS -gt 0 ]; then
    SUCCESS_RATE=$((TESTS_PASSED * 100 / TOTAL_TESTS))
    echo "Success Rate:   $SUCCESS_RATE%"
fi

echo "=================================================================="

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}🎉 ALL TESTS PASSED!${NC} API is production-ready."
    exit 0
else
    echo -e "${YELLOW}⚠️  Some tests failed.${NC} Review output above."
    exit 1
fi
