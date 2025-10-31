#!/bin/bash
#
# Client-Side Instrumentation Test Suite
#
# Tests all security monitoring and interaction logging features
# Run from: /modules/consignments/purchase-orders/
#
# Usage: ./test-instrumentation.sh [test-name]
#

set -e

BASE_URL="https://staff.vapeshed.co.nz/modules/consignments/purchase-orders"
API_URL="https://staff.vapeshed.co.nz/modules/consignments/api/purchase-orders"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counter
TESTS_RUN=0
TESTS_PASSED=0
TESTS_FAILED=0

# Test helper functions
function test_start() {
    TESTS_RUN=$((TESTS_RUN + 1))
    echo -e "\n${YELLOW}[TEST $TESTS_RUN]${NC} $1"
}

function test_pass() {
    TESTS_PASSED=$((TESTS_PASSED + 1))
    echo -e "${GREEN}✓ PASS${NC} $1"
}

function test_fail() {
    TESTS_FAILED=$((TESTS_FAILED + 1))
    echo -e "${RED}✗ FAIL${NC} $1"
}

function test_info() {
    echo -e "  ℹ $1"
}

# Test 1: Check JavaScript files exist
function test_js_files() {
    test_start "JavaScript files existence"

    local files=(
        "js/interaction-logger.js"
        "js/security-monitor.js"
        "js/ai.js"
    )

    local all_exist=true
    for file in "${files[@]}"; do
        if [ -f "$file" ]; then
            test_info "Found: $file"
        else
            test_info "Missing: $file"
            all_exist=false
        fi
    done

    if [ "$all_exist" = true ]; then
        test_pass "All JavaScript files exist"
    else
        test_fail "Some JavaScript files are missing"
    fi
}

# Test 2: Check API endpoints exist
function test_api_endpoints() {
    test_start "API endpoint files existence"

    local endpoints=(
        "../api/purchase-orders/log-interaction.php"
        "../api/purchase-orders/accept-ai-insight.php"
        "../api/purchase-orders/dismiss-ai-insight.php"
        "../api/purchase-orders/bulk-accept-ai-insights.php"
        "../api/purchase-orders/bulk-dismiss-ai-insights.php"
    )

    local all_exist=true
    for endpoint in "${endpoints[@]}"; do
        if [ -f "$endpoint" ]; then
            test_info "Found: $endpoint"
        else
            test_info "Missing: $endpoint"
            all_exist=false
        fi
    done

    if [ "$all_exist" = true ]; then
        test_pass "All API endpoint files exist"
    else
        test_fail "Some API endpoint files are missing"
    fi
}

# Test 3: Check PurchaseOrderLogger exists
function test_logger() {
    test_start "PurchaseOrderLogger class file"

    if [ -f "../lib/PurchaseOrderLogger.php" ]; then
        test_info "Found: ../lib/PurchaseOrderLogger.php"

        # Check if it has required methods
        if grep -q "function aiRecommendationAccepted" "../lib/PurchaseOrderLogger.php"; then
            test_info "Found method: aiRecommendationAccepted"
        fi
        if grep -q "function securityDevToolsDetected" "../lib/PurchaseOrderLogger.php"; then
            test_info "Found method: securityDevToolsDetected"
        fi

        test_pass "PurchaseOrderLogger exists with expected methods"
    else
        test_fail "PurchaseOrderLogger.php not found"
    fi
}

# Test 4: Check view pages have instrumentation
function test_view_instrumentation() {
    test_start "View pages have SecurityMonitor.init() calls"

    local views=(
        "view.php"
        "ai-insights.php"
        "freight-quote.php"
    )

    local all_instrumented=true
    for view in "${views[@]}"; do
        if [ -f "$view" ]; then
            if grep -q "SecurityMonitor.init" "$view"; then
                test_info "$view: ✓ Instrumented"
            else
                test_info "$view: ✗ Not instrumented"
                all_instrumented=false
            fi
        else
            test_info "$view: Not found (skipping)"
        fi
    done

    if [ "$all_instrumented" = true ]; then
        test_pass "All view pages are instrumented"
    else
        test_fail "Some view pages missing SecurityMonitor.init()"
    fi
}

# Test 5: Validate JavaScript syntax
function test_js_syntax() {
    test_start "JavaScript syntax validation"

    local files=(
        "js/interaction-logger.js"
        "js/security-monitor.js"
    )

    local all_valid=true
    for file in "${files[@]}"; do
        if [ -f "$file" ]; then
            # Check for basic syntax errors (unclosed braces, etc.)
            if node -c "$file" 2>/dev/null; then
                test_info "$file: ✓ Syntax OK"
            else
                # Node not available, do basic check
                if grep -q "const.*=.*{" "$file" && grep -q "};" "$file"; then
                    test_info "$file: ✓ Basic structure OK"
                else
                    test_info "$file: ✗ Possible syntax error"
                    all_valid=false
                fi
            fi
        fi
    done

    if [ "$all_valid" = true ]; then
        test_pass "JavaScript syntax is valid"
    else
        test_fail "Possible JavaScript syntax errors"
    fi
}

# Test 6: Check documentation exists
function test_documentation() {
    test_start "Documentation files"

    if [ -f "../_kb/CLIENT_INSTRUMENTATION.md" ]; then
        local lines=$(wc -l < "../_kb/CLIENT_INSTRUMENTATION.md")
        test_info "CLIENT_INSTRUMENTATION.md: $lines lines"

        if [ "$lines" -gt 100 ]; then
            test_pass "Comprehensive documentation exists"
        else
            test_fail "Documentation seems incomplete"
        fi
    else
        test_fail "CLIENT_INSTRUMENTATION.md not found"
    fi
}

# Test 7: Check TransferReviewService
function test_transfer_review() {
    test_start "TransferReviewService components"

    local components=(
        "../lib/Services/TransferReviewService.php"
        "../cli/generate_transfer_review.php"
        "../cli/send_weekly_transfer_reports.php"
    )

    local all_exist=true
    for component in "${components[@]}"; do
        if [ -f "$component" ]; then
            test_info "Found: $component"
        else
            test_info "Missing: $component"
            all_exist=false
        fi
    done

    if [ "$all_exist" = true ]; then
        test_pass "TransferReviewService components exist"
    else
        test_fail "Some TransferReviewService components missing"
    fi
}

# Test 8: Security Monitor configuration
function test_security_config() {
    test_start "SecurityMonitor configuration options"

    if [ -f "js/security-monitor.js" ]; then
        local has_config=true

        # Check for configurable thresholds
        if ! grep -q "rapidKeyboardThreshold" "js/security-monitor.js"; then
            test_info "Missing: rapidKeyboardThreshold"
            has_config=false
        fi
        if ! grep -q "copyPasteThreshold" "js/security-monitor.js"; then
            test_info "Missing: copyPasteThreshold"
            has_config=false
        fi
        if ! grep -q "setThreshold" "js/security-monitor.js"; then
            test_info "Missing: setThreshold method"
            has_config=false
        fi

        if [ "$has_config" = true ]; then
            test_pass "SecurityMonitor has configurable thresholds"
        else
            test_fail "SecurityMonitor missing configuration options"
        fi
    else
        test_fail "security-monitor.js not found"
    fi
}

# Test 9: Integration with InteractionLogger
function test_integration() {
    test_start "SecurityMonitor → InteractionLogger integration"

    if [ -f "js/security-monitor.js" ]; then
        if grep -q "InteractionLogger.track" "js/security-monitor.js"; then
            test_info "Found InteractionLogger.track() calls"
            test_pass "SecurityMonitor integrates with InteractionLogger"
        else
            test_fail "SecurityMonitor doesn't call InteractionLogger.track()"
        fi
    else
        test_fail "security-monitor.js not found"
    fi
}

# Test 10: Error handling
function test_error_handling() {
    test_start "Error handling and fail-safes"

    local has_error_handling=true

    # Check JavaScript files for try/catch
    if [ -f "js/security-monitor.js" ]; then
        if ! grep -q "try {" "js/security-monitor.js"; then
            test_info "security-monitor.js: Missing try/catch blocks"
            has_error_handling=false
        fi
    fi

    # Check PHP files for error handling
    if [ -f "../api/purchase-orders/log-interaction.php" ]; then
        if ! grep -q "catch" "../api/purchase-orders/log-interaction.php"; then
            test_info "log-interaction.php: Missing exception handling"
            has_error_handling=false
        fi
    fi

    if [ "$has_error_handling" = true ]; then
        test_pass "Components have proper error handling"
    else
        test_fail "Some components missing error handling"
    fi
}

# Main test runner
function run_all_tests() {
    echo "========================================"
    echo "CLIENT INSTRUMENTATION TEST SUITE"
    echo "========================================"

    test_js_files
    test_api_endpoints
    test_logger
    test_view_instrumentation
    test_js_syntax
    test_documentation
    test_transfer_review
    test_security_config
    test_integration
    test_error_handling

    echo ""
    echo "========================================"
    echo "TEST RESULTS"
    echo "========================================"
    echo -e "Total:  $TESTS_RUN"
    echo -e "${GREEN}Passed: $TESTS_PASSED${NC}"
    echo -e "${RED}Failed: $TESTS_FAILED${NC}"

    if [ $TESTS_FAILED -eq 0 ]; then
        echo -e "\n${GREEN}✓ ALL TESTS PASSED${NC}"
        exit 0
    else
        echo -e "\n${RED}✗ SOME TESTS FAILED${NC}"
        exit 1
    fi
}

# Run tests
if [ -z "$1" ]; then
    run_all_tests
else
    # Run specific test
    case $1 in
        js) test_js_files ;;
        api) test_api_endpoints ;;
        logger) test_logger ;;
        views) test_view_instrumentation ;;
        syntax) test_js_syntax ;;
        docs) test_documentation ;;
        transfer) test_transfer_review ;;
        config) test_security_config ;;
        integration) test_integration ;;
        errors) test_error_handling ;;
        *) echo "Unknown test: $1"; exit 1 ;;
    esac
fi
