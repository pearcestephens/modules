#!/bin/bash
# ============================================================================
# PAYROLL SCHEMA TEST RUNNER
# ============================================================================
# Purpose: Run validation tests on DEPLOY_PAYROLL_NZ.sql
# Usage: ./run_tests.sh [database] [user] [password]
# ============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Database credentials
DB_NAME="${1:-jcepnzzkmj}"
DB_USER="${2:-jcepnzzkmj}"
DB_PASS="${3}"

if [ -z "$DB_PASS" ]; then
    echo -e "${YELLOW}Usage: $0 [database] [user] [password]${NC}"
    echo -e "${YELLOW}Using defaults: database=$DB_NAME, user=$DB_USER${NC}"
    read -sp "Enter database password: " DB_PASS
    echo
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEST_FILE="$SCRIPT_DIR/TEST_PAYROLL_SCHEMA.sql"
RESULT_FILE="$SCRIPT_DIR/test_results_$(date +%Y%m%d_%H%M%S).log"

echo -e "${GREEN}============================================================================${NC}"
echo -e "${GREEN}PAYROLL SCHEMA VALIDATION TEST${NC}"
echo -e "${GREEN}============================================================================${NC}"
echo -e "Database: $DB_NAME"
echo -e "User: $DB_USER"
echo -e "Test File: $TEST_FILE"
echo -e "Results: $RESULT_FILE"
echo -e "${GREEN}============================================================================${NC}"
echo

# Check if test file exists
if [ ! -f "$TEST_FILE" ]; then
    echo -e "${RED}ERROR: Test file not found: $TEST_FILE${NC}"
    exit 1
fi

# Run tests
echo -e "${YELLOW}Running tests...${NC}"
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$TEST_FILE" 2>&1 | tee "$RESULT_FILE"

# Check results
PASS_COUNT=$(grep -c "✓ PASS" "$RESULT_FILE" || true)
FAIL_COUNT=$(grep -c "✗ FAIL" "$RESULT_FILE" || true)

echo
echo -e "${GREEN}============================================================================${NC}"
echo -e "${GREEN}TEST RESULTS${NC}"
echo -e "${GREEN}============================================================================${NC}"
echo -e "${GREEN}Passed: $PASS_COUNT${NC}"
echo -e "${RED}Failed: $FAIL_COUNT${NC}"
echo -e "Total: $(($PASS_COUNT + $FAIL_COUNT))"
echo -e "${GREEN}============================================================================${NC}"

if [ "$FAIL_COUNT" -gt 0 ]; then
    echo
    echo -e "${RED}FAILURES DETECTED:${NC}"
    grep "✗ FAIL" "$RESULT_FILE" || true
    echo
    exit 1
else
    echo
    echo -e "${GREEN}ALL TESTS PASSED!${NC}"
    echo
    exit 0
fi
