#!/bin/bash
# Sprint 2 - Phase 1: Fix bootstrap files that themselves require app.php

set -e

echo "=== Sprint 2 Phase 1: Fix Bootstrap Files ==="
echo ""

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

MODULES_DIR="/home/master/applications/jcepnzzkmj/public_html/modules"
BACKUP_DIR="/home/master/applications/jcepnzzkmj/private_html/backups/sprint2_phase1_$(date +%Y%m%d_%H%M%S)"

mkdir -p "$BACKUP_DIR"

echo -e "${BLUE}Phase 1: Fix bootstrap files that require app.php${NC}"
echo "--------------------------------------------------------"
echo ""

# Check each module's bootstrap
BOOTSTRAPS=(
    "$MODULES_DIR/flagged_products/bootstrap.php"
    "$MODULES_DIR/flagged-products/bootstrap.php"
    "$MODULES_DIR/shared/bootstrap.php"
    "$MODULES_DIR/consignments/bootstrap.php"
)

FIXED=0

for bootstrap in "${BOOTSTRAPS[@]}"; do
    if [ ! -f "$bootstrap" ]; then
        continue
    fi

    if grep -q "require.*app\.php" "$bootstrap"; then
        echo -e "${YELLOW}Found app.php in: $(basename $(dirname $bootstrap))/bootstrap.php${NC}"

        # Backup
        cp "$bootstrap" "$BACKUP_DIR/$(basename $(dirname $bootstrap))_bootstrap.php.bak"

        # Replace ROOT_PATH . '/app.php' with proper base bootstrap
        # Most modules should use shared/bootstrap.php which handles app.php loading
        sed -i "s|require_once ROOT_PATH \. '/app\.php';|// Bootstrap loads via shared/bootstrap.php - removed app.php direct require|g" "$bootstrap"

        echo -e "${GREEN}✓ Fixed: $bootstrap${NC}"
        ((FIXED++))
    else
        echo -e "${GREEN}✓ Clean: $(basename $(dirname $bootstrap))/bootstrap.php${NC}"
    fi
done

echo ""
echo "=== Phase 1 Summary ==="
echo -e "${GREEN}Fixed: $FIXED bootstrap files${NC}"
echo -e "${BLUE}Backups: $BACKUP_DIR${NC}"
echo ""
echo -e "${GREEN}✓ Bootstrap files cleaned!${NC}"
echo ""
echo "Next: Run sprint2-migrate-bootstrap.sh to fix module files"
