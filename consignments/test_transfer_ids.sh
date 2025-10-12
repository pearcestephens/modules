#!/bin/bash
# Comprehensive Transfer ID Testing Script
# Tests multiple transfer IDs until we find working ones

echo "🧪 Starting Comprehensive Transfer ID Testing"
echo "=============================================="
echo "Testing Date: $(date)"
echo ""

# Base URL with bot bypass
BASE_URL="https://staff.vapeshed.co.nz/modules/consignments/api"

# Test transfer IDs - starting with known ones then random
TEST_IDS=(
    13219   # From documentation
    13218   # User mentioned issues
    13217   # Try nearby
    13220   # Try nearby
    13221   # Try nearby
    13200   # Try round number
    13100   # Try round number
    13000   # Try round number
    12000   # Try different thousands
    11000   # Try different thousands
    10000   # Try different thousands
    5000    # Try mid-range
    1000    # Try smaller
    500     # Try smaller
    100     # Try small
    50      # Try very small
    10      # Try tiny
    1       # Try first
)

# Counter for results
TOTAL_TESTS=0
SUCCESS_COUNT=0
FOUND_WORKING_IDS=()

echo "🔍 Phase 1: Testing Predefined Transfer IDs"
echo "-------------------------------------------"

for TRANSFER_ID in "${TEST_IDS[@]}"; do
    echo -n "Testing Transfer ID $TRANSFER_ID... "
    
    # Test receive_autosave endpoint
    RESPONSE=$(curl -s -w "%{http_code}" -X POST \
        "$BASE_URL/receive_autosave.php?bot=true" \
        -H "Content-Type: application/json" \
        -H "X-Requested-With: XMLHttpRequest" \
        -d '{
            "transfer_id": '$TRANSFER_ID',
            "transfer_mode": "GENERAL",
            "items": [
                {
                    "item_id": 1,
                    "product_id": 1,
                    "qty_requested": 1,
                    "qty_received": 1,
                    "weight_grams": 100
                }
            ],
            "totals": {
                "total_requested": 1,
                "total_received": 1,
                "weight_grams": 100
            },
            "receiver_name": "Test Bot",
            "delivery_notes": "Automated test"
        }' 2>/dev/null)
    
    HTTP_CODE="${RESPONSE: -3}"
    RESPONSE_BODY="${RESPONSE%???}"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    if [ "$HTTP_CODE" = "200" ]; then
        # Check if it's a success response
        if echo "$RESPONSE_BODY" | grep -q '"success":true'; then
            echo "✅ SUCCESS (HTTP $HTTP_CODE)"
            SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
            FOUND_WORKING_IDS+=($TRANSFER_ID)
            echo "   Response: $(echo "$RESPONSE_BODY" | head -c 100)..."
        elif echo "$RESPONSE_BODY" | grep -q '"Transfer not found"'; then
            echo "❌ Transfer not found (HTTP $HTTP_CODE)"
        else
            echo "⚠️  HTTP 200 but error response"
            echo "   Error: $(echo "$RESPONSE_BODY" | head -c 100)..."
        fi
    elif [ "$HTTP_CODE" = "404" ]; then
        echo "❌ Not found (HTTP $HTTP_CODE)"
    elif [ "$HTTP_CODE" = "422" ]; then
        echo "⚠️  Validation error (HTTP $HTTP_CODE)"
        echo "   Error: $(echo "$RESPONSE_BODY" | head -c 100)..."
    else
        echo "❌ Failed (HTTP $HTTP_CODE)"
        if [ ${#RESPONSE_BODY} -gt 0 ]; then
            echo "   Response: $(echo "$RESPONSE_BODY" | head -c 100)..."
        fi
    fi
    
    # Small delay between requests
    sleep 0.2
done

echo ""
echo "🎲 Phase 2: Testing Random Transfer IDs"
echo "---------------------------------------"

# Generate and test random IDs if we haven't found enough working ones
if [ ${#FOUND_WORKING_IDS[@]} -lt 3 ]; then
    echo "Need more working IDs, testing random numbers..."
    
    for i in {1..20}; do
        # Generate random ID between 1 and 20000
        RANDOM_ID=$((RANDOM % 20000 + 1))
        
        echo -n "Testing Random ID $RANDOM_ID... "
        
        RESPONSE=$(curl -s -w "%{http_code}" -X POST \
            "$BASE_URL/receive_autosave.php?bot=true" \
            -H "Content-Type: application/json" \
            -H "X-Requested-With: XMLHttpRequest" \
            -d '{
                "transfer_id": '$RANDOM_ID',
                "transfer_mode": "GENERAL",
                "items": [
                    {
                        "item_id": 1,
                        "product_id": 1,
                        "qty_requested": 1,
                        "qty_received": 1,
                        "weight_grams": 100
                    }
                ],
                "totals": {
                    "total_requested": 1,
                    "total_received": 1,
                    "weight_grams": 100
                },
                "receiver_name": "Random Test Bot",
                "delivery_notes": "Random test delivery"
            }' 2>/dev/null)
        
        HTTP_CODE="${RESPONSE: -3}"
        RESPONSE_BODY="${RESPONSE%???}"
        
        TOTAL_TESTS=$((TOTAL_TESTS + 1))
        
        if [ "$HTTP_CODE" = "200" ] && echo "$RESPONSE_BODY" | grep -q '"success":true'; then
            echo "✅ SUCCESS (HTTP $HTTP_CODE)"
            SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
            FOUND_WORKING_IDS+=($RANDOM_ID)
            echo "   🎉 FOUND WORKING RANDOM ID: $RANDOM_ID"
        elif echo "$RESPONSE_BODY" | grep -q '"Transfer not found"'; then
            echo "❌ Not found"
        else
            echo "❌ Failed ($HTTP_CODE)"
        fi
        
        # Stop if we found enough working IDs
        if [ ${#FOUND_WORKING_IDS[@]} -ge 5 ]; then
            echo "Found enough working IDs, stopping random tests..."
            break
        fi
        
        sleep 0.1
    done
fi

echo ""
echo "📊 TESTING SUMMARY"
echo "=================="
echo "Total Tests Run: $TOTAL_TESTS"
echo "Successful Tests: $SUCCESS_COUNT"
echo "Success Rate: $(echo "scale=2; $SUCCESS_COUNT * 100 / $TOTAL_TESTS" | bc -l)%"
echo ""

if [ ${#FOUND_WORKING_IDS[@]} -gt 0 ]; then
    echo "🎯 WORKING TRANSFER IDs FOUND:"
    echo "=============================="
    for ID in "${FOUND_WORKING_IDS[@]}"; do
        echo "✅ Transfer ID: $ID"
    done
    echo ""
    
    echo "🚀 COMPREHENSIVE ENDPOINT TESTING"
    echo "================================="
    echo "Now testing all endpoints with the first working ID: ${FOUND_WORKING_IDS[0]}"
    echo ""
    
    WORKING_ID=${FOUND_WORKING_IDS[0]}
    
    # Test all endpoints with the working ID
    ENDPOINTS=(
        "receive_autosave.php"
        "receive_submit.php"
        "pack_autosave.php"
        "pack_submit.php"
        "add_line.php"
        "remove_line.php"
        "update_line_qty.php"
        "pack_lock.php"
        "search_products.php"
    )
    
    for ENDPOINT in "${ENDPOINTS[@]}"; do
        echo -n "Testing $ENDPOINT with ID $WORKING_ID... "
        
        # Build appropriate payload for each endpoint
        case $ENDPOINT in
            "receive_autosave.php"|"receive_submit.php")
                PAYLOAD='{
                    "transfer_id": '$WORKING_ID',
                    "transfer_mode": "GENERAL",
                    "items": [
                        {
                            "item_id": 1,
                            "product_id": 1,
                            "qty_requested": 1,
                            "qty_received": 1,
                            "weight_grams": 100
                        }
                    ],
                    "totals": {
                        "total_requested": 1,
                        "total_received": 1,
                        "weight_grams": 100
                    },
                    "receiver_name": "Test Bot"
                }'
                ;;
            "pack_autosave.php"|"pack_submit.php")
                PAYLOAD='{
                    "transfer_id": '$WORKING_ID',
                    "transfer_mode": "GENERAL",
                    "items": [
                        {
                            "item_id": 1,
                            "product_id": 1,
                            "qty_requested": 1,
                            "qty_packed": 1
                        }
                    ]
                }'
                ;;
            "add_line.php")
                PAYLOAD='{
                    "transfer_id": '$WORKING_ID',
                    "product_id": 1,
                    "qty": 1
                }'
                ;;
            "remove_line.php")
                PAYLOAD='{
                    "transfer_id": '$WORKING_ID',
                    "item_id": 1
                }'
                ;;
            "update_line_qty.php")
                PAYLOAD='{
                    "transfer_id": '$WORKING_ID',
                    "item_id": 1,
                    "qty": 2
                }'
                ;;
            "pack_lock.php")
                PAYLOAD='{
                    "transfer_id": '$WORKING_ID',
                    "action": "lock"
                }'
                ;;
            "search_products.php")
                PAYLOAD='{
                    "query": "test",
                    "limit": 10
                }'
                ;;
            *)
                PAYLOAD='{
                    "transfer_id": '$WORKING_ID',
                    "test": true
                }'
                ;;
        esac
        
        # Check if endpoint file exists first
        if ! curl -s -I "$BASE_URL/$ENDPOINT?bot=true" | grep -q "200 OK"; then
            echo "❌ Endpoint not found"
            continue
        fi
        
        RESPONSE=$(curl -s -w "%{http_code}" -X POST \
            "$BASE_URL/$ENDPOINT?bot=true" \
            -H "Content-Type: application/json" \
            -H "X-Requested-With: XMLHttpRequest" \
            -d "$PAYLOAD" 2>/dev/null)
        
        HTTP_CODE="${RESPONSE: -3}"
        RESPONSE_BODY="${RESPONSE%???}"
        
        if [ "$HTTP_CODE" = "200" ]; then
            if echo "$RESPONSE_BODY" | grep -q '"success":true'; then
                echo "✅ SUCCESS"
            else
                echo "⚠️  HTTP 200 but error response"
            fi
        else
            echo "❌ Failed (HTTP $HTTP_CODE)"
        fi
        
        sleep 0.1
    done
    
else
    echo "❌ NO WORKING TRANSFER IDs FOUND"
    echo "Suggestions:"
    echo "1. Check if transfers table has any data"
    echo "2. Verify database connection"
    echo "3. Check if BOT_BYPASS_AUTH is working"
    echo "4. Review API endpoint file permissions"
fi

echo ""
echo "🏁 Testing completed at $(date)"
echo "=============================================="