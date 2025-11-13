#!/bin/bash
# Quick Gap Analysis - Identify Critical Issues

echo "🔍 CONSIGNMENTS MODULE - QUICK GAP CHECK"
echo "========================================"
echo ""

CRITICAL=0
HIGH=0
MEDIUM=0

check_critical() {
    echo "🚨 CRITICAL ISSUES"
    echo "──────────────────"

    # Check for TODO items
    TODO_COUNT=$(grep -r "TODO" /home/master/applications/jcepnzzkmj/public_html/modules/consignments --include="*.php" 2>/dev/null | wc -l)
    echo "  📝 TODO items found: $TODO_COUNT"
    if [ $TODO_COUNT -gt 10 ]; then
        echo "     ⚠️  CRITICAL: Too many incomplete features"
        ((CRITICAL++))
    fi

    # Check for DEBUG statements
    DEBUG_COUNT=$(grep -r "DEBUG" /home/master/applications/jcepnzzkmj/public_html/modules/consignments --include="*.php" 2>/dev/null | grep -v ".md" | grep -v "APP_DEBUG" | wc -l)
    echo "  🐛 Debug statements: $DEBUG_COUNT"
    if [ $DEBUG_COUNT -gt 30 ]; then
        echo "     ⚠️  CRITICAL: Too much debug code in production"
        ((CRITICAL++))
    fi

    # Check if tests run
    echo "  🧪 Testing API suite..."
    if [ -f "/home/master/applications/jcepnzzkmj/public_html/modules/consignments/tests/test_api_working.php" ]; then
        TEST_RESULT=$(php /home/master/applications/jcepnzzkmj/public_html/modules/consignments/tests/test_api_working.php 2>&1 | tail -5)
        echo "     Last test results:"
        echo "$TEST_RESULT" | sed 's/^/     /'
    else
        echo "     ⚠️  CRITICAL: Test file not found"
        ((CRITICAL++))
    fi

    echo ""
}

check_high() {
    echo "⚠️  HIGH PRIORITY"
    echo "─────────────────"

    # Check for .OLD files
    OLD_FILES=$(find /home/master/applications/jcepnzzkmj/public_html/modules/consignments -name "*.OLD" -o -name "*backup*" 2>/dev/null | wc -l)
    echo "  📁 Backup/OLD files: $OLD_FILES"
    if [ $OLD_FILES -gt 5 ]; then
        echo "     ⚠️  Needs cleanup"
        ((HIGH++))
    fi

    # Check for incomplete features
    INCOMPLETE=$(grep -r "incomplete" /home/master/applications/jcepnzzkmj/public_html/modules/consignments --include="*.php" 2>/dev/null | grep -i "status.*incomplete" | wc -l)
    echo "  ❓ Incomplete status checks: $INCOMPLETE"

    # Check for FIXME
    FIXME_COUNT=$(grep -r "FIXME" /home/master/applications/jcepnzzkmj/public_html/modules/consignments --include="*.php" 2>/dev/null | wc -l)
    echo "  🔧 FIXME items: $FIXME_COUNT"
    if [ $FIXME_COUNT -gt 0 ]; then
        echo "     ⚠️  Needs attention"
        ((HIGH++))
    fi

    echo ""
}

check_medium() {
    echo "ℹ️  MEDIUM PRIORITY"
    echo "───────────────────"

    # Check for console.log in production JS
    CONSOLE_LOGS=$(grep -r "console.log" /home/master/applications/jcepnzzkmj/public_html/modules/consignments/assets/js --include="*.js" 2>/dev/null | wc -l)
    echo "  📺 console.log statements: $CONSOLE_LOGS"
    if [ $CONSOLE_LOGS -gt 20 ]; then
        echo "     ⚠️  Consider removing for production"
        ((MEDIUM++))
    fi

    # Check documentation freshness
    echo "  📚 Documentation files:"
    DOC_COUNT=$(find /home/master/applications/jcepnzzkmj/public_html/modules/consignments/_kb -name "*.md" 2>/dev/null | wc -l)
    echo "     Found $DOC_COUNT markdown files"

    echo ""
}

# Run checks
check_critical
check_high
check_medium

# Summary
echo "═══════════════════════════════════════"
echo "📊 SUMMARY"
echo "═══════════════════════════════════════"
echo "  🚨 Critical Issues: $CRITICAL"
echo "  ⚠️  High Priority: $HIGH"
echo "  ℹ️  Medium Priority: $MEDIUM"
echo ""

if [ $CRITICAL -gt 0 ]; then
    echo "⚠️  ACTION REQUIRED: Address critical issues immediately"
    echo ""
    echo "See detailed analysis:"
    echo "  /modules/consignments/_kb/GAP_ANALYSIS_COMPREHENSIVE.md"
    exit 1
else
    echo "✅ No critical blockers found"
    echo ""
    echo "Review full analysis:"
    echo "  /modules/consignments/_kb/GAP_ANALYSIS_COMPREHENSIVE.md"
    exit 0
fi
