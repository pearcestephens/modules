#!/bin/bash

# Transfer Manager API Testing Script
# Tests the new backend-v2.php implementation

BASE_URL="https://staff.vapeshed.co.nz/modules/consignments/TransferManager"
COOKIE_FILE="/tmp/cis_session_cookie.txt"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "=========================================="
echo "Transfer Manager API Test Suite"
echo "=========================================="
echo ""

# Function to test an endpoint
test_endpoint() {
    local name=$1
    local action=$2
    local data=$3
    local expect_success=$4

    echo -n "Testing ${name}... "

    response=$(curl -s -X POST \
        -H "Content-Type: application/json" \
        -b "$COOKIE_FILE" \
        -d "{\"action\": \"${action}\", \"data\": ${data}}" \
        "${BASE_URL}/backend-v2.php")

    # Check if response is valid JSON
    if ! echo "$response" | jq . >/dev/null 2>&1; then
        echo -e "${RED}FAIL${NC} - Invalid JSON response"
        echo "Response: $response"
        return 1
    fi

    # Check success field
    success=$(echo "$response" | jq -r '.success')
    request_id=$(echo "$response" | jq -r '.request_id')

    if [ "$success" = "$expect_success" ]; then
        echo -e "${GREEN}PASS${NC} [request_id: $request_id]"

        # Validate envelope structure
        if [ "$expect_success" = "true" ]; then
            # Check required success fields
            if ! echo "$response" | jq -e '.message' >/dev/null 2>&1; then
                echo -e "  ${YELLOW}WARNING${NC}: Missing 'message' field"
            fi
            if ! echo "$response" | jq -e '.timestamp' >/dev/null 2>&1; then
                echo -e "  ${YELLOW}WARNING${NC}: Missing 'timestamp' field"
            fi
            if ! echo "$response" | jq -e '.data' >/dev/null 2>&1; then
                echo -e "  ${YELLOW}WARNING${NC}: Missing 'data' field"
            fi
            if ! echo "$response" | jq -e '.meta' >/dev/null 2>&1; then
                echo -e "  ${YELLOW}WARNING${NC}: Missing 'meta' field"
            fi

            # Show meta info if present
            if echo "$response" | jq -e '.meta.duration_ms' >/dev/null 2>&1; then
                duration=$(echo "$response" | jq -r '.meta.duration_ms')
                memory=$(echo "$response" | jq -r '.meta.memory_usage // "N/A"')
                echo "  Performance: ${duration}ms, Memory: ${memory}"
            fi
        else
            # Check required error fields
            if ! echo "$response" | jq -e '.error.code' >/dev/null 2>&1; then
                echo -e "  ${YELLOW}WARNING${NC}: Missing 'error.code' field"
            fi
            if ! echo "$response" | jq -e '.error.message' >/dev/null 2>&1; then
                echo -e "  ${YELLOW}WARNING${NC}: Missing 'error.message' field"
            fi

            # Show error info
            error_code=$(echo "$response" | jq -r '.error.code // "UNKNOWN"')
            error_msg=$(echo "$response" | jq -r '.error.message // "No message"')
            echo "  Error: [$error_code] $error_msg"
        fi

        return 0
    else
        echo -e "${RED}FAIL${NC}"
        echo "  Expected success=$expect_success, got success=$success"
        echo "  Response: $response"
        return 1
    fi
}

# Check if logged in (need valid session)
echo "Checking authentication..."
auth_response=$(curl -s -X POST \
    -H "Content-Type: application/json" \
    -b "$COOKIE_FILE" \
    -d '{"action": "init", "data": {}}' \
    "${BASE_URL}/backend-v2.php")

if ! echo "$auth_response" | jq -e '.success' >/dev/null 2>&1; then
    echo -e "${RED}ERROR${NC}: Not authenticated. Please login first and export session cookie to $COOKIE_FILE"
    echo ""
    echo "To get session cookie:"
    echo "1. Login to CIS in browser"
    echo "2. Use browser dev tools to copy PHPSESSID cookie"
    echo "3. Save to $COOKIE_FILE in Netscape format"
    exit 1
fi

echo -e "${GREEN}Authenticated successfully${NC}"
echo ""

# Test counters
total_tests=0
passed_tests=0
failed_tests=0

# ==========================================
# Configuration & Setup Tests
# ==========================================
echo "=========================================="
echo "1. Configuration & Setup"
echo "=========================================="

((total_tests++))
if test_endpoint "Init (get config)" "init" "{}" "true"; then
    ((passed_tests++))
else
    ((failed_tests++))
fi

((total_tests++))
if test_endpoint "Verify Sync Status" "verify_sync" "{}" "true"; then
    ((passed_tests++))
else
    ((failed_tests++))
fi

echo ""

# ==========================================
# Listing & Search Tests
# ==========================================
echo "=========================================="
echo "2. Listing & Search"
echo "=========================================="

((total_tests++))
if test_endpoint "List All Transfers (page 1)" "list_transfers" '{"page": 1, "perPage": 10}' "true"; then
    ((passed_tests++))
else
    ((failed_tests++))
fi

((total_tests++))
if test_endpoint "List with Filter (OUTLET)" "list_transfers" '{"page": 1, "perPage": 10, "type": "OUTLET"}' "true"; then
    ((passed_tests++))
else
    ((failed_tests++))
fi

((total_tests++))
if test_endpoint "List with State Filter (SENT)" "list_transfers" '{"page": 1, "perPage": 10, "state": "SENT"}' "true"; then
    ((passed_tests++))
else
    ((failed_tests++))
fi

((total_tests++))
if test_endpoint "Search Products" "search_products" '{"q": "vape", "limit": 10}' "true"; then
    ((passed_tests++))
else
    ((failed_tests++))
fi

echo ""

# ==========================================
# Validation Tests (should fail)
# ==========================================
echo "=========================================="
echo "3. Validation Tests (Expected Failures)"
echo "=========================================="

((total_tests++))
if test_endpoint "Invalid Action" "invalid_action_name" "{}" "false"; then
    ((passed_tests++))
else
    ((failed_tests++))
fi

((total_tests++))
if test_endpoint "Missing CSRF (create)" "create_transfer" '{"consignment_category": "OUTLET", "outlet_from": 1, "outlet_to": 2}' "false"; then
    ((passed_tests++))
else
    ((failed_tests++))
fi

((total_tests++))
if test_endpoint "Invalid Page Number" "list_transfers" '{"page": -1}' "false"; then
    ((passed_tests++))
else
    ((failed_tests++))
fi

echo ""

# ==========================================
# Performance Tests
# ==========================================
echo "=========================================="
echo "4. Performance Tests"
echo "=========================================="

echo -n "Testing response time (list_transfers)... "
start_time=$(date +%s%3N)
response=$(curl -s -X POST \
    -H "Content-Type: application/json" \
    -b "$COOKIE_FILE" \
    -d '{"action": "list_transfers", "data": {"page": 1, "perPage": 50}}' \
    "${BASE_URL}/backend-v2.php")
end_time=$(date +%s%3N)

elapsed=$((end_time - start_time))

if [ $elapsed -lt 500 ]; then
    echo -e "${GREEN}PASS${NC} - ${elapsed}ms (target: <500ms)"
    ((passed_tests++))
else
    echo -e "${YELLOW}SLOW${NC} - ${elapsed}ms (target: <500ms)"
fi
((total_tests++))

echo ""

# ==========================================
# Summary
# ==========================================
echo "=========================================="
echo "Test Summary"
echo "=========================================="
echo "Total Tests: $total_tests"
echo -e "Passed: ${GREEN}$passed_tests${NC}"
echo -e "Failed: ${RED}$failed_tests${NC}"
echo ""

if [ $failed_tests -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}✗ Some tests failed${NC}"
    exit 1
fi
