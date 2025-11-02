#!/bin/bash
# Commit Objective 1 Changes
# Branch: payroll-hardening-20251101

set -e

echo "📦 Staging Objective 1 files..."
git add controllers/BaseController.php
git add tests/Unit/BaseControllerHelpersTest.php
git add PR_DESCRIPTION.md

echo "✍️  Creating commit..."
git commit -F COMMIT_MSG_OBJ1.txt

echo "🔍 Verifying commit..."
git log -1 --stat

echo "✅ Objective 1 committed successfully!"
echo ""
echo "Next steps:"
echo "  1. Run PHPUnit tests: composer test"
echo "  2. Move to Objective 2: Real validator wiring"
echo "  3. Continue hardening plan..."
