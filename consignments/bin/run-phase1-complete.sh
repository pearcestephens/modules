#!/bin/bash

###############################################################################
# Phase 1 - Complete Migration + Testing Suite
#
# This script:
# 1. Makes all scripts executable
# 2. Runs database migration
# 3. Executes comprehensive test suite
# 4. Reports results
#
# Usage: bash bin/run-phase1-complete.sh
###############################################################################

set -e  # Exit on any error

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MODULE_DIR="$(dirname "$SCRIPT_DIR")"

echo ""
echo "═══════════════════════════════════════════════════════════════════"
echo "  PHASE 1: EMAIL NOTIFICATION SYSTEM"
echo "  Complete Migration + Testing"
echo "═══════════════════════════════════════════════════════════════════"
echo ""

###############################################################################
# Step 1: Make scripts executable
###############################################################################

echo "📝 Step 1: Making scripts executable..."
echo "───────────────────────────────────────────────────────────────────"

chmod +x "$SCRIPT_DIR/run-migration.php"
chmod +x "$SCRIPT_DIR/test-phase1.php"
chmod +x "$SCRIPT_DIR/notification-worker.php"

echo "✓ Scripts are now executable"
echo ""

###############################################################################
# Step 2: Run database migration
###############################################################################

echo "🗄️  Step 2: Running database migration..."
echo "───────────────────────────────────────────────────────────────────"

php "$SCRIPT_DIR/run-migration.php"

if [ $? -ne 0 ]; then
    echo ""
    echo "❌ Migration failed! Please fix errors before proceeding."
    exit 1
fi

echo "✅ Migration completed successfully"
echo ""

###############################################################################
# Step 3: Run comprehensive test suite
###############################################################################

echo "🧪 Step 3: Running comprehensive test suite..."
echo "───────────────────────────────────────────────────────────────────"

php "$SCRIPT_DIR/test-phase1.php" --verbose

if [ $? -ne 0 ]; then
    echo ""
    echo "⚠️  Some tests failed. Please review above."
    exit 1
fi

echo ""

###############################################################################
# Step 4: Test worker operations
###############################################################################

echo "⚙️  Step 4: Testing worker operations..."
echo "───────────────────────────────────────────────────────────────────"

echo ""
echo "Queue Statistics:"
php "$SCRIPT_DIR/notification-worker.php" --stats

echo ""
echo "Testing worker help:"
php "$SCRIPT_DIR/notification-worker.php" --help | head -n 20

echo ""

###############################################################################
# Step 5: Summary & Next Steps
###############################################################################

echo "═══════════════════════════════════════════════════════════════════"
echo "  🎉 PHASE 1 COMPLETE!"
echo "═══════════════════════════════════════════════════════════════════"
echo ""
echo "✅ Database migrated successfully"
echo "✅ All tests passed"
echo "✅ Worker operational"
echo ""
echo "📊 Summary:"
echo "   - 4 database tables created"
echo "   - 9 email templates installed"
echo "   - 8 configuration entries"
echo "   - EmailService (479 lines)"
echo "   - NotificationService (562 lines)"
echo "   - 8 HTML templates"
echo "   - Background worker"
echo "   - 27 unit tests"
echo ""
echo "🚀 Next Steps:"
echo ""
echo "   1. Setup Cron Jobs (IMPORTANT):"
echo "      * * * * * php $SCRIPT_DIR/notification-worker.php --priority=1"
echo "      */5 * * * * php $SCRIPT_DIR/notification-worker.php --priority=2"
echo "      */30 * * * * php $SCRIPT_DIR/notification-worker.php --priority=3"
echo "      0 2 * * * php $SCRIPT_DIR/notification-worker.php --priority=4"
echo "      */15 * * * * php $SCRIPT_DIR/notification-worker.php --retry"
echo ""
echo "   2. Send test email:"
echo "      cd $MODULE_DIR"
echo "      php -r 'require \"bootstrap.php\"; use CIS\Consignments\Services\EmailService; \$s = EmailService::make(); echo \$s->sendTemplate(\"po_created_internal\", \"you@example.com\", \"Test\", [\"po_number\"=>\"TEST-123\",\"supplier_name\"=>\"Test\",\"total_value\"=>\"\$100\",\"created_by\"=>\"Me\",\"created_at\"=>date(\"Y-m-d H:i:s\"),\"po_url\"=>\"#\"], null, 3, 1);'"
echo ""
echo "   3. Process queue:"
echo "      php $SCRIPT_DIR/notification-worker.php --priority=1 --verbose"
echo ""
echo "   4. Monitor queue:"
echo "      watch -n 5 \"php $SCRIPT_DIR/notification-worker.php --stats\""
echo ""
echo "   5. Begin Phase 2 (Approval Workflow)"
echo ""
echo "═══════════════════════════════════════════════════════════════════"
echo ""
