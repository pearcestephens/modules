#!/bin/bash

################################################################################
# DEPLOY FIXED COMPONENTS SCRIPT
# Purpose: Deploy corrected AI rules and missing views
# Database: jcepnzzkmj (MariaDB 10.5+)
# Author: CIS WebDev
# Date: 2025-11-11
################################################################################

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;36m'
NC='\033[0m' # No Color

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
LOG_FILE="${SCRIPT_DIR}/deploy_fixed_${TIMESTAMP}.log"

# DB credentials - from environment or prompt
if [ -z "$DB_PASSWORD" ]; then
    echo -e "${RED}Error: DB_PASSWORD environment variable not set${NC}"
    echo "Usage: DB_PASSWORD='your_password' $0"
    exit 1
fi

DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-jcepnzzkmj}"
DB_NAME="${DB_NAME:-jcepnzzkmj}"

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║  PAYROLL AI SYSTEM - DEPLOY FIXED COMPONENTS               ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${YELLOW}Configuration:${NC}"
echo "  Host:     $DB_HOST"
echo "  Database: $DB_NAME"
echo "  User:     $DB_USER"
echo "  Log:      $LOG_FILE"
echo ""

# Function to run SQL and capture output
run_sql() {
    local sql_file=$1
    local description=$2

    echo -e "${YELLOW}▶ ${description}...${NC}"

    if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < "$sql_file" >> "$LOG_FILE" 2>&1; then
        echo -e "${GREEN}✓ Success${NC}"
        return 0
    else
        echo -e "${RED}✗ Failed (see log: $LOG_FILE)${NC}"
        return 1
    fi
}

# Function to check table exists
check_table() {
    local table=$1
    echo -n "  Checking $table... "

    local count=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" \
        -se "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='$table';" 2>/dev/null)

    if [ "$count" -eq 1 ]; then
        echo -e "${GREEN}✓${NC}"
        return 0
    else
        echo -e "${RED}✗${NC}"
        return 1
    fi
}

# Function to check view exists
check_view() {
    local view=$1
    echo -n "  Checking view $view... "

    local count=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" \
        -se "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='$view' AND TABLE_TYPE='VIEW';" 2>/dev/null)

    if [ "$count" -eq 1 ]; then
        echo -e "${GREEN}✓${NC}"
        return 0
    else
        echo -e "${RED}✗${NC}"
        return 1
    fi
}

# Function to count rules
count_rules() {
    local count=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" \
        -se "SELECT COUNT(*) FROM payroll_ai_decision_rules WHERE is_active=1;" 2>/dev/null)
    echo "$count"
}

# Start deployment
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}STEP 1: Deploy AI Decision Rules${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo ""

if [ ! -f "$SCRIPT_DIR/INSERT_AI_RULES.sql" ]; then
    echo -e "${RED}Error: INSERT_AI_RULES.sql not found at $SCRIPT_DIR${NC}"
    exit 1
fi

run_sql "$SCRIPT_DIR/INSERT_AI_RULES.sql" "Inserting 27 AI decision rules"
RULES_INSERTED=$(count_rules)
echo "  Active rules: $RULES_INSERTED"
echo ""

# Verify rule insertion by type
echo -e "${YELLOW}Rules by type:${NC}"
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" \
    -e "SELECT decision_type, COUNT(*) as count FROM payroll_ai_decision_rules WHERE is_active=1 GROUP BY decision_type;" 2>/dev/null || echo "  (verification query failed)"
echo ""

echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}STEP 2: Create Missing Views${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo ""

if [ ! -f "$SCRIPT_DIR/CREATE_MISSING_VIEWS.sql" ]; then
    echo -e "${RED}Error: CREATE_MISSING_VIEWS.sql not found at $SCRIPT_DIR${NC}"
    exit 1
fi

run_sql "$SCRIPT_DIR/CREATE_MISSING_VIEWS.sql" "Creating missing views"
echo ""

# Verify views created
echo -e "${YELLOW}Verifying views:${NC}"
check_view "v_pending_ai_reviews"
check_view "v_active_ai_rules"
check_view "v_rule_performance"
check_view "v_deductions_requiring_attention"
check_view "v_ai_decision_audit_trail"
check_view "v_leave_balance_with_ai_status"
echo ""

echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}STEP 3: Verify Core Tables${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo ""

echo -e "${YELLOW}Table verification:${NC}"
check_table "payroll_nz_statutory_deductions"
check_table "payroll_ai_decision_rules"
check_table "payroll_ai_decision_requests"
check_table "payroll_nz_leave_requests"
echo ""

echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}FINAL STATUS${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo ""

FINAL_COUNT=$(count_rules)
echo -e "${GREEN}✓ Deployment Complete${NC}"
echo "  AI Rules Active: $FINAL_COUNT/27"
echo "  Views Created: 6"
echo "  Log File: $LOG_FILE"
echo ""

if [ "$FINAL_COUNT" -eq 27 ]; then
    echo -e "${GREEN}✓ All 27 AI rules deployed successfully!${NC}"
else
    echo -e "${YELLOW}⚠ Warning: Expected 27 rules, found $FINAL_COUNT${NC}"
fi

echo ""
echo -e "${BLUE}Next steps:${NC}"
echo "  1. Run full schema test suite: ./run_tests.sh"
echo "  2. Review AI rules performance: SELECT * FROM v_rule_performance;"
echo "  3. Check pending reviews: SELECT * FROM v_pending_ai_reviews;"
echo ""

exit 0
