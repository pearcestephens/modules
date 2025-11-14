#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# BYOK PROXY DEPLOYMENT SCRIPT
# ═══════════════════════════════════════════════════════════════════════════
#
# Safely deploys the new production-grade BYOK proxy
# - Backs up old version
# - Validates new version
# - Runs comprehensive tests
# - Performs atomic swap
# - Monitors for errors

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
API_DIR="$SCRIPT_DIR"
BACKUP_DIR="$SCRIPT_DIR/../../../../private_html/backups/byok_proxy"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}🚀 BYOK PROXY DEPLOYMENT${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

# Step 1: Create backup directory
echo -e "${YELLOW}[1/8]${NC} Creating backup directory..."
mkdir -p "$BACKUP_DIR"
echo -e "${GREEN}✓${NC} Backup directory ready: $BACKUP_DIR"
echo ""

# Step 2: Backup current version
echo -e "${YELLOW}[2/8]${NC} Backing up current version..."
if [ -f "$API_DIR/completions.php" ]; then
    cp "$API_DIR/completions.php" "$BACKUP_DIR/completions_${TIMESTAMP}.php"
    echo -e "${GREEN}✓${NC} Backed up to: $BACKUP_DIR/completions_${TIMESTAMP}.php"
else
    echo -e "${YELLOW}⚠${NC}  No existing completions.php found (first deployment)"
fi
echo ""

# Step 3: Validate new version syntax
echo -e "${YELLOW}[3/8]${NC} Validating PHP syntax..."
php -l "$API_DIR/completions_v2.php" > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} PHP syntax valid"
else
    echo -e "${RED}✗${NC} PHP syntax errors detected!"
    php -l "$API_DIR/completions_v2.php"
    exit 1
fi
echo ""

# Step 4: Check required dependencies
echo -e "${YELLOW}[4/8]${NC} Checking dependencies..."
MISSING_DEPS=0

if [ ! -f "$API_DIR/../../../mcp/includes/02_infrastructure.php" ]; then
    echo -e "${RED}✗${NC} Missing: mcp/includes/02_infrastructure.php"
    MISSING_DEPS=1
fi

if [ ! -f "$API_DIR/../../../.env" ]; then
    echo -e "${RED}✗${NC} Missing: .env file"
    MISSING_DEPS=1
fi

if [ $MISSING_DEPS -eq 1 ]; then
    echo -e "${RED}✗${NC} Missing required dependencies!"
    exit 1
fi

echo -e "${GREEN}✓${NC} All dependencies present"
echo ""

# Step 5: Run test suite
echo -e "${YELLOW}[5/8]${NC} Running test suite..."
echo -e "${BLUE}════════════════════════════════════════════════════════════════${NC}"

php "$API_DIR/test_proxy.php" 2>&1 | tee /tmp/byok_test_results.txt

# Check test results
if grep -q "ALL TESTS PASSED" /tmp/byok_test_results.txt; then
    echo -e "${BLUE}════════════════════════════════════════════════════════════════${NC}"
    echo -e "${GREEN}✓${NC} All tests passed!"
else
    echo -e "${BLUE}════════════════════════════════════════════════════════════════${NC}"
    echo -e "${RED}✗${NC} Some tests failed!"
    echo ""
    read -p "Continue deployment anyway? (y/N) " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${RED}Deployment aborted.${NC}"
        exit 1
    fi
fi
echo ""

# Step 6: Atomic deployment
echo -e "${YELLOW}[6/8]${NC} Deploying new version (atomic swap)..."

# Copy new version to temporary location
cp "$API_DIR/completions_v2.php" "$API_DIR/completions_new.php"

# Atomic rename
mv "$API_DIR/completions_new.php" "$API_DIR/completions.php"

echo -e "${GREEN}✓${NC} New version deployed atomically"
echo ""

# Step 7: Smoke test
echo -e "${YELLOW}[7/8]${NC} Running smoke test..."

SMOKE_TEST_RESULT=$(curl -s -o /dev/null -w "%{http_code}" \
    -X POST https://gpt.ecigdis.co.nz/api/v1/chat/completions \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $(grep MCP_API_KEY "$API_DIR/../../../.env" | cut -d= -f2)" \
    -d '{
        "model": "gpt-4o",
        "messages": [{"role": "user", "content": "Hi"}],
        "max_tokens": 5
    }')

if [ "$SMOKE_TEST_RESULT" == "200" ]; then
    echo -e "${GREEN}✓${NC} Smoke test passed (HTTP 200)"
else
    echo -e "${RED}✗${NC} Smoke test failed (HTTP $SMOKE_TEST_RESULT)"
    echo -e "${YELLOW}⚠${NC}  Rolling back..."

    if [ -f "$BACKUP_DIR/completions_${TIMESTAMP}.php" ]; then
        cp "$BACKUP_DIR/completions_${TIMESTAMP}.php" "$API_DIR/completions.php"
        echo -e "${GREEN}✓${NC} Rolled back to previous version"
    fi

    exit 1
fi
echo ""

# Step 8: Monitor for errors
echo -e "${YELLOW}[8/8]${NC} Monitoring for errors (10 seconds)..."

ERROR_LOG="/home/master/applications/hdgwrzntwa/logs/php-app.access.log"
INITIAL_ERRORS=$(grep -c "completions.php" "$ERROR_LOG" 2>/dev/null || echo "0")

sleep 10

FINAL_ERRORS=$(grep -c "completions.php" "$ERROR_LOG" 2>/dev/null || echo "0")
NEW_ERRORS=$((FINAL_ERRORS - INITIAL_ERRORS))

if [ $NEW_ERRORS -gt 0 ]; then
    echo -e "${RED}✗${NC} Detected $NEW_ERRORS new errors in logs!"
    echo -e "${YELLOW}⚠${NC}  Check logs: tail -f $ERROR_LOG"
else
    echo -e "${GREEN}✓${NC} No errors detected"
fi
echo ""

# Deployment summary
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}🎉 DEPLOYMENT COMPLETE${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "📁 Deployed:  $API_DIR/completions.php"
echo -e "💾 Backup:    $BACKUP_DIR/completions_${TIMESTAMP}.php"
echo -e "📊 Test logs: /tmp/byok_test_results.txt"
echo -e "🔍 Error log: $ERROR_LOG"
echo ""
echo -e "${GREEN}✓${NC} BYOK proxy is now LIVE!"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "1. Update VS Code settings.json with BYOK configuration"
echo "2. Monitor logs for any issues: tail -f $ERROR_LOG"
echo "3. Test in Copilot with: @ecigdis-intelligence test connection"
echo ""
