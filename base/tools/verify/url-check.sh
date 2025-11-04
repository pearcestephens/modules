#!/bin/bash
# ============================================================================
# CIS Base Module - URL Verification Suite
# Tests all admin endpoints for correct status codes and behavior
# ============================================================================

set -e

# Configuration
BASE_URL="${1:-https://staff.vapeshed.co.nz/modules/base}"
ADMIN_TOKEN="${2:-}"
COLORS=1

# Colors
if [ "$COLORS" = "1" ]; then
    RED='\033[0;31m'
    GREEN='\033[0;32m'
    YELLOW='\033[1;33m'
    BLUE='\033[0;34m'
    NC='\033[0m' # No Color
else
    RED=''
    GREEN=''
    YELLOW=''
    BLUE=''
    NC=''
fi

# Stats
TOTAL=0
PASSED=0
FAILED=0

echo -e "${BLUE}╔════════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║        CIS Base Module - URL Verification Suite v1.0              ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${YELLOW}Base URL:${NC} $BASE_URL"
echo -e "${YELLOW}Date:${NC} $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# Helper function to test URL
test_url() {
    local endpoint="$1"
    local expected_code="$2"
    local method="${3:-GET}"
    local description="$4"
    local with_auth="${5:-0}"

    TOTAL=$((TOTAL + 1))

    local url="${BASE_URL}/public/index.php?endpoint=${endpoint}"
    local headers=""

    if [ "$with_auth" = "1" ] && [ -n "$ADMIN_TOKEN" ]; then
        headers="-H \"Authorization: Bearer $ADMIN_TOKEN\""
    fi

    local cmd="curl -s -o /dev/null -w \"%{http_code}\" -X $method $headers \"$url\""
    local status=$(eval $cmd)

    if [ "$status" = "$expected_code" ]; then
        echo -e "  ${GREEN}✓${NC} [$status] $endpoint - $description"
        PASSED=$((PASSED + 1))
    else
        echo -e "  ${RED}✗${NC} [$status] $endpoint - $description (expected $expected_code)"
        FAILED=$((FAILED + 1))
    fi
}

# ============================================================================
# TEST SUITE
# ============================================================================

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}Health Endpoints (Public)${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

test_url "admin/health/ping" "200" "GET" "Health ping"
test_url "admin/health/checks" "200" "GET" "Health checks JSON"

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}Traffic Monitoring Endpoints (Require Auth)${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

test_url "admin/traffic/monitor" "401" "GET" "Traffic monitor (no auth)"
test_url "admin/traffic/stats" "401" "GET" "Traffic stats (no auth)"
test_url "admin/performance/dashboard" "401" "GET" "Performance dashboard (no auth)"

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}API Testing Endpoints (Require Auth)${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

test_url "admin/testing/webhook-lab" "401" "GET" "Webhook lab (no auth)"
test_url "admin/testing/vend-api" "401" "GET" "Vend API tester (no auth)"
test_url "admin/testing/api-endpoints" "401" "GET" "API endpoint tester (no auth)"

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}404 Handling${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

test_url "nonexistent" "404" "GET" "Nonexistent endpoint"
test_url "admin/fake/route" "404" "GET" "Fake admin route"

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}Method Validation${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

test_url "admin/health/ping" "200" "GET" "GET ping (allowed)"
test_url "admin/health/ping" "405" "POST" "POST ping (not allowed)"

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}Rate Limiting (Burst Test)${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -e "  Testing rate limit... (sending 65 requests)"
RATE_LIMIT_TRIGGERED=0
for i in {1..65}; do
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/public/index.php?endpoint=admin/health/ping")
    if [ "$STATUS" = "429" ]; then
        echo -e "  ${GREEN}✓${NC} Rate limit triggered at request $i"
        RATE_LIMIT_TRIGGERED=1
        PASSED=$((PASSED + 1))
        break
    fi
done

TOTAL=$((TOTAL + 1))
if [ "$RATE_LIMIT_TRIGGERED" = "0" ]; then
    echo -e "  ${YELLOW}⚠${NC} Rate limit not triggered after 65 requests (might be disabled)"
fi

# ============================================================================
# SUMMARY
# ============================================================================

echo ""
echo -e "${BLUE}╔════════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║                          TEST SUMMARY                              ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  ${YELLOW}Total Tests:${NC}  $TOTAL"
echo -e "  ${GREEN}Passed:${NC}       $PASSED"
echo -e "  ${RED}Failed:${NC}       $FAILED"
echo ""

if [ "$FAILED" -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}✗ Some tests failed!${NC}"
    exit 1
fi
