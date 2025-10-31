#!/bin/bash

###############################################################################
# CIS ADMIN UI - Complete Deployment Verification & Status Report
# Purpose: Verify all Phase 5 enhancements are properly deployed
# Usage: bash /modules/admin-ui/VERIFY_ALL.sh
# Status: ✅ PRODUCTION-READY VERIFICATION SUITE
###############################################################################

PROJECT_ROOT="/home/master/applications/jcepnzzkmj/public_html"
ADMIN_UI_PATH="$PROJECT_ROOT/modules/admin-ui"
DOMAIN="staff.vapeshed.co.nz"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0
WARNINGS=0

# Functions
test_passed() {
    echo -e "${GREEN}✅ $1${NC}"
    ((PASSED_TESTS++))
    ((TOTAL_TESTS++))
}

test_failed() {
    echo -e "${RED}❌ $1${NC}"
    ((FAILED_TESTS++))
    ((TOTAL_TESTS++))
}

test_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
    ((WARNINGS++))
}

test_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

###############################################################################
# SECTION 1: FILE VERIFICATION
###############################################################################

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║     CIS ADMIN UI DEPLOYMENT VERIFICATION REPORT               ║"
echo "║     Time: $TIMESTAMP"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo "SECTION 1: FILE VERIFICATION"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Test core files
declare -a REQUIRED_FILES=(
    "config.php"
    "index.php"
    "api/version-api.php"
    "api/ai-config-api.php"
    "js/theme-switcher.js"
    "js/ai-config-panel.js"
    "js/main-ui.js"
    "css/admin-ui-styles.css"
    "README_v1.md"
    "DEPLOYMENT_GUIDE.md"
)

echo "Checking required files..."
for file in "${REQUIRED_FILES[@]}"; do
    full_path="$ADMIN_UI_PATH/$file"
    if [ -f "$full_path" ]; then
        size=$(du -h "$full_path" | cut -f1)
        test_passed "Found: $file ($size)"
    else
        test_failed "Missing: $file"
    fi
done

echo ""

###############################################################################
# SECTION 2: PHP SYNTAX VERIFICATION
###############################################################################

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo "SECTION 2: PHP SYNTAX VERIFICATION"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

declare -a PHP_FILES=(
    "config.php"
    "index.php"
    "api/version-api.php"
    "api/ai-config-api.php"
)

echo "Checking PHP syntax..."
for file in "${PHP_FILES[@]}"; do
    full_path="$ADMIN_UI_PATH/$file"
    if php -l "$full_path" > /dev/null 2>&1; then
        test_passed "Syntax OK: $file"
    else
        test_failed "Syntax ERROR: $file"
        php -l "$full_path" 2>&1 | grep -v "^No syntax"
    fi
done

echo ""

###############################################################################
# SECTION 3: FILE PERMISSIONS
###############################################################################

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo "SECTION 3: FILE PERMISSIONS"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

echo "Checking file permissions..."
if [ -r "$ADMIN_UI_PATH/config.php" ]; then
    test_passed "config.php is readable"
else
    test_failed "config.php is NOT readable"
fi

if [ -r "$ADMIN_UI_PATH/css/admin-ui-styles.css" ]; then
    test_passed "CSS file is readable"
else
    test_failed "CSS file is NOT readable"
fi

if [ -r "$ADMIN_UI_PATH/js/theme-switcher.js" ]; then
    test_passed "JavaScript files are readable"
else
    test_failed "JavaScript files are NOT readable"
fi

if [ -w "$ADMIN_UI_PATH" ]; then
    test_passed "admin-ui directory is writable"
else
    test_warning "admin-ui directory is NOT writable (may affect logging)"
fi

echo ""

###############################################################################
# SECTION 4: WEB ACCESSIBILITY
###############################################################################

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo "SECTION 4: WEB ACCESSIBILITY"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

echo "Testing HTTPS connectivity to $DOMAIN..."

# Main page
if curl -I -s -k https://$DOMAIN/modules/admin-ui/index.php 2>/dev/null | grep -q "200"; then
    test_passed "Admin UI index.php accessible (HTTP 200)"
else
    test_failed "Admin UI index.php not accessible"
fi

# Version API
if curl -I -s -k https://$DOMAIN/modules/admin-ui/api/version-api.php 2>/dev/null | grep -q "200"; then
    test_passed "Version API accessible (HTTP 200)"
else
    test_failed "Version API not accessible"
fi

# AI Config API
if curl -I -s -k https://$DOMAIN/modules/admin-ui/api/ai-config-api.php 2>/dev/null | grep -q "200"; then
    test_passed "AI Config API accessible (HTTP 200)"
else
    test_failed "AI Config API not accessible"
fi

echo ""

###############################################################################
# SECTION 5: API FUNCTIONALITY
###############################################################################

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo "SECTION 5: API FUNCTIONALITY"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

echo "Testing Version API endpoints..."

# Test version info
version=$(curl -s -k "https://$DOMAIN/modules/admin-ui/api/version-api.php?action=info" 2>/dev/null | grep -o '"version":"[^"]*' | cut -d'"' -f4)
if [ "$version" = "1.0.0" ]; then
    test_passed "Version API (info): Returns correct version $version"
else
    test_failed "Version API (info): Expected 1.0.0, got $version"
fi

# Test changelog
changelog=$(curl -s -k "https://$DOMAIN/modules/admin-ui/api/version-api.php?action=changelog" 2>/dev/null | grep -o '"changelog"' | wc -l)
if [ "$changelog" -gt 0 ]; then
    test_passed "Version API (changelog): Returns changelog data"
else
    test_failed "Version API (changelog): No data returned"
fi

# Test features
features=$(curl -s -k "https://$DOMAIN/modules/admin-ui/api/version-api.php?action=features" 2>/dev/null | grep -o '"validation"' | wc -l)
if [ "$features" -gt 0 ]; then
    test_passed "Version API (features): Returns features list"
else
    test_failed "Version API (features): No data returned"
fi

# Test system status
status=$(curl -s -k "https://$DOMAIN/modules/admin-ui/api/version-api.php?action=system_status" 2>/dev/null | grep -o '"status"' | wc -l)
if [ "$status" -gt 0 ]; then
    test_passed "Version API (system_status): Returns system status"
else
    test_failed "Version API (system_status): No data returned"
fi

echo ""
echo "Testing AI Config API endpoints..."

# Test list
agents=$(curl -s -k "https://$DOMAIN/modules/admin-ui/api/ai-config-api.php?action=list" 2>/dev/null | grep -o '"name"' | wc -l)
if [ "$agents" -ge 3 ]; then
    test_passed "AI Config API (list): Returns $agents agents"
else
    test_failed "AI Config API (list): Expected 3+ agents, got $agents"
fi

# Test config
config=$(curl -s -k "https://$DOMAIN/modules/admin-ui/api/ai-config-api.php?action=config" 2>/dev/null | grep -o '"themes"' | wc -l)
if [ "$config" -gt 0 ]; then
    test_passed "AI Config API (config): Returns configuration"
else
    test_failed "AI Config API (config): No data returned"
fi

echo ""

###############################################################################
# SECTION 6: CONFIGURATION VERIFICATION
###############################################################################

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo "SECTION 6: CONFIGURATION VERIFICATION"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

echo "Checking configuration..."

# Check if config.php defines required constants
if grep -q "VERSION" "$ADMIN_UI_PATH/config.php"; then
    test_passed "config.php defines VERSION constant"
else
    test_failed "config.php missing VERSION constant"
fi

if grep -q "BUILD_ID" "$ADMIN_UI_PATH/config.php"; then
    test_passed "config.php defines BUILD_ID constant"
else
    test_failed "config.php missing BUILD_ID constant"
fi

# Check themes
themes=$(grep -c "'.*' => \[" "$ADMIN_UI_PATH/config.php")
if [ "$themes" -ge 3 ]; then
    test_passed "config.php defines $themes themes"
else
    test_warning "config.php defines $themes themes (expected 3+)"
fi

# Check AI agents
if grep -q "'openai'" "$ADMIN_UI_PATH/config.php"; then
    test_passed "config.php includes OpenAI agent"
else
    test_failed "config.php missing OpenAI agent"
fi

if grep -q "'local'" "$ADMIN_UI_PATH/config.php"; then
    test_passed "config.php includes Local agent"
else
    test_failed "config.php missing Local agent"
fi

echo ""

###############################################################################
# SECTION 7: CSS & JAVASCRIPT VALIDATION
###############################################################################

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo "SECTION 7: CSS & JAVASCRIPT VALIDATION"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

echo "Checking CSS..."

# Check for CSS variables
if grep -q "^:root {" "$ADMIN_UI_PATH/css/admin-ui-styles.css"; then
    test_passed "CSS defines root variables"
else
    test_failed "CSS missing root variables"
fi

# Check for theme variants
if grep -q ":root.theme-light" "$ADMIN_UI_PATH/css/admin-ui-styles.css"; then
    test_passed "CSS includes light theme"
else
    test_failed "CSS missing light theme"
fi

if grep -q ":root.theme-high-contrast" "$ADMIN_UI_PATH/css/admin-ui-styles.css"; then
    test_passed "CSS includes high-contrast theme"
else
    test_failed "CSS missing high-contrast theme"
fi

# Check CSS file size
css_size=$(wc -l < "$ADMIN_UI_PATH/css/admin-ui-styles.css")
if [ "$css_size" -gt 500 ]; then
    test_passed "CSS file has $css_size lines (comprehensive)"
else
    test_warning "CSS file has only $css_size lines (may be incomplete)"
fi

echo ""
echo "Checking JavaScript..."

# Check for ThemeSwitcher class
if grep -q "class ThemeSwitcher" "$ADMIN_UI_PATH/js/theme-switcher.js"; then
    test_passed "theme-switcher.js defines ThemeSwitcher class"
else
    test_failed "theme-switcher.js missing ThemeSwitcher class"
fi

# Check for AIConfigPanel class
if grep -q "class AIConfigPanel" "$ADMIN_UI_PATH/js/ai-config-panel.js"; then
    test_passed "ai-config-panel.js defines AIConfigPanel class"
else
    test_failed "ai-config-panel.js missing AIConfigPanel class"
fi

# Check for AdminUI class
if grep -q "class AdminUI" "$ADMIN_UI_PATH/js/main-ui.js"; then
    test_passed "main-ui.js defines AdminUI class"
else
    test_failed "main-ui.js missing AdminUI class"
fi

# Check file sizes
js_sizes=$(wc -l "$ADMIN_UI_PATH/js"/*.js | tail -1 | awk '{print $1}')
if [ "$js_sizes" -gt 900 ]; then
    test_passed "JavaScript files total $js_sizes lines"
else
    test_warning "JavaScript files total only $js_sizes lines (may be incomplete)"
fi

echo ""

###############################################################################
# SECTION 8: INTEGRATION CHECK
###############################################################################

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo "SECTION 8: INTEGRATION CHECK"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

echo "Checking component integration..."

# Check if index.php includes new stylesheets
if grep -q "admin-ui-styles.css" "$ADMIN_UI_PATH/index.php"; then
    test_passed "index.php includes new CSS"
else
    test_failed "index.php doesn't include new CSS"
fi

# Check if index.php loads JavaScript
if grep -q "theme-switcher.js" "$ADMIN_UI_PATH/index.php" || grep -q "main-ui.js" "$ADMIN_UI_PATH/index.php"; then
    test_passed "index.php loads JavaScript components"
else
    test_warning "index.php may not load all JavaScript"
fi

# Check if APIs are accessible from index
if grep -q "version-api\|ai-config-api" "$ADMIN_UI_PATH/js/main-ui.js"; then
    test_passed "main-ui.js references both APIs"
else
    test_warning "main-ui.js may not reference all APIs"
fi

echo ""

###############################################################################
# SECTION 9: SUMMARY & STATUS
###############################################################################

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo "DEPLOYMENT SUMMARY"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Calculate percentages
if [ "$TOTAL_TESTS" -gt 0 ]; then
    PASS_PERCENT=$((PASSED_TESTS * 100 / TOTAL_TESTS))
    FAIL_PERCENT=$((FAILED_TESTS * 100 / TOTAL_TESTS))
else
    PASS_PERCENT=0
    FAIL_PERCENT=0
fi

echo "Total Tests Run:    $TOTAL_TESTS"
echo -e "Passed:             ${GREEN}$PASSED_TESTS ($PASS_PERCENT%)${NC}"
echo -e "Failed:             ${RED}$FAILED_TESTS ($FAIL_PERCENT%)${NC}"
echo -e "Warnings:           ${YELLOW}$WARNINGS${NC}"
echo ""

# Final status
if [ "$FAILED_TESTS" -eq 0 ]; then
    echo -e "${GREEN}╔═══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║          ✅ DEPLOYMENT SUCCESSFUL & PRODUCTION-READY          ║${NC}"
    echo -e "${GREEN}║                All Systems Operational                        ║${NC}"
    echo -e "${GREEN}╚═══════════════════════════════════════════════════════════════╝${NC}"
    EXIT_CODE=0
else
    echo -e "${RED}╔═══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║         ❌ DEPLOYMENT HAS ISSUES - Review Above              ║${NC}"
    echo -e "${RED}║              $FAILED_TESTS test(s) failed                         ║${NC}"
    echo -e "${RED}╚═══════════════════════════════════════════════════════════════╝${NC}"
    EXIT_CODE=1
fi

echo ""
echo "Generated: $TIMESTAMP"
echo "Report file can be saved with: bash VERIFY_ALL.sh > verification-report.txt"
echo ""

exit $EXIT_CODE
