#!/bin/bash
#
# Comprehensive API Test Runner
# Runs all tests and generates detailed reports
#

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
MODULE_DIR="$(dirname "$SCRIPT_DIR")"
TESTS_DIR="$SCRIPT_DIR/api"
LOGS_DIR="$MODULE_DIR/_logs"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}╔════════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║        CONSIGNMENTS MODULE - API TEST SUITE RUNNER                 ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Create logs directory if not exists
mkdir -p "$LOGS_DIR"

# Check PHP version
PHP_VERSION=$(php -r 'echo PHP_VERSION;')
echo -e "${BLUE}PHP Version:${NC} $PHP_VERSION"

# Check required extensions
echo -e "\n${BLUE}Checking PHP Extensions:${NC}"
REQUIRED_EXTS=("curl" "json" "pdo" "pdo_mysql" "mbstring")
for ext in "${REQUIRED_EXTS[@]}"; do
    if php -m | grep -q "^$ext$"; then
        echo -e "  ✅ $ext"
    else
        echo -e "  ${RED}❌ $ext (MISSING!)${NC}"
        exit 1
    fi
done

# Run PHPUnit tests if available
if [ -f "$MODULE_DIR/vendor/bin/phpunit" ]; then
    echo -e "\n${BLUE}Running PHPUnit Tests...${NC}"
    "$MODULE_DIR/vendor/bin/phpunit" --configuration "$MODULE_DIR/phpunit.xml" --testsuite api
fi

# Run custom API test suite
echo -e "\n${BLUE}Running Custom API Test Suite...${NC}"
php "$TESTS_DIR/APITestSuite.php" | tee "$LOGS_DIR/api_test_output_$(date +%Y%m%d_%H%M%S).log"

EXIT_CODE=${PIPESTATUS[0]}

# Run web crawler tests
echo -e "\n${BLUE}Running Web Crawler Tests...${NC}"
if [ -f "$TESTS_DIR/WebCrawlerTest.php" ]; then
    php "$TESTS_DIR/WebCrawlerTest.php"
fi

# Generate HTML report
echo -e "\n${BLUE}Generating HTML Report...${NC}"
LATEST_REPORT=$(ls -t "$LOGS_DIR"/api_test_report_*.json | head -1)
if [ -f "$LATEST_REPORT" ]; then
    php "$SCRIPT_DIR/generate_html_report.php" "$LATEST_REPORT" > "${LATEST_REPORT%.json}.html"
    echo -e "${GREEN}✅ HTML report generated: ${LATEST_REPORT%.json}.html${NC}"
fi

# Summary
echo ""
echo -e "${BLUE}╔════════════════════════════════════════════════════════════════════╗${NC}"
if [ $EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}║  ✅  ALL TESTS PASSED - API IS PRODUCTION READY                   ║${NC}"
else
    echo -e "${RED}║  ❌  SOME TESTS FAILED - REVIEW REPORTS BEFORE DEPLOYMENT         ║${NC}"
fi
echo -e "${BLUE}╚════════════════════════════════════════════════════════════════════╝${NC}"

exit $EXIT_CODE
