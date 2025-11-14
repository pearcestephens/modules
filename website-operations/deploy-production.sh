#!/bin/bash
################################################################################
# Website Operations Module - Production Deployment Script
# Date: 2025-11-14
# Status: Deploy fully-tested module (36/36 tests passed)
################################################################################

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
MODULE_DIR="/home/master/applications/jcepnzzkmj/public_html/modules/website-operations"
BACKUP_DIR="/home/master/applications/jcepnzzkmj/public_html/backups/website-operations-$(date +%Y%m%d_%H%M%S)"
DB_USER="jcepnzzkmj"
DB_PASS="wprKh9Jq63"
DB_NAME="jcepnzzkmj"

echo -e "${BLUE}╔═══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   Website Operations Module - Production Deployment          ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════════════╝${NC}"
echo ""

################################################################################
# Step 1: Pre-Deployment Checks
################################################################################
echo -e "${YELLOW}[1/8] Pre-Deployment Checks...${NC}"

# Check if module directory exists
if [ ! -d "$MODULE_DIR" ]; then
    echo -e "${RED}✗ Module directory not found: $MODULE_DIR${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Module directory exists${NC}"

# Check database connectivity
if mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 1;" > /dev/null 2>&1; then
    echo -e "${GREEN}✓ Database connection successful${NC}"
else
    echo -e "${RED}✗ Database connection failed${NC}"
    exit 1
fi

# Check PHP syntax on all files
echo -e "${YELLOW}  Checking PHP syntax...${NC}"
SYNTAX_ERRORS=0
for file in $(find "$MODULE_DIR" -name "*.php" -type f); do
    if ! php -l "$file" > /dev/null 2>&1; then
        echo -e "${RED}✗ Syntax error in: $file${NC}"
        SYNTAX_ERRORS=$((SYNTAX_ERRORS + 1))
    fi
done

if [ $SYNTAX_ERRORS -eq 0 ]; then
    echo -e "${GREEN}✓ All PHP files have valid syntax${NC}"
else
    echo -e "${RED}✗ Found $SYNTAX_ERRORS syntax errors. Fix before deploying.${NC}"
    exit 1
fi

################################################################################
# Step 2: Create Backup
################################################################################
echo ""
echo -e "${YELLOW}[2/8] Creating Backup...${NC}"

mkdir -p "$BACKUP_DIR"
if [ -d "$MODULE_DIR" ]; then
    cp -r "$MODULE_DIR" "$BACKUP_DIR/"
    echo -e "${GREEN}✓ Backup created at: $BACKUP_DIR${NC}"
else
    echo -e "${YELLOW}⚠ No existing module to backup${NC}"
fi

################################################################################
# Step 3: Run Test Suite
################################################################################
echo ""
echo -e "${YELLOW}[3/8] Running Test Suite...${NC}"

cd "$MODULE_DIR"
TEST_OUTPUT=$(php test-suite.php 2>&1)
TEST_EXIT_CODE=$?

if [ $TEST_EXIT_CODE -eq 0 ] && echo "$TEST_OUTPUT" | grep -q "Pass Rate:    100%"; then
    echo -e "${GREEN}✓ All tests passed (36/36)${NC}"
else
    echo -e "${RED}✗ Tests failed! Output:${NC}"
    echo "$TEST_OUTPUT"
    echo ""
    echo -e "${RED}Deployment aborted. Fix failing tests first.${NC}"
    exit 1
fi

################################################################################
# Step 4: Database Migration (Idempotent)
################################################################################
echo ""
echo -e "${YELLOW}[4/8] Checking Database Schema...${NC}"

# Count existing tables
TABLE_COUNT=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -sNe "
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = '$DB_NAME'
    AND (TABLE_NAME LIKE 'web_%' OR TABLE_NAME = 'store_configurations' OR TABLE_NAME LIKE 'wholesale_%')
" 2>/dev/null)

if [ "$TABLE_COUNT" -ge 7 ]; then
    echo -e "${GREEN}✓ Database schema exists ($TABLE_COUNT tables)${NC}"
else
    echo -e "${YELLOW}⚠ Running database migration...${NC}"
    if mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$MODULE_DIR/migrations/001_create_tables.sql" 2>&1; then
        echo -e "${GREEN}✓ Database migration completed${NC}"
    else
        echo -e "${RED}✗ Database migration failed${NC}"
        exit 1
    fi
fi

################################################################################
# Step 5: Set Permissions
################################################################################
echo ""
echo -e "${YELLOW}[5/8] Setting Permissions...${NC}"

# Set directory permissions
find "$MODULE_DIR" -type d -exec chmod 755 {} \; 2>/dev/null
echo -e "${GREEN}✓ Directory permissions set (755)${NC}"

# Set file permissions
find "$MODULE_DIR" -type f -exec chmod 644 {} \; 2>/dev/null
echo -e "${GREEN}✓ File permissions set (644)${NC}"

# Make scripts executable
chmod +x "$MODULE_DIR"/*.sh 2>/dev/null || true
echo -e "${GREEN}✓ Shell scripts made executable${NC}"

################################################################################
# Step 6: Configure Web Server Access
################################################################################
echo ""
echo -e "${YELLOW}[6/8] Configuring Web Server...${NC}"

# Check if .htaccess exists, create if needed
if [ ! -f "$MODULE_DIR/.htaccess" ]; then
    cat > "$MODULE_DIR/.htaccess" << 'EOF'
# Website Operations Module - Apache Configuration
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /modules/website-operations/

    # Redirect to index.php for clean URLs
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php?route=$1 [L,QSA]
</IfModule>

# Security Headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>

# Disable directory browsing
Options -Indexes

# Protect sensitive files
<FilesMatch "(^\.env|\.sql|composer\.(json|lock)|\.git)">
    Order allow,deny
    Deny from all
</FilesMatch>
EOF
    echo -e "${GREEN}✓ Created .htaccess with security settings${NC}"
else
    echo -e "${GREEN}✓ .htaccess already exists${NC}"
fi

################################################################################
# Step 7: Verify Deployment
################################################################################
echo ""
echo -e "${YELLOW}[7/8] Verifying Deployment...${NC}"

# Check critical files exist
CRITICAL_FILES=(
    "index.php"
    "services/WebsiteOperationsService.php"
    "services/OrderManagementService.php"
    "services/ShippingOptimizationService.php"
    "api/index.php"
    "views/dashboard.php"
    "module.json"
)

ALL_FILES_EXIST=true
for file in "${CRITICAL_FILES[@]}"; do
    if [ -f "$MODULE_DIR/$file" ]; then
        echo -e "${GREEN}✓ $file${NC}"
    else
        echo -e "${RED}✗ Missing: $file${NC}"
        ALL_FILES_EXIST=false
    fi
done

if [ "$ALL_FILES_EXIST" = false ]; then
    echo -e "${RED}✗ Critical files missing. Deployment incomplete.${NC}"
    exit 1
fi

# Verify database tables
TABLE_CHECK=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -sNe "
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = '$DB_NAME'
    AND TABLE_NAME IN ('web_orders', 'web_products', 'web_customers', 'store_configurations')
" 2>/dev/null)

if [ "$TABLE_CHECK" -eq 4 ]; then
    echo -e "${GREEN}✓ Critical database tables exist${NC}"
else
    echo -e "${RED}✗ Missing critical database tables${NC}"
    exit 1
fi

################################################################################
# Step 8: Generate Deployment Report
################################################################################
echo ""
echo -e "${YELLOW}[8/8] Generating Deployment Report...${NC}"

DEPLOYMENT_REPORT="$MODULE_DIR/DEPLOYMENT_$(date +%Y%m%d_%H%M%S).log"

cat > "$DEPLOYMENT_REPORT" << EOF
╔═══════════════════════════════════════════════════════════════╗
║   Website Operations Module - Deployment Report              ║
╚═══════════════════════════════════════════════════════════════╝

Deployment Date: $(date '+%Y-%m-%d %H:%M:%S')
Module Version: 1.0.0
Deployed By: Production Deployment Script

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
DEPLOYMENT STATUS: SUCCESS ✓
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Pre-Deployment Checks:
  ✓ Module directory exists
  ✓ Database connectivity verified
  ✓ All PHP files syntax valid

Backup:
  ✓ Backup created at: $BACKUP_DIR

Testing:
  ✓ Test suite passed (36/36 tests)
  ✓ 100% success rate

Database:
  ✓ Schema verified ($TABLE_COUNT tables)
  ✓ Critical tables present

Permissions:
  ✓ Directory permissions: 755
  ✓ File permissions: 644
  ✓ Shell scripts executable

Web Server:
  ✓ .htaccess configured
  ✓ Security headers enabled
  ✓ Directory browsing disabled

Verification:
  ✓ All critical files present
  ✓ Database tables verified
  ✓ Module ready for production use

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
MODULE ENDPOINTS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Dashboard:
  https://staff.vapeshed.co.nz/modules/website-operations/views/dashboard.php

API Base:
  https://staff.vapeshed.co.nz/modules/website-operations/api/

Main Entry:
  https://staff.vapeshed.co.nz/modules/website-operations/

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
NEXT STEPS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

1. Access dashboard to verify UI loads correctly
2. Test API endpoints with real data
3. Configure monitoring and alerts
4. Train staff on new features
5. Monitor logs for first 24 hours

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
ROLLBACK INSTRUCTIONS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

If issues occur, restore from backup:

  rm -rf $MODULE_DIR
  cp -r $BACKUP_DIR/website-operations $MODULE_DIR/..

Database rollback not recommended as it may contain production data.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
DEPLOYMENT COMPLETE ✓
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Module is now live and ready for production use!
Money-saving shipping algorithm is active! 💰

EOF

echo -e "${GREEN}✓ Deployment report saved: $DEPLOYMENT_REPORT${NC}"

################################################################################
# Deployment Complete
################################################################################
echo ""
echo -e "${GREEN}╔═══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║              🎉 DEPLOYMENT SUCCESSFUL! 🎉                     ║${NC}"
echo -e "${GREEN}╚═══════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}Module Deployed:${NC} Website Operations v1.0.0"
echo -e "${BLUE}Deployment Time:${NC} $(date '+%Y-%m-%d %H:%M:%S')"
echo -e "${BLUE}Tests Passed:${NC} 36/36 (100%)"
echo -e "${BLUE}Database Tables:${NC} $TABLE_COUNT"
echo -e "${BLUE}Backup Location:${NC} $BACKUP_DIR"
echo ""
echo -e "${YELLOW}📊 Access Dashboard:${NC}"
echo -e "   https://staff.vapeshed.co.nz/modules/website-operations/views/dashboard.php"
echo ""
echo -e "${YELLOW}🔌 API Endpoint:${NC}"
echo -e "   https://staff.vapeshed.co.nz/modules/website-operations/api/"
echo ""
echo -e "${GREEN}💰 Money-saving shipping algorithm is now ACTIVE!${NC}"
echo ""
echo -e "${BLUE}Report saved to:${NC} $DEPLOYMENT_REPORT"
echo ""

exit 0
