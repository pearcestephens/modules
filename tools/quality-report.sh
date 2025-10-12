#!/bin/bash
# Code Quality Report Generator for CIS Modules
# Generates comprehensive quality metrics and reports

set -e

REPORT_FILE="docs/quality-report-$(date +%Y%m%d-%H%M%S).md"

echo "# CIS Modules Quality Report" > "$REPORT_FILE"
echo "**Generated:** $(date)" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

# PHP Files Count
echo "## 📊 Codebase Statistics" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"
PHP_COUNT=$(find . -name "*.php" -not -path "*/vendor/*" -not -path "*/node_modules/*" | wc -l)
echo "- **PHP Files:** $PHP_COUNT" >> "$REPORT_FILE"

JS_COUNT=$(find . -name "*.js" -not -path "*/vendor/*" -not -path "*/node_modules/*" | wc -l)
echo "- **JS Files:** $JS_COUNT" >> "$REPORT_FILE"

TOTAL_LINES=$(find . -name "*.php" -not -path "*/vendor/*" -not -path "*/node_modules/*" -exec wc -l {} + | tail -1 | awk '{print $1}')
echo "- **Total PHP Lines:** $TOTAL_LINES" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

# PHPStan Analysis
echo "## 🔍 PHPStan Analysis (Level 5)" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"
if command -v phpstan &> /dev/null; then
    echo "\`\`\`" >> "$REPORT_FILE"
    phpstan analyse --level=5 --no-progress >> "$REPORT_FILE" 2>&1 || true
    echo "\`\`\`" >> "$REPORT_FILE"
else
    echo "⚠️ PHPStan not installed" >> "$REPORT_FILE"
fi
echo "" >> "$REPORT_FILE"

# Code Style
echo "## 🎨 Code Style Compliance (PSR-12)" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"
if command -v php-cs-fixer &> /dev/null; then
    echo "\`\`\`" >> "$REPORT_FILE"
    php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.dist.php >> "$REPORT_FILE" 2>&1 || true
    echo "\`\`\`" >> "$REPORT_FILE"
else
    echo "⚠️ PHP CS Fixer not installed" >> "$REPORT_FILE"
fi
echo "" >> "$REPORT_FILE"

# Security Patterns
echo "## 🔒 Security Pattern Analysis" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

INLINE_HANDLERS=$(grep -r 'onclick=' --include="*.php" . | grep -v vendor | wc -l)
echo "- **Inline onclick handlers:** $INLINE_HANDLERS" >> "$REPORT_FILE"

DIRECT_SQL=$(grep -rE '\$_(GET|POST|REQUEST).*\.(query|prepare)' --include="*.php" . | grep -v vendor | wc -l)
echo "- **Potential SQL injection sites:** $DIRECT_SQL" >> "$REPORT_FILE"

UNESCAPED=$(grep -rE 'echo.*\$_(GET|POST|REQUEST)' --include="*.php" . | grep -v vendor | wc -l)
echo "- **Potential XSS vulnerabilities:** $UNESCAPED" >> "$REPORT_FILE"

NO_STRICT=$(grep -rL 'declare(strict_types=1);' --include="*.php" . | grep -v vendor | wc -l)
echo "- **Files without strict_types:** $NO_STRICT" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

# Complexity Analysis
echo "## 📈 Complexity Metrics" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

LARGE_FILES=$(find . -name "*.php" -not -path "*/vendor/*" -exec wc -l {} + | awk '$1 > 500 {print}' | wc -l)
echo "- **Files >500 lines:** $LARGE_FILES" >> "$REPORT_FILE"

LONG_METHODS=$(grep -rn 'function ' --include="*.php" . | grep -v vendor | wc -l)
echo "- **Total methods/functions:** $LONG_METHODS" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

# Test Coverage
echo "## 🧪 Test Coverage" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"
TEST_FILES=$(find . -path "*/tests/*" -name "*Test.php" | wc -l)
echo "- **Test files:** $TEST_FILES" >> "$REPORT_FILE"

if command -v phpunit &> /dev/null; then
    echo "- **Coverage:** Run \`phpunit --coverage-text\` for details" >> "$REPORT_FILE"
else
    echo "- **Coverage:** ⚠️ PHPUnit not installed" >> "$REPORT_FILE"
fi
echo "" >> "$REPORT_FILE"

# Recommendations
echo "## 💡 Recommendations" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

if [ $INLINE_HANDLERS -gt 0 ]; then
    echo "1. **Remove inline onclick handlers** - Violates CSP, use event delegation" >> "$REPORT_FILE"
fi

if [ $NO_STRICT -gt 10 ]; then
    echo "2. **Add strict_types declarations** - Found $NO_STRICT files missing it" >> "$REPORT_FILE"
fi

if [ $LARGE_FILES -gt 0 ]; then
    echo "3. **Refactor large files** - $LARGE_FILES files exceed 500 lines" >> "$REPORT_FILE"
fi

if [ $TEST_FILES -lt 10 ]; then
    echo "4. **Improve test coverage** - Only $TEST_FILES test files found" >> "$REPORT_FILE"
fi

echo "" >> "$REPORT_FILE"
echo "---" >> "$REPORT_FILE"
echo "Report saved to: $REPORT_FILE" >> "$REPORT_FILE"

cat "$REPORT_FILE"
echo ""
echo "✅ Report generated: $REPORT_FILE"
