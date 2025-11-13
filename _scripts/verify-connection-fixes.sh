#!/bin/bash
# Verify all MySQL connection leak fixes are in place

echo "🔍 MySQL Connection Leak Fix Verification"
echo "=========================================="
echo ""

PASSED=0
FAILED=0

check_file() {
    local file="$1"
    local pattern="$2"
    local desc="$3"

    if [ ! -f "$file" ]; then
        echo "  ❌ File not found: $file"
        ((FAILED++))
        return
    fi

    if grep -q "$pattern" "$file"; then
        echo "  ✅ $desc"
        ((PASSED++))
    else
        echo "  ❌ MISSING: $desc"
        echo "     File: $file"
        echo "     Expected pattern: $pattern"
        ((FAILED++))
    fi
}

echo "Phase 1: Critical API Endpoints"
echo "────────────────────────────────"
check_file "/home/master/applications/jcepnzzkmj/public_html/modules/consignments/api/index.php" "finally {" "API index.php has finally block"
check_file "/home/master/applications/jcepnzzkmj/public_html/modules/consignments/lib/TransferManagerAPI.php" "__destruct" "TransferManagerAPI has destructor"
check_file "/home/master/applications/jcepnzzkmj/public_html/modules/hr-portal/api/deny-item.php" "finally {" "deny-item.php has finally block"
check_file "/home/master/applications/jcepnzzkmj/public_html/modules/hr-portal/api/toggle-autopilot.php" "finally {" "toggle-autopilot.php has finally block"
check_file "/home/master/applications/jcepnzzkmj/public_html/modules/hr-portal/api/batch-approve.php" "finally {" "batch-approve.php has finally block"
check_file "/home/master/applications/jcepnzzkmj/public_html/modules/hr-portal/api/dashboard-stats.php" "finally {" "dashboard-stats.php has finally block"
check_file "/home/master/applications/jcepnzzkmj/public_html/modules/hr-portal/api/approve-item.php" "finally {" "approve-item.php has finally block"

echo ""
echo "Phase 2: Scripts & Migrations"
echo "────────────────────────────────"
check_file "/home/master/applications/jcepnzzkmj/public_html/modules/consignments/transfer-manager.php" "register_shutdown_function" "transfer-manager.php has shutdown handler"
check_file "/home/master/applications/jcepnzzkmj/public_html/modules/consignments/TransferManager/backend.php" "register_shutdown_function" "backend.php has shutdown handler"
check_file "/home/master/applications/jcepnzzkmj/public_html/modules/consignments/database/run-migration.php" "finally {" "run-migration.php has finally block"
check_file "/home/master/applications/jcepnzzkmj/public_html/modules/consignments/database/critical-queue-tables-fix.php" "finally {" "critical-queue-tables-fix.php has finally block"
check_file "/home/master/applications/jcepnzzkmj/public_html/modules/staff-accounts/check-webhook-cli.php" "finally {" "check-webhook-cli.php has finally block"
check_file "/home/master/applications/jcepnzzkmj/public_html/modules/staff-accounts/lookup-users.php" "finally {" "lookup-users.php has finally block"
check_file "/home/master/applications/jcepnzzkmj/public_html/modules/staff-accounts/cli/phase-e-v3-simple.php" "mysqli_close" "phase-e-v3-simple.php closes mysqli"

echo ""
echo "Phase 3: Bootstrap & Migrations"
echo "────────────────────────────────"
check_file "/home/master/applications/jcepnzzkmj/public_html/modules/bank-transactions/migrations/002_create_bank_deposits_table.php" "finally {" "002_create_bank_deposits has finally"
check_file "/home/master/applications/jcepnzzkmj/public_html/modules/bank-transactions/migrations/003_create_register_closure_bank_deposits_table.php" "finally {" "003_create_register_closure has finally"
check_file "/home/master/applications/jcepnzzkmj/public_html/modules/store-reports/bootstrap.php" "register_shutdown_function" "store-reports bootstrap has shutdown handler"
check_file "/home/master/applications/jcepnzzkmj/public_html/modules/staff-performance/bootstrap.php" "register_shutdown_function" "staff-performance bootstrap has shutdown handler"
check_file "/home/master/applications/jcepnzzkmj/public_html/modules/control-panel/bootstrap.php" "register_shutdown_function" "control-panel bootstrap has shutdown handler"

echo ""
echo "═══════════════════════════════════════"
echo "📊 VERIFICATION RESULTS"
echo "═══════════════════════════════════════"
echo "  ✅ Passed: $PASSED checks"
echo "  ❌ Failed: $FAILED checks"
echo ""

if [ $FAILED -eq 0 ]; then
    echo "🎉 ALL FIXES VERIFIED - Ready for deployment!"
    exit 0
else
    echo "⚠️  Some fixes are missing - review output above"
    exit 1
fi
