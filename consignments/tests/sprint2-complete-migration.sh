#!/bin/bash
# Sprint 2: Complete Bootstrap Migration - SAFE & COMPREHENSIVE
# Fixes bootstrap files, then migrates all module files

set -e

echo "=========================================="
echo "  Sprint 2: Bootstrap Pattern Migration  "
echo "=========================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

MODULES_DIR="/home/master/applications/jcepnzzkmj/public_html/modules"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/home/master/applications/jcepnzzkmj/private_html/backups/sprint2_$TIMESTAMP"

# Counters
TOTAL_BOOTSTRAP_FIXED=0
TOTAL_FILES_FIXED=0
TOTAL_FAILED=0

echo -e "${CYAN}📦 Creating backup directory...${NC}"
mkdir -p "$BACKUP_DIR"
echo -e "${GREEN}✓ Backups will be saved to: $BACKUP_DIR${NC}"
echo ""

# ============================================================================
# PHASE 1: FIX BOOTSTRAP FILES THEMSELVES
# ============================================================================

echo -e "${BLUE}═══════════════════════════════════════${NC}"
echo -e "${BLUE}  PHASE 1: Fix Bootstrap Files${NC}"
echo -e "${BLUE}═══════════════════════════════════════${NC}"
echo ""

# List of bootstrap files that need fixing
BOOTSTRAP_FILES=(
    "$MODULES_DIR/flagged_products/bootstrap.php"
    "$MODULES_DIR/flagged-products/bootstrap.php"
    "$MODULES_DIR/shared/bootstrap.php"
    "$MODULES_DIR/consignments/bootstrap.php"
)

for bootstrap in "${BOOTSTRAP_FILES[@]}"; do
    if [ ! -f "$bootstrap" ]; then
        continue
    fi

    module=$(basename $(dirname "$bootstrap"))

    if grep -q "require.*app\.php" "$bootstrap"; then
        echo -e "${YELLOW}⚠ Found app.php in: $module/bootstrap.php${NC}"

        # Backup
        cp "$bootstrap" "$BACKUP_DIR/${module}_bootstrap.php.bak"

        # Comment out app.php requires (safer than deleting)
        sed -i 's|^\(\s*\)require_once ROOT_PATH . '\''/app\.php'\'';|\1// REMOVED: require_once ROOT_PATH . '\''/app\.php'\''; // Sprint 2: Use shared bootstrap|g' "$bootstrap"
        sed -i 's|^\(\s*\)require_once ROOT_PATH . '\''/bootstrap/app\.php'\'';|\1// REMOVED: require_once ROOT_PATH . '\''/bootstrap/app\.php'\''; // Sprint 2: Use shared bootstrap|g' "$bootstrap"

        echo -e "${GREEN}  ✓ Fixed: $module/bootstrap.php${NC}"
        ((TOTAL_BOOTSTRAP_FIXED++))
    else
        echo -e "${GREEN}  ✓ Clean: $module/bootstrap.php${NC}"
    fi
done

echo ""
echo -e "${GREEN}Phase 1 Complete: Fixed $TOTAL_BOOTSTRAP_FIXED bootstrap files${NC}"
echo ""

# ============================================================================
# PHASE 2: MIGRATE MODULE FILES
# ============================================================================

echo -e "${BLUE}═══════════════════════════════════════${NC}"
echo -e "${BLUE}  PHASE 2: Migrate Module Files${NC}"
echo -e "${BLUE}═══════════════════════════════════════${NC}"
echo ""

# Function to calculate relative path from file to bootstrap
get_relative_bootstrap_path() {
    local file=$1
    local module=$2
    local bootstrap="$MODULES_DIR/$module/bootstrap.php"

    if [ ! -f "$bootstrap" ]; then
        echo "ERROR: No bootstrap"
        return 1
    fi

    local file_dir=$(dirname "$file")
    local rel_path=$(realpath --relative-to="$file_dir" "$bootstrap" 2>/dev/null || echo "")

    if [ -z "$rel_path" ]; then
        echo "ERROR: Could not calculate path"
        return 1
    fi

    echo "$rel_path"
}

# Function to migrate a single file
migrate_file() {
    local file=$1
    local module=$2

    # Skip exclusions
    if [[ $file == *"/_archive/"* ]] || \
       [[ $file == *"/tests/"* ]] || \
       [[ $file == *"/_docs/"* ]] || \
       [[ $file == *"/docs/"* ]] || \
       [[ $file == *".md" ]] || \
       [[ $file == *".sh" ]] || \
       [[ $file == *"backup"* ]]; then
        return 0
    fi

    # Check if already using bootstrap pattern
    if grep -q "require_once __DIR__ \. '/.*bootstrap\.php'" "$file"; then
        echo -e "${GREEN}    ✓ Already migrated: $(basename $file)${NC}"
        return 0
    fi

    # Check if has app.php
    if ! grep -q "require.*app\.php" "$file"; then
        return 0
    fi

    # Calculate relative path to bootstrap
    local rel_path=$(get_relative_bootstrap_path "$file" "$module")
    if [[ $rel_path == ERROR* ]]; then
        echo -e "${RED}    ✗ Failed to calculate path: $(basename $file)${NC}"
        ((TOTAL_FAILED++))
        return 1
    fi

    # Backup file
    local backup_name=$(echo "$file" | sed 's/\//_/g')
    cp "$file" "$BACKUP_DIR/$backup_name.bak"

    # Replace patterns (most common first)
    local changed=false

    # Pattern 1: $_SERVER['DOCUMENT_ROOT'] . '/app.php'
    if sed -i "s|require_once \$_SERVER\['DOCUMENT_ROOT'\] \. '/app\.php';|require_once __DIR__ . '/$rel_path';|g" "$file"; then
        changed=true
    fi

    # Pattern 2: __DIR__ . '/../../../app.php' or similar
    if sed -i "s|require_once __DIR__ \. '/\.\./\.\./\.\./app\.php';|require_once __DIR__ . '/$rel_path';|g" "$file"; then
        changed=true
    fi
    if sed -i "s|require_once __DIR__ \. '/\.\./\.\./\.\./\.\./app\.php';|require_once __DIR__ . '/$rel_path';|g" "$file"; then
        changed=true
    fi

    # Pattern 3: dirname(__DIR__, N) . '/app.php'
    if sed -i "s|require_once dirname(__DIR__, [0-9]) \. '/app\.php';|require_once __DIR__ . '/$rel_path';|g" "$file"; then
        changed=true
    fi

    # Pattern 4: ROOT_PATH . '/app.php'
    if sed -i "s|require_once ROOT_PATH \. '/app\.php';|require_once __DIR__ . '/$rel_path';|g" "$file"; then
        changed=true
    fi

    # Pattern 5: Absolute path
    if sed -i "s|require_once '/home/master/applications/jcepnzzkmj/public_html/app\.php';|require_once __DIR__ . '/$rel_path';|g" "$file"; then
        changed=true
    fi

    if [ "$changed" = true ]; then
        echo -e "${GREEN}    ✓ Migrated: $(basename $file)${NC}"
        ((TOTAL_FILES_FIXED++))
    fi
}

# Migrate each module
MODULES_TO_MIGRATE=(
    "flagged_products"
    "flagged-products"
    "human_resources/payroll"
    "base"
    "staff-accounts"
    "bank-transactions"
    "admin-ui"
    "consignments"
)

for module in "${MODULES_TO_MIGRATE[@]}"; do
    module_path="$MODULES_DIR/$module"

    if [ ! -d "$module_path" ]; then
        echo -e "${YELLOW}⊘ Module not found: $module${NC}"
        continue
    fi

    echo -e "${CYAN}📁 Processing: $module${NC}"

    # Find all PHP files with app.php requires
    files=$(grep -rl "require.*app\.php" "$module_path" 2>/dev/null | \
            grep "\.php$" | \
            grep -v "/_archive/" | \
            grep -v "/tests/" | \
            grep -v "/docs/" | \
            grep -v "backup" || true)

    if [ -z "$files" ]; then
        echo -e "${GREEN}  ✓ No files to migrate${NC}"
        echo ""
        continue
    fi

    # Migrate each file
    for file in $files; do
        # Extract module name (handle nested like human_resources/payroll)
        if [[ $module == *"/"* ]]; then
            base_module=$(echo "$module" | cut -d'/' -f1)
        else
            base_module=$module
        fi
        migrate_file "$file" "$base_module"
    done

    echo ""
done

# ============================================================================
# PHASE 3: VALIDATION
# ============================================================================

echo -e "${BLUE}═══════════════════════════════════════${NC}"
echo -e "${BLUE}  PHASE 3: Validation${NC}"
echo -e "${BLUE}═══════════════════════════════════════${NC}"
echo ""

echo -e "${CYAN}Running syntax check on migrated files...${NC}"

SYNTAX_ERRORS=0

for backup in "$BACKUP_DIR"/*.bak; do
    if [ ! -f "$backup" ]; then
        continue
    fi

    # Get original file path
    original=$(echo "$backup" | sed 's/.bak$//' | sed 's/_/\//g' | sed "s|$BACKUP_DIR/|/home/master/applications/jcepnzzkmj/public_html/modules/|")

    if [ -f "$original" ] && [[ $original == *.php ]]; then
        if ! php -l "$original" > /dev/null 2>&1; then
            echo -e "${RED}  ✗ Syntax error: $original${NC}"
            echo "    Restoring from backup..."
            cp "$backup" "$original"
            ((SYNTAX_ERRORS++))
        fi
    fi
done

if [ $SYNTAX_ERRORS -eq 0 ]; then
    echo -e "${GREEN}  ✓ All files pass syntax check${NC}"
else
    echo -e "${RED}  ✗ $SYNTAX_ERRORS files had syntax errors (restored from backup)${NC}"
fi

echo ""

# ============================================================================
# FINAL SUMMARY
# ============================================================================

echo ""
echo -e "${BLUE}═══════════════════════════════════════${NC}"
echo -e "${BLUE}  MIGRATION COMPLETE${NC}"
echo -e "${BLUE}═══════════════════════════════════════${NC}"
echo ""
echo -e "${GREEN}✓ Bootstrap files fixed: $TOTAL_BOOTSTRAP_FIXED${NC}"
echo -e "${GREEN}✓ Module files migrated: $TOTAL_FILES_FIXED${NC}"
echo -e "${RED}✗ Failed migrations: $TOTAL_FAILED${NC}"
echo -e "${RED}✗ Syntax errors: $SYNTAX_ERRORS${NC}"
echo ""
echo -e "${BLUE}📦 Backups saved to:${NC}"
echo "   $BACKUP_DIR"
echo ""

# Final check for remaining app.php usage
echo -e "${CYAN}Checking for remaining app.php usage...${NC}"

remaining=$(grep -r "require.*app\.php" "$MODULES_DIR" 2>/dev/null | \
            grep -v ".md:" | \
            grep -v "/_archive/" | \
            grep -v "/tests/" | \
            grep -v "/docs/" | \
            grep -v "backup" | \
            grep -v ".sh:" | \
            grep -v "^#" | \
            wc -l || echo "0")

if [ "$remaining" -eq 0 ]; then
    echo -e "${GREEN}✓ No app.php usage remaining in active code!${NC}"
else
    echo -e "${YELLOW}⚠ $remaining files still reference app.php${NC}"
    echo "   (May need manual review)"
fi

echo ""
echo -e "${GREEN}════════════════════════════════════════${NC}"
echo -e "${GREEN}  Sprint 2 Migration Complete! 🎉${NC}"
echo -e "${GREEN}════════════════════════════════════════${NC}"
echo ""
echo "Next steps:"
echo "1. Test critical endpoints"
echo "2. Monitor error logs"
echo "3. Run: modules/consignments/tests/test-sprint1-endpoints.php"
echo "4. Commit: git add -A && git commit -m 'Sprint 2: Migrate to bootstrap pattern'"
echo ""

exit 0
