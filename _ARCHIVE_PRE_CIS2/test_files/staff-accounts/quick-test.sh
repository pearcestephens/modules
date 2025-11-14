#!/bin/bash
#
# Quick Test - Staff Accounts Module
# Fast verification of critical paths
#
# Usage: ./quick-test.sh
#

GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

BASE_URL="https://staff.vapeshed.co.nz/modules/staff-accounts"

echo -e "${BLUE}Quick Test - Staff Accounts Module${NC}"
echo ""

# Test main page
echo -n "Index page... "
code=$(curl -s -o /dev/null -w "%{http_code}" -L "${BASE_URL}/index.php")
if [ "$code" = "200" ] || [ "$code" = "302" ]; then
    echo -e "${GREEN}✓ ${code}${NC}"
else
    echo -e "${RED}✗ ${code}${NC}"
fi

# Test my-account
echo -n "My account... "
code=$(curl -s -o /dev/null -w "%{http_code}" -L "${BASE_URL}/views/my-account.php")
if [ "$code" = "200" ] || [ "$code" = "302" ]; then
    echo -e "${GREEN}✓ ${code}${NC}"
else
    echo -e "${RED}✗ ${code}${NC}"
fi

# Test CSS
echo -n "CSS file... "
code=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/css/staff-accounts.css")
if [ "$code" = "200" ]; then
    echo -e "${GREEN}✓ ${code}${NC}"
else
    echo -e "${RED}✗ ${code}${NC}"
fi

# Test JS
echo -n "JavaScript file... "
code=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/js/staff-accounts.js")
if [ "$code" = "200" ]; then
    echo -e "${GREEN}✓ ${code}${NC}"
else
    echo -e "${RED}✗ ${code}${NC}"
fi

# Test API
echo -n "Payment API... "
code=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/api/payment.php")
if [ "$code" = "200" ] || [ "$code" = "401" ] || [ "$code" = "403" ] || [ "$code" = "302" ]; then
    echo -e "${GREEN}✓ ${code}${NC}"
else
    echo -e "${RED}✗ ${code}${NC}"
fi

echo ""
echo -e "${GREEN}Quick test complete!${NC}"
