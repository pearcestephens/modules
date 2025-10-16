#!/bin/bash
# Enhanced Consignment Upload System - Schema Verification
# 
# Standalone schema verification script that works without full CIS bootstrap
# Uses MySQL command line to check database schema

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration - Update these values for your environment
DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-root}"  
DB_NAME="${DB_NAME:-jcepnzzkmj}"

echo -e "${BLUE}Enhanced Consignment Upload System - Schema Verification${NC}"
echo "========================================================"

# Check if mysql command is available
if ! command -v mysql &> /dev/null; then
    echo -e "${RED}ERROR: mysql command not found${NC}"
    exit 1
fi

# Prompt for database password if not set
if [ -z "$DB_PASS" ]; then
    echo -n "Enter MySQL password for $DB_USER: "
    read -s DB_PASS
    echo
fi

# Test database connection
echo "Testing database connection..."
if ! mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 1;" &> /dev/null; then
    echo -e "${RED}ERROR: Cannot connect to database${NC}"
    echo "Please check your database credentials"
    exit 1
fi
echo -e "${GREEN}✓ Database connection successful${NC}"

# Check required tables
echo
echo "Checking Enhanced Consignment Upload schema..."

REQUIRED_TABLES=(
    "queue_consignments"
    "queue_consignment_products" 
    "queue_consignment_state_transitions"
    "consignment_upload_progress"
    "consignment_product_progress"
    "queue_jobs"
    "queue_webhook_events"
)

EXISTING_TABLES=()
MISSING_TABLES=()

for table in "${REQUIRED_TABLES[@]}"; do
    if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SHOW TABLES LIKE '$table';" 2>/dev/null | grep -q "$table"; then
        EXISTING_TABLES+=("$table")
        echo -e "${GREEN}✓ $table${NC}"
    else
        MISSING_TABLES+=("$table")
        echo -e "${RED}✗ $table${NC}"
    fi
done

# Check transfers.consignment_id column
echo
echo "Checking transfers table modifications..."
if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SHOW COLUMNS FROM transfers LIKE 'consignment_id';" 2>/dev/null | grep -q "consignment_id"; then
    echo -e "${GREEN}✓ transfers.consignment_id column exists${NC}"
else
    echo -e "${RED}✗ transfers.consignment_id column missing${NC}"
    MISSING_TABLES+=("transfers_modification")
fi

# Check system configuration
echo
echo "Checking system configuration..."
CONFIG_KEYS=(
    "consignment_upload_timeout"
    "consignment_upload_max_retries"
    "consignment_upload_batch_size"
    "sse_heartbeat_interval"
    "lightspeed_api_rate_limit"
)

if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SHOW TABLES LIKE 'system_config';" 2>/dev/null | grep -q "system_config"; then
    for key in "${CONFIG_KEYS[@]}"; do
        if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT COUNT(*) as count FROM system_config WHERE \`key\` = '$key';" 2>/dev/null | tail -1 | grep -q "1"; then
            echo -e "${GREEN}✓ $key${NC}"
        else
            echo -e "${YELLOW}⚠ $key (missing)${NC}"
        fi
    done
else
    echo -e "${RED}✗ system_config table missing${NC}"
fi

# Summary
echo
echo "========================================================"
echo "SUMMARY:"

if [ ${#EXISTING_TABLES[@]} -gt 0 ]; then
    echo -e "${GREEN}Existing tables (${#EXISTING_TABLES[@]}):${NC}"
    printf '%s\n' "${EXISTING_TABLES[@]}" | sed 's/^/  - /'
fi

if [ ${#MISSING_TABLES[@]} -gt 0 ]; then
    echo -e "${RED}Missing tables/modifications (${#MISSING_TABLES[@]}):${NC}"
    printf '%s\n' "${MISSING_TABLES[@]}" | sed 's/^/  - /'
    echo
    echo -e "${YELLOW}Migration required!${NC}"
    echo "Run the SQL schema manually or fix the PHP migration script."
else
    echo -e "${GREEN}✓ All required tables and modifications exist${NC}"
    echo -e "${GREEN}Enhanced Consignment Upload system is ready!${NC}"
fi

echo
echo "========================================================"

# Generate SQL migration if tables are missing
if [ ${#MISSING_TABLES[@]} -gt 0 ]; then
    echo "To manually create missing tables, you can run:"
    echo "mysql -h $DB_HOST -u $DB_USER -p $DB_NAME < enhanced-consignment-schema.sql"
fi