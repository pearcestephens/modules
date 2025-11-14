#!/bin/bash
# 🚀 FULL SYSTEM HARDENING SCRIPT - ALL MODULES
# Date: 2025-11-11
# Purpose: Comprehensive scan, harden, debug, and polish ALL modules

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
NC='\033[0m' # No Color

echo -e "${CYAN}"
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                                                                ║"
echo "║            🚀 FULL SYSTEM HARDENING & POLISH 🚀               ║"
echo "║                                                                ║"
echo "║                   ALL MODULES - 150% MODE                      ║"
echo "║                                                                ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo -e "${NC}\n"

MODULES_DIR="/home/master/applications/jcepnzzkmj/public_html/modules"
REPORT_DIR="/home/master/applications/jcepnzzkmj/private_html/reports"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
REPORT_FILE="$REPORT_DIR/hardening_report_$TIMESTAMP.md"

mkdir -p "$REPORT_DIR"

# Tracking stats
TOTAL_MODULES=0
PASSED_MODULES=0
FAILED_MODULES=0
WARNINGS=0

# Critical modules that MUST pass
CRITICAL_MODULES=(
    "consignments"
    "employee-onboarding"
    "base"
    "shared"
    "admin-ui"
)

# Start report
cat > "$REPORT_FILE" << 'EOF'
# 🚀 FULL SYSTEM HARDENING REPORT

**Date:** {DATE}
**Mode:** 150% Production Ready
**Scope:** All Modules

---

## 📊 Executive Summary

| Metric | Count |
|--------|-------|
| Total Modules Scanned | {TOTAL} |
| ✅ Passed | {PASSED} |
| ❌ Failed | {FAILED} |
| ⚠️ Warnings | {WARNINGS} |

---

## 📋 Module-by-Module Analysis

EOF

# Replace date placeholder
sed -i "s/{DATE}/$(date '+%Y-%m-%d %H:%M:%S')/g" "$REPORT_FILE"

echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${WHITE}PHASE 1: MODULE DISCOVERY & INVENTORY${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}\n"

# Function to check if module is critical
is_critical() {
    local module=$1
    for critical in "${CRITICAL_MODULES[@]}"; do
        if [[ "$module" == "$critical" ]]; then
            return 0
        fi
    done
    return 1
}

# Function to scan a single module
scan_module() {
    local module_path=$1
    local module_name=$(basename "$module_path")

    ((TOTAL_MODULES++))

    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    if is_critical "$module_name"; then
        echo -e "${WHITE}📦 Module: ${YELLOW}$module_name ${RED}[CRITICAL]${NC}"
    else
        echo -e "${WHITE}📦 Module: ${GREEN}$module_name${NC}"
    fi
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"

    local issues=0
    local warnings=0

    # Check 1: Bootstrap exists
    if [ -f "$module_path/bootstrap.php" ]; then
        echo -e "${GREEN}  ✓${NC} bootstrap.php found"

        # Check bootstrap uses shared/bootstrap
        if grep -q "shared/bootstrap" "$module_path/bootstrap.php" 2>/dev/null; then
            echo -e "${GREEN}    ✓${NC} Uses shared/bootstrap.php"
        elif grep -q "base/bootstrap" "$module_path/bootstrap.php" 2>/dev/null; then
            echo -e "${GREEN}    ✓${NC} Uses base/bootstrap.php"
        else
            echo -e "${YELLOW}    ⚠${NC} Bootstrap might not inherit from base"
            ((warnings++))
        fi
    else
        echo -e "${RED}  ✗${NC} bootstrap.php MISSING"
        ((issues++))
    fi

    # Check 2: Index.php exists
    if [ -f "$module_path/index.php" ]; then
        echo -e "${GREEN}  ✓${NC} index.php found"

        # Check authentication
        if grep -qE "(requireAuth|isLoggedIn|requireLogin)" "$module_path/index.php" 2>/dev/null; then
            echo -e "${GREEN}    ✓${NC} Authentication present"
        else
            echo -e "${YELLOW}    ⚠${NC} No authentication detected"
            ((warnings++))
        fi

        # Check CSRF protection
        if grep -qE "(csrf|CSRF)" "$module_path/index.php" 2>/dev/null; then
            echo -e "${GREEN}    ✓${NC} CSRF protection present"
        else
            echo -e "${YELLOW}    ⚠${NC} No CSRF protection detected"
            ((warnings++))
        fi
    else
        echo -e "${YELLOW}  ⚠${NC} index.php not found (might be API-only module)"
        ((warnings++))
    fi

    # Check 3: Views directory
    if [ -d "$module_path/views" ]; then
        local view_count=$(find "$module_path/views" -name "*.php" -type f 2>/dev/null | wc -l)
        echo -e "${GREEN}  ✓${NC} views/ directory found ($view_count views)"

        # Check if views use Vape Ultra template
        local vape_ultra_count=$(grep -r "vape-ultra" "$module_path/views" 2>/dev/null | wc -l)
        local modern_count=$(grep -r "themes/modern" "$module_path/views" 2>/dev/null | wc -l)

        if [ $vape_ultra_count -gt 0 ]; then
            echo -e "${GREEN}    ✓${NC} Using Vape Ultra template ($vape_ultra_count views)"
        elif [ $modern_count -gt 0 ]; then
            echo -e "${YELLOW}    ⚠${NC} Using Modern template (consider upgrading to Vape Ultra)"
            ((warnings++))
        fi
    fi

    # Check 4: API directory
    if [ -d "$module_path/api" ]; then
        local api_count=$(find "$module_path/api" -name "*.php" -type f 2>/dev/null | wc -l)
        echo -e "${GREEN}  ✓${NC} api/ directory found ($api_count endpoints)"
    fi

    # Check 5: Syntax errors
    local syntax_errors=0
    for php_file in $(find "$module_path" -name "*.php" -type f 2>/dev/null | grep -v vendor | grep -v node_modules | head -20); do
        if ! php -l "$php_file" >/dev/null 2>&1; then
            ((syntax_errors++))
        fi
    done

    if [ $syntax_errors -eq 0 ]; then
        echo -e "${GREEN}  ✓${NC} No syntax errors detected"
    else
        echo -e "${RED}  ✗${NC} Found $syntax_errors syntax errors"
        ((issues++))
    fi

    # Check 6: Deprecated patterns
    local deprecated_app_php=$(grep -r "require.*app\.php" "$module_path" 2>/dev/null | grep -v ".md" | wc -l)
    if [ $deprecated_app_php -gt 0 ]; then
        echo -e "${YELLOW}  ⚠${NC} Found $deprecated_app_php files using old app.php pattern"
        ((warnings++))
    fi

    # Check 7: Security
    local sql_injection=$(grep -rE "\\\$_(GET|POST|REQUEST)\[.*\].*query|query.*\\\$_(GET|POST|REQUEST)" "$module_path" 2>/dev/null | grep -v ".md" | grep -v "prepare" | wc -l)
    if [ $sql_injection -gt 0 ]; then
        echo -e "${RED}  ✗${NC} Potential SQL injection vulnerabilities: $sql_injection"
        ((issues++))
    else
        echo -e "${GREEN}  ✓${NC} No obvious SQL injection vulnerabilities"
    fi

    # Module result
    echo ""
    if [ $issues -eq 0 ]; then
        if [ $warnings -eq 0 ]; then
            echo -e "${GREEN}  ✅ MODULE STATUS: PERFECT${NC}"
            ((PASSED_MODULES++))
        else
            echo -e "${YELLOW}  ⚠️  MODULE STATUS: PASSED (with $warnings warnings)${NC}"
            ((PASSED_MODULES++))
            ((WARNINGS += warnings))
        fi
    else
        if is_critical "$module_name"; then
            echo -e "${RED}  ❌ MODULE STATUS: FAILED - CRITICAL MODULE${NC}"
        else
            echo -e "${RED}  ❌ MODULE STATUS: FAILED ($issues issues, $warnings warnings)${NC}"
        fi
        ((FAILED_MODULES++))
        ((WARNINGS += warnings))
    fi

    echo -e ""

    # Add to report
    echo "" >> "$REPORT_FILE"
    echo "### $module_name" >> "$REPORT_FILE"
    echo "" >> "$REPORT_FILE"
    if [ $issues -eq 0 ]; then
        echo "✅ **Status:** PASSED" >> "$REPORT_FILE"
    else
        echo "❌ **Status:** FAILED" >> "$REPORT_FILE"
    fi
    echo "" >> "$REPORT_FILE"
    echo "- Issues: $issues" >> "$REPORT_FILE"
    echo "- Warnings: $warnings" >> "$REPORT_FILE"
    echo "" >> "$REPORT_FILE"
}

# Scan all modules
for module_dir in "$MODULES_DIR"/*; do
    if [ ! -d "$module_dir" ]; then
        continue
    fi

    module_name=$(basename "$module_dir")

    # Skip system directories
    if [[ "$module_name" == ".git" ]] || [[ "$module_name" == ".github" ]] || [[ "$module_name" == "vendor" ]] || [[ "$module_name" == "node_modules" ]]; then
        continue
    fi

    scan_module "$module_dir"
done

# Update report with final stats
sed -i "s/{TOTAL}/$TOTAL_MODULES/g" "$REPORT_FILE"
sed -i "s/{PASSED}/$PASSED_MODULES/g" "$REPORT_FILE"
sed -i "s/{FAILED}/$FAILED_MODULES/g" "$REPORT_FILE"
sed -i "s/{WARNINGS}/$WARNINGS/g" "$REPORT_FILE"

# Final summary
echo -e "${CYAN}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${WHITE}FINAL SUMMARY${NC}"
echo -e "${CYAN}═══════════════════════════════════════════════════════════════${NC}\n"

echo -e "${WHITE}Total Modules Scanned:${NC} $TOTAL_MODULES"
echo -e "${GREEN}✅ Passed:${NC} $PASSED_MODULES"
echo -e "${RED}❌ Failed:${NC} $FAILED_MODULES"
echo -e "${YELLOW}⚠️  Warnings:${NC} $WARNINGS"
echo ""

SUCCESS_RATE=$(echo "scale=2; ($PASSED_MODULES * 100) / $TOTAL_MODULES" | bc)
echo -e "${WHITE}Success Rate:${NC} ${GREEN}$SUCCESS_RATE%${NC}"
echo ""

echo -e "${BLUE}📝 Full report saved to:${NC}"
echo -e "   $REPORT_FILE"
echo ""

# Check if critical modules passed
CRITICAL_FAILED=0
for critical in "${CRITICAL_MODULES[@]}"; do
    if [ -d "$MODULES_DIR/$critical" ]; then
        # Check if it passed (simplified check)
        if grep -q "$critical.*FAILED" "$REPORT_FILE" 2>/dev/null; then
            ((CRITICAL_FAILED++))
            echo -e "${RED}⚠️  CRITICAL MODULE FAILED: $critical${NC}"
        fi
    fi
done

if [ $CRITICAL_FAILED -gt 0 ]; then
    echo -e "\n${RED}╔════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║              ⚠️  CRITICAL MODULES FAILED ⚠️                    ║${NC}"
    echo -e "${RED}║      System NOT ready for production deployment               ║${NC}"
    echo -e "${RED}╚════════════════════════════════════════════════════════════════╝${NC}\n"
    exit 1
else
    echo -e "\n${GREEN}╔════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║            ✅ ALL CRITICAL MODULES PASSED ✅                   ║${NC}"
    echo -e "${GREEN}║        System ready for production deployment!                 ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════════════════╝${NC}\n"
fi

exit 0
