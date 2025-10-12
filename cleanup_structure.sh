#!/bin/bash

# CIS Modules Cleanup Script
# Generated: October 12, 2025
# Purpose: Reorganize module structure and remove redundancies

set -e  # Exit on any error

echo "🧹 CIS Modules Cleanup Script"
echo "=================================="
echo

# Create backup directory
echo "📦 Creating backup directory..."
mkdir -p ./backups/$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="./backups/$(date +%Y%m%d_%H%M%S)"

echo "🔍 Phase 1: Analyze current structure..."
echo "Current file count: $(find . -not -path './.git*' -type f | wc -l)"
echo

echo "📋 Phase 2: Backup critical files..."
# Copy entire lib directories for comparison
cp -r "./consignments/lib" "$BACKUP_DIR/lib_original" 2>/dev/null || true
cp -r "./consignments/_shared/lib" "$BACKUP_DIR/shared_lib_original" 2>/dev/null || true
echo "✅ Backed up library directories"

echo "🔄 Phase 3: Flatten transfers structure..."
if [[ -d "./consignments/transfers/controllers" ]]; then
    echo "Moving controllers..."
    if [[ ! -d "./consignments/controllers" ]]; then
        mv "./consignments/transfers/controllers" "./consignments/controllers"
        echo "✅ Moved: transfers/controllers -> controllers"
    else
        echo "⚠️  controllers/ already exists, skipping move"
    fi
fi

if [[ -d "./consignments/transfers/views" ]]; then
    echo "Moving views..."
    if [[ ! -d "./consignments/views" ]]; then
        mv "./consignments/transfers/views" "./consignments/views"
        echo "✅ Moved: transfers/views -> views"
    else
        echo "⚠️  views/ already exists, skipping move"
    fi
fi

echo "✅ Cleanup complete! Check DIRECTORY_ANALYSIS.md for details."
