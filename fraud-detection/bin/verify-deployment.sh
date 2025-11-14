#!/bin/bash
# =====================================================
# Fraud Detection System - Pre-Deployment Verification
# Run this script to verify all components are ready
# =====================================================

echo "🔍 Fraud Detection System - Pre-Deployment Check"
echo "=================================================="
echo ""

ERRORS=0
WARNINGS=0

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# =====================================================
# CHECK 1: Database Schema
# =====================================================
echo "📊 Checking Database Schema..."

if [ -f "database/advanced-fraud-detection-schema.sql" ]; then
    echo -e "${GREEN}✓${NC} Schema file exists"

    # Count tables
    TABLE_COUNT=$(grep -c "CREATE TABLE IF NOT EXISTS" database/advanced-fraud-detection-schema.sql)
    echo "  Found $TABLE_COUNT tables in schema"

    if [ $TABLE_COUNT -lt 25 ]; then
        echo -e "${YELLOW}⚠${NC} Warning: Expected 29+ tables, found $TABLE_COUNT"
        ((WARNINGS++))
    else
        echo -e "${GREEN}✓${NC} Table count looks good"
    fi
else
    echo -e "${RED}✗${NC} Schema file not found!"
    ((ERRORS++))
fi

echo ""

# =====================================================
# CHECK 2: PHP Files
# =====================================================
echo "📝 Checking PHP Files..."

FILES=(
    "lib/EncryptionService.php"
    "api/camera-management.php"
    "api/cv-callback.php"
    "bin/generate-encryption-key.php"
    "bin/seed-database.php"
    "PredictiveFraudForecaster.php"
    "ComputerVisionBehavioralAnalyzer.php"
    "CommunicationAnalysisEngine.php"
    "CustomerLoyaltyCollusionDetector.php"
    "AIShadowStaffEngine.php"
    "MultiSourceFraudOrchestrator.php"
)

for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}✓${NC} $file"

        # Check for syntax errors
        php -l "$file" > /dev/null 2>&1
        if [ $? -ne 0 ]; then
            echo -e "${RED}  ✗ Syntax error in $file${NC}"
            ((ERRORS++))
        fi
    else
        echo -e "${RED}✗${NC} Missing: $file"
        ((ERRORS++))
    fi
done

echo ""

# =====================================================
# CHECK 3: Composer Configuration
# =====================================================
echo "📦 Checking Composer..."

if [ -f "composer.json" ]; then
    echo -e "${GREEN}✓${NC} composer.json exists"

    if command -v composer &> /dev/null; then
        echo -e "${GREEN}✓${NC} Composer is installed"

        # Check if vendor directory exists
        if [ -d "vendor" ]; then
            echo -e "${GREEN}✓${NC} Dependencies installed"
        else
            echo -e "${YELLOW}⚠${NC} Dependencies not installed. Run: composer install"
            ((WARNINGS++))
        fi
    else
        echo -e "${YELLOW}⚠${NC} Composer not found in PATH"
        ((WARNINGS++))
    fi
else
    echo -e "${RED}✗${NC} composer.json not found!"
    ((ERRORS++))
fi

echo ""

# =====================================================
# CHECK 4: Environment Configuration
# =====================================================
echo "🔐 Checking Environment Configuration..."

if [ -f ".env" ]; then
    echo -e "${GREEN}✓${NC} .env file exists"

    # Check for encryption key
    if grep -q "FRAUD_ENCRYPTION_KEY" .env; then
        echo -e "${GREEN}✓${NC} FRAUD_ENCRYPTION_KEY is set"
    else
        echo -e "${YELLOW}⚠${NC} FRAUD_ENCRYPTION_KEY not found in .env"
        echo "  Run: php bin/generate-encryption-key.php"
        ((WARNINGS++))
    fi

    # Check for CV token
    if grep -q "CV_PIPELINE_TOKEN" .env; then
        echo -e "${GREEN}✓${NC} CV_PIPELINE_TOKEN is set"
    else
        echo -e "${YELLOW}⚠${NC} CV_PIPELINE_TOKEN not set (needed for Python integration)"
        ((WARNINGS++))
    fi
else
    echo -e "${YELLOW}⚠${NC} No .env file found"
    echo "  Create one with:"
    echo "  FRAUD_ENCRYPTION_KEY=\"your_key\""
    echo "  CV_PIPELINE_TOKEN=\"your_token\""
    ((WARNINGS++))
fi

echo ""

# =====================================================
# CHECK 5: Directory Permissions
# =====================================================
echo "📁 Checking Permissions..."

DIRS=(
    "api"
    "lib"
    "bin"
    "views"
    "database"
)

for dir in "${DIRS[@]}"; do
    if [ -d "$dir" ]; then
        if [ -r "$dir" ] && [ -x "$dir" ]; then
            echo -e "${GREEN}✓${NC} $dir/ is readable"
        else
            echo -e "${RED}✗${NC} $dir/ permission issues"
            ((ERRORS++))
        fi
    fi
done

# Check if bin scripts are executable
if [ -f "bin/generate-encryption-key.php" ]; then
    if [ -x "bin/generate-encryption-key.php" ]; then
        echo -e "${GREEN}✓${NC} bin scripts are executable"
    else
        echo -e "${YELLOW}⚠${NC} bin scripts not executable. Run: chmod +x bin/*.php"
        ((WARNINGS++))
    fi
fi

echo ""

# =====================================================
# CHECK 6: Database Connection
# =====================================================
echo "🗄️  Checking Database Connection..."

# Try to connect using shared function
php -r "
require_once 'shared/functions/db_connect.php';
try {
    \$db = db_connect();
    echo 'Connected successfully' . PHP_EOL;
    exit(0);
} catch (Exception \$e) {
    echo 'Connection failed: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
" 2>&1

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Database connection successful"
else
    echo -e "${RED}✗${NC} Cannot connect to database"
    ((ERRORS++))
fi

echo ""

# =====================================================
# CHECK 7: Web Server Access
# =====================================================
echo "🌐 Checking Web Server..."

# Check if we can detect web server
if pgrep -x "nginx" > /dev/null || pgrep -x "apache2" > /dev/null || pgrep -x "httpd" > /dev/null; then
    echo -e "${GREEN}✓${NC} Web server is running"
else
    echo -e "${YELLOW}⚠${NC} Web server not detected (nginx/apache)"
    ((WARNINGS++))
fi

echo ""

# =====================================================
# CHECK 8: PHP Extensions
# =====================================================
echo "🔧 Checking PHP Extensions..."

EXTENSIONS=(
    "pdo"
    "pdo_mysql"
    "json"
    "openssl"
    "sodium"
)

for ext in "${EXTENSIONS[@]}"; do
    php -m | grep -q "$ext"
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓${NC} $ext"
    else
        echo -e "${RED}✗${NC} Missing: $ext"
        ((ERRORS++))
    fi
done

echo ""

# =====================================================
# CHECK 9: Documentation
# =====================================================
echo "📚 Checking Documentation..."

DOCS=(
    "CODE_AUDIT_GAP_ANALYSIS.md"
    "IMPLEMENTATION_PROGRESS_REPORT.md"
    "BUILD_SUMMARY.md"
    "composer.json"
)

for doc in "${DOCS[@]}"; do
    if [ -f "$doc" ]; then
        echo -e "${GREEN}✓${NC} $doc"
    else
        echo -e "${YELLOW}⚠${NC} Missing: $doc"
        ((WARNINGS++))
    fi
done

echo ""

# =====================================================
# SUMMARY
# =====================================================
echo "=================================================="
echo "📋 VERIFICATION SUMMARY"
echo "=================================================="

if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo -e "${GREEN}✓ ALL CHECKS PASSED!${NC}"
    echo ""
    echo "🚀 System is ready for deployment!"
    echo ""
    echo "Next steps:"
    echo "1. Run database schema: mysql < database/advanced-fraud-detection-schema.sql"
    echo "2. Seed patterns: php bin/seed-database.php --patterns-only"
    echo "3. Register cameras: Visit /views/camera-management.html"
    echo "4. Integrate Python CV pipeline with /api/cv-callback.php"
    exit 0
elif [ $ERRORS -eq 0 ]; then
    echo -e "${YELLOW}⚠ $WARNINGS WARNINGS${NC}"
    echo ""
    echo "System is mostly ready, but review warnings above."
    exit 0
else
    echo -e "${RED}✗ $ERRORS ERRORS, $WARNINGS WARNINGS${NC}"
    echo ""
    echo "Please fix errors before deployment!"
    exit 1
fi
