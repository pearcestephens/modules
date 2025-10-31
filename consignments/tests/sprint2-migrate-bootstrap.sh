#!/bin/bash
# Sprint 2: Replace app.php with bootstrap.php across remaining modules
# This script safely replaces app.php requires with module-local bootstrap.php

set -e

echo "=== Sprint 2: Bootstrap Pattern Migration ==="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

MODULES_DIR="/home/master/applications/jcepnzzkmj/public_html/modules"
BACKUP_DIR="/home/master/applications/jcepnzzkmj/private_html/backups/sprint2_$(date +%Y%m%d_%H%M%S)"

echo -e "${BLUE}Creating backup directory: $BACKUP_DIR${NC}"
mkdir -p "$BACKUP_DIR"

# Counter
TOTAL_FIXED=0
TOTAL_FAILED=0

# Function to replace app.php with bootstrap.php
replace_bootstrap() {
    local file=$1
    local module=$(echo "$file" | grep -oP 'modules/\K[^/]+' | head -1)

    # Skip if in tests, docs, or archive
    if [[ $file == *"/_archive/"* ]] || [[ $file == *"/tests/"* ]] || [[ $file == *"/_docs/"* ]] || [[ $file == *".md" ]]; then
        echo -e "${YELLOW}⊘ Skipping (test/doc/archive): $file${NC}"
        return 0
    fi

    # Skip consignments module (already fixed in Sprint 1)
    if [[ $module == "consignments" ]]; then
        echo -e "${GREEN}✓ Already fixed (Sprint 1): $file${NC}"
        return 0
    fi

    # Check if module has bootstrap.php
    local bootstrap_path="$MODULES_DIR/$module/bootstrap.php"
    if [ ! -f "$bootstrap_path" ]; then
        echo -e "${RED}✗ No bootstrap.php in $module module${NC}"
        ((TOTAL_FAILED++))
        return 1
    fi

    # Backup file
    local backup_file="$BACKUP_DIR/$(echo $file | sed 's/\//_/g')"
    cp "$file" "$backup_file"

    # Determine relative path to bootstrap
    local file_dir=$(dirname "$file")
    local rel_path=$(realpath --relative-to="$file_dir" "$bootstrap_path")

    # Replace patterns
    local changed=false

    # Pattern 1: require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
    if grep -q "require_once \$_SERVER\['DOCUMENT_ROOT'\] \. '/app\.php';" "$file"; then
        sed -i "s|require_once \$_SERVER\['DOCUMENT_ROOT'\] \. '/app\.php';|require_once __DIR__ . '/$rel_path';|g" "$file"
        changed=true
    fi

    # Pattern 2: require_once __DIR__ . '/../../../app.php';
    if grep -q "require_once __DIR__ \. '/.*app\.php';" "$file"; then
        sed -i "s|require_once __DIR__ \. '/.*app\.php';|require_once __DIR__ . '/$rel_path';|g" "$file"
        changed=true
    fi

    # Pattern 3: require_once ROOT_PATH . '/app.php';
    if grep -q "require_once ROOT_PATH \. '/app\.php';" "$file"; then
        sed -i "s|require_once ROOT_PATH \. '/app\.php';|require_once __DIR__ . '/$rel_path';|g" "$file"
        changed=true
    fi

    if [ "$changed" = true ]; then
        echo -e "${GREEN}✓ Fixed: $file${NC}"
        echo "  → __DIR__ . '/$rel_path'"
        ((TOTAL_FIXED++))
    else
        echo -e "${YELLOW}⊘ No changes needed: $file${NC}"
    fi
}

echo ""
echo -e "${BLUE}Phase 1: Fix flagged_products module${NC}"
echo "----------------------------------------"

FILES=$(grep -rl "require.*app\.php" "$MODULES_DIR/flagged_products/" 2>/dev/null | grep -v ".md$" | grep -v "_archive" || true)
for file in $FILES; do
    replace_bootstrap "$file"
done

echo ""
echo -e "${BLUE}Phase 2: Fix flagged-products module${NC}"
echo "----------------------------------------"

FILES=$(grep -rl "require.*app\.php" "$MODULES_DIR/flagged-products/" 2>/dev/null | grep -v ".md$" | grep -v "_archive" || true)
for file in $FILES; do
    replace_bootstrap "$file"
done

echo ""
echo -e "${BLUE}Phase 3: Fix human_resources module${NC}"
echo "----------------------------------------"

FILES=$(grep -rl "require.*app\.php" "$MODULES_DIR/human_resources/" 2>/dev/null | grep -v ".md$" | grep -v "_archive" | grep -v "tests/" || true)
for file in $FILES; do
    replace_bootstrap "$file"
done

echo ""
echo -e "${BLUE}Phase 4: Fix base module${NC}"
echo "----------------------------------------"

FILES=$(grep -rl "require.*app\.php" "$MODULES_DIR/base/" 2>/dev/null | grep -v ".md$" | grep -v "_archive" || true)
for file in $FILES; do
    replace_bootstrap "$file"
done

echo ""
echo -e "${BLUE}Phase 5: Fix staff-accounts module${NC}"
echo "----------------------------------------"

FILES=$(grep -rl "require.*app\.php" "$MODULES_DIR/staff-accounts/" 2>/dev/null | grep -v ".md$" | grep -v "_archive" || true)
for file in $FILES; do
    replace_bootstrap "$file"
done

echo ""
echo -e "${BLUE}Phase 6: Fix bank-transactions module${NC}"
echo "----------------------------------------"

FILES=$(grep -rl "require.*app\.php" "$MODULES_DIR/bank-transactions/" 2>/dev/null | grep -v ".md$" | grep -v "_archive" || true)
for file in $FILES; do
    replace_bootstrap "$file"
done

echo ""
echo -e "${BLUE}Phase 7: Fix admin-ui module${NC}"
echo "----------------------------------------"

FILES=$(grep -rl "require.*app\.php" "$MODULES_DIR/admin-ui/" 2>/dev/null | grep -v ".md$" | grep -v "_archive" | grep -v "backup" || true)
for file in $FILES; do
    replace_bootstrap "$file"
done

echo ""
echo -e "${BLUE}Phase 8: Fix remaining consignments UI files${NC}"
echo "----------------------------------------"

FILES=$(grep -rl "require.*app\.php" "$MODULES_DIR/consignments/purchase-orders/" 2>/dev/null | grep -v ".md$" || true)
FILES+=" "$(grep -rl "require.*app\.php" "$MODULES_DIR/consignments/api/purchase-orders/" 2>/dev/null | grep -v ".md$" | grep -v "accept-ai-insight\|dismiss-ai-insight\|bulk-" || true)
FILES+=" "$(grep -rl "require.*app\.php" "$MODULES_DIR/consignments/api/products/" 2>/dev/null | grep -v ".md$" || true)
FILES+=" "$(grep -rl "require.*app\.php" "$MODULES_DIR/consignments/cli/" 2>/dev/null | grep -v ".md$" || true)

for file in $FILES; do
    if [ -f "$file" ]; then
        replace_bootstrap "$file"
    fi
done

echo ""
echo "=== Migration Summary ==="
echo -e "${GREEN}Fixed: $TOTAL_FIXED files${NC}"
echo -e "${RED}Failed: $TOTAL_FAILED files${NC}"
echo -e "${BLUE}Backups: $BACKUP_DIR${NC}"
echo ""

if [ $TOTAL_FAILED -gt 0 ]; then
    echo -e "${YELLOW}⚠ Some files could not be migrated automatically${NC}"
    echo "Review the output above and fix manually"
    exit 1
fi

echo -e "${GREEN}✓ Sprint 2 migration complete!${NC}"
echo ""
echo "Next steps:"
echo "1. Run syntax check: find $MODULES_DIR -name '*.php' -exec php -l {} \; | grep -v 'No syntax errors'"
echo "2. Test critical endpoints"
echo "3. Monitor error logs"
echo "4. Commit changes: git add -A && git commit -m 'Sprint 2: Migrate all modules to bootstrap.php pattern'"
