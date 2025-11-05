#!/bin/bash
# Test Consignments API with BASE Envelope Pattern
# Run: bash test-api-envelope.sh

API_URL="http://localhost/modules/consignments/api.php"

echo "🧪 Testing Consignments API - BASE Envelope Pattern"
echo "=================================================="
echo ""

# Test 1: Get Recent (Success)
echo "✅ Test 1: Get Recent Consignments"
curl -s -X POST "$API_URL" \
  -H "Content-Type: application/json" \
  -d '{"action":"get_recent","data":{"limit":5}}' | jq '.'
echo ""

# Test 2: Get Consignment (Missing ID - Error)
echo "❌ Test 2: Get Consignment (Missing ID - Should Error)"
curl -s -X POST "$API_URL" \
  -H "Content-Type: application/json" \
  -d '{"action":"get_consignment","data":{}}' | jq '.'
echo ""

# Test 3: Search Consignments
echo "🔍 Test 3: Search Consignments"
curl -s -X POST "$API_URL" \
  -H "Content-Type: application/json" \
  -d '{"action":"search_consignments","data":{"ref_code":"CONS","limit":10}}' | jq '.'
echo ""

# Test 4: Get Stats
echo "📊 Test 4: Get Statistics"
curl -s -X POST "$API_URL" \
  -H "Content-Type: application/json" \
  -d '{"action":"get_stats","data":{}}' | jq '.'
echo ""

# Test 5: Invalid Action (Error)
echo "❌ Test 5: Invalid Action (Should Error)"
curl -s -X POST "$API_URL" \
  -H "Content-Type: application/json" \
  -d '{"action":"invalid_action","data":{}}' | jq '.'
echo ""

# Test 6: Verify Envelope Structure
echo "🔍 Test 6: Verify Response Envelope Structure"
RESPONSE=$(curl -s -X POST "$API_URL" \
  -H "Content-Type: application/json" \
  -d '{"action":"get_recent","data":{"limit":1}}')

echo "Response has 'success' field: $(echo $RESPONSE | jq 'has("success")')"
echo "Response has 'message' field: $(echo $RESPONSE | jq 'has("message")')"
echo "Response has 'timestamp' field: $(echo $RESPONSE | jq 'has("timestamp")')"
echo "Response has 'request_id' field: $(echo $RESPONSE | jq 'has("request_id")')"
echo "Response has 'data' field: $(echo $RESPONSE | jq 'has("data")')"
echo "Response has 'meta' field: $(echo $RESPONSE | jq 'has("meta")')"
echo "Meta has 'duration_ms': $(echo $RESPONSE | jq '.meta | has("duration_ms")')"
echo "Meta has 'memory_usage': $(echo $RESPONSE | jq '.meta | has("memory_usage")')"
echo ""

echo "✅ Tests Complete!"
echo ""
echo "📋 What to Check:"
echo "  1. All responses have 'success' field (true/false)"
echo "  2. Success responses have 'data' and 'meta'"
echo "  3. Error responses have 'error' object with 'code' and 'message'"
echo "  4. All responses have unique 'request_id'"
echo "  5. Meta includes 'duration_ms' and 'memory_usage'"
