#!/bin/bash
# Pre-Migration Verification - Check module structure before migration

set -e

echo "=== Pre-Migration Verification ==="
echo ""

MODULES_DIR="/home/master/applications/jcepnzzkmj/public_html/modules"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}Checking module bootstrap files...${NC}"
echo ""

MODULES=("flagged_products" "flagged-products" "human_resources" "base" "staff-accounts" "bank-transactions" "admin-ui" "consignments")

MISSING_BOOTSTRAP=0

for module in "${MODULES[@]}"; do
    bootstrap="$MODULES_DIR/$module/bootstrap.php"
    if [ -f "$bootstrap" ]; then
        echo -e "${GREEN}✓ $module has bootstrap.php${NC}"
    else
        echo -e "${RED}✗ $module missing bootstrap.php${NC}"
        ((MISSING_BOOTSTRAP++))
    fi
done

echo ""
echo -e "${BLUE}Counting app.php usages per module (excluding docs/tests/archives)...${NC}"
echo ""

for module in "${MODULES[@]}"; do
    if [ ! -d "$MODULES_DIR/$module" ]; then
        continue
    fi

    count=$(grep -rl "require.*app\.php" "$MODULES_DIR/$module/" 2>/dev/null | \
            grep -v ".md$" | \
            grep -v "_archive" | \
            grep -v "/tests/" | \
            grep -v "/docs/" | \
            grep -v "backup" | \
            wc -l || echo "0")

    if [ "$count" -gt 0 ]; then
        echo -e "${YELLOW}$module: $count files${NC}"
    else
        echo -e "${GREEN}$module: $count files${NC}"
    fi
done

echo ""
echo "=== Summary ==="
if [ $MISSING_BOOTSTRAP -gt 0 ]; then
    echo -e "${RED}✗ $MISSING_BOOTSTRAP modules missing bootstrap.php${NC}"
    echo "These modules need bootstrap.php created before migration"
    exit 1
else
    echo -e "${GREEN}✓ All modules have bootstrap.php${NC}"
    echo "Ready for migration!"
fi
