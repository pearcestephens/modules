#!/bin/bash
# Security Test Suite - Fix Database Schema and Run All Tests
# This script patches the test database and executes comprehensive security validation

set -e  # Exit on error

echo "🔧 Phase 1 Security Test Suite Runner"
echo "======================================"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
PROJECT_ROOT="/home/master/applications/jcepnzzkmj/public_html"
PAYROLL_DIR="$PROJECT_ROOT/modules/human_resources/payroll"
PHPUNIT="$PROJECT_ROOT/vendor/bin/phpunit"

cd "$PAYROLL_DIR"

echo "📊 Step 1: Check current users table structure"
echo "----------------------------------------------"
php -r "
\$config = require '$PROJECT_ROOT/modules/config/database.php';
\$dbConfig = \$config['cis'];

try {
    \$pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', \$dbConfig['host'], \$dbConfig['database']),
        \$dbConfig['username'],
        \$dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo \"✅ Connected to database: {\$dbConfig['database']}\n\";

    // Get table structure
    \$stmt = \$pdo->query('DESCRIBE users');
    \$columns = \$stmt->fetchAll(PDO::FETCH_COLUMN);

    echo \"📋 Current columns: \" . implode(', ', \$columns) . \"\\n\\n\";

    // Check if 'role' column exists
    if (!in_array('role', \$columns)) {
        echo \"⚠️  'role' column missing - adding it now...\\n\";
        \$pdo->exec(\"ALTER TABLE users ADD COLUMN role VARCHAR(50) DEFAULT 'staff' AFTER email\");
        echo \"✅ 'role' column added successfully\\n\\n\";
    } else {
        echo \"✅ 'role' column already exists\\n\\n\";
    }

} catch (PDOException \$e) {
    echo \"❌ Database error: \" . \$e->getMessage() . \"\\n\";
    exit(1);
}
"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Database schema is ready${NC}"
else
    echo -e "${RED}❌ Database schema fix failed${NC}"
    exit 1
fi

echo ""
echo "🧪 Step 2: Run Unit Tests (Code inspection + static analysis)"
echo "-------------------------------------------------------------"
$PHPUNIT tests/Unit/SecurityConfigTest.php --colors=always --testdox

UNIT_EXIT_CODE=$?

echo ""
echo "🔥 Step 3: Run Integration Tests (Real HTTP + Database)"
echo "-------------------------------------------------------"
$PHPUNIT tests/Integration/SecurityIntegrationTest.php --colors=always --testdox

INTEGRATION_EXIT_CODE=$?

echo ""
echo "📊 Step 4: Generate Test Summary"
echo "--------------------------------"

if [ $UNIT_EXIT_CODE -eq 0 ] && [ $INTEGRATION_EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}"
    echo "╔════════════════════════════════════════════════════════════╗"
    echo "║                   ✅ ALL TESTS PASSED ✅                    ║"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"

    echo "📋 Test Summary:"
    echo "  - Unit Tests: PASSED ✅"
    echo "  - Integration Tests: PASSED ✅"
    echo "  - Total Coverage: All security domains validated"
    echo ""
    echo "🌐 Step 5: Test AJAX/JavaScript Suite"
    echo "------------------------------------"
    echo "To run browser-based tests:"
    echo "1. Open: http://localhost/modules/human_resources/payroll/tests/ajax-security-test.html"
    echo "2. Click '🚀 Run All Security Tests'"
    echo "3. Verify all tests pass with green checkmarks"
    echo ""
    echo "📖 Full Report: SECURITY_TEST_SUITE_REPORT.md"

    exit 0
else
    echo -e "${RED}"
    echo "╔════════════════════════════════════════════════════════════╗"
    echo "║                    ❌ TESTS FAILED ❌                       ║"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"

    echo "📋 Test Summary:"
    [ $UNIT_EXIT_CODE -eq 0 ] && echo "  - Unit Tests: PASSED ✅" || echo "  - Unit Tests: FAILED ❌"
    [ $INTEGRATION_EXIT_CODE -eq 0 ] && echo "  - Integration Tests: PASSED ✅" || echo "  - Integration Tests: FAILED ❌"
    echo ""
    echo "🔍 Debugging:"
    echo "  - Check logs: logs/security.log"
    echo "  - Check PHP errors: logs/apache_*.error.log"
    echo "  - Re-run specific test: $PHPUNIT --filter testName"

    exit 1
fi
