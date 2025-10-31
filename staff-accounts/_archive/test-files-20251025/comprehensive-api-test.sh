#!/bin/bash
# Comprehensive Employee Mapping API Test Suite
# Tests all endpoints and validates responses

BASE_URL="https://staff.vapeshed.co.nz/modules/staff-accounts"
PASS=0
FAIL=0

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "╔══════════════════════════════════════════════════════════╗"
echo "║                                                          ║"
echo "║      EMPLOYEE MAPPING API - COMPREHENSIVE TEST           ║"
echo "║                                                          ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

# Function to test endpoint
test_endpoint() {
    local test_name="$1"
    local method="$2"
    local endpoint="$3"
    local data="$4"
    local expected_success="$5"
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "Test: $test_name"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    if [ "$method" == "GET" ]; then
        response=$(curl -s -k -X GET "${BASE_URL}/?endpoint=${endpoint}")
    else
        response=$(curl -s -k -X $method "${BASE_URL}/?endpoint=${endpoint}" \
            -H "Content-Type: application/json" \
            -d "$data")
    fi
    
    # Check if response is valid JSON
    echo "$response" | python3 -m json.tool > /dev/null 2>&1
    if [ $? -ne 0 ]; then
        echo -e "${RED}✗ FAIL${NC} - Invalid JSON response"
        echo "Raw response: $response"
        ((FAIL++))
        return 1
    fi
    
    # Check success field
    success=$(echo "$response" | python3 -c "import sys, json; print(json.load(sys.stdin).get('success', False))")
    
    if [ "$success" == "$expected_success" ]; then
        echo -e "${GREEN}✓ PASS${NC}"
        echo "Response:"
        echo "$response" | python3 -m json.tool | head -20
        ((PASS++))
    else
        echo -e "${RED}✗ FAIL${NC} - Expected success=$expected_success, got success=$success"
        echo "Full response:"
        echo "$response" | python3 -m json.tool
        ((FAIL++))
    fi
    
    echo ""
}

# Test 1: GET all mappings (should work, might be empty)
test_endpoint \
    "GET all employee mappings" \
    "GET" \
    "employee-mappings" \
    "" \
    "True"

# Test 2: GET unmapped employees
test_endpoint \
    "GET unmapped employees" \
    "GET" \
    "employee-mappings-unmapped" \
    "" \
    "True"

# Test 3: GET mappings with status filter
test_endpoint \
    "GET mappings with status=unmapped filter" \
    "GET" \
    "employee-mappings&status=unmapped" \
    "" \
    "True"

# Test 4: GET mappings with limit
test_endpoint \
    "GET mappings with limit=10" \
    "GET" \
    "employee-mappings&limit=10" \
    "" \
    "True"

# Test 5: Auto-match (should work, might find 0 matches)
test_endpoint \
    "POST auto-match employees" \
    "POST" \
    "employee-mappings-auto-match" \
    '{"csrf_token":"test"}' \
    "True"

# Test 6: Create mapping with invalid data (should fail)
test_endpoint \
    "POST create mapping - missing required fields (should fail)" \
    "POST" \
    "employee-mappings" \
    '{"csrf_token":"test"}' \
    "False"

# Test 7: Update non-existent mapping (should fail)
test_endpoint \
    "PUT update non-existent mapping (should fail)" \
    "PUT" \
    "employee-mappings&id=999999" \
    '{"vend_customer_id":"test","csrf_token":"test"}' \
    "False"

# Test 8: Delete non-existent mapping (should fail)
test_endpoint \
    "DELETE non-existent mapping (should fail)" \
    "DELETE" \
    "employee-mappings&id=999999" \
    "" \
    "False"

echo "╔══════════════════════════════════════════════════════════╗"
echo "║                                                          ║"
echo "║                   TEST SUMMARY                           ║"
echo "║                                                          ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""
echo -e "Total Tests:  $((PASS + FAIL))"
echo -e "${GREEN}Passed:       $PASS${NC}"
echo -e "${RED}Failed:       $FAIL${NC}"
echo ""

if [ $FAIL -eq 0 ]; then
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${GREEN}                 ALL TESTS PASSED! ✓                     ${NC}"
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    echo "✅ Backend API is fully functional!"
    echo "✅ All endpoints responding correctly"
    echo "✅ Error handling working as expected"
    echo ""
    echo "🚀 Ready for Day 2 UI development!"
    exit 0
else
    echo -e "${RED}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${RED}              SOME TESTS FAILED ✗                         ${NC}"
    echo -e "${RED}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    echo "⚠️  Check the failed tests above"
    echo "⚠️  Review error logs for details"
    echo "⚠️  Fix issues before proceeding to Day 2"
    exit 1
fi
