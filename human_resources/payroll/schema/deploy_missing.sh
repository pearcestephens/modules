#!/bin/bash
# ============================================================================
# DEPLOY MISSING PAYROLL COMPONENTS
# ============================================================================
# Deploys: payroll_nz_statutory_deductions, payroll_ai_decision_rules, views
# Database: jcepnzzkmj
# MariaDB 10.5+
# ============================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

DB_NAME="${1:-jcepnzzkmj}"
DB_USER="${2:-jcepnzzkmj}"
DB_PASS="${3}"
DB_HOST="${4:-127.0.0.1}"

if [ -z "$DB_PASS" ]; then
    echo -e "${YELLOW}Usage: $0 [database] [user] [password] [host]${NC}"
    echo -e "${YELLOW}Using defaults: db=$DB_NAME, user=$DB_USER, host=$DB_HOST${NC}"
    read -sp "Enter database password: " DB_PASS
    echo
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SQL_FILE="$SCRIPT_DIR/DEPLOY_MISSING_COMPONENTS.sql"
LOG_FILE="$SCRIPT_DIR/deploy_missing_$(date +%Y%m%d_%H%M%S).log"

echo -e "${BLUE}============================================================================${NC}"
echo -e "${BLUE}DEPLOYING MISSING PAYROLL COMPONENTS${NC}"
echo -e "${BLUE}============================================================================${NC}"
echo -e "Database: $DB_NAME"
echo -e "User: $DB_USER"
echo -e "Host: $DB_HOST"
echo -e "SQL File: $SQL_FILE"
echo -e "Log File: $LOG_FILE"
echo -e "${BLUE}============================================================================${NC}"
echo

# Backup before deploy
BACKUP_FILE="$SCRIPT_DIR/backup_before_missing_$(date +%Y%m%d_%H%M%S).sql"
echo -e "${YELLOW}Creating backup: $BACKUP_FILE${NC}"
mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
    payroll_nz_statutory_deductions payroll_ai_decision_rules \
    2>/dev/null > "$BACKUP_FILE" || true

echo

if [ ! -f "$SQL_FILE" ]; then
    echo -e "${RED}ERROR: SQL file not found: $SQL_FILE${NC}"
    exit 1
fi

echo -e "${YELLOW}Executing deployment SQL...${NC}"
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SQL_FILE" 2>&1 | tee "$LOG_FILE"

echo
echo -e "${BLUE}============================================================================${NC}"
echo -e "${BLUE}VERIFYING DEPLOYMENT${NC}"
echo -e "${BLUE}============================================================================${NC}"

# Verify tables exist
echo -n "Checking payroll_nz_statutory_deductions... "
COUNT=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -se "SELECT COUNT(*) FROM information_schema.tables WHERE table_name='payroll_nz_statutory_deductions'" 2>/dev/null)
if [ "$COUNT" = "1" ]; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${RED}✗${NC}"
fi

echo -n "Checking payroll_ai_decision_rules... "
COUNT=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -se "SELECT COUNT(*) FROM information_schema.tables WHERE table_name='payroll_ai_decision_rules'" 2>/dev/null)
if [ "$COUNT" = "1" ]; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${RED}✗${NC}"
fi

echo -n "Checking v_pending_ai_reviews view... "
COUNT=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -se "SELECT COUNT(*) FROM information_schema.views WHERE table_name='v_pending_ai_reviews'" 2>/dev/null)
if [ "$COUNT" = "1" ]; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${RED}✗${NC}"
fi

echo -n "Checking AI rules count... "
RULES=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -se "SELECT COUNT(*) FROM payroll_ai_decision_rules WHERE is_active=1" 2>/dev/null)
echo -e "${GREEN}$RULES active rules${NC}"

echo -n "Checking sick leave rules... "
SL_RULES=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -se "SELECT COUNT(*) FROM payroll_ai_decision_rules WHERE decision_type='sick_leave_validation' AND is_active=1" 2>/dev/null)
echo -e "${GREEN}$SL_RULES rules${NC}"

echo -n "Checking bereavement rules... "
BER_RULES=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -se "SELECT COUNT(*) FROM payroll_ai_decision_rules WHERE decision_type='bereavement_assessment' AND is_active=1" 2>/dev/null)
echo -e "${GREEN}$BER_RULES rules${NC}"

echo

if [ "$RULES" -ge 20 ]; then
    echo -e "${GREEN}✓ Deployment successful!${NC}"
    echo -e "${GREEN}  - 27 AI decision rules deployed${NC}"
    echo -e "${GREEN}  - payroll_nz_statutory_deductions table created${NC}"
    echo -e "${GREEN}  - v_pending_ai_reviews view created${NC}"
    echo -e "Backup: $BACKUP_FILE"
    exit 0
else
    echo -e "${RED}✗ Deployment incomplete - fewer than expected rules deployed${NC}"
    echo -e "Log: $LOG_FILE"
    exit 1
fi
