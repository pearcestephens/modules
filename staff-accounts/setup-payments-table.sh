#!/bin/bash
# Setup script for staff account payments table
# Run: bash setup-payments-table.sh

echo "================================================"
echo "Staff Account Payments Table Setup"
echo "================================================"
echo ""

# Create the table
echo "1. Creating staff_account_payments table..."
mysql < schema/staff-account-payments.sql
if [ $? -eq 0 ]; then
    echo "   ✓ Table created successfully"
else
    echo "   ✗ Error creating table"
    exit 1
fi

echo ""

# Run initial sync
echo "2. Running initial payment sync..."
php lib/sync-payments.php
if [ $? -eq 0 ]; then
    echo "   ✓ Sync completed successfully"
else
    echo "   ✗ Error during sync"
    exit 1
fi

echo ""
echo "================================================"
echo "Setup complete!"
echo "================================================"
echo ""
echo "Next steps:"
echo "  - Check index.php to verify data is loading"
echo "  - Add to cron: 0 * * * * php $(pwd)/lib/sync-payments.php"
echo ""
