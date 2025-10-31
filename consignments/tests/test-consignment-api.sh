#!/bin/bash
# =====================================================================
# Consignment API Test Suite
# =====================================================================
# Tests all endpoints of the new consignment JSON API
#
# Requirements:
# - curl installed
# - jq installed (for JSON parsing)
# - Valid session cookie with CSRF token
#
# Usage:
#   ./test-consignment-api.sh [base_url]
#
# Example:
#   ./test-consignment-api.sh https://staff.vapeshed.co.nz
# =====================================================================

set -e  # Exit on first error

# Configuration
BASE_URL="${1:-https://staff.vapeshed.co.nz}"
API_ENDPOINT="${BASE_URL}/modules/consignments/api.php"
COOKIE_FILE="/tmp/consignment-api-cookies.txt"
CSRF_TOKEN=""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test counters
TESTS_RUN=0
TESTS_PASSED=0
TESTS_FAILED=0

# =====================================================================
# Helper Functions
# =====================================================================

log() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

success() {
    echo -e "${GREEN}[PASS]${NC} $1"
    ((TESTS_PASSED++))
}

fail() {
    echo -e "${RED}[FAIL]${NC} $1"
    ((TESTS_FAILED++))
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

test_start() {
    ((TESTS_RUN++))
    echo ""
    log "Test #${TESTS_RUN}: $1"
}

# Make API request
api_request() {
    local action="$1"
    local data="$2"
    local expect_ok="${3:-true}"

    local payload="{\"action\":\"${action}\",\"data\":${data}}"

    log "Request: ${payload}"

    response=$(curl -s -X POST "${API_ENDPOINT}" \
        -H "Content-Type: application/json" \
        -b "${COOKIE_FILE}" \
        -d "${payload}")

    log "Response: ${response}"

    # Check if response is valid JSON
    if ! echo "${response}" | jq . > /dev/null 2>&1; then
        fail "Response is not valid JSON"
        return 1
    fi

    # Check ok status
    ok=$(echo "${response}" | jq -r '.ok')
    if [ "${ok}" = "${expect_ok}" ]; then
        success "Expected ok=${expect_ok}, got ok=${ok}"
    else
        fail "Expected ok=${expect_ok}, got ok=${ok}"
        return 1
    fi

    echo "${response}"
}

# =====================================================================
# Test Cases
# =====================================================================

# Test 1: Health Check (Method Not Allowed)
test_health_check() {
    test_start "Health check - GET should return 405"

    response=$(curl -s -w "\n%{http_code}" -X GET "${API_ENDPOINT}")
    http_code=$(echo "${response}" | tail -n1)
    body=$(echo "${response}" | head -n-1)

    if [ "${http_code}" = "405" ]; then
        success "GET rejected with 405 (Method Not Allowed)"
    else
        fail "Expected HTTP 405, got ${http_code}"
    fi
}

# Test 2: Invalid JSON
test_invalid_json() {
    test_start "Invalid JSON payload should return 400"

    response=$(curl -s -X POST "${API_ENDPOINT}" \
        -H "Content-Type: application/json" \
        -d '{"invalid json')

    ok=$(echo "${response}" | jq -r '.ok')
    error=$(echo "${response}" | jq -r '.error')

    if [ "${ok}" = "false" ] && echo "${error}" | grep -iq "json"; then
        success "Invalid JSON rejected with appropriate error"
    else
        fail "Invalid JSON not handled correctly"
    fi
}

# Test 3: Missing Action
test_missing_action() {
    test_start "Missing action should return 400"

    response=$(api_request "" "{}" false)
    error=$(echo "${response}" | jq -r '.error')

    if echo "${error}" | grep -iq "action"; then
        success "Missing action rejected"
    else
        fail "Missing action not handled correctly"
    fi
}

# Test 4: Unknown Action
test_unknown_action() {
    test_start "Unknown action should return 400"

    response=$(api_request "unknown_action" "{}" false)
    error=$(echo "${response}" | jq -r '.error')

    if echo "${error}" | grep -iq "unknown"; then
        success "Unknown action rejected"
    else
        fail "Unknown action not handled correctly"
    fi
}

# Test 5: Recent Consignments (default limit)
test_recent_default() {
    test_start "Get recent consignments (default limit)"

    response=$(api_request "recent" "{}")

    if echo "${response}" | jq -e '.data.rows' > /dev/null; then
        count=$(echo "${response}" | jq '.data.count')
        success "Retrieved ${count} recent consignments"
    else
        fail "Could not retrieve recent consignments"
    fi
}

# Test 6: Recent Consignments (custom limit)
test_recent_limit() {
    test_start "Get recent consignments with limit=5"

    response=$(api_request "recent" "{\"limit\":5}")

    count=$(echo "${response}" | jq '.data.count')
    rows=$(echo "${response}" | jq '.data.rows | length')

    if [ "${rows}" -le 5 ]; then
        success "Returned ${rows} rows (limit respected)"
    else
        fail "Returned ${rows} rows (limit not respected)"
    fi
}

# Test 7: Get Single Consignment (invalid ID)
test_get_invalid() {
    test_start "Get consignment with invalid ID should return 404 or null"

    response=$(api_request "get" "{\"id\":999999}" false)

    if echo "${response}" | jq -e '.error' > /dev/null; then
        success "Invalid ID handled gracefully"
    else
        fail "Invalid ID not handled correctly"
    fi
}

# Test 8: Get Single Consignment (valid ID, if exists)
test_get_valid() {
    test_start "Get consignment with valid ID (if data exists)"

    # First get recent to find a valid ID
    recent=$(api_request "recent" "{\"limit\":1}")
    id=$(echo "${recent}" | jq -r '.data.rows[0].id // empty')

    if [ -z "${id}" ]; then
        warn "No consignments in database, skipping get test"
        return
    fi

    response=$(api_request "get" "{\"id\":${id}}")

    if echo "${response}" | jq -e '.data.consignment' > /dev/null; then
        success "Retrieved consignment ID ${id}"
    else
        fail "Could not retrieve consignment"
    fi
}

# Test 9: Search by ref_code
test_search_refcode() {
    test_start "Search consignments by ref_code"

    response=$(api_request "search" "{\"ref_code\":\"CON\",\"limit\":10}")

    if echo "${response}" | jq -e '.data.rows' > /dev/null; then
        count=$(echo "${response}" | jq '.data.count')
        success "Search returned ${count} results"
    else
        fail "Search failed"
    fi
}

# Test 10: Search by outlet_id
test_search_outlet() {
    test_start "Search consignments by outlet_id"

    response=$(api_request "search" "{\"outlet_id\":1,\"limit\":10}")

    if echo "${response}" | jq -e '.data.rows' > /dev/null; then
        count=$(echo "${response}" | jq '.data.count')
        success "Search returned ${count} results for outlet 1"
    else
        fail "Search by outlet failed"
    fi
}

# Test 11: Get Statistics
test_stats() {
    test_start "Get consignment statistics"

    response=$(api_request "stats" "{}")

    if echo "${response}" | jq -e '.data' > /dev/null; then
        total=$(echo "${response}" | jq '.data.total // 0')
        success "Stats retrieved (total: ${total})"
    else
        fail "Could not retrieve stats"
    fi
}

# Test 12: Get Statistics by outlet
test_stats_outlet() {
    test_start "Get consignment statistics for outlet"

    response=$(api_request "stats" "{\"outlet_id\":1}")

    if echo "${response}" | jq -e '.data' > /dev/null; then
        total=$(echo "${response}" | jq '.data.total // 0')
        success "Stats for outlet 1 retrieved (total: ${total})"
    else
        fail "Could not retrieve outlet stats"
    fi
}

# Test 13: Create without CSRF (should fail)
test_create_no_csrf() {
    test_start "Create consignment without CSRF should fail"

    response=$(api_request "create" "{\"ref_code\":\"TEST-001\",\"origin_outlet_id\":1,\"dest_outlet_id\":2,\"created_by\":1}" false)

    if echo "${response}" | jq -e '.error' | grep -iq "csrf"; then
        success "Create without CSRF rejected"
    else
        fail "Create without CSRF not properly validated"
    fi
}

# Test 14: Create with invalid CSRF (should fail)
test_create_invalid_csrf() {
    test_start "Create consignment with invalid CSRF should fail"

    response=$(api_request "create" "{\"csrf\":\"invalid_token\",\"ref_code\":\"TEST-001\",\"origin_outlet_id\":1,\"dest_outlet_id\":2,\"created_by\":1}" false)

    if echo "${response}" | jq -e '.error' | grep -iq "csrf"; then
        success "Create with invalid CSRF rejected"
    else
        fail "Create with invalid CSRF not properly validated"
    fi
}

# Test 15: Create with missing fields
test_create_missing_fields() {
    test_start "Create consignment with missing required fields should fail"

    # Assuming we have a CSRF token for this test
    response=$(api_request "create" "{\"csrf\":\"${CSRF_TOKEN}\",\"ref_code\":\"TEST-001\"}" false)

    if echo "${response}" | jq -e '.error' | grep -iq "missing"; then
        success "Missing fields validation working"
    else
        fail "Missing fields not validated"
    fi
}

# Test 16: Add item without CSRF (should fail)
test_add_item_no_csrf() {
    test_start "Add item without CSRF should fail"

    response=$(api_request "add_item" "{\"consignment_id\":1,\"product_id\":\"abc123\",\"sku\":\"SKU-001\",\"qty\":10}" false)

    if echo "${response}" | jq -e '.error' | grep -iq "csrf"; then
        success "Add item without CSRF rejected"
    else
        fail "Add item without CSRF not properly validated"
    fi
}

# Test 17: Update status without CSRF (should fail)
test_update_status_no_csrf() {
    test_start "Update status without CSRF should fail"

    response=$(api_request "status" "{\"id\":1,\"status\":\"sent\"}" false)

    if echo "${response}" | jq -e '.error' | grep -iq "csrf"; then
        success "Update status without CSRF rejected"
    else
        fail "Update status without CSRF not properly validated"
    fi
}

# =====================================================================
# Main Test Runner
# =====================================================================

main() {
    log "Starting Consignment API Test Suite"
    log "API Endpoint: ${API_ENDPOINT}"
    log "Cookie File: ${COOKIE_FILE}"
    echo ""

    # Check dependencies
    if ! command -v curl &> /dev/null; then
        fail "curl is not installed. Please install curl."
        exit 1
    fi

    if ! command -v jq &> /dev/null; then
        warn "jq is not installed. JSON parsing may be limited."
        warn "Install jq for better test output: apt-get install jq"
    fi

    # Run all tests
    test_health_check
    test_invalid_json
    test_missing_action
    test_unknown_action
    test_recent_default
    test_recent_limit
    test_get_invalid
    test_get_valid
    test_search_refcode
    test_search_outlet
    test_stats
    test_stats_outlet
    test_create_no_csrf
    test_create_invalid_csrf
    test_create_missing_fields
    test_add_item_no_csrf
    test_update_status_no_csrf

    # Summary
    echo ""
    echo "========================================"
    echo "Test Summary"
    echo "========================================"
    echo -e "Total tests run: ${BLUE}${TESTS_RUN}${NC}"
    echo -e "Tests passed:    ${GREEN}${TESTS_PASSED}${NC}"
    echo -e "Tests failed:    ${RED}${TESTS_FAILED}${NC}"
    echo "========================================"

    if [ ${TESTS_FAILED} -eq 0 ]; then
        echo -e "${GREEN}All tests passed!${NC}"
        exit 0
    else
        echo -e "${RED}Some tests failed!${NC}"
        exit 1
    fi
}

# Run main
main
