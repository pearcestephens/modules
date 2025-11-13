#!/bin/bash

# Store Reports API - Test Runner
# Enterprise-grade test execution with reporting

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
MODULE_DIR="$( dirname "$SCRIPT_DIR" )"
LOG_DIR="${MODULE_DIR}/logs/tests"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
LOG_FILE="${LOG_DIR}/test_run_${TIMESTAMP}.log"

# Load environment variables from .env
ENV_FILE="/home/master/applications/jcepnzzkmj/public_html/.env"
if [ -f "$ENV_FILE" ]; then
    echo "Loading environment from: $ENV_FILE"
    export $(grep -v '^#' "$ENV_FILE" | grep -v '^$' | xargs)
fi

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Create log directory
mkdir -p "$LOG_DIR"

echo -e "${BLUE}════════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}   STORE REPORTS API - ENTERPRISE TEST RUNNER                   ${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════════════${NC}"
echo ""
echo "Test Suite: STRICT MODE (Hard Level)"
echo "Started: $(date)"
echo "Log File: $LOG_FILE"
echo ""

# Check prerequisites
echo -e "${YELLOW}Checking prerequisites...${NC}"

# Check PHP
if ! command -v php &> /dev/null; then
    echo -e "${RED}✗ PHP not found${NC}"
    exit 1
fi
echo -e "${GREEN}✓ PHP found: $(php -v | head -n1)${NC}"

# Check database connection
if ! php -r "new PDO('mysql:host=localhost;dbname=jcepnzzkmj', 'user', 'pass');" 2>/dev/null; then
    echo -e "${YELLOW}⚠ Database connection check skipped (will be tested in suite)${NC}"
fi

# Check required extensions
REQUIRED_EXTENSIONS=("pdo" "pdo_mysql" "curl" "json" "gd")
for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if php -m | grep -q "^$ext$"; then
        echo -e "${GREEN}✓ Extension: $ext${NC}"
    else
        echo -e "${RED}✗ Missing extension: $ext${NC}"
        exit 1
    fi
done

echo ""
echo -e "${BLUE}Running test suite...${NC}"
echo ""

# Run tests and capture output
if php "$SCRIPT_DIR/test-suite-strict.php" 2>&1 | tee "$LOG_FILE"; then
    EXIT_CODE=0
else
    EXIT_CODE=$?
fi

echo ""
echo -e "${BLUE}════════════════════════════════════════════════════════════════${NC}"
echo ""

# Parse results from log
PASSED=$(grep -o "✅ Passed:.*[0-9]\+" "$LOG_FILE" | grep -o "[0-9]\+" || echo "0")
FAILED=$(grep -o "❌ Failed:.*[0-9]\+" "$LOG_FILE" | grep -o "[0-9]\+" || echo "0")
WARNINGS=$(grep -o "⚠️  Warnings:.*[0-9]\+" "$LOG_FILE" | grep -o "[0-9]\+" || echo "0")

echo "Test Results:"
echo -e "  ${GREEN}Passed:   $PASSED${NC}"
echo -e "  ${RED}Failed:   $FAILED${NC}"
echo -e "  ${YELLOW}Warnings: $WARNINGS${NC}"
echo ""

if [ "$FAILED" -eq 0 ]; then
    echo -e "${GREEN}🎉 ALL TESTS PASSED!${NC}"
    echo ""
    echo "✅ API is ready for deployment"
    EXIT_CODE=0
else
    echo -e "${RED}⚠️  SOME TESTS FAILED${NC}"
    echo ""
    echo "Please review the log file:"
    echo "  $LOG_FILE"
    echo ""
    echo "Failed tests must be fixed before deployment."
    EXIT_CODE=1
fi

echo ""
echo "Completed: $(date)"
echo ""

# Generate summary report
REPORT_FILE="${LOG_DIR}/test_summary_${TIMESTAMP}.txt"
cat > "$REPORT_FILE" << EOF
Store Reports API - Test Summary
═══════════════════════════════════════════════════════════════

Test Run: ${TIMESTAMP}
Test Mode: STRICT (Hard Level)
Duration: N/A

Results:
  Passed:   ${PASSED}
  Failed:   ${FAILED}
  Warnings: ${WARNINGS}
  Total:    $((PASSED + FAILED))

Pass Rate: $(awk "BEGIN {print ($PASSED / ($PASSED + $FAILED)) * 100}")%

Status: $([ "$FAILED" -eq 0 ] && echo "✅ PASS" || echo "❌ FAIL")

Full Log: ${LOG_FILE}
EOF

echo "Summary report: $REPORT_FILE"

exit $EXIT_CODE
