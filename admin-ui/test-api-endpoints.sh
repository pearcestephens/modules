#!/bin/bash
# API Testing Suite for Asset Control Center
# Tests all endpoints for 200 OK + JSON success:true

echo "╔════════════════════════════════════════════════════════════╗"
echo "║  🧪 ASSET CONTROL CENTER - API TEST SUITE                 ║"
echo "║      Testing all endpoints with BaseAPI envelope          ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

BASE_PATH="/home/master/applications/jcepnzzkmj/public_html/modules/admin-ui"
PASSED=0
FAILED=0

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

test_endpoint() {
    local name=$1
    local file=$2
    local action=$3
    local extra_post=$4

    echo -n "Testing $name... "

    cd "$BASE_PATH"

    result=$(php -d display_errors=0 -r "
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['SCRIPT_FILENAME'] = '$file';
\$_POST['action'] = '$action';
$extra_post
ob_start();
require '$file';
\$output = ob_get_clean();
echo \$output;
" 2>&1)

    if echo "$result" | grep -q '"success": true'; then
        echo -e "${GREEN}✅ PASS${NC}"
        ((PASSED++))
        return 0
    else
        echo -e "${RED}❌ FAIL${NC}"
        echo "   Response: $result"
        ((FAILED++))
        return 1
    fi
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📦 THEME API TESTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

test_endpoint "List Themes" "api/themes.php" "list_themes" ""

test_endpoint "Save Theme" "api/themes.php" "save_theme" "\$_POST['theme_data'] = json_encode(['id' => 'test_theme', 'name' => 'Test Theme', 'colors' => ['primary' => '#667eea']]);"

test_endpoint "Load Theme" "api/themes.php" "load_theme" "\$_POST['theme_id'] = 'test_theme';"

test_endpoint "Export Theme" "api/themes.php" "export_theme" "\$_POST['theme_data'] = json_encode(['name' => 'Export Test']);"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🎨 CSS API TESTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

test_endpoint "List CSS Files" "api/css.php" "list_css_files" ""

test_endpoint "Save CSS Version" "api/css.php" "save_css_version" "\$_POST['css_file'] = 'custom/test.css'; \$_POST['css_content'] = '.test { color: red; }'; \$_POST['message'] = 'Test CSS version';"

test_endpoint "Get CSS Versions" "api/css.php" "get_css_versions" "\$_POST['css_file'] = 'custom/test.css';"

test_endpoint "Minify CSS" "api/css.php" "minify_css" "\$_POST['css_content'] = '/* comment */ .test { color: red; }';"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "⚡ JS API TESTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

test_endpoint "List JS Files" "api/js.php" "list_js_files" ""

test_endpoint "Save JS Version" "api/js.php" "save_js_version" "\$_POST['js_file'] = 'modules/test.js'; \$_POST['js_content'] = 'function test() { return true; }'; \$_POST['message'] = 'Test JS version';"

test_endpoint "Get JS Versions" "api/js.php" "get_js_versions" "\$_POST['js_file'] = 'modules/test.js';"

test_endpoint "Minify JS" "api/js.php" "minify_js" "\$_POST['js_content'] = '// comment\nfunction test() { return true; }';"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🧩 COMPONENT API TESTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

test_endpoint "List Components" "api/components.php" "list_components" ""

test_endpoint "Get Categories" "api/components.php" "get_categories" ""

test_endpoint "Save Component" "api/components.php" "save_component" "\$_POST['component_data'] = json_encode(['id' => 'test_comp', 'name' => 'Test Component', 'category' => 'buttons', 'html' => '<button>Test</button>', 'description' => 'A test button component']);"

test_endpoint "Get Component" "api/components.php" "get_component" "\$_POST['component_id'] = 'test_comp';"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🏗️  BUILD SYSTEM API TESTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

test_endpoint "Build CSS (Dev)" "api/build.php" "build_css" "\$_POST['profile'] = 'dev';"

test_endpoint "Build JS (Staging)" "api/build.php" "build_js" "\$_POST['profile'] = 'staging';"

test_endpoint "Build All (Production)" "api/build.php" "build_all" "\$_POST['profile'] = 'production';"

test_endpoint "Get Build History" "api/build.php" "get_build_history" ""

test_endpoint "Clean Build" "api/build.php" "clean_build" ""

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "� ANALYTICS API TESTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

test_endpoint "Get Overview" "api/analytics.php" "get_overview" ""

test_endpoint "Get Component Trends" "api/analytics.php" "get_component_trends" ""

test_endpoint "Get File Size Trends" "api/analytics.php" "get_file_size_trends" ""

test_endpoint "Track Event" "api/analytics.php" "track_event" "\$_POST['event_type'] = 'test_event'; \$_POST['event_data'] = json_encode(['test' => 'data']);"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "�📊 TEST SUMMARY"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "✅ Passed: ${GREEN}$PASSED${NC}"
echo -e "❌ Failed: ${RED}$FAILED${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}🎉 ALL TESTS PASSED!${NC}"
    exit 0
else
    echo -e "${RED}⚠️  SOME TESTS FAILED${NC}"
    exit 1
fi
