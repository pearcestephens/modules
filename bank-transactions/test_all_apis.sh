#!/bin/bash

BASE_URL="https://staff.vapeshed.co.nz/modules/bank-transactions/api"
BOT="?bot=1"

echo "=========================================="
echo "API ENDPOINT TESTING - November 11, 2025"
echo "=========================================="
echo ""

# Test 1: Dashboard Metrics
echo "1. Testing dashboard-metrics.php (GET)"
echo "   URL: ${BASE_URL}/dashboard-metrics.php${BOT}&date=2025-11-11"
curl -s -w "\n   HTTP Status: %{http_code}\n" "${BASE_URL}/dashboard-metrics.php${BOT}&date=2025-11-11" | head -20
echo ""

# Test 2: Match Suggestions
echo "2. Testing match-suggestions.php (GET)"
echo "   URL: ${BASE_URL}/match-suggestions.php${BOT}&transaction_id=1"
curl -s -w "\n   HTTP Status: %{http_code}\n" "${BASE_URL}/match-suggestions.php${BOT}&transaction_id=1" | head -20
echo ""

# Test 3: Export
echo "3. Testing export.php (GET)"
echo "   URL: ${BASE_URL}/export.php${BOT}&date_from=2025-11-01&date_to=2025-11-11"
curl -s -w "\n   HTTP Status: %{http_code}\n" "${BASE_URL}/export.php${BOT}&date_from=2025-11-01&date_to=2025-11-11" | head -20
echo ""

# Test 4: Settings (GET - read settings)
echo "4. Testing settings.php (GET)"
echo "   URL: ${BASE_URL}/settings.php${BOT}"
curl -s -w "\n   HTTP Status: %{http_code}\n" "${BASE_URL}/settings.php${BOT}" | head -20
echo ""

# Test 5: Auto-Match Single (POST)
echo "5. Testing auto-match-single.php (POST)"
echo "   URL: ${BASE_URL}/auto-match-single.php"
curl -s -w "\n   HTTP Status: %{http_code}\n" -X POST "${BASE_URL}/auto-match-single.php" -d "bot=1&transaction_id=1" | head -20
echo ""

# Test 6: Auto-Match All (POST)
echo "6. Testing auto-match-all.php (POST)"
echo "   URL: ${BASE_URL}/auto-match-all.php"
curl -s -w "\n   HTTP Status: %{http_code}\n" -X POST "${BASE_URL}/auto-match-all.php" -d "bot=1&date=2025-11-11" | head -20
echo ""

# Test 7: Bulk Auto-Match (POST)
echo "7. Testing bulk-auto-match.php (POST)"
echo "   URL: ${BASE_URL}/bulk-auto-match.php"
curl -s -w "\n   HTTP Status: %{http_code}\n" -X POST "${BASE_URL}/bulk-auto-match.php" -d "bot=1&transaction_ids[]=1&transaction_ids[]=2" | head -20
echo ""

# Test 8: Bulk Send Review (POST)
echo "8. Testing bulk-send-review.php (POST)"
echo "   URL: ${BASE_URL}/bulk-send-review.php"
curl -s -w "\n   HTTP Status: %{http_code}\n" -X POST "${BASE_URL}/bulk-send-review.php" -d "bot=1&transaction_ids[]=1" | head -20
echo ""

# Test 9: Reassign Payment (POST)
echo "9. Testing reassign-payment.php (POST)"
echo "   URL: ${BASE_URL}/reassign-payment.php"
curl -s -w "\n   HTTP Status: %{http_code}\n" -X POST "${BASE_URL}/reassign-payment.php" -d "bot=1&transaction_id=1&old_order_id=1&new_order_id=2&reason=testing" | head -20
echo ""

echo "=========================================="
echo "TEST COMPLETE"
echo "=========================================="
