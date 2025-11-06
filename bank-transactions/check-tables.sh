#!/bin/bash
#
# Quick Database Table Check for Bank Transactions Module
#

echo "🔍 Checking Database Tables..."
echo "======================================"

DB_HOST="127.0.0.1"
DB_USER="jcepnzzkmj"
DB_PASS="wprKh9Jq63"
DB_NAME="jcepnzzkmj"

# Check if tables exist
echo ""
echo "📊 Checking for bank_transactions tables..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SHOW TABLES LIKE 'bank_%';" 2>&1

echo ""
echo "📊 Checking for orders tables..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SHOW TABLES LIKE 'orders%';" 2>&1

echo ""
echo "======================================"
echo "✅ Table check complete"
echo ""
echo "To create missing tables, run:"
echo "  php migrations/001_create_bank_transactions_tables.php"
echo "  php migrations/002_create_bank_deposits_table.php"
