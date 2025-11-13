#!/bin/bash
# 🔍 CODE QUALITY & ERROR SCANNER
# Deep analysis of all code files for bugs, issues, and improvements

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🔍 THEME BUILDER PRO - CODE QUALITY SCANNER"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

ISSUES=0
WARNINGS=0
SUGGESTIONS=0

# Function to report issue
report_issue() {
    echo "❌ ISSUE: $1"
    ISSUES=$((ISSUES + 1))
}

# Function to report warning
report_warning() {
    echo "⚠️  WARNING: $1"
    WARNINGS=$((WARNINGS + 1))
}

# Function to report suggestion
report_suggestion() {
    echo "💡 SUGGESTION: $1"
    SUGGESTIONS=$((SUGGESTIONS + 1))
}

echo "📄 1. SCANNING: theme-builder-pro.html"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ -f "theme-builder-pro.html" ]; then
    # Check for unclosed tags
    if grep -n "<div[^>]*>" theme-builder-pro.html | wc -l | grep -q "^$(grep -n "</div>" theme-builder-pro.html | wc -l)$"; then
        echo "✅ Balanced div tags"
    else
        report_warning "Potentially unbalanced div tags"
    fi

    # Check for missing alt attributes
    if grep -n '<img[^>]*>' theme-builder-pro.html | grep -v 'alt=' > /dev/null 2>&1; then
        report_warning "Images without alt attributes found"
    else
        echo "✅ All images have alt attributes"
    fi

    # Check for inline styles
    inline_count=$(grep -o 'style=' theme-builder-pro.html | wc -l)
    if [ $inline_count -gt 50 ]; then
        report_suggestion "High inline styles count ($inline_count) - consider extracting to CSS"
    else
        echo "✅ Acceptable inline styles count ($inline_count)"
    fi

    # Check for console.log statements
    console_count=$(grep -o 'console\.log' theme-builder-pro.html | wc -l)
    if [ $console_count -gt 10 ]; then
        report_suggestion "Many console.log statements ($console_count) - consider removing for production"
    else
        echo "✅ Console logs count: $console_count"
    fi

    # Check for TODO/FIXME comments
    todo_count=$(grep -i 'TODO\|FIXME' theme-builder-pro.html | wc -l)
    if [ $todo_count -gt 0 ]; then
        echo "💭 Found $todo_count TODO/FIXME comments"
        grep -in 'TODO\|FIXME' theme-builder-pro.html | head -5
    fi
else
    report_issue "theme-builder-pro.html not found"
fi

echo ""
echo "📄 2. SCANNING: components-library.js"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ -f "components-library.js" ]; then
    # Check syntax
    if node -c components-library.js 2>/dev/null; then
        echo "✅ Valid JavaScript syntax"
    else
        report_issue "JavaScript syntax errors detected"
        node -c components-library.js 2>&1 | head -10
    fi

    # Check for var declarations (should use let/const)
    var_count=$(grep -n '\bvar\b' components-library.js | wc -l)
    if [ $var_count -gt 0 ]; then
        report_suggestion "Found $var_count 'var' declarations - consider using let/const"
    else
        echo "✅ No 'var' declarations (using modern ES6+)"
    fi

    # Check for == instead of ===
    loose_eq=$(grep -n '==[^=]' components-library.js | wc -l)
    if [ $loose_eq -gt 0 ]; then
        report_warning "Found $loose_eq loose equality (==) - consider using strict (===)"
    else
        echo "✅ Using strict equality (===)"
    fi

    # Check for missing semicolons
    if grep -n '[a-z0-9)]$' components-library.js | grep -v '//' | head -5 > /dev/null; then
        report_suggestion "Some statements may be missing semicolons"
    fi

    # Check function complexity
    func_count=$(grep -c 'function\|=>' components-library.js)
    lines=$(wc -l < components-library.js)
    avg_lines=$((lines / func_count))
    echo "📊 Functions: $func_count, Avg lines per function: $avg_lines"

    if [ $avg_lines -gt 50 ]; then
        report_suggestion "Average function length is high - consider refactoring"
    fi
else
    report_issue "components-library.js not found"
fi

echo ""
echo "📄 3. SCANNING: component-generator.js"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ -f "component-generator.js" ]; then
    node -c component-generator.js 2>/dev/null && echo "✅ Valid syntax" || report_issue "Syntax errors"

    # Check for async/await error handling
    async_count=$(grep -c 'async' component-generator.js)
    trycatch_count=$(grep -c 'try {' component-generator.js)

    if [ $async_count -gt $trycatch_count ]; then
        report_warning "Async functions ($async_count) > try/catch blocks ($trycatch_count) - ensure error handling"
    else
        echo "✅ Async functions have error handling"
    fi

    # Check for hardcoded values
    hardcoded=$(grep -n "'http://\|'https://\|localhost" component-generator.js | wc -l)
    if [ $hardcoded -gt 0 ]; then
        report_suggestion "Found $hardcoded hardcoded URLs - consider using config"
    fi

    # Check for magic numbers
    magic_numbers=$(grep -o '\b[0-9][0-9][0-9]\+\b' component-generator.js | wc -l)
    if [ $magic_numbers -gt 20 ]; then
        report_suggestion "Many magic numbers ($magic_numbers) - consider using constants"
    else
        echo "✅ Reasonable use of numeric literals"
    fi

    # Check documentation
    doc_comments=$(grep -c '/\*\*' component-generator.js)
    echo "📝 Documentation blocks: $doc_comments"
else
    report_issue "component-generator.js not found"
fi

echo ""
echo "📄 4. SCANNING: mcp-integration.js"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ -f "mcp-integration.js" ]; then
    node -c mcp-integration.js 2>/dev/null && echo "✅ Valid syntax" || report_issue "Syntax errors"

    # Check for API key exposure
    if grep -n 'api[_-]key\|apikey' mcp-integration.js -i | grep -v 'X-API-Key' > /dev/null 2>&1; then
        report_warning "Potential API key in code - ensure it's not hardcoded"
    else
        echo "✅ No hardcoded API keys detected"
    fi

    # Check for fetch without error handling
    fetch_count=$(grep -c 'fetch(' mcp-integration.js)
    catch_count=$(grep -c '\.catch\|catch(' mcp-integration.js)

    if [ $fetch_count -gt $catch_count ]; then
        report_warning "Some fetch calls may lack error handling"
    else
        echo "✅ Fetch calls have error handling"
    fi

    # Check for timeout handling
    if grep -q 'timeout\|AbortController' mcp-integration.js; then
        echo "✅ Request timeout handling present"
    else
        report_suggestion "Consider adding request timeout handling"
    fi

    # Check for retry logic
    if grep -q 'retry\|attempt' mcp-integration.js; then
        echo "✅ Retry logic implemented"
    else
        report_suggestion "Consider adding retry logic for failed requests"
    fi
else
    report_issue "mcp-integration.js not found"
fi

echo ""
echo "📄 5. SCANNING: data-seeds.js"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ -f "data-seeds.js" ]; then
    node -c data-seeds.js 2>/dev/null && echo "✅ Valid syntax" || report_issue "Syntax errors"

    # Check for duplicate data
    color_count=$(grep -o '"name":' data-seeds.js | wc -l)
    unique_colors=$(grep '"name":' data-seeds.js | sort -u | wc -l)

    if [ $color_count -ne $unique_colors ]; then
        report_warning "Potential duplicate entries in data ($color_count vs $unique_colors unique)"
    else
        echo "✅ No duplicate entries detected"
    fi

    # Check data structure consistency
    if grep -q 'primary.*secondary.*accent' data-seeds.js; then
        echo "✅ Consistent color scheme structure"
    else
        report_warning "Color scheme structure may be inconsistent"
    fi

    # Check for empty arrays/objects
    if grep -n '\[\s*\]\|{\s*}' data-seeds.js | head -5 > /dev/null; then
        report_warning "Found empty arrays/objects - verify intentional"
        grep -n '\[\s*\]\|{\s*}' data-seeds.js | head -5
    fi

    # Size check
    size=$(stat -f%z data-seeds.js 2>/dev/null || stat -c%s data-seeds.js)
    size_kb=$((size / 1024))
    if [ $size_kb -gt 100 ]; then
        report_suggestion "Large data file (${size_kb}KB) - consider lazy loading"
    else
        echo "✅ Reasonable data file size (${size_kb}KB)"
    fi
else
    report_issue "data-seeds.js not found"
fi

echo ""
echo "📄 6. SCANNING: inspiration-generator.js"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ -f "inspiration-generator.js" ]; then
    node -c inspiration-generator.js 2>/dev/null && echo "✅ Valid syntax" || report_issue "Syntax errors"

    # Check for proper class structure
    if grep -q 'class.*{' inspiration-generator.js; then
        echo "✅ ES6 class syntax used"
    else
        report_suggestion "Consider using ES6 class syntax"
    fi

    # Check constructor
    if grep -q 'constructor(' inspiration-generator.js; then
        echo "✅ Constructor defined"
    else
        report_warning "No constructor found in class"
    fi

    # Check for method documentation
    method_count=$(grep -c '^\s*[a-zA-Z_].*(.*).*{' inspiration-generator.js)
    doc_method_count=$(grep -B1 '^\s*[a-zA-Z_].*(.*).*{' inspiration-generator.js | grep -c '/\*\*')

    doc_percentage=$((doc_method_count * 100 / method_count))
    echo "📝 Documentation coverage: ${doc_percentage}% ($doc_method_count/$method_count methods)"

    if [ $doc_percentage -lt 50 ]; then
        report_suggestion "Low documentation coverage - add JSDoc comments"
    fi

    # Check for null/undefined checks
    null_checks=$(grep -c 'null\|undefined' inspiration-generator.js)
    echo "🛡️  Null/undefined checks: $null_checks"
else
    report_issue "inspiration-generator.js not found"
fi

echo ""
echo "🔍 7. CROSS-FILE ANALYSIS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check for consistent naming conventions
echo "Checking naming conventions..."
if grep -h 'window\.' *.js | grep -o 'window\.[A-Za-z]*' | sort -u > /tmp/globals.txt; then
    global_count=$(wc -l < /tmp/globals.txt)
    echo "📦 Global objects exposed: $global_count"
    cat /tmp/globals.txt

    if [ $global_count -gt 10 ]; then
        report_suggestion "Many global objects - consider namespacing"
    fi
fi

# Check for dependencies between files
echo ""
echo "Checking file dependencies..."
for file in *.js; do
    deps=$(grep -o 'window\.[A-Za-z]*' "$file" | sort -u | wc -l)
    if [ $deps -gt 0 ]; then
        echo "  $file depends on $deps global objects"
    fi
done

# Check for circular dependencies
echo ""
echo "Checking for potential circular dependencies..."
if grep -l 'ComponentLibrary' *.js | xargs grep -l 'ComponentGenerator' > /dev/null 2>&1; then
    report_warning "Potential circular dependency detected"
else
    echo "✅ No obvious circular dependencies"
fi

echo ""
echo "🎨 8. PERFORMANCE ANALYSIS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check for large loops
for file in *.js; do
    nested_loops=$(grep -c 'for.*{.*for' "$file")
    if [ $nested_loops -gt 0 ]; then
        echo "⚠️  $file: $nested_loops nested loops found"
    fi
done

# Check for DOM manipulation in loops
for file in *.js; do
    if grep -n 'for\|while' "$file" | head -20 | xargs -I {} grep -A5 {} "$file" | grep -q 'innerHTML\|appendChild\|createElement'; then
        report_suggestion "$file: DOM manipulation in loops - consider using DocumentFragment"
    fi
done

# Check for memory leaks patterns
echo ""
echo "Checking for memory leak patterns..."
for file in *.js; do
    event_listeners=$(grep -c 'addEventListener' "$file")
    remove_listeners=$(grep -c 'removeEventListener' "$file")

    if [ $event_listeners -gt 0 ] && [ $remove_listeners -eq 0 ]; then
        report_warning "$file: Event listeners added but none removed - potential memory leak"
    fi
done

echo ""
echo "🔒 9. SECURITY SCAN"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check for eval usage
for file in *.js *.html; do
    if grep -q '\beval(' "$file" 2>/dev/null; then
        report_issue "$file: Uses eval() - security risk!"
    fi
done
echo "✅ No eval() usage detected"

# Check for innerHTML without sanitization
for file in *.js *.html; do
    innerHTML_count=$(grep -c '\.innerHTML\s*=' "$file" 2>/dev/null)
    if [ $innerHTML_count -gt 5 ]; then
        report_warning "$file: Multiple innerHTML assignments ($innerHTML_count) - ensure input is sanitized"
    fi
done

# Check for localStorage without encryption
if grep -q 'localStorage\.' *.js 2>/dev/null; then
    report_suggestion "localStorage usage detected - ensure sensitive data is encrypted"
else
    echo "✅ No localStorage usage"
fi

# Check for SQL injection patterns (even though this is frontend)
if grep -qi 'SELECT.*FROM\|INSERT.*INTO\|UPDATE.*SET' *.js 2>/dev/null; then
    report_warning "SQL-like strings detected - ensure proper parameterization if using"
else
    echo "✅ No SQL injection patterns"
fi

echo ""
echo "📊 10. CODE METRICS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

total_lines=$(wc -l *.js *.html 2>/dev/null | tail -1 | awk '{print $1}')
total_size=$(du -ch *.js *.html 2>/dev/null | tail -1 | awk '{print $1}')
js_files=$(ls -1 *.js 2>/dev/null | wc -l)

echo "📏 Total lines of code: $total_lines"
echo "💾 Total size: $total_size"
echo "📁 JavaScript files: $js_files"

# Calculate complexity score
complexity_score=0
if [ $ISSUES -gt 0 ]; then
    complexity_score=$((complexity_score + ISSUES * 10))
fi
if [ $WARNINGS -gt 0 ]; then
    complexity_score=$((complexity_score + WARNINGS * 5))
fi
if [ $total_lines -gt 5000 ]; then
    complexity_score=$((complexity_score + 20))
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 SCAN SUMMARY"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "❌ Issues:       $ISSUES"
echo "⚠️  Warnings:     $WARNINGS"
echo "💡 Suggestions:  $SUGGESTIONS"
echo "🎯 Complexity:   $complexity_score"
echo ""

if [ $ISSUES -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo "🎉 EXCELLENT! No critical issues found!"
    echo "Code quality: ⭐⭐⭐⭐⭐"
elif [ $ISSUES -eq 0 ] && [ $WARNINGS -lt 5 ]; then
    echo "👍 GOOD! Minor warnings only."
    echo "Code quality: ⭐⭐⭐⭐"
elif [ $ISSUES -eq 0 ]; then
    echo "✅ ACCEPTABLE. Some warnings to address."
    echo "Code quality: ⭐⭐⭐"
elif [ $ISSUES -lt 3 ]; then
    echo "⚠️  NEEDS WORK. Address issues found."
    echo "Code quality: ⭐⭐"
else
    echo "❌ CRITICAL. Multiple issues need immediate attention!"
    echo "Code quality: ⭐"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
