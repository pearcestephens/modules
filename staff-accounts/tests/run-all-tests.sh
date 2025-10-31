#!/bin/bash
# Master Test Runner for Phase 1: Urgent Staff Payment Verification
# Runs all critical tests to verify staff can be paid this week

set -e  # Exit on error

echo "════════════════════════════════════════════════════════════════"
echo "  🚨 PHASE 1: URGENT STAFF PAYMENT VERIFICATION"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Testing all critical systems for staff payment..."
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

TESTS_PASSED=0
TESTS_FAILED=0
CRITICAL_FAILURES=()

# Function to run test and track results
run_test() {
    local test_name=$1
    local test_command=$2
    local is_critical=${3:-false}

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "Testing: $test_name"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""

    if eval "$test_command"; then
        echo ""
        echo -e "${GREEN}✅ $test_name: PASSED${NC}"
        ((TESTS_PASSED++))
    else
        echo ""
        echo -e "${RED}❌ $test_name: FAILED${NC}"
        ((TESTS_FAILED++))

        if [ "$is_critical" = true ]; then
            CRITICAL_FAILURES+=("$test_name")
        fi
    fi

    echo ""
}

# Navigate to module directory
cd /home/master/applications/jcepnzzkmj/public_html/modules/staff-accounts/

# Create tests directory
mkdir -p tests

# ============================================================================
# TEST 1: Database Connectivity (CRITICAL)
# ============================================================================
run_test "Database Connectivity" "php tests/test-database.php" true

# ============================================================================
# TEST 2: Nuvei Payment Gateway (CRITICAL)
# ============================================================================
run_test "Nuvei Payment Gateway" "php tests/test-nuvei-connection.php" true

# ============================================================================
# TEST 3: API Endpoints
# ============================================================================
run_test "API Endpoints" "php tests/test-api-endpoints.php" false

# ============================================================================
# TEST 4: Xero Integration (CRITICAL)
# ============================================================================
run_test "Xero Integration" "php tests/test-xero-integration.php" true

# ============================================================================
# TEST 5: Deputy Integration (CRITICAL for payroll)
# ============================================================================
# Navigate to payroll module
cd /home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll/
run_test "Deputy Integration" "php tests/test-deputy-integration.php" true

# ============================================================================
# TEST 6: Payroll Database Schema (CRITICAL)
# ============================================================================
run_test "Payroll Database Schema" "php tests/test-payroll-schema.php" true

# ============================================================================
# FINAL SUMMARY
# ============================================================================
echo ""
echo "════════════════════════════════════════════════════════════════"
echo "  📊 TEST RESULTS SUMMARY"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Tests Passed: ${GREEN}$TESTS_PASSED${NC}"
echo "Tests Failed: ${RED}$TESTS_FAILED${NC}"
echo ""

if [ ${#CRITICAL_FAILURES[@]} -gt 0 ]; then
    echo -e "${RED}🚨 CRITICAL FAILURES:${NC}"
    for failure in "${CRITICAL_FAILURES[@]}"; do
        echo "   ❌ $failure"
    done
    echo ""
    echo -e "${RED}⚠️  WARNING: STAFF PAYMENT MAY NOT BE POSSIBLE${NC}"
    echo ""
    echo "Immediate actions required:"
    echo "1. Review failed tests above"
    echo "2. Fix critical issues"
    echo "3. Re-run this test suite"
    echo ""
    exit 1
else
    echo -e "${GREEN}✅ ALL CRITICAL SYSTEMS OPERATIONAL${NC}"
    echo ""
    echo "Staff payment workflow verified:"
    echo "✅ Database accessible"
    echo "✅ Payment gateway operational"
    echo "✅ Xero integration working"
    echo "✅ Deputy integration working"
    echo "✅ Payroll system ready"
    echo ""
    echo -e "${GREEN}🎉 STAFF CAN BE PAID THIS WEEK!${NC}"
    echo ""
fi

echo "════════════════════════════════════════════════════════════════"
echo "  Test suite complete"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Next steps:"
echo "1. Review any warnings above"
echo "2. Test payment flow manually if needed"
echo "3. Proceed with Phase 2 (API Migration)"
echo ""
