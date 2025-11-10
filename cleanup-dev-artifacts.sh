#!/bin/bash
# Module Cleanup Script - Remove Test Files & Debug Code
# Cleans up development artifacts before production deployment

echo "🧹 CIS Module Cleanup Script"
echo "============================"
echo ""

MODULES_DIR="/home/master/applications/jcepnzzkmj/public_html/modules"
BACKUP_DIR="/home/master/applications/jcepnzzkmj/backups/test-files-$(date +%Y%m%d-%H%M%S)"

# Create backup directory
mkdir -p "$BACKUP_DIR"

echo "📋 Scanning for cleanup targets..."
echo ""

# 1. Find and backup test files
echo "1️⃣ Test Files (134+ found)"
find "$MODULES_DIR" -name "test*.php" -o -name "*test.php" | while read -r file; do
    echo "   Backing up: $file"
    # Create directory structure in backup
    rel_path="${file#$MODULES_DIR/}"
    backup_file="$BACKUP_DIR/$rel_path"
    mkdir -p "$(dirname "$backup_file")"
    cp "$file" "$backup_file"
done

echo ""
echo "2️⃣ TODO/FIXME Comments"
grep -r "TODO\|FIXME\|XXX\|HACK\|BUG" "$MODULES_DIR" --include="*.php" --exclude-dir=vendor | head -20
TODO_COUNT=$(grep -r "TODO\|FIXME\|XXX\|HACK\|BUG" "$MODULES_DIR" --include="*.php" --exclude-dir=vendor | wc -l)
echo "   Found: $TODO_COUNT comments"

echo ""
echo "3️⃣ Console.log Statements (Debug Code)"
grep -r "console\.log" "$MODULES_DIR" --include="*.js" --exclude-dir=node_modules | head -20
CONSOLE_COUNT=$(grep -r "console\.log" "$MODULES_DIR" --include="*.js" --exclude-dir=node_modules | wc -l)
echo "   Found: $CONSOLE_COUNT statements"

echo ""
echo "4️⃣ Duplicate modules/modules/ Directory"
if [ -d "$MODULES_DIR/modules" ]; then
    echo "   ⚠️  Found: $MODULES_DIR/modules/"
    echo "   Comparing with $MODULES_DIR/human_resources/..."
    diff -r "$MODULES_DIR/modules/human_resources" "$MODULES_DIR/human_resources" || echo "   Directories differ - manual review required"
else
    echo "   ✅ No duplicate directory found"
fi

echo ""
echo "📊 Cleanup Summary:"
echo "   Test files: 134+"
echo "   TODO comments: $TODO_COUNT"
echo "   console.log: $CONSOLE_COUNT"
echo "   Backup location: $BACKUP_DIR"

echo ""
echo "⚠️  MANUAL ACTIONS REQUIRED:"
echo ""
echo "1. Review backed up test files"
echo "2. Resolve TODO/FIXME comments"
echo "3. Remove or comment console.log statements"
echo "4. Delete modules/modules/ after verification"

echo ""
echo "🚀 To remove test files (AFTER BACKUP VERIFICATION):"
echo "   find $MODULES_DIR -name 'test*.php' -delete"
echo ""
