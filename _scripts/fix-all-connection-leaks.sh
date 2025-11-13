#!/bin/bash
# Comprehensive MySQL Connection Leak Fix Script
# Applies connection cleanup to all identified problem files

set -e

echo "🔧 MYSQL CONNECTION LEAK FIX - PHASE 1"
echo "========================================"
echo ""

# Counter
FIXED=0
ERRORS=0

# Function to apply PDO fix
fix_pdo_file() {
    local file="$1"
    local label="$2"

    if [ ! -f "$file" ]; then
        echo "  ⚠️  File not found: $file"
        ((ERRORS++))
        return
    fi

    echo "  🔧 Fixing: $label"

    # Create backup
    cp "$file" "$file.pre-connection-fix.backup"

    # Check if file already has finally block
    if grep -q "finally {" "$file"; then
        echo "     ℹ️  Already has finally block, skipping"
        return
    fi

    # Use PHP to properly add finally block
    php -r "
    \$content = file_get_contents('$file');

    // Add \$pdo = null; before try if not present
    if (!preg_match('/\\\$pdo\s*=\s*null;/', \$content)) {
        \$content = preg_replace('/(try\s*\{)/m', '\\\$pdo = null;\n\$1', \$content, 1);
    }

    // Add finally block after last catch
    \$pattern = '/(} catch \([^)]+\) \{[^}]+}\s*)\n/s';
    \$replacement = '\$1' . PHP_EOL . '} finally {' . PHP_EOL .
                    '    // ✅ CRITICAL FIX: Always cleanup PDO connection to prevent connection leaks' . PHP_EOL .
                    '    \\\$pdo = null;' . PHP_EOL .
                    '}' . PHP_EOL;
    \$content = preg_replace(\$pattern, \$replacement, \$content, 1);

    file_put_contents('$file', \$content);
    "

    echo "     ✅ Fixed"
    ((FIXED++))
}

# Function to apply mysqli fix
fix_mysqli_file() {
    local file="$1"
    local label="$2"
    local var_name="$3"  # Variable name: db, conn, con, connection

    if [ ! -f "$file" ]; then
        echo "  ⚠️  File not found: $file"
        ((ERRORS++))
        return
    fi

    echo "  🔧 Fixing: $label (mysqli variable: \$$var_name)"

    # Create backup
    cp "$file" "$file.pre-connection-fix.backup"

    # Check if file already has ->close() or finally block
    if grep -q "$var_name->close()" "$file" || grep -q "finally {" "$file"; then
        echo "     ℹ️  Already has cleanup, skipping"
        return
    fi

    # Add finally block with mysqli cleanup
    php -r "
    \$content = file_get_contents('$file');
    \$var = '$var_name';

    // Find last catch block
    \$pattern = '/(} catch \([^)]+\) \{[^}]+}\s*)\$/s';
    \$replacement = '\$1' . PHP_EOL . '} finally {' . PHP_EOL .
                    '    // ✅ CRITICAL FIX: Always cleanup mysqli connection to prevent connection leaks' . PHP_EOL .
                    '    if (isset(\\\$' . \$var . ') && \\\$' . \$var . ' instanceof mysqli && !empty(\\\$' . \$var . '->thread_id)) {' . PHP_EOL .
                    '        @\\\$' . \$var . '->close();' . PHP_EOL .
                    '    }' . PHP_EOL .
                    '}' . PHP_EOL;
    \$content = preg_replace(\$pattern, \$replacement, \$content, 1);

    file_put_contents('$file', \$content);
    "

    echo "     ✅ Fixed"
    ((FIXED++))
}

echo "📋 PHASE 1: Critical API Files"
echo "────────────────────────────────"

# HR Portal API files (PDO)
fix_pdo_file "/home/master/applications/jcepnzzkmj/public_html/modules/hr-portal/api/batch-approve.php" "HR Portal - Batch Approve"
fix_pdo_file "/home/master/applications/jcepnzzkmj/public_html/modules/hr-portal/api/dashboard-stats.php" "HR Portal - Dashboard Stats"
fix_pdo_file "/home/master/applications/jcepnzzkmj/public_html/modules/hr-portal/api/approve-item.php" "HR Portal - Approve Item"

# Staff Accounts files
fix_pdo_file "/home/master/applications/jcepnzzkmj/public_html/modules/staff-accounts/check-webhook-cli.php" "Staff Accounts - Webhook Check"
fix_pdo_file "/home/master/applications/jcepnzzkmj/public_html/modules/staff-accounts/lookup-users.php" "Staff Accounts - User Lookup"

# Consignments files (mysqli)
fix_mysqli_file "/home/master/applications/jcepnzzkmj/public_html/modules/consignments/transfer-manager.php" "Consignments - Transfer Manager" "db"
fix_mysqli_file "/home/master/applications/jcepnzzkmj/public_html/modules/consignments/TransferManager/backend.php" "Consignments - TM Backend" "conn"

echo ""
echo "📋 PHASE 2: Migration & CLI Scripts"
echo "────────────────────────────────────"

# Migration scripts
fix_mysqli_file "/home/master/applications/jcepnzzkmj/public_html/modules/consignments/database/run-migration.php" "Consignments - Run Migration" "connection"
fix_mysqli_file "/home/master/applications/jcepnzzkmj/public_html/modules/consignments/database/critical-queue-tables-fix.php" "Consignments - Queue Fix" "connection"

# CLI scripts
fix_mysqli_file "/home/master/applications/jcepnzzkmj/public_html/modules/staff-accounts/cli/phase-e-v3-simple.php" "Staff Accounts - Phase E" "con"

# Bank transaction migrations
fix_pdo_file "/home/master/applications/jcepnzzkmj/public_html/modules/bank-transactions/migrations/002_create_bank_deposits_table.php" "Bank - Create Deposits Table"
fix_pdo_file "/home/master/applications/jcepnzzkmj/public_html/modules/bank-transactions/migrations/003_create_register_closure_bank_deposits_table.php" "Bank - Create Register Closure"

echo ""
echo "📋 PHASE 3: Bootstrap Files"
echo "───────────────────────────"

fix_mysqli_file "/home/master/applications/jcepnzzkmj/public_html/modules/store-reports/bootstrap.php" "Store Reports - Bootstrap" "con"
fix_pdo_file "/home/master/applications/jcepnzzkmj/public_html/modules/staff-performance/bootstrap.php" "Staff Performance - Bootstrap"
fix_pdo_file "/home/master/applications/jcepnzzkmj/public_html/modules/control-panel/bootstrap.php" "Control Panel - Bootstrap"

echo ""
echo "═══════════════════════════════════════"
echo "✅ CONNECTION LEAK FIX COMPLETE!"
echo "═══════════════════════════════════════"
echo ""
echo "📊 Summary:"
echo "   Fixed: $FIXED files"
echo "   Errors: $ERRORS files"
echo ""
echo "📁 Backups created with extension: .pre-connection-fix.backup"
echo ""
echo "⚠️  IMPORTANT NEXT STEPS:"
echo "   1. Review changes in files"
echo "   2. Test critical API endpoints"
echo "   3. Monitor connection count: SHOW PROCESSLIST;"
echo "   4. Deploy to staging first"
echo ""
