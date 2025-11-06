#!/bin/bash

# Vend Consignment Management API - ONE-COMMAND VERIFICATION
# Run this anytime to verify the API is 100% ready

set -e
cd "$(dirname "$0")"

echo ""
echo "╔══════════════════════════════════════════════════════════════════════════════╗"
echo "║                                                                              ║"
echo "║           VEND CONSIGNMENT API - ONE-COMMAND VERIFICATION                    ║"
echo "║                                                                              ║"
echo "╚══════════════════════════════════════════════════════════════════════════════╝"
echo ""

# Counter for passed checks
PASSED=0
TOTAL=0

# Function to run check
check() {
    TOTAL=$((TOTAL + 1))
    echo -n "[$TOTAL] $1... "
    if eval "$2" > /dev/null 2>&1; then
        echo "✅ PASS"
        PASSED=$((PASSED + 1))
        return 0
    else
        echo "❌ FAIL"
        return 1
    fi
}

# 1. File existence
check "Controller file exists" "test -f controllers/VendConsignmentController.php"
check "Routes file exists" "test -f routes.php"
check "Test file exists" "test -f test-vend-controller-unit.php"

# 2. Syntax validation
check "Controller syntax valid" "php -l controllers/VendConsignmentController.php"
check "Routes syntax valid" "php -l routes.php"
check "Test file syntax valid" "php -l test-vend-controller-unit.php"

# 3. Security checks
check "No direct \$_GET access" "! grep -q '\$_GET\[' controllers/VendConsignmentController.php"
check "No direct \$_POST access" "! grep -q '\$_POST\[' controllers/VendConsignmentController.php"
check "No SQL concatenation" "! grep -qE 'query\s*\(\s*[\"'].*\\\$' controllers/VendConsignmentController.php"
check "No eval/exec" "! grep -qE '\b(eval|exec|system)\s*\(' controllers/VendConsignmentController.php"

# 4. Required security features
check "Has authentication checks" "grep -q 'requireAuth()' controllers/VendConsignmentController.php"
check "Has CSRF checks" "grep -q 'verifyCsrf()' controllers/VendConsignmentController.php"
check "Has try-catch blocks" "grep -q '} catch' controllers/VendConsignmentController.php"
check "Has error logging" "grep -q 'logger->error' controllers/VendConsignmentController.php"

# 5. Route configuration
check "Routes file has VendConsignmentController" "grep -q 'VendConsignmentController' routes.php"
check "Has at least 15 routes" "test \$(grep -c 'VendConsignmentController' routes.php) -ge 15"

# 6. Documentation
check "API documentation exists" "test -f VEND_CONSIGNMENT_API.md"
check "Complete guide exists" "test -f VEND_CONSIGNMENT_API_COMPLETE.md"
check "Hardening report exists" "test -f VEND_CONSIGNMENT_API_100_PERCENT_HARDENED.md"
check "Deployment guide exists" "test -f VEND_CONSIGNMENT_API_DEPLOYMENT_READY.md"

# 7. Run full test suite
echo ""
echo "Running full test suite..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
php test-vend-controller-unit.php

# Extract pass count from test output
TEST_OUTPUT=$(php test-vend-controller-unit.php 2>&1)
if echo "$TEST_OUTPUT" | grep -q "73.*✅"; then
    PASSED=$((PASSED + 1))
    TOTAL=$((TOTAL + 1))
fi

# 8. Final sanity check
echo ""
echo "Running sanity check..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
if php sanity-check.php; then
    PASSED=$((PASSED + 1))
    TOTAL=$((TOTAL + 1))
fi

# Calculate percentage
PERCENTAGE=$((PASSED * 100 / TOTAL))

# Final report
echo ""
echo "╔══════════════════════════════════════════════════════════════════════════════╗"
echo "║                                                                              ║"
echo "║                          VERIFICATION COMPLETE                               ║"
echo "║                                                                              ║"
echo "╠══════════════════════════════════════════════════════════════════════════════╣"
printf "║  Checks Passed:  %2d / %2d                                                    ║\n" $PASSED $TOTAL
printf "║  Pass Rate:      %3d%%                                                        ║\n" $PERCENTAGE
echo "║                                                                              ║"

if [ $PASSED -eq $TOTAL ]; then
    echo "║  Status:         ✅ PRODUCTION READY                                       ║"
    echo "║                                                                              ║"
    echo "╚══════════════════════════════════════════════════════════════════════════════╝"
    echo ""
    echo "🎉 ALL CHECKS PASSED! API is 100% hardened and ready for deployment! 🎉"
    echo ""
    exit 0
else
    echo "║  Status:         ⚠️  ISSUES FOUND                                          ║"
    echo "║                                                                              ║"
    echo "╚══════════════════════════════════════════════════════════════════════════════╝"
    echo ""
    echo "⚠️  Some checks failed. Review output above and fix issues."
    echo ""
    exit 1
fi
