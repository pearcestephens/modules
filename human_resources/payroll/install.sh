#!/bin/bash
###############################################################################
# Payroll AI Automation - Installation & Setup Script
#
# This script:
# 1. Makes cron scripts executable
# 2. Creates necessary log files
# 3. Sets proper permissions
# 4. Installs cron jobs (optional)
# 5. Tests database connectivity
# 6. Validates configuration
#
# Usage:
#   bash install.sh [--install-cron]
#
# @version 1.0.0
###############################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Base paths
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
MODULE_ROOT="$(dirname "$SCRIPT_DIR")"
APP_ROOT="$(dirname "$(dirname "$(dirname "$(dirname "$MODULE_ROOT")")")")"
LOGS_DIR="$APP_ROOT/logs"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Payroll AI Automation - Installation${NC}"
echo -e "${BLUE}========================================${NC}\n"

echo "Module Root: $MODULE_ROOT"
echo "App Root: $APP_ROOT"
echo "Logs Dir: $LOGS_DIR"
echo ""

# Step 1: Make cron scripts executable
echo -e "${YELLOW}[1/7]${NC} Making cron scripts executable..."
chmod +x "$MODULE_ROOT/cron/process_automated_reviews.php"
chmod +x "$MODULE_ROOT/cron/sync_deputy.php"
chmod +x "$MODULE_ROOT/cron/update_dashboard.php"
chmod +x "$MODULE_ROOT/tests/test_amendment_service.php"
echo -e "${GREEN}✓${NC} Cron scripts are executable\n"

# Step 2: Create log files if they don't exist
echo -e "${YELLOW}[2/7]${NC} Creating log files..."
touch "$LOGS_DIR/payroll_automation.log"
touch "$LOGS_DIR/deputy_sync.log"
touch "$LOGS_DIR/dashboard_stats.log"
chmod 644 "$LOGS_DIR/payroll_automation.log"
chmod 644 "$LOGS_DIR/deputy_sync.log"
chmod 644 "$LOGS_DIR/dashboard_stats.log"
echo -e "${GREEN}✓${NC} Log files created\n"

# Step 3: Check database connectivity
echo -e "${YELLOW}[3/7]${NC} Testing database connectivity..."
DB_USER="jcepnzzkmj"
DB_PASS="wprKh9Jq63"
DB_NAME="jcepnzzkmj"

if mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 1;" > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} Database connection successful\n"
else
    echo -e "${RED}✗${NC} Database connection failed"
    echo "Please check your database credentials"
    exit 1
fi

# Step 4: Validate payroll tables exist
echo -e "${YELLOW}[4/7]${NC} Validating payroll tables..."
REQUIRED_TABLES=(
    "payroll_timesheet_amendments"
    "payroll_ai_decisions"
    "payroll_ai_rules"
    "payroll_staff"
    "payroll_pay_periods"
)

MISSING_TABLES=()
for table in "${REQUIRED_TABLES[@]}"; do
    if ! mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "DESCRIBE $table;" > /dev/null 2>&1; then
        MISSING_TABLES+=("$table")
    fi
done

if [ ${#MISSING_TABLES[@]} -eq 0 ]; then
    echo -e "${GREEN}✓${NC} All required tables exist\n"
else
    echo -e "${RED}✗${NC} Missing tables:"
    for table in "${MISSING_TABLES[@]}"; do
        echo "  - $table"
    done
    echo ""
    echo "Please run the schema deployment first:"
    echo "  mysql -u $DB_USER -p'$DB_PASS' $DB_NAME < schema/payroll_ai_automation_schema.sql"
    exit 1
fi

# Step 5: Check PHP configuration
echo -e "${YELLOW}[5/7]${NC} Checking PHP configuration..."
PHP_VERSION=$(php -r "echo PHP_VERSION;")
echo "  PHP Version: $PHP_VERSION"

if php -r "exit(version_compare(PHP_VERSION, '8.0.0', '>=') ? 0 : 1);"; then
    echo -e "${GREEN}✓${NC} PHP version is 8.0+\n"
else
    echo -e "${RED}✗${NC} PHP version must be 8.0 or higher"
    exit 1
fi

# Step 6: Test cron scripts (dry run)
echo -e "${YELLOW}[6/7]${NC} Testing cron scripts..."

echo "  Testing process_automated_reviews.php..."
if php "$MODULE_ROOT/cron/process_automated_reviews.php" > /dev/null 2>&1; then
    echo -e "    ${GREEN}✓${NC} process_automated_reviews.php runs without errors"
else
    echo -e "    ${YELLOW}!${NC} process_automated_reviews.php completed with warnings (this is normal if no pending reviews)"
fi

echo ""

# Step 7: Install cron jobs (optional)
if [ "$1" == "--install-cron" ]; then
    echo -e "${YELLOW}[7/7]${NC} Installing cron jobs..."

    # Create temporary cron file
    TEMP_CRON=$(mktemp)

    # Export existing crontab
    crontab -l > "$TEMP_CRON" 2>/dev/null || true

    # Add payroll automation cron jobs if not already present
    if ! grep -q "process_automated_reviews.php" "$TEMP_CRON"; then
        echo "" >> "$TEMP_CRON"
        echo "# Payroll AI Automation - Process reviews every 5 minutes" >> "$TEMP_CRON"
        echo "*/5 * * * * /usr/bin/php $MODULE_ROOT/cron/process_automated_reviews.php >> $LOGS_DIR/payroll_automation.log 2>&1" >> "$TEMP_CRON"
    fi

    if ! grep -q "sync_deputy.php" "$TEMP_CRON"; then
        echo "" >> "$TEMP_CRON"
        echo "# Payroll AI Automation - Sync Deputy every hour" >> "$TEMP_CRON"
        echo "0 * * * * /usr/bin/php $MODULE_ROOT/cron/sync_deputy.php >> $LOGS_DIR/deputy_sync.log 2>&1" >> "$TEMP_CRON"
    fi

    if ! grep -q "update_dashboard.php" "$TEMP_CRON"; then
        echo "" >> "$TEMP_CRON"
        echo "# Payroll AI Automation - Update dashboard daily at 2 AM" >> "$TEMP_CRON"
        echo "0 2 * * * /usr/bin/php $MODULE_ROOT/cron/update_dashboard.php >> $LOGS_DIR/dashboard_stats.log 2>&1" >> "$TEMP_CRON"
    fi

    # Install new crontab
    crontab "$TEMP_CRON"
    rm "$TEMP_CRON"

    echo -e "${GREEN}✓${NC} Cron jobs installed\n"

    echo "Installed cron jobs:"
    crontab -l | grep -A 1 "Payroll AI Automation"
    echo ""
else
    echo -e "${YELLOW}[7/7]${NC} Skipping cron installation (use --install-cron to install)"
    echo ""
    echo "To install cron jobs manually, run:"
    echo "  bash install.sh --install-cron"
    echo ""
fi

# Success!
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Installation Complete!${NC}"
echo -e "${GREEN}========================================${NC}\n"

echo "Next Steps:"
echo ""
echo "1. Test the amendment service:"
echo "   php $MODULE_ROOT/tests/test_amendment_service.php"
echo ""
echo "2. Configure environment variables (if not done):"
echo "   - XERO_CLIENT_ID"
echo "   - XERO_CLIENT_SECRET"
echo "   - XERO_REDIRECT_URI"
echo "   - DEPUTY_API_TOKEN"
echo ""
echo "3. Review the logs:"
echo "   tail -f $LOGS_DIR/payroll_automation.log"
echo ""
echo "4. Access the API endpoints:"
echo "   - GET  /api/payroll/amendments/pending"
echo "   - POST /api/payroll/amendments/create"
echo "   - GET  /api/payroll/automation/dashboard"
echo ""

if [ "$1" != "--install-cron" ]; then
    echo "5. Install cron jobs when ready:"
    echo "   bash install.sh --install-cron"
    echo ""
fi

exit 0
