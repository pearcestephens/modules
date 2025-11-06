#!/bin/bash
# Staff Accounts Module - Comprehensive Page Health Check
# Tests syntax and basic accessibility of all PHP files

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

BASE_DIR="/home/master/applications/jcepnzzkmj/public_html/modules/staff-accounts"
ERRORS=0
WARNINGS=0
SUCCESS=0

echo -e "\n${BLUE}========================================${NC}"
echo -e "${BLUE}  Staff Accounts - Page Health Check${NC}"
echo -e "${BLUE}========================================${NC}\n"

# Function to check PHP syntax
check_syntax() {
    local file="$1"
    local label="$2"

    echo -e "${YELLOW}Testing:${NC} $label"
    echo -e "  File: $file"

    # Check if file exists
    if [ ! -f "$file" ]; then
        echo -e "  ${RED}✗ File not found${NC}\n"
        ((ERRORS++))
        return 1
    fi

    # Check PHP syntax
    php -l "$file" > /dev/null 2>&1
    if [ $? -ne 0 ]; then
        echo -e "  ${RED}✗ Syntax Error${NC}"
        php -l "$file"
        ((ERRORS++))
        echo ""
        return 1
    fi

    echo -e "  ${GREEN}✓ Syntax OK${NC}\n"
    ((SUCCESS++))
    return 0
}

# Test main index
check_syntax "$BASE_DIR/index.php" "index.php"

# Test view pages
echo -e "${BLUE}--- View Pages ---${NC}\n"
check_syntax "$BASE_DIR/views/apply-payments.php" "views/apply-payments.php"
check_syntax "$BASE_DIR/views/make-payment.php" "views/make-payment.php"
check_syntax "$BASE_DIR/views/my-account.php" "views/my-account.php"
check_syntax "$BASE_DIR/views/payment-success.php" "views/payment-success.php"
check_syntax "$BASE_DIR/views/staff-list.php" "views/staff-list.php"

# Test API endpoints
echo -e "${BLUE}--- API Endpoints ---${NC}\n"
check_syntax "$BASE_DIR/api/apply-payment.php" "api/apply-payment.php"
check_syntax "$BASE_DIR/api/customer-search.php" "api/customer-search.php"
check_syntax "$BASE_DIR/api/auto-match-suggestions.php" "api/auto-match-suggestions.php"
check_syntax "$BASE_DIR/api/employee-mapping.php" "api/employee-mapping.php"
check_syntax "$BASE_DIR/api/manager-dashboard.php" "api/manager-dashboard.php"
check_syntax "$BASE_DIR/api/staff-reconciliation.php" "api/staff-reconciliation.php"
check_syntax "$BASE_DIR/api/payment.php" "api/payment.php"
check_syntax "$BASE_DIR/api/process-payment.php" "api/process-payment.php"
check_syntax "$BASE_DIR/api/email-statement.php" "api/email-statement.php"

# Test library files
echo -e "${BLUE}--- Library Files ---${NC}\n"
if [ -d "$BASE_DIR/lib" ]; then
    for lib_file in "$BASE_DIR/lib"/*.php; do
        if [ -f "$lib_file" ]; then
            check_syntax "$lib_file" "lib/$(basename $lib_file)"
        fi
    done
fi

# Summary
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  Summary${NC}"
echo -e "${BLUE}========================================${NC}\n"

echo -e "${GREEN}✓ Success:${NC} $SUCCESS files"
echo -e "${RED}✗ Errors:${NC} $ERRORS files"

if [ $ERRORS -gt 0 ]; then
    echo -e "\n${RED}========================================${NC}"
    echo -e "${RED}  ERRORS DETECTED${NC}"
    echo -e "${RED}========================================${NC}"
    exit 1
else
    echo -e "\n${GREEN}========================================${NC}"
    echo -e "${GREEN}  ALL FILES OK!${NC}"
    echo -e "${GREEN}========================================${NC}"
    exit 0
fi
