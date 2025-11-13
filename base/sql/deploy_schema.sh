#!/bin/bash

# ============================================================================
# Notification & Messenger Schema Deployment Script
# ============================================================================
# Usage: ./deploy_schema.sh -u USERNAME -p PASSWORD -d DATABASE -h HOSTNAME
# Example: ./deploy_schema.sh -u root -p mypassword -d jcepnzzkmj -h localhost
# ============================================================================

set -e

# Default values
DB_USER=""
DB_PASS=""
DB_NAME=""
DB_HOST="localhost"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

# Function to show usage
show_usage() {
    cat << EOF
Usage: $0 -u USERNAME -p PASSWORD -d DATABASE [-h HOSTNAME]

Options:
  -u USERNAME    MySQL username (required)
  -p PASSWORD    MySQL password (required)
  -d DATABASE    Database name (required)
  -h HOSTNAME    MySQL hostname (default: localhost)
  --help         Show this help message

Example:
  $0 -u root -p mypassword -d jcepnzzkmj -h localhost

EOF
    exit 1
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -u) DB_USER="$2"; shift 2 ;;
        -p) DB_PASS="$2"; shift 2 ;;
        -d) DB_NAME="$2"; shift 2 ;;
        -h) DB_HOST="$2"; shift 2 ;;
        --help) show_usage ;;
        *) print_error "Unknown option: $1"; show_usage ;;
    esac
done

# Validate arguments
if [[ -z "$DB_USER" || -z "$DB_PASS" || -z "$DB_NAME" ]]; then
    print_error "Missing required arguments"
    show_usage
fi

# Check if schema file exists
if [[ ! -f "$SCRIPT_DIR/notification_messenger_schema.sql" ]]; then
    print_error "Schema file not found: $SCRIPT_DIR/notification_messenger_schema.sql"
    exit 1
fi

echo ""
echo "=========================================="
echo "Notification & Messenger Schema Deploy"
echo "=========================================="
echo ""
echo "Configuration:"
echo "  Host: $DB_HOST"
echo "  Database: $DB_NAME"
echo "  User: $DB_USER"
echo ""

# Test connection
print_status "Testing database connection..."
if ! mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "SELECT 1;" > /dev/null 2>&1; then
    print_error "Cannot connect to database"
    print_error "Please check your credentials and try again"
    exit 1
fi
print_status "Connection successful"

# Deploy schema
echo ""
print_status "Deploying schema..."
if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SCRIPT_DIR/notification_messenger_schema.sql"; then
    print_status "Schema deployment completed"
else
    print_error "Schema deployment failed"
    exit 1
fi

# Verify tables
echo ""
print_status "Verifying tables created..."
TABLES=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -sNe \
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_name LIKE 'chat_%' OR table_name LIKE 'notification%'")

if [ "$TABLES" -ge 8 ]; then
    print_status "All tables created successfully ($TABLES tables found)"
else
    print_warning "Expected 10 tables but found $TABLES"
fi

# List tables
echo ""
print_status "Created tables:"
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e \
    "SELECT table_name FROM information_schema.tables WHERE table_schema='$DB_NAME' AND (table_name LIKE 'chat_%' OR table_name LIKE 'notification%') ORDER BY table_name;" | \
    sed 's/^/    /'

# Show next steps
echo ""
echo "=========================================="
echo "Deployment Complete! ✓"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Verify tables in your database application"
echo "2. Test API endpoints (see BACKEND_QUICK_REFERENCE.md)"
echo "3. Connect frontend to API endpoints"
echo "4. Once cis_users exists, run foreign keys script:"
echo "   mysql -u $DB_USER -p'$DB_PASS' $DB_NAME < notification_messenger_foreign_keys.sql"
echo ""
echo "Documentation:"
echo "  - DEPLOYMENT_GUIDE.md - Full setup instructions"
echo "  - BACKEND_QUICK_REFERENCE.md - API reference"
echo "  - NotificationEngine.php - Core logic"
echo ""
