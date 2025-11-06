#!/bin/bash
###############################################################################
# Staff Performance Module - Cron Setup Script
###############################################################################

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
MODULE_DIR="$(dirname "$SCRIPT_DIR")"

echo "==========================================="
echo "Staff Performance - Cron Setup"
echo "==========================================="
echo ""

# Make cron scripts executable
echo "Making cron scripts executable..."
chmod +x "$SCRIPT_DIR/process-reviews.php"
chmod +x "$SCRIPT_DIR/update-stats.php"
chmod +x "$SCRIPT_DIR/check-achievements.php"

# Create logs directory
echo "Creating logs directory..."
mkdir -p "$MODULE_DIR/logs"
chmod 755 "$MODULE_DIR/logs"

# Display crontab entries
echo ""
echo "Add these lines to your crontab (crontab -e):"
echo "==========================================="
echo "# Staff Performance Module - Process Google Reviews every 6 hours"
echo "0 */6 * * * $SCRIPT_DIR/process-reviews.php >> $MODULE_DIR/logs/cron.log 2>&1"
echo ""
echo "# Staff Performance Module - Update monthly stats daily at 1am"
echo "0 1 * * * $SCRIPT_DIR/update-stats.php >> $MODULE_DIR/logs/cron.log 2>&1"
echo ""
echo "# Staff Performance Module - Check achievements daily at 2am"
echo "0 2 * * * $SCRIPT_DIR/check-achievements.php >> $MODULE_DIR/logs/cron.log 2>&1"
echo "==========================================="
echo ""

# Test cron scripts
echo "Testing cron scripts..."
echo ""

echo "1. Testing process-reviews.php..."
php "$SCRIPT_DIR/process-reviews.php"
echo ""

echo "2. Testing update-stats.php..."
php "$SCRIPT_DIR/update-stats.php"
echo ""

echo "3. Testing check-achievements.php..."
php "$SCRIPT_DIR/check-achievements.php"
echo ""

echo "==========================================="
echo "Setup Complete!"
echo "==========================================="
echo ""
echo "Next steps:"
echo "1. Review the test output above for any errors"
echo "2. Add the crontab entries shown above"
echo "3. Monitor logs in: $MODULE_DIR/logs/"
echo ""
