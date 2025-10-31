#!/bin/bash
#
# PRODUCTION DEPLOYMENT - Staff Accounts Payment System
# One-command deploy with automatic backup and rollback capability
#
# Usage:
#   bash deploy-payment-system.sh
#   bash deploy-payment-system.sh --rollback  (to undo)
#

set -e  # Exit on any error

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/home/master/applications/jcepnzzkmj/backups/staff-accounts-${TIMESTAMP}"
MODULE_DIR="/home/master/applications/jcepnzzkmj/public_html/modules/staff-accounts"

# Auto-detect database name from PHP
DB_NAME=$(php -r "
    require_once '$MODULE_DIR/bootstrap.php';
    if (isset(\$db) && \$db instanceof mysqli) {
        \$result = \$db->query('SELECT DATABASE()');
        if (\$result) {
            \$row = \$result->fetch_row();
            echo \$row[0];
        }
    }
" 2>/dev/null)

# Fallback if PHP detection fails
if [ -z "$DB_NAME" ]; then
    DB_NAME="${DB_NAME:-jcepnzzkmj}"
    echo "⚠️  Could not auto-detect database, using default: $DB_NAME"
fi

echo "📊 Using database: $DB_NAME"

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  STAFF ACCOUNTS PAYMENT SYSTEM - PRODUCTION DEPLOYMENT"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Check for rollback flag
if [ "$1" == "--rollback" ]; then
    echo "🔄 ROLLBACK MODE - This will restore the previous version"
    echo ""
    read -p "Enter backup directory to restore (e.g., /home/master/applications/jcepnzzkmj/backups/staff-accounts-20251023_143022): " RESTORE_DIR
    
    if [ ! -d "$RESTORE_DIR" ]; then
        echo "❌ Backup directory not found: $RESTORE_DIR"
        exit 1
    fi
    
    echo "Restoring database from backup..."
    mysql "$DB_NAME" < "$RESTORE_DIR/database.sql"
    
    echo "✅ Rollback complete!"
    exit 0
fi

# ============================================================================
# STEP 1: PRE-FLIGHT CHECKS
# ============================================================================

echo "📋 Step 1: Pre-flight checks"
echo "───────────────────────────────────────────────────────────"

# Check if we're in the right directory
if [ ! -f "$MODULE_DIR/bootstrap.php" ]; then
    echo "❌ ERROR: Not in staff-accounts module directory"
    echo "   Expected: $MODULE_DIR"
    exit 1
fi

# Check database connection
if ! mysql -e "SELECT 1" "$DB_NAME" > /dev/null 2>&1; then
    echo "❌ ERROR: Cannot connect to database '$DB_NAME'"
    echo "   Please check database credentials"
    exit 1
fi

# Check PHP version
PHP_VERSION=$(php -r 'echo PHP_VERSION;')
echo "✅ PHP Version: $PHP_VERSION"

# Check required PHP extensions
for ext in mysqli pdo pdo_mysql json curl; do
    if ! php -m | grep -q "^$ext$"; then
        echo "❌ ERROR: Required PHP extension missing: $ext"
        exit 1
    fi
done
echo "✅ Required PHP extensions installed"

echo ""

# ============================================================================
# STEP 2: CREATE BACKUP
# ============================================================================

echo "💾 Step 2: Creating backup"
echo "───────────────────────────────────────────────────────────"

mkdir -p "$BACKUP_DIR"

# Backup database
echo "   Backing up database..."
mysqldump "$DB_NAME" > "$BACKUP_DIR/database.sql"

# Backup files (only if they exist)
echo "   Backing up files..."
if [ -f "$MODULE_DIR/manager-dashboard.php" ]; then
    cp "$MODULE_DIR/manager-dashboard.php" "$BACKUP_DIR/"
fi
if [ -f "$MODULE_DIR/api/payment.php" ]; then
    cp "$MODULE_DIR/api/payment.php" "$BACKUP_DIR/"
fi
if [ -f "$MODULE_DIR/api/manager-dashboard.php" ]; then
    cp "$MODULE_DIR/api/manager-dashboard.php" "$BACKUP_DIR/"
fi

echo "✅ Backup created: $BACKUP_DIR"
echo ""

# ============================================================================
# STEP 3: RUN DATABASE MIGRATIONS
# ============================================================================

echo "🗄️  Step 3: Running database migrations"
echo "───────────────────────────────────────────────────────────"

# Nuvei payment tables
if [ -f "$MODULE_DIR/database/nuvei-tables.sql" ]; then
    echo "   Creating Nuvei payment tables..."
    mysql "$DB_NAME" < "$MODULE_DIR/database/nuvei-tables.sql"
    echo "   ✅ Nuvei tables created"
fi

# Manager dashboard tables
if [ -f "$MODULE_DIR/database/manager-dashboard-tables.sql" ]; then
    echo "   Creating manager dashboard tables..."
    mysql "$DB_NAME" < "$MODULE_DIR/database/manager-dashboard-tables.sql"
    echo "   ✅ Manager dashboard tables created"
fi

echo "✅ All migrations complete"
echo ""

# ============================================================================
# STEP 4: VERIFY SCHEMA
# ============================================================================

echo "🔍 Step 4: Verifying database schema"
echo "───────────────────────────────────────────────────────────"

# Check critical tables exist
REQUIRED_TABLES=(
    "staff_payment_transactions"
    "staff_saved_cards"
    "staff_payment_plans"
    "staff_payment_plan_installments"
    "staff_reminder_log"
    "staff_account_balance"
)

for table in "${REQUIRED_TABLES[@]}"; do
    if mysql -e "SHOW TABLES LIKE '$table'" "$DB_NAME" | grep -q "$table"; then
        echo "   ✅ $table"
    else
        echo "   ❌ $table - MISSING!"
        exit 1
    fi
done

echo "✅ All required tables exist"
echo ""

# ============================================================================
# STEP 5: CONFIGURE SYSTEM
# ============================================================================

echo "⚙️  Step 5: System configuration"
echo "───────────────────────────────────────────────────────────"

# Add Nuvei config placeholders (if not exist)
mysql "$DB_NAME" <<EOF
INSERT IGNORE INTO config (setting_key, setting_value, setting_group) VALUES
('nuvei_merchant_id', 'CONFIGURE_ME', 'payment'),
('nuvei_merchant_site_id', 'CONFIGURE_ME', 'payment'),
('nuvei_secret_key', 'CONFIGURE_ME', 'payment'),
('nuvei_environment', 'sandbox', 'payment');
EOF

echo "   ✅ Nuvei config entries created (remember to update with real credentials)"

# Grant manager permissions to admin users
mysql "$DB_NAME" <<EOF
UPDATE users SET is_manager = 1 
WHERE role IN ('admin', 'director', 'manager') 
AND is_active = 1;
EOF

MANAGER_COUNT=$(mysql -s -N -e "SELECT COUNT(*) FROM users WHERE is_manager = 1" "$DB_NAME")
echo "   ✅ Manager permissions granted to $MANAGER_COUNT users"

echo ""

# ============================================================================
# STEP 6: FILE PERMISSIONS
# ============================================================================

echo "🔐 Step 6: Setting file permissions"
echo "───────────────────────────────────────────────────────────"

chmod 755 "$MODULE_DIR"
chmod 755 "$MODULE_DIR/api"
chmod 644 "$MODULE_DIR"/api/*.php
chmod 644 "$MODULE_DIR"/lib/*.php
chmod 644 "$MODULE_DIR"/*.php

echo "✅ File permissions set"
echo ""

# ============================================================================
# STEP 7: SMOKE TESTS
# ============================================================================

echo "🧪 Step 7: Running smoke tests"
echo "───────────────────────────────────────────────────────────"

# Test 1: Check if files exist
FILES_TO_CHECK=(
    "$MODULE_DIR/manager-dashboard.php"
    "$MODULE_DIR/api/payment.php"
    "$MODULE_DIR/api/manager-dashboard.php"
    "$MODULE_DIR/lib/NuveiPayment.php"
    "$MODULE_DIR/lib/LightspeedAPI.php"
)

for file in "${FILES_TO_CHECK[@]}"; do
    if [ -f "$file" ]; then
        echo "   ✅ $(basename "$file")"
    else
        echo "   ❌ $(basename "$file") - MISSING!"
        exit 1
    fi
done

# Test 2: PHP syntax check
echo ""
echo "   Checking PHP syntax..."
for file in "${FILES_TO_CHECK[@]}"; do
    if [ -f "$file" ]; then
        if php -l "$file" > /dev/null 2>&1; then
            echo "   ✅ $(basename "$file") - syntax OK"
        else
            echo "   ❌ $(basename "$file") - SYNTAX ERROR!"
            php -l "$file"
            exit 1
        fi
    fi
done

# Test 3: Database queries
echo ""
echo "   Testing database queries..."
BALANCE_COUNT=$(mysql -s -N -e "SELECT COUNT(*) FROM staff_account_balance" "$DB_NAME")
echo "   ✅ Found $BALANCE_COUNT staff account balances"

PAYMENT_COUNT=$(mysql -s -N -e "SELECT COUNT(*) FROM staff_payment_transactions" "$DB_NAME")
echo "   ✅ Payment transactions table ready (current: $PAYMENT_COUNT records)"

echo "✅ All smoke tests passed"
echo ""

# ============================================================================
# DEPLOYMENT COMPLETE
# ============================================================================

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  ✅ DEPLOYMENT SUCCESSFUL"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📦 Backup saved to: $BACKUP_DIR"
echo ""
echo "🚀 Next Steps:"
echo ""
echo "   1. Update Nuvei credentials in config table:"
echo "      UPDATE config SET setting_value = 'YOUR_VALUE' WHERE setting_key = 'nuvei_merchant_id';"
echo ""
echo "   2. Access Manager Dashboard:"
echo "      https://staff.vapeshed.co.nz/modules/staff-accounts/manager-dashboard.php"
echo ""
echo "   3. Test payment flow:"
echo "      https://staff.vapeshed.co.nz/modules/staff-accounts/staff-reconciliation.php"
echo ""
echo "   4. To rollback if needed:"
echo "      bash deploy-payment-system.sh --rollback"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
