#!/bin/bash
# Design Studio Quick Test Script
# Run this to verify basic functionality

echo "🧪 DESIGN STUDIO - QUICK TEST SCRIPT"
echo "===================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test counter
PASS=0
FAIL=0

# Function to test
test_check() {
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓${NC} $1"
        ((PASS++))
    else
        echo -e "${RED}✗${NC} $1"
        ((FAIL++))
    fi
}

echo -e "${BLUE}📁 Checking File Structure...${NC}"
echo ""

# Test 1: Main file exists
test -f design-studio.php
test_check "design-studio.php exists"

# Test 2: PHP syntax valid
php -l design-studio.php > /dev/null 2>&1
test_check "PHP syntax valid"

# Test 3: Required directories exist
test -d config
test_check "config/ directory exists"

test -d css/custom
test_check "css/custom/ directory exists"

test -d components
test_check "components/ directory exists"

test -d css-versions
test_check "css-versions/ directory exists"

# Test 4: Directories writable
test -w config
test_check "config/ is writable"

test -w css/custom
test_check "css/custom/ is writable"

test -w components
test_check "components/ is writable"

test -w css-versions
test_check "css-versions/ is writable"

echo ""
echo -e "${BLUE}🔍 Checking CSS Architecture...${NC}"
echo ""

# Test 5: CSS files exist
test -f css/core/base.css
test_check "css/core/base.css exists"

test -f css/custom/theme.css
test_check "css/custom/theme.css exists"

echo ""
echo -e "${BLUE}📦 Checking Components...${NC}"
echo ""

# Test 6: Component files
COMPONENT_COUNT=$(ls -1 components/*.json 2>/dev/null | wc -l)
if [ $COMPONENT_COUNT -gt 0 ]; then
    echo -e "${GREEN}✓${NC} Found $COMPONENT_COUNT component(s)"
    ((PASS++))
else
    echo -e "${YELLOW}⚠${NC} No components found (expected - will be populated)"
fi

echo ""
echo -e "${BLUE}🌐 Checking Web Accessibility...${NC}"
echo ""

# Test 7: File permissions for web server
OWNER=$(stat -c '%U' design-studio.php)
if [[ "$OWNER" == "www-data" ]] || [[ "$OWNER" == "master_anjzctzjhr" ]]; then
    echo -e "${GREEN}✓${NC} File ownership correct ($OWNER)"
    ((PASS++))
else
    echo -e "${RED}✗${NC} File ownership may need adjustment ($OWNER)"
    ((FAIL++))
fi

# Test 8: File readable by web server
test -r design-studio.php
test_check "design-studio.php is readable"

echo ""
echo -e "${BLUE}🔧 Checking Code Quality...${NC}"
echo ""

# Test 9: Check for TODO comments
TODO_COUNT=$(grep -c "TODO:" design-studio.php)
echo -e "${YELLOW}ℹ${NC} Found $TODO_COUNT TODO comments (items to implement)"

# Test 10: Check for console.log (should be minimal in production)
CONSOLE_LOG=$(grep -c "console.log" design-studio.php)
if [ $CONSOLE_LOG -lt 5 ]; then
    echo -e "${GREEN}✓${NC} Console.log usage acceptable ($CONSOLE_LOG instances)"
    ((PASS++))
else
    echo -e "${YELLOW}⚠${NC} Many console.log found ($CONSOLE_LOG) - consider removing for production"
fi

# Test 11: Check for error handlers
ERROR_HANDLERS=$(grep -c "try\|catch" design-studio.php)
if [ $ERROR_HANDLERS -gt 0 ]; then
    echo -e "${GREEN}✓${NC} Error handling present ($ERROR_HANDLERS try/catch blocks)"
    ((PASS++))
else
    echo -e "${YELLOW}⚠${NC} Limited error handling detected"
fi

echo ""
echo -e "${BLUE}📏 File Size Analysis...${NC}"
echo ""

# Test 12: File size reasonable
FILE_SIZE=$(stat -f%z design-studio.php 2>/dev/null || stat -c%s design-studio.php)
FILE_SIZE_KB=$((FILE_SIZE / 1024))
echo -e "${GREEN}ℹ${NC} design-studio.php size: ${FILE_SIZE_KB}KB"

if [ $FILE_SIZE_KB -lt 100 ]; then
    echo -e "${GREEN}✓${NC} File size acceptable (< 100KB)"
    ((PASS++))
else
    echo -e "${YELLOW}⚠${NC} File size large (${FILE_SIZE_KB}KB) - consider optimization"
fi

echo ""
echo -e "${BLUE}🔗 Checking Dependencies...${NC}"
echo ""

# Test 13: Check for external dependencies
echo -e "${GREEN}ℹ${NC} Monaco Editor: CDN (unpkg.com)"
echo -e "${GREEN}ℹ${NC} Bootstrap: v4.6.2"
echo -e "${GREEN}ℹ${NC} jQuery: v3.6.0"
echo -e "${GREEN}ℹ${NC} FontAwesome: v6.4.0"

echo ""
echo "======================================"
echo -e "${GREEN}✓ PASSED: $PASS${NC}"
if [ $FAIL -gt 0 ]; then
    echo -e "${RED}✗ FAILED: $FAIL${NC}"
fi
echo "======================================"
echo ""

# Overall result
if [ $FAIL -eq 0 ]; then
    echo -e "${GREEN}🎉 ALL CRITICAL TESTS PASSED!${NC}"
    echo ""
    echo "✅ Ready for browser testing!"
    echo ""
    echo "Next steps:"
    echo "1. Open in browser: https://staff.vapeshed.co.nz/modules/admin-ui/design-studio.php"
    echo "2. Open DevTools (F12) and check Console tab"
    echo "3. Follow DESIGN_STUDIO_TEST_PLAN.md for comprehensive testing"
    echo ""
    exit 0
else
    echo -e "${YELLOW}⚠️  SOME TESTS FAILED${NC}"
    echo ""
    echo "Please review failures above and fix before browser testing."
    echo ""
    exit 1
fi
