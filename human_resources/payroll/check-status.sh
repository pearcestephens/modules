#!/bin/bash
# Check what actually changed in payroll module
set -e

echo "🔍 CHECKING PAYROLL MODULE STATUS"
echo "=================================="
echo ""

echo "📁 Current directory:"
pwd
echo ""

echo "📋 Git status:"
git status
echo ""

echo "📊 Files in this directory:"
ls -la controllers/BaseController.php 2>/dev/null || echo "❌ BaseController.php not found"
ls -la index.php 2>/dev/null || echo "❌ index.php not found"
echo ""

echo "🔄 Recent commits:"
git log --oneline -5
echo ""

echo "📝 What was in the last commit:"
git show --stat HEAD
echo ""

echo "💡 DIAGNOSIS:"
echo "============="
if [ -f "controllers/BaseController.php" ]; then
    echo "✅ BaseController.php exists"
    echo "   Last modified: $(stat -c %y controllers/BaseController.php)"
else
    echo "❌ BaseController.php NOT FOUND"
fi

if [ -f "index.php" ]; then
    echo "✅ index.php exists"
    echo "   Last modified: $(stat -c %y index.php)"
else
    echo "❌ index.php NOT FOUND"
fi

if [ -d "tests/Unit" ]; then
    echo "✅ tests/Unit directory exists"
    echo "   Files: $(ls tests/Unit/*.php 2>/dev/null | wc -l)"
else
    echo "❌ tests/Unit directory NOT FOUND"
fi

echo ""
echo "🎯 RECOMMENDATION:"
if git diff --quiet && git diff --cached --quiet; then
    echo "No uncommitted changes detected."
    echo "Either:"
    echo "1. Changes were already committed"
    echo "2. Files weren't actually modified"
    echo "3. We're in the wrong directory"
else
    echo "There ARE uncommitted changes!"
    echo "Run: git diff --stat"
    echo "Then: git add <files> && git commit"
fi
