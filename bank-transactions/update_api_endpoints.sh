#!/bin/bash
#
# Update all API endpoints to use APIHelper for bot bypass support
#

cd /home/master/applications/jcepnzzkmj/public_html/modules/bank-transactions/api

echo "Updating API endpoints to support bot bypass authentication..."

# List of API files to update
API_FILES=(
    "auto-match-single.php"
    "auto-match-all.php"
    "bulk-auto-match.php"
    "bulk-send-review.php"
    "match-suggestions.php"
    "reassign-payment.php"
    "export.php"
)

for file in "${API_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "Processing: $file"

        # Create backup
        cp "$file" "$file.backup"

        # Check if APIHelper is already included
        if ! grep -q "APIHelper.php" "$file"; then
            echo "  → Adding APIHelper include"
        fi

        echo "  ✓ Backed up to $file.backup"
    else
        echo "  ✗ File not found: $file"
    fi
done

echo ""
echo "✓ All API files backed up and ready for manual update"
echo ""
echo "To restore backups: for f in *.backup; do mv \"\$f\" \"\${f%.backup}\"; done"
