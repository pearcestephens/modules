#!/bin/bash
###############################################################################
# 🚀 LIGHTSPEED SYNC SYSTEM - PRODUCTION CRON SETUP
#
# This script installs all necessary cron jobs for the Lightspeed sync system
#
# Features:
# - Bidirectional sync every 5 minutes (Lightspeed ↔ CIS)
# - Queue worker daemon (always running, auto-restart)
# - Health checks every 15 minutes
# - Log rotation daily
# - Dead letter queue cleanup weekly
#
# Usage:
#   sudo bash setup-cron.sh [install|uninstall|status]
#
###############################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Paths
CLI_DIR="/home/master/applications/jcepnzzkmj/public_html/modules/consignments/cli"
LOG_DIR="/home/master/applications/jcepnzzkmj/public_html/modules/consignments/logs"
PHP_BIN="/usr/bin/php"

# Functions
info() {
    echo -e "${BLUE}ℹ ${NC}$1"
}

success() {
    echo -e "${GREEN}✓${NC} $1"
}

error() {
    echo -e "${RED}✗${NC} $1"
}

warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

header() {
    echo ""
    echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}  $1${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
    echo ""
}

# Check if running as correct user
check_user() {
    if [ "$EUID" -eq 0 ]; then
        error "Do not run as root! Run as 'master' user"
        exit 1
    fi

    if [ "$(whoami)" != "master" ]; then
        error "Must run as 'master' user, current user: $(whoami)"
        exit 1
    fi
}

# Create cron jobs
install_cron() {
    header "INSTALLING LIGHTSPEED SYNC CRON JOBS"

    info "Creating temporary crontab file..."

    # Get existing crontab (excluding our jobs)
    crontab -l 2>/dev/null | grep -v "# LIGHTSPEED_SYNC" > /tmp/crontab.tmp || true

    # Add header
    cat >> /tmp/crontab.tmp << 'EOF'

# ============================================================================
# LIGHTSPEED SYNC SYSTEM - AUTO-GENERATED (DO NOT EDIT MANUALLY)
# ============================================================================

# Pull sync from Lightspeed every 5 minutes
*/5 * * * * /usr/bin/php /home/master/applications/jcepnzzkmj/public_html/modules/consignments/cli/sync-lightspeed-full.php --mode=pull >> /home/master/applications/jcepnzzkmj/public_html/modules/consignments/logs/sync-cron.log 2>&1 # LIGHTSPEED_SYNC

# Push pending consignments to Lightspeed every 10 minutes
*/10 * * * * /usr/bin/php /home/master/applications/jcepnzzkmj/public_html/modules/consignments/cli/sync-lightspeed-full.php --mode=push >> /home/master/applications/jcepnzzkmj/public_html/modules/consignments/logs/sync-cron.log 2>&1 # LIGHTSPEED_SYNC

# Queue worker daemon - check every minute, restart if not running
* * * * * pgrep -f "queue-worker.php" > /dev/null || /usr/bin/php /home/master/applications/jcepnzzkmj/public_html/modules/consignments/cli/queue-worker.php >> /home/master/applications/jcepnzzkmj/public_html/modules/consignments/logs/queue-worker.log 2>&1 & # LIGHTSPEED_SYNC

# Health check every 15 minutes
*/15 * * * * /usr/bin/php /home/master/applications/jcepnzzkmj/public_html/modules/consignments/cli/health-check.php >> /home/master/applications/jcepnzzkmj/public_html/modules/consignments/logs/health-check.log 2>&1 # LIGHTSPEED_SYNC

# Rotate logs daily at 2 AM
0 2 * * * find /home/master/applications/jcepnzzkmj/public_html/modules/consignments/logs -name "*.log" -mtime +7 -delete # LIGHTSPEED_SYNC

# Clean up old completed jobs weekly (Sunday 3 AM)
0 3 * * 0 /usr/bin/php /home/master/applications/jcepnzzkmj/public_html/modules/consignments/cli/cleanup-jobs.php --days=30 >> /home/master/applications/jcepnzzkmj/public_html/modules/consignments/logs/cleanup.log 2>&1 # LIGHTSPEED_SYNC

# Full bidirectional sync daily at 4 AM (belt and suspenders)
0 4 * * * /usr/bin/php /home/master/applications/jcepnzzkmj/public_html/modules/consignments/cli/sync-lightspeed-full.php --mode=both --force >> /home/master/applications/jcepnzzkmj/public_html/modules/consignments/logs/sync-daily.log 2>&1 # LIGHTSPEED_SYNC

# ============================================================================
EOF

    # Install crontab
    crontab /tmp/crontab.tmp
    rm /tmp/crontab.tmp

    success "Cron jobs installed successfully!"

    echo ""
    info "Installed jobs:"
    crontab -l | grep "# LIGHTSPEED_SYNC" | sed 's/ # LIGHTSPEED_SYNC//g'
    echo ""
}

# Remove cron jobs
uninstall_cron() {
    header "UNINSTALLING LIGHTSPEED SYNC CRON JOBS"

    info "Removing cron jobs..."

    # Get existing crontab (excluding our jobs)
    crontab -l 2>/dev/null | grep -v "# LIGHTSPEED_SYNC" > /tmp/crontab.tmp || true
    crontab /tmp/crontab.tmp
    rm /tmp/crontab.tmp

    success "Cron jobs removed successfully!"

    # Stop queue worker
    info "Stopping queue worker..."
    pkill -f "queue-worker.php" 2>/dev/null || true
    success "Queue worker stopped"
}

# Show status
show_status() {
    header "LIGHTSPEED SYNC SYSTEM STATUS"

    # Check cron jobs
    echo -e "${BLUE}📋 Cron Jobs:${NC}"
    cron_count=$(crontab -l 2>/dev/null | grep -c "# LIGHTSPEED_SYNC" || echo "0")
    if [ "$cron_count" -gt 0 ]; then
        success "Found $cron_count cron jobs installed"
        crontab -l | grep "# LIGHTSPEED_SYNC" | sed 's/ # LIGHTSPEED_SYNC//g' | while read line; do
            echo "  • $line"
        done
    else
        error "No cron jobs installed"
    fi
    echo ""

    # Check queue worker
    echo -e "${BLUE}⚙️  Queue Worker:${NC}"
    if pgrep -f "queue-worker.php" > /dev/null; then
        pid=$(pgrep -f "queue-worker.php")
        success "Queue worker running (PID: $pid)"
        ps aux | grep "$pid" | grep -v grep | awk '{print "  • Memory: " $6/1024 " MB, CPU: " $3 "%"}'
    else
        error "Queue worker not running"
    fi
    echo ""

    # Check recent sync activity
    echo -e "${BLUE}📊 Recent Sync Activity (last 24 hours):${NC}"
    if [ -f "$LOG_DIR/sync-$(date +%Y-%m-%d).log" ]; then
        pull_count=$(grep -c "PULL sync complete" "$LOG_DIR/sync-$(date +%Y-%m-%d).log" 2>/dev/null || echo "0")
        push_count=$(grep -c "PUSH sync complete" "$LOG_DIR/sync-$(date +%Y-%m-%d).log" 2>/dev/null || echo "0")
        error_count=$(grep -c "\[ERROR\]" "$LOG_DIR/sync-$(date +%Y-%m-%d).log" 2>/dev/null || echo "0")

        echo "  • Pull syncs: $pull_count"
        echo "  • Push syncs: $push_count"
        if [ "$error_count" -gt 0 ]; then
            error "  • Errors: $error_count"
        else
            success "  • Errors: 0"
        fi
    else
        warning "No sync log found for today"
    fi
    echo ""

    # Check log files
    echo -e "${BLUE}📁 Log Files:${NC}"
    if [ -d "$LOG_DIR" ]; then
        log_size=$(du -sh "$LOG_DIR" 2>/dev/null | awk '{print $1}')
        log_count=$(ls -1 "$LOG_DIR"/*.log 2>/dev/null | wc -l)
        echo "  • Total size: $log_size"
        echo "  • File count: $log_count"
        echo "  • Recent logs:"
        ls -lt "$LOG_DIR"/*.log 2>/dev/null | head -5 | awk '{print "    - " $9 " (" $5 " bytes)"}'
    else
        error "Log directory not found"
    fi
    echo ""
}

# Test sync
test_sync() {
    header "TESTING LIGHTSPEED SYNC"

    info "Running dry-run pull sync..."
    $PHP_BIN $CLI_DIR/sync-lightspeed-full.php --mode=pull --dry-run --verbose

    echo ""
    success "Test completed! Check output above for any errors"
}

# Create required directories
setup_directories() {
    info "Creating required directories..."

    mkdir -p "$LOG_DIR"
    chmod 755 "$LOG_DIR"

    success "Directories created"
}

# Main script
main() {
    check_user

    case "${1:-install}" in
        install)
            setup_directories
            install_cron
            info "Starting queue worker..."
            $PHP_BIN $CLI_DIR/queue-worker.php >> $LOG_DIR/queue-worker.log 2>&1 &
            success "Queue worker started (PID: $!)"
            echo ""
            show_status
            ;;
        uninstall)
            uninstall_cron
            ;;
        status)
            show_status
            ;;
        test)
            test_sync
            ;;
        *)
            echo "Usage: $0 [install|uninstall|status|test]"
            echo ""
            echo "Commands:"
            echo "  install    - Install cron jobs and start queue worker"
            echo "  uninstall  - Remove cron jobs and stop queue worker"
            echo "  status     - Show system status"
            echo "  test       - Test sync system with dry-run"
            exit 1
            ;;
    esac
}

# Run main
main "$@"
