#!/bin/bash
###############################################################################
# Smart Cron 2.0 - Complete Installation & Setup Script
#
# This script will:
# 1. Create all necessary directories
# 2. Set correct permissions
# 3. Import database schema
# 4. Configure crontab
# 5. Run initial health checks
# 6. Generate backup
#
# Usage: sudo bash install.sh
###############################################################################

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
INSTALL_DIR="/home/master/applications/jcepnzzkmj/public_html/modules/smart-cron"
WEB_USER="www-data"
WEB_GROUP="www-data"
LOG_DIR="/var/log/smart-cron"
LOCK_DIR="/var/run/smart-cron"
BACKUP_DIR="/var/backups/smart-cron"

# Database config (will load from config file)
DB_HOST="localhost"
DB_USER="your_db_user"
DB_PASS="your_db_pass"
DB_NAME="your_db_name"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  Smart Cron 2.0 Installation${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}ERROR: Please run as root (use sudo)${NC}"
    exit 1
fi

# Step 1: Create directories
echo -e "${YELLOW}[1/7] Creating directories...${NC}"
mkdir -p "$LOG_DIR"
mkdir -p "$LOCK_DIR"
mkdir -p "$BACKUP_DIR"
mkdir -p "$INSTALL_DIR"/{cron,includes,dashboard,database,config}

echo -e "${GREEN}✓ Directories created${NC}"

# Step 2: Set permissions
echo -e "${YELLOW}[2/7] Setting permissions...${NC}"

# Log directory - writable by web user
chown -R $WEB_USER:$WEB_GROUP "$LOG_DIR"
chmod 750 "$LOG_DIR"

# Lock directory - writable by web user
chown -R $WEB_USER:$WEB_GROUP "$LOCK_DIR"
chmod 750 "$LOCK_DIR"

# Backup directory - writable by web user
chown -R $WEB_USER:$WEB_GROUP "$BACKUP_DIR"
chmod 750 "$BACKUP_DIR"

# Application directory
chown -R $WEB_USER:$WEB_GROUP "$INSTALL_DIR"
chmod 750 "$INSTALL_DIR"

# Cron scripts - executable
chmod 750 "$INSTALL_DIR"/cron/*.php 2>/dev/null || true

# Includes - readable only
chmod 640 "$INSTALL_DIR"/includes/*.php 2>/dev/null || true

# Dashboard - readable by web server
chmod 750 "$INSTALL_DIR"/dashboard
chmod 640 "$INSTALL_DIR"/dashboard/*.php 2>/dev/null || true

echo -e "${GREEN}✓ Permissions set${NC}"

# Step 3: Create .htaccess for cron directory (block web access)
echo -e "${YELLOW}[3/7] Securing cron scripts...${NC}"

cat > "$INSTALL_DIR/cron/.htaccess" << 'EOF'
# Block all web access to cron scripts
Order Deny,Allow
Deny from all
EOF

chmod 644 "$INSTALL_DIR/cron/.htaccess"

echo -e "${GREEN}✓ Cron scripts secured${NC}"

# Step 4: Load database configuration
echo -e "${YELLOW}[4/7] Loading database configuration...${NC}"

if [ -f "$INSTALL_DIR/../config/database.php" ]; then
    echo -e "${GREEN}✓ Found database config${NC}"
    echo -e "${BLUE}Please enter database credentials:${NC}"
    read -p "Database Host [localhost]: " input_host
    DB_HOST=${input_host:-localhost}

    read -p "Database Name: " DB_NAME
    read -p "Database User: " DB_USER
    read -sp "Database Password: " DB_PASS
    echo ""
else
    echo -e "${RED}WARNING: Database config not found${NC}"
    echo "Please configure database manually"
fi

# Step 5: Import database schema
echo -e "${YELLOW}[5/7] Importing database schema...${NC}"

if [ -f "$INSTALL_DIR/database/schema.sql" ]; then
    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$INSTALL_DIR/database/schema.sql"

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Database schema imported successfully${NC}"
    else
        echo -e "${RED}ERROR: Failed to import schema${NC}"
        exit 1
    fi
else
    echo -e "${RED}ERROR: schema.sql not found${NC}"
    exit 1
fi

# Step 6: Configure crontab
echo -e "${YELLOW}[6/7] Configuring crontab...${NC}"

CRON_CMD="* * * * * /usr/bin/php $INSTALL_DIR/cron/master_runner.php >> $LOG_DIR/master.log 2>&1"

# Check if cron already exists
crontab -u $WEB_USER -l 2>/dev/null | grep -q "master_runner.php" && {
    echo -e "${YELLOW}Cron job already exists, skipping...${NC}"
} || {
    # Add new cron
    (crontab -u $WEB_USER -l 2>/dev/null; echo "$CRON_CMD") | crontab -u $WEB_USER -
    echo -e "${GREEN}✓ Cron job added${NC}"
}

# Add health monitor (every 5 minutes)
HEALTH_CMD="*/5 * * * * /usr/bin/php $INSTALL_DIR/cron/health_monitor.php >> $LOG_DIR/health.log 2>&1"

crontab -u $WEB_USER -l 2>/dev/null | grep -q "health_monitor.php" || {
    (crontab -u $WEB_USER -l 2>/dev/null; echo "$HEALTH_CMD") | crontab -u $WEB_USER -
    echo -e "${GREEN}✓ Health monitor cron added${NC}"
}

echo -e "${GREEN}✓ Crontab configured${NC}"

# Step 7: Run initial health check
echo -e "${YELLOW}[7/7] Running initial health check...${NC}"

# Test database connection
mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -e "SELECT COUNT(*) FROM smart_cron_tasks_config" "$DB_NAME" > /dev/null 2>&1

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Database connection successful${NC}"
else
    echo -e "${RED}WARNING: Database connection test failed${NC}"
fi

# Create initial backup
echo -e "${BLUE}Creating initial backup...${NC}"
BACKUP_FILE="$BACKUP_DIR/initial-backup-$(date +%Y%m%d-%H%M%S).sql.gz"
mysqldump -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$BACKUP_FILE"
chmod 600 "$BACKUP_FILE"
echo -e "${GREEN}✓ Initial backup created: $BACKUP_FILE${NC}"

# Final summary
echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Installation Complete! ✓${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "${BLUE}Next Steps:${NC}"
echo ""
echo "1. Access the dashboard:"
echo -e "   ${YELLOW}https://your-domain.com/modules/smart-cron/dashboard/${NC}"
echo ""
echo "2. View logs:"
echo -e "   ${YELLOW}tail -f $LOG_DIR/master-$(date +%Y-%m-%d).log${NC}"
echo ""
echo "3. Check cron status:"
echo -e "   ${YELLOW}crontab -u $WEB_USER -l${NC}"
echo ""
echo "4. Monitor health:"
echo -e "   ${YELLOW}tail -f $LOG_DIR/health.log${NC}"
echo ""
echo "5. Backup location:"
echo -e "   ${YELLOW}$BACKUP_DIR${NC}"
echo ""
echo -e "${GREEN}System is ready to schedule tasks!${NC}"
echo ""

# Create quick reference card
cat > "$INSTALL_DIR/QUICK_REFERENCE.md" << 'EOF'
# Smart Cron 2.0 - Quick Reference

## Essential Commands

### View Logs
```bash
# Master runner log (today)
tail -f /var/log/smart-cron/master-$(date +%Y-%m-%d).log

# Health monitor log
tail -f /var/log/smart-cron/health.log

# All logs from last hour
find /var/log/smart-cron -type f -mmin -60 -exec tail -n 50 {} \;
```

### Manage Cron
```bash
# View current crontab
crontab -u www-data -l

# Edit crontab
crontab -u www-data -e

# Disable Smart Cron temporarily
crontab -u www-data -l | grep -v "master_runner.php" | crontab -u www-data -

# Re-enable Smart Cron
(crontab -u www-data -l; echo "* * * * * /usr/bin/php /path/to/master_runner.php >> /var/log/smart-cron/master.log 2>&1") | crontab -u www-data -
```

### Database Operations
```bash
# Backup database
mysqldump -u user -p database_name | gzip > /var/backups/smart-cron/backup-$(date +%Y%m%d-%H%M%S).sql.gz

# Restore database
gunzip < /var/backups/smart-cron/backup-YYYYMMDD-HHMMSS.sql.gz | mysql -u user -p database_name

# View task status
mysql -u user -p -e "SELECT * FROM smart_cron_task_performance" database_name
```

### Manual Task Execution
```bash
# Run a specific task manually
/usr/bin/php /path/to/your/task_script.php

# Run with timeout
timeout 300s /usr/bin/php /path/to/your/task_script.php
```

### Troubleshooting
```bash
# Check if cron daemon is running
systemctl status cron

# Check disk space
df -h /var/log/smart-cron

# Check permissions
ls -la /var/log/smart-cron
ls -la /var/run/smart-cron

# View recent errors
grep ERROR /var/log/smart-cron/master-$(date +%Y-%m-%d).log | tail -n 20

# Check stuck tasks
mysql -u user -p -e "SELECT * FROM smart_cron_tasks_config WHERE is_running = 1" database_name
```

## Dashboard Access

URL: https://your-domain.com/modules/smart-cron/dashboard/

## Important Files

- Master Runner: `/modules/smart-cron/cron/master_runner.php`
- Health Monitor: `/modules/smart-cron/cron/health_monitor.php`
- Database Schema: `/modules/smart-cron/database/schema.sql`
- Dashboard: `/modules/smart-cron/dashboard/index.php`
- Logs: `/var/log/smart-cron/`
- Locks: `/var/run/smart-cron/`
- Backups: `/var/backups/smart-cron/`

## Emergency Procedures

### Stop All Tasks
```bash
# Remove cron jobs
crontab -u www-data -r

# Kill any running PHP processes
pkill -f "smart-cron"

# Clear running flags
mysql -u user -p -e "UPDATE smart_cron_tasks_config SET is_running = 0" database_name
```

### System Recovery
```bash
# 1. Restore from backup
gunzip < /var/backups/smart-cron/latest.sql.gz | mysql -u user -p database_name

# 2. Clear stale locks
rm -f /var/run/smart-cron/*.lock

# 3. Reset failed tasks
mysql -u user -p -e "UPDATE smart_cron_tasks_config SET consecutive_failures = 0, is_running = 0" database_name

# 4. Restart cron
systemctl restart cron
```

## Support

For issues or questions, contact: Pearce Stephens <pearce.stephens@ecigdis.co.nz>
EOF

echo -e "${GREEN}✓ Quick reference created: $INSTALL_DIR/QUICK_REFERENCE.md${NC}"
echo ""
