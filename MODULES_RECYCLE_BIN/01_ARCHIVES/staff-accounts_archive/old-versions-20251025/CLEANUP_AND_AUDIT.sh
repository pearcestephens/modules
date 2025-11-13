#!/bin/bash
# Staff Accounts Module - Complete Cleanup and Audit
# Date: 2025-10-26

cd "$(dirname "$0")"

echo "========================================"
echo "STAFF ACCOUNTS MODULE CLEANUP & AUDIT"
echo "========================================"
echo ""

# Create directories
mkdir -p archived/_test_files
mkdir -p archived/_docs
mkdir -p _kb/docs
mkdir -p _kb/schema

echo "✅ Created archive and KB directories"
echo ""

# Move test/debug files to archive
echo "📦 Archiving test and debug files..."
for file in \
    api-endpoint-validator.php \
    api-validation-report-*.json \
    check-*.php \
    cleanup-active-staff-only.php \
    database-integrity-test.sh \
    deploy-payment-system.* \
    extract_schema.sql \
    final-mapping-summary.php \
    fix-*.php \
    manual-mapping-tool.php \
    map-existing-deductions.php \
    schema-report.json \
    test_api.php \
    testing-bot-bypass.php \
    vend-customer-mapping.php \
    verify-schema.php
do
    if [ -f "$file" ]; then
        mv "$file" archived/_test_files/
        echo "  → $file"
    fi
done

# Move documentation to KB
echo ""
echo "📚 Moving documentation to _kb/docs..."
for file in *.md COMPLETE_SCHEMA_EXPORT.sql; do
    if [ -f "$file" ]; then
        mv "$file" _kb/docs/
        echo "  → $file"
    fi
done

# Archive backup PHP files in views/
echo ""
echo "🗂️  Archiving backup files in views/..."
if [ -d "views" ]; then
    for file in views/*backup*.php views/*REFACTORED*.php views/*old*.php; do
        if [ -f "$file" ]; then
            mv "$file" archived/_test_files/
            echo "  → $(basename $file)"
        fi
    done
fi

echo ""
echo "========================================"
echo "CLEANUP COMPLETE!"
echo "========================================"
echo ""
echo "📊 CURRENT STRUCTURE:"
echo ""
ls -la | grep -E "^d" | awk '{print "  📁 " $9}'
echo ""
echo "🎯 PRODUCTION FILES:"
ls -1 *.php 2>/dev/null | while read f; do
    echo "  ✅ $f"
done
echo ""
echo "📦 ARCHIVED FILES: $(find archived/_test_files -type f | wc -l) files"
echo "📚 KB DOCS: $(find _kb/docs -type f | wc -l) files"
echo ""
