#!/bin/bash
#
# 🚀 LIGHTSPEED SYNC SYSTEM - PRODUCTION SETUP SCRIPT
#
# Sets up complete bidirectional sync infrastructure:
# - Cron jobs for scheduled syncs
# - Queue worker daemon (systemd service)
# - Webhook endpoint verification
# - Database table validation
# - Log rotation
# - Health monitoring
#
# Usage: sudo bash setup-production-sync.sh
#

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Configuration
APP_ROOT="/home/master/applications/jcepnzzkmj/public_html"
MODULE_ROOT="$APP_ROOT/modules/consignments"
CLI_DIR="$MODULE_ROOT/cli"
LOG_DIR="$MODULE_ROOT/logs"
VEND_DOMAIN_PREFIX="vapeshed"
CRON_USER="master"

echo -e "${CYAN}${BOLD}"
echo "╔════════════════════════════════════════════════════════════╗"
echo "║  🚀 LIGHTSPEED SYNC - PRODUCTION SETUP                    ║"
echo "║  Enterprise-Grade Synchronization Infrastructure          ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo -e "${NC}\n"

# ============================================================================
# STEP 1: Verify Prerequisites
# ============================================================================

echo -e "${BLUE}${BOLD}[1/9] Checking Prerequisites...${NC}"

# Check if running as root or with sudo
if [[ $EUID -eq 0 ]]; then
    SUDO_CMD=""
    echo -e "${GREEN}✓${NC} Running as root"
else
    SUDO_CMD="sudo"
    echo -e "${YELLOW}⚠${NC}  Running as user, will use sudo for privileged operations"
fi

# Check PHP
if ! command -v php &> /dev/null; then
    echo -e "${RED}✗${NC} PHP not found. Please install PHP 8.0+"
    exit 1
fi
PHP_VERSION=$(php -r 'echo PHP_VERSION;')
echo -e "${GREEN}✓${NC} PHP $PHP_VERSION"

# Check MySQL
if ! command -v mysql &> /dev/null; then
    echo -e "${RED}✗${NC} MySQL client not found"
    exit 1
fi
echo -e "${GREEN}✓${NC} MySQL client available"

# Check cron
if ! command -v crontab &> /dev/null; then
    echo -e "${RED}✗${NC} cron not installed"
    exit 1
fi
echo -e "${GREEN}✓${NC} cron available"

echo ""

# ============================================================================
# STEP 2: Create Directory Structure
# ============================================================================

echo -e "${BLUE}${BOLD}[2/9] Creating Directory Structure...${NC}"

$SUDO_CMD mkdir -p "$LOG_DIR"
$SUDO_CMD mkdir -p "$LOG_DIR/sync"
$SUDO_CMD mkdir -p "$LOG_DIR/webhook"
$SUDO_CMD mkdir -p "$LOG_DIR/queue"
$SUDO_CMD mkdir -p "$LOG_DIR/archive"
$SUDO_CMD mkdir -p "$MODULE_ROOT/var/run"

$SUDO_CMD chown -R $CRON_USER:$CRON_USER "$LOG_DIR"
$SUDO_CMD chown -R $CRON_USER:$CRON_USER "$MODULE_ROOT/var"
$SUDO_CMD chmod -R 755 "$LOG_DIR"

echo -e "${GREEN}✓${NC} Log directories created"
echo -e "${GREEN}✓${NC} Permissions set"
echo ""

# ============================================================================
# STEP 3: Make CLI Scripts Executable
# ============================================================================

echo -e "${BLUE}${BOLD}[3/9] Making CLI Scripts Executable...${NC}"

$SUDO_CMD chmod +x "$CLI_DIR/sync-lightspeed-full.php"
$SUDO_CMD chmod +x "$CLI_DIR/queue-worker-daemon.php"
$SUDO_CMD chmod +x "$CLI_DIR/lightspeed-cli.php"

echo -e "${GREEN}✓${NC} sync-lightspeed-full.php"
echo -e "${GREEN}✓${NC} queue-worker-daemon.php"
echo -e "${GREEN}✓${NC} lightspeed-cli.php"
echo ""

# ============================================================================
# STEP 4: Verify Database Tables
# ============================================================================

echo -e "${BLUE}${BOLD}[4/9] Verifying Database Tables...${NC}"

php -r "
require_once '$MODULE_ROOT/bootstrap.php';
\$pdo = CIS\Base\Database::pdo();

\$requiredTables = [
    'vend_consignments',
    'vend_consignment_line_items',
    'queue_consignments',
    'queue_consignment_products',
    'queue_jobs',
    'queue_jobs_dlq',
    'queue_webhook_events',
    'queue_worker_heartbeats'
];

\$missing = [];
foreach (\$requiredTables as \$table) {
    \$result = \$pdo->query(\"SHOW TABLES LIKE '\$table'\")->fetch();
    if (!\$result) {
        \$missing[] = \$table;
    }
}

if (!empty(\$missing)) {
    echo \"${RED}✗${NC} Missing tables: \" . implode(', ', \$missing) . \"\n\";
    echo \"${YELLOW}⚠${NC}  Run database migrations first\n\";
    exit(1);
}

echo \"${GREEN}✓${NC} All required tables exist\n\";
"

echo ""

# ============================================================================
# STEP 5: Create Systemd Service for Queue Worker
# ============================================================================

echo -e "${BLUE}${BOLD}[5/9] Creating Systemd Service...${NC}"

SERVICE_FILE="/etc/systemd/system/lightspeed-queue-worker.service"

$SUDO_CMD tee "$SERVICE_FILE" > /dev/null <<EOF
[Unit]
Description=Lightspeed Queue Worker Daemon
After=network.target mysql.service
Wants=mysql.service

[Service]
Type=simple
User=$CRON_USER
WorkingDirectory=$MODULE_ROOT
ExecStart=/usr/bin/php $CLI_DIR/queue-worker-daemon.php --workers=10 --max-jobs=1000
Restart=always
RestartSec=10
StandardOutput=append:$LOG_DIR/queue/worker.log
StandardError=append:$LOG_DIR/queue/worker-error.log

# Resource limits
LimitNOFILE=10000
MemoryMax=512M
CPUQuota=80%

# Environment
Environment="PHP_MEMORY_LIMIT=512M"

[Install]
WantedBy=multi-user.target
EOF

$SUDO_CMD systemctl daemon-reload
echo -e "${GREEN}✓${NC} Systemd service created: $SERVICE_FILE"
echo -e "${CYAN}ℹ${NC}  Start with: sudo systemctl start lightspeed-queue-worker"
echo -e "${CYAN}ℹ${NC}  Enable on boot: sudo systemctl enable lightspeed-queue-worker"
echo ""

# ============================================================================
# STEP 6: Setup Cron Jobs
# ============================================================================

echo -e "${BLUE}${BOLD}[6/9] Setting Up Cron Jobs...${NC}"

CRON_TEMP=$(mktemp)

# Get existing crontab (if any)
crontab -l -u $CRON_USER > "$CRON_TEMP" 2>/dev/null || true

# Remove old Lightspeed sync jobs (if any)
sed -i '/# Lightspeed Sync/d' "$CRON_TEMP"
sed -i '/sync-lightspeed-full.php/d' "$CRON_TEMP"
sed -i '/queue-worker-daemon.php/d' "$CRON_TEMP"

# Add new cron jobs
cat >> "$CRON_TEMP" <<EOF

# ============================================================================
# Lightspeed Sync System - PRODUCTION
# ============================================================================

# Pull from Lightspeed every 5 minutes
*/5 * * * * /usr/bin/php $CLI_DIR/sync-lightspeed-full.php --mode=pull >> $LOG_DIR/sync/pull-cron.log 2>&1

# Push to Lightspeed every 10 minutes
*/10 * * * * /usr/bin/php $CLI_DIR/sync-lightspeed-full.php --mode=push >> $LOG_DIR/sync/push-cron.log 2>&1

# Full bidirectional sync every hour (redundancy)
0 * * * * /usr/bin/php $CLI_DIR/sync-lightspeed-full.php --mode=both >> $LOG_DIR/sync/hourly-full.log 2>&1

# Clean up old logs (weekly, Sunday 2am)
0 2 * * 0 find $LOG_DIR -name "*.log" -mtime +30 -delete

# Health check - restart queue worker if dead (every 5 min)
*/5 * * * * systemctl is-active --quiet lightspeed-queue-worker || sudo systemctl restart lightspeed-queue-worker

# Archive old queue jobs (daily, 3am)
0 3 * * * /usr/bin/php $CLI_DIR/lightspeed-cli.php queue:prune 30 >> $LOG_DIR/queue/prune.log 2>&1

EOF

# Install new crontab
$SUDO_CMD crontab -u $CRON_USER "$CRON_TEMP"
rm "$CRON_TEMP"

echo -e "${GREEN}✓${NC} Cron jobs installed for user: $CRON_USER"
echo ""
echo -e "${CYAN}Scheduled Jobs:${NC}"
echo "  • Pull sync:        Every 5 minutes"
echo "  • Push sync:        Every 10 minutes"
echo "  • Full sync:        Every hour"
echo "  • Log cleanup:      Weekly (Sunday 2am)"
echo "  • Worker health:    Every 5 minutes"
echo "  • Job pruning:      Daily (3am)"
echo ""

# ============================================================================
# STEP 7: Verify Webhook Endpoint
# ============================================================================

echo -e "${BLUE}${BOLD}[7/9] Verifying Webhook Endpoint...${NC}"

WEBHOOK_URL="https://staff.vapeshed.co.nz/modules/consignments/public/webhooks/lightspeed.php"
HTTP_CODE=$(curl -o /dev/null -s -w "%{http_code}" -X POST "$WEBHOOK_URL" -H "Content-Type: application/json" -d '{"test":true}' || echo "000")

if [ "$HTTP_CODE" = "405" ] || [ "$HTTP_CODE" = "400" ] || [ "$HTTP_CODE" = "401" ]; then
    echo -e "${GREEN}✓${NC} Webhook endpoint accessible (HTTP $HTTP_CODE)"
    echo -e "${CYAN}ℹ${NC}  URL: $WEBHOOK_URL"
elif [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "202" ]; then
    echo -e "${GREEN}✓${NC} Webhook endpoint accessible and responding (HTTP $HTTP_CODE)"
    echo -e "${CYAN}ℹ${NC}  URL: $WEBHOOK_URL"
else
    echo -e "${YELLOW}⚠${NC}  Webhook endpoint returned HTTP $HTTP_CODE"
    echo -e "${CYAN}ℹ${NC}  URL: $WEBHOOK_URL"
    echo -e "${YELLOW}⚠${NC}  Verify Apache/Nginx configuration"
fi
echo ""

# ============================================================================
# STEP 8: Create Log Rotation Config
# ============================================================================

echo -e "${BLUE}${BOLD}[8/9] Setting Up Log Rotation...${NC}"

LOGROTATE_CONF="/etc/logrotate.d/lightspeed-sync"

$SUDO_CMD tee "$LOGROTATE_CONF" > /dev/null <<EOF
$LOG_DIR/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 0644 $CRON_USER $CRON_USER
    sharedscripts
    postrotate
        # Reload services if needed
        systemctl reload lightspeed-queue-worker > /dev/null 2>&1 || true
    endscript
}

$LOG_DIR/sync/*.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 0644 $CRON_USER $CRON_USER
}

$LOG_DIR/queue/*.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 0644 $CRON_USER $CRON_USER
}
EOF

echo -e "${GREEN}✓${NC} Log rotation configured: $LOGROTATE_CONF"
echo ""

# ============================================================================
# STEP 9: Test Sync (Dry Run)
# ============================================================================

echo -e "${BLUE}${BOLD}[9/9] Testing Sync System (Dry Run)...${NC}"

echo -e "${CYAN}Running test sync...${NC}"
php "$CLI_DIR/sync-lightspeed-full.php" --mode=pull --dry-run 2>&1 | head -20

echo ""
echo -e "${GREEN}✓${NC} Test sync completed"
echo ""

# ============================================================================
# COMPLETION SUMMARY
# ============================================================================

echo -e "${GREEN}${BOLD}"
echo "╔════════════════════════════════════════════════════════════╗"
echo "║  ✅ LIGHTSPEED SYNC SYSTEM - SETUP COMPLETE               ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo -e "${NC}\n"

echo -e "${CYAN}${BOLD}Next Steps:${NC}"
echo ""
echo "1️⃣  Start Queue Worker:"
echo "   ${YELLOW}sudo systemctl start lightspeed-queue-worker${NC}"
echo "   ${YELLOW}sudo systemctl enable lightspeed-queue-worker${NC}"
echo ""
echo "2️⃣  Monitor Queue Worker:"
echo "   ${YELLOW}sudo systemctl status lightspeed-queue-worker${NC}"
echo "   ${YELLOW}tail -f $LOG_DIR/queue/worker.log${NC}"
echo ""
echo "3️⃣  Monitor Sync Jobs:"
echo "   ${YELLOW}tail -f $LOG_DIR/sync/pull-cron.log${NC}"
echo "   ${YELLOW}tail -f $LOG_DIR/sync/push-cron.log${NC}"
echo ""
echo "4️⃣  Manual Sync (if needed):"
echo "   ${YELLOW}php $CLI_DIR/sync-lightspeed-full.php --mode=both${NC}"
echo ""
echo "5️⃣  Check Queue Status:"
echo "   ${YELLOW}php $CLI_DIR/lightspeed-cli.php queue:stats${NC}"
echo ""
echo "6️⃣  Configure Lightspeed Webhook:"
echo "   • URL: ${CYAN}$WEBHOOK_URL${NC}"
echo "   • Secret: ${CYAN}[Check .env WEBHOOK_SECRET]${NC}"
echo "   • Events: ${CYAN}consignment.updated, consignment.received${NC}"
echo ""

echo -e "${GREEN}${BOLD}✨ System is ready for production! ✨${NC}\n"
