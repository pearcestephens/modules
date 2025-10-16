#!/bin/bash
# Quick API Test Script
# Tests StandardResponse implementation on live server

echo "========================================="
echo "🧪 API STANDARDIZATION - LIVE TEST"
echo "========================================="
echo ""

BASE_URL="https://staff.vapeshed.co.nz/modules/consignments/api"

echo "1. Testing Central Router - Missing Action (should return 400)"
echo "-----------------------------------------------------------"
curl -s -X POST "$BASE_URL/api.php" \
  -H "Content-Type: application/json" \
  -d '{}' | jq '.'
echo ""

echo "2. Testing Central Router - Unknown Action (should return 404)"
echo "-----------------------------------------------------------"
curl -s -X POST "$BASE_URL/api.php" \
  -H "Content-Type: application/json" \
  -d '{"action":"invalid_test_action"}' | jq '.'
echo ""

echo "3. Testing Submit Transfer - Invalid Transfer ID (should return 400)"
echo "-----------------------------------------------------------"
curl -s -X POST "$BASE_URL/api.php" \
  -H "Content-Type: application/json" \
  -d '{"action":"submit_transfer","transfer_id":0,"items":[]}' | jq '.'
echo ""

echo "========================================="
echo "✅ VERIFICATION CHECKLIST:"
echo "========================================="
echo "[ ] All responses have 'success' field (true/false)"
echo "[ ] All responses have 'data' field"
echo "[ ] All responses have 'error' field (null on success)"
echo "[ ] All responses have 'meta' field with:"
echo "    [ ] timestamp (ISO 8601 format)"
echo "    [ ] request_id (format: req_*)"
echo "    [ ] version (1.0)"
echo "[ ] Error responses have:"
echo "    [ ] error.message"
echo "    [ ] error.code (MISSING_ACTION, UNKNOWN_ACTION, etc.)"
echo "    [ ] error.http_code"
echo "[ ] HTTP status codes correct (400, 404, etc.)"
echo ""
echo "========================================="
echo "📋 NEXT STEPS:"
echo "========================================="
echo "1. Verify responses match API contract format"
echo "2. Test pack.js submit button on pack-REFACTORED.php"
echo "3. Check browser console for any errors"
echo "4. Monitor logs: tail -f logs/apache_phpstack-*.error.log"
echo ""
