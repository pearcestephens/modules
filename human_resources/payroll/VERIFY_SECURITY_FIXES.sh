#!/bin/bash
#
# Security Lockdown Phase 1 - Deployment Verification Script
# Verifies all critical security fixes are in place
#
# Usage: bash VERIFY_SECURITY_FIXES.sh
#

echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║  🔒 SECURITY LOCKDOWN PHASE 1 - VERIFICATION                  ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counter
PASSED=0
FAILED=0

PAYROLL_DIR="/home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll"
CONFIG_DIR="/home/master/applications/jcepnzzkmj/public_html/modules/config"

# ============================================================================
# TEST 1: DATABASE CONFIG FILE EXISTS
# ============================================================================
echo -e "${YELLOW}TEST 1: Database Config File${NC}"
echo "─────────────────────────────────────────────────────────────────"

echo -n "  Checking for config/database.php... "
if [ -f "$CONFIG_DIR/database.php" ]; then
    echo -e "${GREEN}✅ PASS${NC}"
    ((PASSED++))
else
    echo -e "${RED}❌ FAIL${NC}"
    ((FAILED++))
fi

echo ""

# ============================================================================
# TEST 2: NO HARDCODED CREDENTIALS
# ============================================================================
echo -e "${YELLOW}TEST 2: No Hardcoded Database Credentials${NC}"
echo "─────────────────────────────────────────────────────────────────"

echo -n "  Checking index.php for hardcoded passwords... "
if grep -q "wprKh9Jq63" "$PAYROLL_DIR/index.php" 2>/dev/null; then
    echo -e "${RED}❌ FAIL${NC} (hardcoded password found)"
    ((FAILED++))
else
    echo -e "${GREEN}✅ PASS${NC}"
    ((PASSED++))
fi

echo -n "  Checking tests for hardcoded passwords... "
if grep -q "wprKh9Jq63" "$PAYROLL_DIR/tests/test_complete_integration.php" 2>/dev/null; then
    echo -e "${RED}❌ FAIL${NC} (hardcoded password found)"
    ((FAILED++))
else
    echo -e "${GREEN}✅ PASS${NC}"
    ((PASSED++))
fi

echo ""

# ============================================================================
# TEST 3: DEBUG GATING
# ============================================================================
echo -e "${YELLOW}TEST 3: Debug Output Gating${NC}"
echo "─────────────────────────────────────────────────────────────────"

echo -n "  Checking for environment-aware debug... "
if grep -q "appConfig.*debug" "$PAYROLL_DIR/index.php" 2>/dev/null; then
    echo -e "${GREEN}✅ PASS${NC}"
    ((PASSED++))
else
    echo -e "${RED}❌ FAIL${NC}"
    ((FAILED++))
fi

echo -n "  Checking debug is NOT always on... "
if grep -q "^ini_set('display_errors', '1');" "$PAYROLL_DIR/index.php" 2>/dev/null; then
    echo -e "${RED}❌ FAIL${NC} (debug always enabled)"
    ((FAILED++))
else
    echo -e "${GREEN}✅ PASS${NC}"
    ((PASSED++))
fi

echo ""

# ============================================================================
# TEST 4: PERMISSION SYSTEM
# ============================================================================
echo -e "${YELLOW}TEST 4: Permission System Enabled${NC}"
echo "─────────────────────────────────────────────────────────────────"

echo -n "  Checking BaseController::hasPermission()... "
if grep -q "TEMPORARILY DISABLED" "$PAYROLL_DIR/controllers/BaseController.php" 2>/dev/null; then
    echo -e "${RED}❌ FAIL${NC} (permissions still disabled)"
    ((FAILED++))
else
    echo -e "${GREEN}✅ PASS${NC}"
    ((PASSED++))
fi

echo -n "  Checking routes for permission enforcement... "
if grep -q "// 'permission'" "$PAYROLL_DIR/routes.php" 2>/dev/null; then
    echo -e "${RED}❌ FAIL${NC} (permissions commented out)"
    ((FAILED++))
else
    echo -e "${GREEN}✅ PASS${NC}"
    ((PASSED++))
fi

echo ""

# ============================================================================
# TEST 5: CONTROLLER METHODS
# ============================================================================
echo -e "${YELLOW}TEST 5: Controller API Methods${NC}"
echo "─────────────────────────────────────────────────────────────────"

echo -n "  Checking for jsonSuccess() method... "
if grep -q "function jsonSuccess" "$PAYROLL_DIR/controllers/BaseController.php" 2>/dev/null; then
    echo -e "${GREEN}✅ PASS${NC}"
    ((PASSED++))
else
    echo -e "${RED}❌ FAIL${NC}"
    ((FAILED++))
fi

echo -n "  Checking for jsonError() method... "
if grep -q "function jsonError" "$PAYROLL_DIR/controllers/BaseController.php" 2>/dev/null; then
    echo -e "${GREEN}✅ PASS${NC}"
    ((PASSED++))
else
    echo -e "${RED}❌ FAIL${NC}"
    ((FAILED++))
fi

echo ""

# ============================================================================
# TEST 6: PHP SYNTAX
# ============================================================================
echo -e "${YELLOW}TEST 6: PHP Syntax Validation${NC}"
echo "─────────────────────────────────────────────────────────────────"

FILES=(
    "$CONFIG_DIR/database.php"
    "$PAYROLL_DIR/index.php"
    "$PAYROLL_DIR/controllers/BaseController.php"
    "$PAYROLL_DIR/routes.php"
)

for file in "${FILES[@]}"; do
    filename=$(basename "$file")
    echo -n "  Checking $filename... "
    if php -l "$file" > /dev/null 2>&1; then
        echo -e "${GREEN}✅ PASS${NC}"
        ((PASSED++))
    else
        echo -e "${RED}❌ FAIL${NC}"
        ((FAILED++))
    fi
done

echo ""

# ============================================================================
# TEST 7: ENV FILE CHECK
# ============================================================================
echo -e "${YELLOW}TEST 7: Environment Configuration${NC}"
echo "─────────────────────────────────────────────────────────────────"

echo -n "  Checking for .env file... "
if [ -f "/home/master/applications/jcepnzzkmj/public_html/modules/.env" ]; then
    echo -e "${GREEN}✅ PASS${NC}"
    ((PASSED++))
else
    echo -e "${YELLOW}⚠️  WARNING${NC} (optional - using defaults)"
fi

echo ""

# ============================================================================
# SUMMARY
# ============================================================================
echo "═══════════════════════════════════════════════════════════════"
echo -e "${YELLOW}VERIFICATION SUMMARY${NC}"
echo "═══════════════════════════════════════════════════════════════"
echo "Total Tests: $((PASSED + FAILED))"
echo -e "${GREEN}Passed: $PASSED${NC}"
echo -e "${RED}Failed: $FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}╔═══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║  ✅ ALL SECURITY FIXES VERIFIED!                              ║${NC}"
    echo -e "${GREEN}║  Module is ready for production deployment.                   ║${NC}"
    echo -e "${GREEN}╚═══════════════════════════════════════════════════════════════╝${NC}"
    exit 0
else
    echo -e "${YELLOW}╔═══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${YELLOW}║  ⚠️  SOME CHECKS FAILED - REVIEW ERRORS ABOVE                 ║${NC}"
    echo -e "${YELLOW}╚═══════════════════════════════════════════════════════════════╝${NC}"
    exit 1
fi
