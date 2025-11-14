#!/bin/bash

# Fraud Detection Test Suite Runner
# Run all PHPUnit tests with coverage reporting

set -e

echo "=========================================="
echo "  Fraud Detection - PHPUnit Test Suite"
echo "=========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if PHPUnit is installed
if [ ! -f "vendor/bin/phpunit" ]; then
    echo -e "${RED}✗ PHPUnit not found${NC}"
    echo "Installing dependencies..."
    composer install
fi

# Create test database if it doesn't exist
echo -e "${YELLOW}Setting up test database...${NC}"
mysql -u root -e "CREATE DATABASE IF NOT EXISTS cis_test;" 2>/dev/null || true

# Run migrations on test database
echo -e "${YELLOW}Running migrations...${NC}"
for migration in database/migrations/*.sql; do
    echo "  - $(basename $migration)"
    mysql -u root cis_test < "$migration" 2>/dev/null || true
done

echo ""
echo -e "${YELLOW}Running tests...${NC}"
echo ""

# Run different test suites
echo "=== Unit Tests ==="
vendor/bin/phpunit --testsuite "Unit Tests" --colors=always

echo ""
echo "=== Integration Tests ==="
vendor/bin/phpunit --testsuite "Integration Tests" --colors=always

echo ""
echo "=== All Tests with Coverage ==="
vendor/bin/phpunit --colors=always --coverage-text --coverage-html=coverage/html

# Check coverage threshold
COVERAGE=$(vendor/bin/phpunit --coverage-text | grep "Lines:" | awk '{print $2}' | sed 's/%//')

echo ""
if (( $(echo "$COVERAGE >= 80" | bc -l) )); then
    echo -e "${GREEN}✓ Code coverage: ${COVERAGE}% (target: 80%)${NC}"
else
    echo -e "${YELLOW}⚠ Code coverage: ${COVERAGE}% (target: 80%)${NC}"
    echo "  Coverage report: coverage/html/index.html"
fi

echo ""
echo -e "${GREEN}✓ All tests completed!${NC}"
echo ""
echo "Reports:"
echo "  - HTML Coverage: coverage/html/index.html"
echo "  - Clover XML: coverage/clover.xml"
echo ""
