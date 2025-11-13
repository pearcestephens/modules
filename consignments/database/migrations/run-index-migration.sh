#!/bin/bash
# =====================================================================
# Safe Index Migration Script
# =====================================================================
# Checks existing indexes before running migration
# Provides summary and rollback instructions
#
# Usage:
#   ./run-index-migration.sh [--check-only|--force]
#
# Options:
#   --check-only   Only check existing indexes, don't migrate
#   --force        Skip confirmation prompt
# =====================================================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CHECK_SCRIPT="${SCRIPT_DIR}/check-existing-indexes.php"
MIGRATION_SQL="${SCRIPT_DIR}/add-consignment-indexes.sql"
VERIFY_SCRIPT="${SCRIPT_DIR}/verify-indexes.php"

# Parse arguments
CHECK_ONLY=0
FORCE=0

for arg in "$@"; do
    case $arg in
        --check-only)
            CHECK_ONLY=1
            shift
            ;;
        --force)
            FORCE=1
            shift
            ;;
        --help|-h)
            echo "Usage: $0 [--check-only|--force]"
            echo ""
            echo "Options:"
            echo "  --check-only   Only check existing indexes, don't migrate"
            echo "  --force        Skip confirmation prompt"
            echo ""
            exit 0
            ;;
    esac
done

echo ""
echo -e "${BLUE}=======================================${NC}"
echo -e "${BLUE}  Consignment Index Migration Tool${NC}"
echo -e "${BLUE}=======================================${NC}"
echo ""

# Step 1: Check existing indexes
echo -e "${BLUE}[STEP 1/4]${NC} Checking existing indexes..."
echo ""

if [ ! -f "$CHECK_SCRIPT" ]; then
    echo -e "${RED}Error: Check script not found: ${CHECK_SCRIPT}${NC}"
    exit 1
fi

php "$CHECK_SCRIPT"
CHECK_EXIT=$?

if [ $CHECK_EXIT -ne 0 ]; then
    echo -e "${RED}Error checking indexes. Please verify database connection.${NC}"
    exit 1
fi

echo ""

# If check-only mode, exit here
if [ $CHECK_ONLY -eq 1 ]; then
    echo -e "${GREEN}Check-only mode. Exiting.${NC}"
    exit 0
fi

# Step 2: Confirm migration
if [ $FORCE -eq 0 ]; then
    echo -e "${YELLOW}[STEP 2/4]${NC} Confirm migration"
    echo ""
    echo "This will add missing indexes to consignments tables."
    echo "Existing indexes will be skipped (IF NOT EXISTS)."
    echo ""
    read -p "Continue with migration? [y/N] " -n 1 -r
    echo ""

    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${YELLOW}Migration cancelled.${NC}"
        exit 0
    fi
else
    echo -e "${YELLOW}[STEP 2/4]${NC} Force mode - skipping confirmation"
fi

echo ""

# Step 3: Run migration
echo -e "${BLUE}[STEP 3/4]${NC} Running migration..."
echo ""

if [ ! -f "$MIGRATION_SQL" ]; then
    echo -e "${RED}Error: Migration SQL not found: ${MIGRATION_SQL}${NC}"
    exit 1
fi

# Get database credentials from PHP
DB_HOST=$(php -r "require '${SCRIPT_DIR}/../bootstrap.php'; echo defined('DB_HOST') ? DB_HOST : '127.0.0.1';")
DB_NAME=$(php -r "require '${SCRIPT_DIR}/../bootstrap.php'; echo defined('DB_NAME') ? DB_NAME : '';")
DB_USER=$(php -r "require '${SCRIPT_DIR}/../bootstrap.php'; echo defined('DB_USER') ? DB_USER : '';")
DB_PASS=$(php -r "require '${SCRIPT_DIR}/../bootstrap.php'; echo defined('DB_PASS') ? DB_PASS : '';")

if [ -z "$DB_NAME" ] || [ -z "$DB_USER" ]; then
    echo -e "${RED}Error: Could not load database credentials from bootstrap.php${NC}"
    exit 1
fi

# Run migration
echo "Executing SQL migration..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$MIGRATION_SQL"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Migration completed successfully${NC}"
else
    echo -e "${RED}✗ Migration failed${NC}"
    exit 1
fi

echo ""

# Step 4: Verify indexes
echo -e "${BLUE}[STEP 4/4]${NC} Verifying indexes..."
echo ""

if [ ! -f "$VERIFY_SCRIPT" ]; then
    echo -e "${YELLOW}Warning: Verify script not found: ${VERIFY_SCRIPT}${NC}"
    echo -e "${YELLOW}Skipping verification step.${NC}"
else
    php "$VERIFY_SCRIPT"

    if [ $? -eq 0 ]; then
        echo ""
        echo -e "${GREEN}=======================================${NC}"
        echo -e "${GREEN}  Migration Complete! ✓${NC}"
        echo -e "${GREEN}=======================================${NC}"
        echo ""
        echo -e "${GREEN}All indexes verified successfully.${NC}"
        echo ""
        echo "Next steps:"
        echo "  1. Monitor query performance"
        echo "  2. Run ANALYZE TABLE consignments;"
        echo "  3. Run ANALYZE TABLE consignment_items;"
        echo ""
    else
        echo -e "${YELLOW}Warning: Index verification failed.${NC}"
        echo "Please manually verify indexes with: SHOW INDEXES FROM consignments;"
    fi
fi

exit 0
