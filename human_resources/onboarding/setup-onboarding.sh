#!/usr/bin/env bash
set -euo pipefail

# Universal Employee Onboarding - Setup Script
# Usage: bash setup-onboarding.sh

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MODULE_DIR="$ROOT_DIR"
DB_NAME="jcepnzzkmj"
DB_USER="jcepnzzkmj"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

msg() { echo -e "${YELLOW}==>${NC} $*"; }
success() { echo -e "${GREEN}✔${NC} $*"; }
error() { echo -e "${RED}✖${NC} $*"; }

msg "Installing database schema..."
mysql -u "$DB_USER" -p"${MYSQL_PWD:-}" "$DB_NAME" < "$MODULE_DIR/database/schema.sql" || {
  error "Failed to import schema. If prompted, run manually: mysql -u $DB_USER -p $DB_NAME < database/schema.sql"; exit 1; }

success "Database schema installed"

msg "Seeding director account (pearce)..."
php "$MODULE_DIR/add-pearce.php" || { error "Failed to create director account"; }
success "Seeded director account (if not existing)"

msg "Ensuring .env Deputy defaults..."
ENV_FILE="$ROOT_DIR/../../.env"
if [ -f "$ENV_FILE" ]; then
  grep -q '^DEPUTY_ENDPOINT=' "$ENV_FILE" || echo 'DEPUTY_ENDPOINT=vapeshed.au.deputy.com' >> "$ENV_FILE"
  grep -q '^DEPUTY_TOKEN=' "$ENV_FILE" || echo 'DEPUTY_TOKEN=CHANGE_ME' >> "$ENV_FILE"
  success ".env updated"
else
  msg "No .env found at $ENV_FILE (skipping)"
fi

msg "Quick URL checks (no auth):"
BASE_URL="https://staff.vapeshed.co.nz/modules/employee-onboarding"
for path in \
  "/onboarding-wizard.php" \
  "/dashboard.php" \
  "/api/onboard.php" \
; do
  code=$(curl -s -o /dev/null -w "%{http_code}" -I "$BASE_URL$path" || true)
  echo "  $path -> HTTP $code"
 done

success "Setup complete. Next steps:"
echo "- Login and visit $BASE_URL/onboarding-wizard.php"
echo "- Configure Deputy token in .env (DEPUTY_TOKEN)"
echo "- Test onboarding via API: $BASE_URL/api/onboard.php"
