#!/bin/bash
# Test Consignments Module Production Readiness
# Tests routes, controllers, freight integration, and error handling

echo "========================================="
echo "Testing Consignments Module"
echo "========================================="

BASE_URL="http://localhost/modules/consignments"
API_URL="http://localhost/modules/consignments/api"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

test_count=0
pass_count=0

# Test function
test_route() {
    local name="$1"
    local url="$2"
    local expected_code="${3:-200}"

    test_count=$((test_count + 1))

    response_code=$(curl -s -o /dev/null -w "%{http_code}" "$url")

    if [ "$response_code" == "$expected_code" ]; then
        echo -e "${GREEN}✓${NC} $name (HTTP $response_code)"
        pass_count=$((pass_count + 1))
    else
        echo -e "${RED}✗${NC} $name (Expected HTTP $expected_code, got $response_code)"
    fi
}

echo ""
echo "1. Testing Main Routes"
echo "-----------------------------------"
test_route "Home Dashboard" "$BASE_URL/"
test_route "Stock Transfers List" "$BASE_URL/?route=stock-transfers"
test_route "Purchase Orders List" "$BASE_URL/?route=purchase-orders"
test_route "Transfer Manager" "$BASE_URL/?route=transfer-manager"
test_route "Freight" "$BASE_URL/?route=freight"
test_route "404 Handler" "$BASE_URL/?route=invalid-route" "404"

echo ""
echo "2. Testing API Endpoints"
echo "-----------------------------------"
test_route "API Dashboard" "$API_URL/"
test_route "Stock Transfers API" "$API_URL/?endpoint=stock-transfers/list"
test_route "Purchase Orders API" "$API_URL/?endpoint=purchase-orders/list"
test_route "Invalid Endpoint" "$API_URL/?endpoint=invalid" "404"

echo ""
echo "3. Testing Controller Actions"
echo "-----------------------------------"
test_route "Stock Transfer Pack (no ID)" "$BASE_URL/?route=stock-transfers&action=pack" "500"
test_route "PO View (no ID)" "$BASE_URL/?route=purchase-orders&action=view" "500"
test_route "PO Create" "$BASE_URL/?route=purchase-orders&action=create"

echo ""
echo "========================================="
echo "Test Results: $pass_count/$test_count passed"
echo "========================================="

if [ $pass_count -eq $test_count ]; then
    echo -e "${GREEN}All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}Some tests failed.${NC}"
    exit 1
fi
