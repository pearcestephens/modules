#!/bin/bash
# Quick Auth Control Script
# Usage: ./auth-control.sh [status|enable|disable]

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

APP_CONFIG="/home/master/applications/jcepnzzkmj/public_html/modules/config/app.php"

function show_status() {
    echo -e "${YELLOW}╔══════════════════════════════════════════════════════╗${NC}"
    echo -e "${YELLOW}║  PAYROLL AUTHENTICATION STATUS                      ║${NC}"
    echo -e "${YELLOW}╚══════════════════════════════════════════════════════╝${NC}"
    echo ""

    php -r "
    \$config = require '$APP_CONFIG';
    \$enabled = \$config['payroll_auth_enabled'] ?? false;

    if (\$enabled) {
        echo '\033[0;31m❌ AUTHENTICATION: ENABLED\033[0m' . PHP_EOL;
        echo '   - All routes require login' . PHP_EOL;
        echo '   - 401/403 responses for unauthorized access' . PHP_EOL;
        echo '   - Production mode' . PHP_EOL;
    } else {
        echo '\033[0;32m✅ AUTHENTICATION: DISABLED\033[0m' . PHP_EOL;
        echo '   - All routes are open' . PHP_EOL;
        echo '   - No login required' . PHP_EOL;
        echo '   - Development/Testing mode' . PHP_EOL;
    }
    "
    echo ""
}

function enable_auth() {
    echo -e "${YELLOW}Enabling authentication...${NC}"

    # Update app.php
    sed -i "s/'payroll_auth_enabled' => (bool)env('PAYROLL_AUTH_ENABLED', false)/'payroll_auth_enabled' => (bool)env('PAYROLL_AUTH_ENABLED', true)/" "$APP_CONFIG"

    echo -e "${GREEN}✅ Authentication ENABLED${NC}"
    echo ""
    show_status
}

function disable_auth() {
    echo -e "${YELLOW}Disabling authentication...${NC}"

    # Update app.php
    sed -i "s/'payroll_auth_enabled' => (bool)env('PAYROLL_AUTH_ENABLED', true)/'payroll_auth_enabled' => (bool)env('PAYROLL_AUTH_ENABLED', false)/" "$APP_CONFIG"

    echo -e "${GREEN}✅ Authentication DISABLED${NC}"
    echo ""
    show_status
}

case "$1" in
    enable)
        enable_auth
        ;;
    disable)
        disable_auth
        ;;
    status|"")
        show_status
        ;;
    *)
        echo "Usage: $0 [status|enable|disable]"
        echo ""
        echo "Commands:"
        echo "  status  - Show current authentication status (default)"
        echo "  enable  - Enable authentication (production mode)"
        echo "  disable - Disable authentication (development mode)"
        exit 1
        ;;
esac
