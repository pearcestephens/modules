#!/bin/bash
#
# Test Complete Payroll Run
#
# This script tests the full payroll automation:
# 1. CLI script (dry run)
# 2. Web API endpoint (dry run)
# 3. Shows you exactly what would happen
#

echo ""
echo "╔══════════════════════════════════════════════════╗"
echo "║        TEST COMPLETE PAYROLL AUTOMATION          ║"
echo "╚══════════════════════════════════════════════════╝"
echo ""

BASE_PATH="/home/master/applications/jcepnzzkmj/public_html"
PAYROLL_PATH="$BASE_PATH/modules/human_resources/payroll"

# Test 1: CLI Script (dry run)
echo "🧪 TEST 1: CLI Script (dry run)"
echo "════════════════════════════════════════════════════"
echo ""

php "$PAYROLL_PATH/cli/run-full-payroll.php" --dry-run

echo ""
echo "────────────────────────────────────────────────────"
echo ""

# Test 2: Web API endpoint (dry run)
echo "🧪 TEST 2: Web API Endpoint (dry run)"
echo "════════════════════════════════════════════════════"
echo ""

curl -X POST \
  "https://staff.vapeshed.co.nz/modules/human_resources/payroll/?api=run_complete_cycle" \
  -H "Content-Type: application/json" \
  -d '{"dry_run": true}' \
  2>/dev/null | python3 -m json.tool

echo ""
echo "────────────────────────────────────────────────────"
echo ""

# Summary
echo "✅ TESTS COMPLETE!"
echo ""
echo "To run for REAL (apply payments):"
echo ""
echo "  CLI:  php $PAYROLL_PATH/cli/run-full-payroll.php"
echo ""
echo "  API:  curl -X POST 'https://staff.vapeshed.co.nz/modules/human_resources/payroll/?api=run_complete_cycle' \\"
echo "             -H 'Content-Type: application/json' \\"
echo "             -d '{\"dry_run\": false}'"
echo ""
