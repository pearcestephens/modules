#!/bin/bash

# Website Operations Module - Quick Deployment Script
# Version: 1.0.0
# Purpose: Deploy the module to production

set -e

echo "======================================"
echo "Website Operations Module - Deployment"
echo "======================================"
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Configuration
MODULE_PATH="/home/master/applications/jcepnzzkmj/public_html/modules/website-operations"
DB_NAME="cis"
DB_USER="master"

echo -e "${YELLOW}Step 1: Checking prerequisites...${NC}"

# Check if module directory exists
if [ ! -d "$MODULE_PATH" ]; then
    echo -e "${RED}Error: Module directory not found at $MODULE_PATH${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Module directory found${NC}"

# Check if database exists
if mysql -u $DB_USER -p -e "USE $DB_NAME;" 2>/dev/null; then
    echo -e "${GREEN}✓ Database connection successful${NC}"
else
    echo -e "${RED}Error: Cannot connect to database${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}Step 2: Running database migrations...${NC}"

# Run migration
mysql -u $DB_USER -p $DB_NAME < "$MODULE_PATH/migrations/001_create_tables.sql"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Database tables created successfully${NC}"
else
    echo -e "${RED}Error: Database migration failed${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}Step 3: Setting file permissions...${NC}"

# Set correct permissions
chmod 755 "$MODULE_PATH/api/index.php"
chmod 755 "$MODULE_PATH/views/"*.php
chmod 644 "$MODULE_PATH/module.json"
chmod 644 "$MODULE_PATH/README.md"

echo -e "${GREEN}✓ Permissions set${NC}"

echo ""
echo -e "${YELLOW}Step 4: Testing API endpoint...${NC}"

# Test API health endpoint
HEALTH_URL="https://staff.vapeshed.co.nz/modules/website-operations/api/index.php?endpoint=health"
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" "$HEALTH_URL")

if [ "$RESPONSE" -eq 200 ]; then
    echo -e "${GREEN}✓ API health check passed (HTTP $RESPONSE)${NC}"
else
    echo -e "${YELLOW}⚠ API returned HTTP $RESPONSE (may need configuration)${NC}"
fi

echo ""
echo -e "${YELLOW}Step 5: Verifying module registration...${NC}"

# Check if module.json is valid JSON
if jq empty "$MODULE_PATH/module.json" 2>/dev/null; then
    echo -e "${GREEN}✓ module.json is valid${NC}"
else
    echo -e "${RED}Error: module.json is not valid JSON${NC}"
    exit 1
fi

echo ""
echo "======================================"
echo -e "${GREEN}Deployment Complete!${NC}"
echo "======================================"
echo ""
echo "Next Steps:"
echo ""
echo "1. Configure API keys in .env:"
echo "   - VAPESHED_API_KEY"
echo "   - ECIGDIS_API_KEY"
echo "   - INTERNAL_API_KEY"
echo ""
echo "2. Visit dashboard:"
echo "   https://staff.vapeshed.co.nz/modules/website-operations/views/dashboard.php"
echo ""
echo "3. Test API endpoints:"
echo "   curl -H 'X-API-KEY: your_key' https://staff.vapeshed.co.nz/modules/website-operations/api/index.php/health"
echo ""
echo "4. Review README:"
echo "   cat $MODULE_PATH/README.md"
echo ""
echo "======================================"
