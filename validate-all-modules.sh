#!/bin/bash
# Complete Module Validation Suite
# Tests all critical aspects of module health

echo "🔍 CIS MODULE VALIDATION SUITE"
echo "=============================="
echo ""

MODULES_DIR="/home/master/applications/jcepnzzkmj/public_html/modules"
REPORT_FILE="/home/master/applications/jcepnzzkmj/public_html/MODULE_VALIDATION_REPORT_$(date +%Y%m%d-%H%M%S).md"

# Initialize report
cat > "$REPORT_FILE" << 'EOF'
# CIS Module Validation Report

**Generated:** $(date)
**Status:** IN PROGRESS

---

## 1. PHP Syntax Validation

EOF

echo "1️⃣ PHP Syntax Check..."
SYNTAX_ERRORS=0

find "$MODULES_DIR" -name "*.php" -not -path "*/vendor/*" -not -path "*/node_modules/*" | while read -r file; do
    php -l "$file" > /dev/null 2>&1
    if [ $? -ne 0 ]; then
        echo "   ❌ $file"
        echo "- ❌ \`$file\`" >> "$REPORT_FILE"
        ((SYNTAX_ERRORS++))
    fi
done

if [ $SYNTAX_ERRORS -eq 0 ]; then
    echo "   ✅ All PHP files valid"
    echo "**Status:** ✅ All files valid" >> "$REPORT_FILE"
else
    echo "   ❌ Found $SYNTAX_ERRORS syntax errors"
    echo "**Status:** ❌ $SYNTAX_ERRORS errors found" >> "$REPORT_FILE"
fi

echo "" >> "$REPORT_FILE"
echo "## 2. Namespace Validation" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

echo ""
echo "2️⃣ Namespace Audit..."
bash "$MODULES_DIR/audit-namespaces.sh" | tee -a "$REPORT_FILE"

echo "" >> "$REPORT_FILE"
echo "## 3. Composer Dependencies" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

echo ""
echo "3️⃣ Composer Dependencies..."

# Check if composer.lock exists
if [ -f "$MODULES_DIR/composer.lock" ]; then
    echo "   ✅ Root composer.lock exists"
    echo "- ✅ Root composer.lock exists" >> "$REPORT_FILE"
else
    echo "   ⚠️  Root composer.lock missing - run: composer install"
    echo "- ⚠️ Root composer.lock missing" >> "$REPORT_FILE"
fi

# Check critical modules
for module in consignments "human_resources/payroll" base; do
    if [ -f "$MODULES_DIR/$module/composer.lock" ]; then
        echo "   ✅ $module/composer.lock exists"
        echo "- ✅ \`$module/composer.lock\` exists" >> "$REPORT_FILE"
    else
        echo "   ⚠️  $module/composer.lock missing"
        echo "- ⚠️ \`$module/composer.lock\` missing" >> "$REPORT_FILE"
    fi
done

echo "" >> "$REPORT_FILE"
echo "## 4. Bootstrap Validation" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

echo ""
echo "4️⃣ Bootstrap Files..."

# Check if all modules properly bootstrap to base
BOOTSTRAP_ERRORS=0
for index_file in "$MODULES_DIR"/*/index.php; do
    module=$(basename "$(dirname "$index_file")")

    # Skip special directories
    if [[ "$module" == "vendor" || "$module" == "node_modules" ]]; then
        continue
    fi

    # Check if it requires base bootstrap
    if grep -q "require.*base/bootstrap.php" "$index_file"; then
        echo "   ✅ $module - uses base bootstrap"
        echo "- ✅ \`$module\` - uses base bootstrap" >> "$REPORT_FILE"
    else
        echo "   ⚠️  $module - custom bootstrap"
        echo "- ⚠️ \`$module\` - custom bootstrap" >> "$REPORT_FILE"
        ((BOOTSTRAP_ERRORS++))
    fi
done

echo "" >> "$REPORT_FILE"
echo "## 5. README Documentation" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

echo ""
echo "5️⃣ README Files..."

# Check for README.md in critical modules
CRITICAL_MODULES=("base" "consignments" "human_resources/payroll" "bank-transactions" "ecommerce-ops")
for module in "${CRITICAL_MODULES[@]}"; do
    if [ -f "$MODULES_DIR/$module/README.md" ]; then
        lines=$(wc -l < "$MODULES_DIR/$module/README.md")
        echo "   ✅ $module - README.md ($lines lines)"
        echo "- ✅ \`$module\` - README.md ($lines lines)" >> "$REPORT_FILE"
    else
        echo "   ❌ $module - Missing README.md"
        echo "- ❌ \`$module\` - Missing README.md" >> "$REPORT_FILE"
    fi
done

echo "" >> "$REPORT_FILE"
echo "## 6. Database Migrations" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

echo ""
echo "6️⃣ Database Migrations..."

# Count migration files per module
for module_dir in "$MODULES_DIR"/*; do
    module=$(basename "$module_dir")

    if [ -d "$module_dir/database/migrations" ]; then
        count=$(find "$module_dir/database/migrations" -name "*.sql" | wc -l)
        if [ $count -gt 0 ]; then
            echo "   📊 $module - $count migrations"
            echo "- \`$module\` - $count migrations" >> "$REPORT_FILE"
        fi
    fi
done

echo "" >> "$REPORT_FILE"
echo "## 7. .gitignore Coverage" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

echo ""
echo "7️⃣ .gitignore Security..."

# Check if sensitive files are gitignored
if [ -f "$MODULES_DIR/.gitignore" ]; then
    echo "   ✅ .gitignore exists"
    echo "**Status:** ✅ .gitignore exists" >> "$REPORT_FILE"

    # Check critical patterns
    for pattern in "*.env" ".env" "*.key" "vendor/" "node_modules/"; do
        if grep -q "$pattern" "$MODULES_DIR/.gitignore"; then
            echo "   ✅ Ignores: $pattern"
            echo "- ✅ Ignores: \`$pattern\`" >> "$REPORT_FILE"
        else
            echo "   ⚠️  Missing: $pattern"
            echo "- ⚠️ Missing: \`$pattern\`" >> "$REPORT_FILE"
        fi
    done
else
    echo "   ❌ .gitignore missing!"
    echo "**Status:** ❌ .gitignore missing!" >> "$REPORT_FILE"
fi

# Finalize report
echo "" >> "$REPORT_FILE"
echo "---" >> "$REPORT_FILE"
echo "**Report Generated:** $(date)" >> "$REPORT_FILE"
echo "**Status:** ✅ COMPLETE" >> "$REPORT_FILE"

echo ""
echo "✅ Validation Complete!"
echo "📄 Report saved to: $REPORT_FILE"
echo ""
