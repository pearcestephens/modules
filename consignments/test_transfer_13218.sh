#!/bin/bash

# Quick test script for transfer 13218 with bot bypass
# Run this to test the specific transfer with issues

echo "🧪 Testing Transfer 13218 with BOT_BYPASS_AUTH"
echo "=============================================="

BASE_URL="https://staff.vapeshed.co.nz"

# Test 1: Receive Autosave for Transfer 13218
echo "📡 Testing receive_autosave for transfer 13218..."

TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

curl -X POST \
  "${BASE_URL}/modules/consignments/api/receive_autosave.php?bot=true" \
  -H "Content-Type: application/json" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "User-Agent: CIS-Test-Bot/1.0" \
  -d "{
    \"transfer_id\": 13218,
    \"transfer_mode\": \"GENERAL\",
    \"items\": [
      {
        \"item_id\": 1,
        \"product_id\": 1,
        \"qty_requested\": 10,
        \"qty_received\": 8,
        \"weight_grams\": 100
      }
    ],
    \"totals\": {
      \"total_requested\": 10,
      \"total_received\": 8,
      \"weight_grams\": 800
    },
    \"receiver_name\": \"Test Bot\",
    \"delivery_notes\": \"Automated test via bot bypass\",
    \"timestamp\": \"${TIMESTAMP}\"
  }" \
  --connect-timeout 30 \
  --max-time 60 \
  --silent \
  --show-error \
  --write-out "\n\nHTTP Status: %{http_code}\nTime: %{time_total}s\n"

echo -e "\n\n"

# Test 2: Check if transfer exists first
echo "🔍 Checking if transfer 13218 exists..."

curl -X POST \
  "${BASE_URL}/modules/consignments/api/search_products.php?bot=true" \
  -H "Content-Type: application/json" \
  -H "X-Requested-With: XMLHttpRequest" \
  -d "{
    \"query\": \"test\",
    \"limit\": 1,
    \"transfer_id\": 13218
  }" \
  --connect-timeout 10 \
  --max-time 20 \
  --silent \
  --show-error \
  --write-out "\nHTTP Status: %{http_code}\n"

echo -e "\n\n"

# Test 3: Try a different transfer mode
echo "📡 Testing receive_autosave for transfer 13218 with JUICE mode..."

curl -X POST \
  "${BASE_URL}/modules/consignments/api/receive_autosave.php?bot=true" \
  -H "Content-Type: application/json" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "User-Agent: CIS-Test-Bot/1.0" \
  -d "{
    \"transfer_id\": 13218,
    \"transfer_mode\": \"JUICE\",
    \"items\": [
      {
        \"item_id\": 1,
        \"product_id\": 1,
        \"qty_requested\": 5,
        \"qty_received\": 5,
        \"weight_grams\": 50
      }
    ],
    \"totals\": {
      \"total_requested\": 5,
      \"total_received\": 5,
      \"weight_grams\": 250
    },
    \"receiver_name\": \"Test Bot JUICE\",
    \"delivery_notes\": \"Automated JUICE test via bot bypass\"
  }" \
  --connect-timeout 30 \
  --max-time 60 \
  --silent \
  --show-error \
  --write-out "\n\nHTTP Status: %{http_code}\nTime: %{time_total}s\n"

echo -e "\n\n"

# Test 4: Try another transfer ID for comparison
echo "📡 Testing receive_autosave for transfer 13219 (comparison)..."

curl -X POST \
  "${BASE_URL}/modules/consignments/api/receive_autosave.php?bot=true" \
  -H "Content-Type: application/json" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "User-Agent: CIS-Test-Bot/1.0" \
  -d "{
    \"transfer_id\": 13219,
    \"transfer_mode\": \"GENERAL\", 
    \"items\": [
      {
        \"item_id\": 1,
        \"product_id\": 1,
        \"qty_requested\": 10,
        \"qty_received\": 8,
        \"weight_grams\": 100
      }
    ],
    \"totals\": {
      \"total_requested\": 10,
      \"total_received\": 8,
      \"weight_grams\": 800
    },
    \"receiver_name\": \"Test Bot\",
    \"delivery_notes\": \"Comparison test for 13219\"
  }" \
  --connect-timeout 30 \
  --max-time 60 \
  --silent \
  --show-error \
  --write-out "\n\nHTTP Status: %{http_code}\nTime: %{time_total}s\n"

echo -e "\n\n"
echo "✅ Testing completed!"
echo "Expected: HTTP 200 with success:true responses"
echo "If you see 404/500 errors, the transfer may not exist or have issues"