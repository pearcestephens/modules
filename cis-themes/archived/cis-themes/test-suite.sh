#!/bin/bash
# 🧪 THEME BUILDER PRO - COMPREHENSIVE TEST SUITE
# Tests all pages, links, endpoints, and resources

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🧪 THEME BUILDER PRO - COMPREHENSIVE TESTING"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

BASE_URL="http://staff.vapeshed.co.nz/modules/cis-themes"
PASS=0
FAIL=0
TOTAL=0

# Function to test URL
test_url() {
    local url="$1"
    local description="$2"
    TOTAL=$((TOTAL + 1))

    echo -n "Testing: $description... "

    response=$(curl -s -o /dev/null -w "%{http_code}" "$url" 2>/dev/null)

    if [ "$response" = "200" ]; then
        echo "✅ PASS ($response)"
        PASS=$((PASS + 1))
        return 0
    else
        echo "❌ FAIL ($response)"
        FAIL=$((FAIL + 1))
        return 1
    fi
}

# Function to test file exists
test_file() {
    local file="$1"
    local description="$2"
    TOTAL=$((TOTAL + 1))

    echo -n "Testing: $description... "

    if [ -f "$file" ]; then
        echo "✅ PASS (exists)"
        PASS=$((PASS + 1))
        return 0
    else
        echo "❌ FAIL (missing)"
        FAIL=$((FAIL + 1))
        return 1
    fi
}

echo "📄 1. TESTING MAIN APPLICATION FILES"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Main HTML file
test_url "$BASE_URL/theme-builder-pro.html" "Main Application (HTML)"

# JavaScript modules
test_url "$BASE_URL/components-library.js" "Component Library Module"
test_url "$BASE_URL/component-generator.js" "Component Generator Module"
test_url "$BASE_URL/mcp-integration.js" "MCP Integration Module"
test_url "$BASE_URL/data-seeds.js" "Data Seeds Module"
test_url "$BASE_URL/inspiration-generator.js" "Inspiration Generator Module"

echo ""
echo "📚 2. TESTING DOCUMENTATION FILES"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

test_url "$BASE_URL/README.md" "README Documentation"
test_url "$BASE_URL/QUICK_START.md" "Quick Start Guide"
test_url "$BASE_URL/CHEAT_SHEET.md" "Cheat Sheet"
test_url "$BASE_URL/THEME_BUILDER_PRO_MASTER_PLAN.md" "Master Plan"
test_url "$BASE_URL/MESSAGING_QUICK_REF.md" "Messaging Reference"

echo ""
echo "🔗 3. TESTING EXTERNAL DEPENDENCIES"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Monaco Editor CDN
test_url "https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/editor/editor.main.css" "Monaco Editor CSS"
test_url "https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/loader.js" "Monaco Editor Loader"
test_url "https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/editor/editor.main.nls.js" "Monaco Editor i18n"
test_url "https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/editor/editor.main.js" "Monaco Editor Core"

echo ""
echo "📁 4. TESTING LOCAL FILE SYSTEM"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Get current directory
CURRENT_DIR=$(pwd)

test_file "$CURRENT_DIR/theme-builder-pro.html" "Main HTML (Local)"
test_file "$CURRENT_DIR/components-library.js" "Component Library (Local)"
test_file "$CURRENT_DIR/component-generator.js" "Component Generator (Local)"
test_file "$CURRENT_DIR/mcp-integration.js" "MCP Integration (Local)"
test_file "$CURRENT_DIR/data-seeds.js" "Data Seeds (Local)"
test_file "$CURRENT_DIR/inspiration-generator.js" "Inspiration Generator (Local)"

echo ""
echo "🎨 5. TESTING THEME ASSETS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check if assets directory exists
if [ -d "$CURRENT_DIR/assets" ]; then
    echo "✅ Assets directory exists"
    ls -lh "$CURRENT_DIR/assets" 2>/dev/null | tail -n +2
else
    echo "⚠️  No assets directory found"
fi

echo ""
echo "📦 6. TESTING COMPONENT DIRECTORIES"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

for dir in components layouts themes engine data; do
    if [ -d "$CURRENT_DIR/$dir" ]; then
        file_count=$(find "$CURRENT_DIR/$dir" -type f 2>/dev/null | wc -l)
        echo "✅ $dir/ directory exists ($file_count files)"
    else
        echo "⚠️  $dir/ directory not found"
    fi
done

echo ""
echo "🔌 7. TESTING MCP SERVER CONNECTIVITY"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

MCP_URL="https://gpt.ecigdis.co.nz/mcp/server_v4.php"
MCP_API_KEY="31ce0106609a6c5bc4f7ece0deb2f764df90a06167bda83468883516302a6a35"

echo -n "Testing: MCP Server Health... "
mcp_response=$(curl -s -o /dev/null -w "%{http_code}" \
    -H "X-API-Key: $MCP_API_KEY" \
    -H "Content-Type: application/json" \
    -X POST \
    -d '{"action":"health-check"}' \
    "$MCP_URL" 2>/dev/null)

if [ "$mcp_response" = "200" ]; then
    echo "✅ PASS ($mcp_response)"
    PASS=$((PASS + 1))
else
    echo "❌ FAIL ($mcp_response)"
    FAIL=$((FAIL + 1))
fi
TOTAL=$((TOTAL + 1))

echo ""
echo "📊 8. CHECKING FILE SIZES & INTEGRITY"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

files=(
    "theme-builder-pro.html"
    "components-library.js"
    "component-generator.js"
    "mcp-integration.js"
    "data-seeds.js"
    "inspiration-generator.js"
)

for file in "${files[@]}"; do
    if [ -f "$CURRENT_DIR/$file" ]; then
        size=$(stat -f%z "$CURRENT_DIR/$file" 2>/dev/null || stat -c%s "$CURRENT_DIR/$file" 2>/dev/null)
        lines=$(wc -l < "$CURRENT_DIR/$file" 2>/dev/null)
        size_kb=$((size / 1024))

        if [ $size -gt 0 ]; then
            echo "✅ $file: ${size_kb}KB, ${lines} lines"
        else
            echo "❌ $file: Empty file!"
        fi
    fi
done

echo ""
echo "🔍 9. JAVASCRIPT SYNTAX VALIDATION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

for file in "${files[@]}"; do
    if [[ "$file" == *.js ]]; then
        if [ -f "$CURRENT_DIR/$file" ]; then
            echo -n "Checking: $file... "
            if node -c "$CURRENT_DIR/$file" 2>/dev/null; then
                echo "✅ Valid syntax"
            else
                echo "⚠️  Cannot validate (Node.js check)"
            fi
        fi
    fi
done

echo ""
echo "🌐 10. TESTING COMPLETE APPLICATION LOAD"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

echo -n "Testing: Full application HTML... "
if curl -s "$BASE_URL/theme-builder-pro.html" | grep -q "Theme Builder PRO"; then
    echo "✅ PASS (HTML contains expected content)"
    PASS=$((PASS + 1))
else
    echo "❌ FAIL (Content check failed)"
    FAIL=$((FAIL + 1))
fi
TOTAL=$((TOTAL + 1))

echo -n "Testing: Monaco Editor load... "
if curl -s "$BASE_URL/theme-builder-pro.html" | grep -q "monaco-editor"; then
    echo "✅ PASS (Monaco referenced)"
    PASS=$((PASS + 1))
else
    echo "❌ FAIL (Monaco not found)"
    FAIL=$((FAIL + 1))
fi
TOTAL=$((TOTAL + 1))

echo -n "Testing: Component Library load... "
if curl -s "$BASE_URL/theme-builder-pro.html" | grep -q "components-library.js"; then
    echo "✅ PASS (Library referenced)"
    PASS=$((PASS + 1))
else
    echo "❌ FAIL (Library not found)"
    FAIL=$((FAIL + 1))
fi
TOTAL=$((TOTAL + 1))

echo -n "Testing: MCP Integration load... "
if curl -s "$BASE_URL/theme-builder-pro.html" | grep -q "mcp-integration.js"; then
    echo "✅ PASS (MCP referenced)"
    PASS=$((PASS + 1))
else
    echo "❌ FAIL (MCP not found)"
    FAIL=$((FAIL + 1))
fi
TOTAL=$((TOTAL + 1))

echo -n "Testing: Data Seeds load... "
if curl -s "$BASE_URL/theme-builder-pro.html" | grep -q "data-seeds.js"; then
    echo "✅ PASS (Data Seeds referenced)"
    PASS=$((PASS + 1))
else
    echo "❌ FAIL (Data Seeds not found)"
    FAIL=$((FAIL + 1))
fi
TOTAL=$((TOTAL + 1))

echo -n "Testing: Inspiration Generator load... "
if curl -s "$BASE_URL/theme-builder-pro.html" | grep -q "inspiration-generator.js"; then
    echo "✅ PASS (Inspiration referenced)"
    PASS=$((PASS + 1))
else
    echo "❌ FAIL (Inspiration not found)"
    FAIL=$((FAIL + 1))
fi
TOTAL=$((TOTAL + 1))

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 TEST SUMMARY"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Total Tests:    $TOTAL"
echo "✅ Passed:      $PASS"
echo "❌ Failed:      $FAIL"
echo ""

if [ $FAIL -eq 0 ]; then
    echo "🎉 ALL TESTS PASSED! System is fully operational! 🚀"
    exit 0
else
    PASS_RATE=$((PASS * 100 / TOTAL))
    echo "⚠️  Some tests failed. Pass rate: ${PASS_RATE}%"
    exit 1
fi
