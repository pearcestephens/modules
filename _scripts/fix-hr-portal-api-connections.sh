#!/bin/bash
# Fix all HR Portal API files to add PDO cleanup in finally blocks

echo "🔧 Fixing HR Portal API files for connection cleanup..."

FILES=(
    "toggle-autopilot.php"
    "batch-approve.php"
    "dashboard-stats.php"
    "approve-item.php"
)

for file in "${FILES[@]}"; do
    filepath="/home/master/applications/jcepnzzkmj/public_html/modules/hr-portal/api/$file"

    if [ -f "$filepath" ]; then
        echo "  ✓ Processing $file..."

        # Create backup
        cp "$filepath" "$filepath.backup"

        # Add $pdo = null; at the start of try block if not present
        # Add finally block with cleanup
        sed -i 's/try {/\$pdo = null;\ntry {/' "$filepath"
        sed -i 's/} catch (Exception \$e) {/} catch (Exception \$e) {\n    \/\/ Error handling/' "$filepath"

        # Check if finally block exists
        if ! grep -q "finally {" "$filepath"; then
            # Add finally block before the last closing brace
            sed -i '$ d' "$filepath"  # Remove last }
            cat >> "$filepath" << 'EOF'
} finally {
    // ✅ CRITICAL FIX: Always cleanup PDO connection to prevent connection leaks
    $pdo = null;
}

EOF
        fi

        echo "    ✓ Fixed $file"
    else
        echo "    ⚠️  File not found: $file"
    fi
done

echo ""
echo "✅ HR Portal API files fixed!"
echo ""
echo "Backups created with .backup extension"
