#!/bin/bash

# ============================================================================
# Notification & Messenger System - API Test Suite
# ============================================================================
# Test the API endpoints to verify everything works
# Usage: ./test_apis.sh
# ============================================================================

set -e

# Configuration
API_BASE="http://localhost/api"
BEARER_TOKEN="your_bearer_token_here"  # Replace with actual token

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Test counter
TESTS_PASSED=0
TESTS_FAILED=0

# Function to run a test
run_test() {
    local test_name=$1
    local method=$2
    local endpoint=$3
    local data=$4

    echo -e "\n${YELLOW}Testing:${NC} $test_name"
    echo "  Method: $method"
    echo "  Endpoint: $endpoint"

    if [ "$method" = "GET" ]; then
        response=$(curl -s -X GET \
            -H "Authorization: Bearer $BEARER_TOKEN" \
            -H "Content-Type: application/json" \
            "$API_BASE$endpoint")
    else
        response=$(curl -s -X POST \
            -H "Authorization: Bearer $BEARER_TOKEN" \
            -H "Content-Type: application/json" \
            -d "$data" \
            "$API_BASE$endpoint")
    fi

    echo "  Response: $response" | head -c 200

    # Check if response contains "success" or "error"
    if echo "$response" | grep -q '"success"'; then
        echo -e " ${GREEN}✓ PASS${NC}"
        ((TESTS_PASSED++))
    elif echo "$response" | grep -q "error\|Error"; then
        echo -e " ${RED}✗ FAIL${NC}"
        echo "  Error: $(echo $response | grep -o '"error":"[^"]*' | cut -d'"' -f4)"
        ((TESTS_FAILED++))
    else
        echo -e " ${YELLOW}? UNKNOWN${NC}"
    fi
}

# ============================================================================
# NOTIFICATION TESTS
# ============================================================================

echo ""
echo "=========================================="
echo "NOTIFICATION API TESTS"
echo "=========================================="

run_test "Get unread count" "GET" "/notifications/unread" ""

run_test "Get notifications list" "GET" "/notifications?limit=10" ""

run_test "Get preferences" "GET" "/notifications/preferences" ""

run_test "Create test notification" "POST" "/notifications/trigger" \
    '{"user_id":1,"category":"test","priority":"normal","title":"Test Notification","message":"This is a test"}'

run_test "Mark as read" "POST" "/notifications/1/read" ""

# ============================================================================
# MESSENGER TESTS
# ============================================================================

echo ""
echo "=========================================="
echo "MESSENGER API TESTS"
echo "=========================================="

run_test "Get conversations" "GET" "/messenger/conversations" ""

run_test "Create direct conversation" "POST" "/messenger/conversations" \
    '{"type":"direct","participant_id":2}'

run_test "Get conversation details" "GET" "/messenger/conversations/1" ""

run_test "Send message" "POST" "/messenger/conversations/1/messages" \
    '{"message_text":"Hello, this is a test message"}'

run_test "Get messages" "GET" "/messenger/conversations/1/messages?limit=10" ""

run_test "Update typing indicator" "POST" "/messenger/conversations/1/typing" \
    '{"is_typing":true}'

run_test "Add emoji reaction" "POST" "/messenger/messages/1/react" \
    '{"emoji":"👍","add":true}'

run_test "Search messages" "GET" "/messenger/messages/search?conversation_id=1&query=test" ""

# ============================================================================
# SUMMARY
# ============================================================================

echo ""
echo "=========================================="
echo "TEST SUMMARY"
echo "=========================================="
echo -e "Passed: ${GREEN}$TESTS_PASSED${NC}"
echo -e "Failed: ${RED}$TESTS_FAILED${NC}"
echo "Total:  $((TESTS_PASSED + TESTS_FAILED))"
echo "=========================================="

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}Some tests failed. Check the errors above.${NC}"
    exit 1
fi
