#!/bin/bash
echo "════════════════════════════════════════════════════════════"
echo "  📦 IMPORTING CIS MODULE DATABASE SCHEMAS"
echo "════════════════════════════════════════════════════════════"
echo ""

# Get database credentials
DB_USER="jcepnzzkmj"
DB_NAME="jcepnzzkmj"
DB_PASS=$(grep DB_PASSWORD /home/129337.cloudwaysapps.com/jcepnzzkmj/public_html/.env | cut -d'=' -f2 | tr -d '"' | tr -d "'")

if [ -z "$DB_PASS" ]; then
    echo "❌ Could not find DB_PASSWORD in .env file"
    exit 1
fi

echo "🔐 Using database: $DB_NAME"
echo ""

# Import Stock Transfer Engine schema
echo "📊 Step 1: Importing Stock Transfer Engine base tables..."
mysql -u $DB_USER -p"$DB_PASS" $DB_NAME < stock_transfer_engine/database/current_database_schema.sql 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Stock Transfer Engine base tables imported"
else
    echo "⚠️  Some tables may already exist (this is OK if extending)"
fi
echo ""

# Import additive migration
echo "🔧 Step 2: Applying additive migration (extends existing tables)..."
mysql -u $DB_USER -p"$DB_PASS" $DB_NAME < stock_transfer_engine/database/migration_addon.sql 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Additive migration applied successfully"
else
    echo "⚠️  Check for errors above"
fi
echo ""

# Import Crawler schema
echo "🕷️  Step 3: Importing Crawler tables..."
mysql -u $DB_USER -p"$DB_PASS" $DB_NAME < crawlers/database_schema.sql 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Crawler tables imported"
fi
echo ""

# Import Dynamic Pricing schema
echo "💰 Step 4: Importing Dynamic Pricing tables..."
mysql -u $DB_USER -p"$DB_PASS" $DB_NAME < dynamic_pricing/database_schema.sql 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Dynamic Pricing tables imported"
fi
echo ""

# Verify import
echo "🔍 Step 5: Verifying all tables..."
echo ""
mysql -u $DB_USER -p"$DB_PASS" $DB_NAME -e "
SELECT 
    'Stock Transfer Tables' as category,
    COUNT(*) as count 
FROM information_schema.tables 
WHERE table_schema = '$DB_NAME' 
AND table_name LIKE '%stock_transfer%'
UNION ALL
SELECT 
    'Crawler Tables' as category,
    COUNT(*) as count 
FROM information_schema.tables 
WHERE table_schema = '$DB_NAME' 
AND table_name LIKE 'crawler_%'
UNION ALL
SELECT 
    'Pricing Tables' as category,
    COUNT(*) as count 
FROM information_schema.tables 
WHERE table_schema = '$DB_NAME' 
AND table_name LIKE '%pricing%';" 2>&1

echo ""
echo "════════════════════════════════════════════════════════════"
echo "  ✅ SCHEMA IMPORT COMPLETE"
echo "════════════════════════════════════════════════════════════"
