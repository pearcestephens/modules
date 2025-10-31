#!/bin/bash
# Sprint 1 Manual Verification Commands
# Run these commands to verify all fixes are working in production

echo "=== Sprint 1 Manual Verification Suite ==="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

BASE_URL="https://staff.vapeshed.co.nz/modules/consignments/api/purchase-orders"

echo -e "${YELLOW}[1/8] Test Accept AI Insight Endpoint${NC}"
echo "Creating test insight..."
INSIGHT_ID=$(mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -sN -e "
INSERT INTO consignment_ai_insights
(po_id, type, category, suggestion, confidence_score, status, created_at)
VALUES (999999, 'TEST_ACCEPT', 'test', 'Test suggestion', 0.95, 'PENDING', NOW());
SELECT LAST_INSERT_ID();
")

if [ -z "$INSIGHT_ID" ]; then
    echo -e "${RED}✗ Failed to create test insight${NC}"
    exit 1
fi

echo "Test insight created with ID: $INSIGHT_ID"
echo "Calling accept endpoint..."

RESPONSE=$(curl -s -X POST "$BASE_URL/accept-ai-insight.php" \
    -H "Content-Type: application/json" \
    -d "{
        \"insight_id\": $INSIGHT_ID,
        \"po_id\": 999999,
        \"feedback\": \"Test acceptance\"
    }")

echo "Response: $RESPONSE"

# Check if insight was updated
STATUS=$(mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -sN -e "
SELECT status FROM consignment_ai_insights WHERE id = $INSIGHT_ID;
")

if [ "$STATUS" == "ACCEPTED" ]; then
    echo -e "${GREEN}✓ Accept endpoint working correctly${NC}"
else
    echo -e "${RED}✗ Accept endpoint failed (status: $STATUS)${NC}"
fi

# Cleanup
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "
DELETE FROM consignment_ai_insights WHERE id = $INSIGHT_ID;
"
echo ""

echo -e "${YELLOW}[2/8] Test Dismiss AI Insight Endpoint${NC}"
# Create another test insight
INSIGHT_ID=$(mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -sN -e "
INSERT INTO consignment_ai_insights
(po_id, type, category, suggestion, confidence_score, status, created_at)
VALUES (999999, 'TEST_DISMISS', 'test', 'Test suggestion', 0.95, 'PENDING', NOW());
SELECT LAST_INSERT_ID();
")

echo "Test insight created with ID: $INSIGHT_ID"
echo "Calling dismiss endpoint..."

RESPONSE=$(curl -s -X POST "$BASE_URL/dismiss-ai-insight.php" \
    -H "Content-Type: application/json" \
    -d "{
        \"insight_id\": $INSIGHT_ID,
        \"po_id\": 999999,
        \"reason\": \"Test dismissal\"
    }")

echo "Response: $RESPONSE"

STATUS=$(mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -sN -e "
SELECT status FROM consignment_ai_insights WHERE id = $INSIGHT_ID;
")

if [ "$STATUS" == "DISMISSED" ]; then
    echo -e "${GREEN}✓ Dismiss endpoint working correctly${NC}"
else
    echo -e "${RED}✗ Dismiss endpoint failed (status: $STATUS)${NC}"
fi

mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "
DELETE FROM consignment_ai_insights WHERE id = $INSIGHT_ID;
"
echo ""

echo -e "${YELLOW}[3/8] Test Bulk Accept Endpoint${NC}"
# Create 3 test insights
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "
INSERT INTO consignment_ai_insights
(po_id, type, category, suggestion, confidence_score, status, created_at)
VALUES
(999999, 'BULK_TEST_1', 'test', 'Test 1', 0.95, 'PENDING', NOW()),
(999999, 'BULK_TEST_2', 'test', 'Test 2', 0.95, 'PENDING', NOW()),
(999999, 'BULK_TEST_3', 'test', 'Test 3', 0.95, 'PENDING', NOW());
"

INSIGHT_IDS=$(mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -sN -e "
SELECT GROUP_CONCAT(id) FROM consignment_ai_insights WHERE type LIKE 'BULK_TEST_%';
")

echo "Created insights: $INSIGHT_IDS"

RESPONSE=$(curl -s -X POST "$BASE_URL/bulk-accept-ai-insights.php" \
    -H "Content-Type: application/json" \
    -d "{
        \"insight_ids\": [${INSIGHT_IDS//,/, }],
        \"po_id\": 999999
    }")

echo "Response: $RESPONSE"

COUNT=$(mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -sN -e "
SELECT COUNT(*) FROM consignment_ai_insights
WHERE type LIKE 'BULK_TEST_%' AND status = 'ACCEPTED';
")

if [ "$COUNT" == "3" ]; then
    echo -e "${GREEN}✓ Bulk accept working correctly${NC}"
else
    echo -e "${RED}✗ Bulk accept failed (accepted: $COUNT/3)${NC}"
fi

mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "
DELETE FROM consignment_ai_insights WHERE type LIKE 'BULK_TEST_%';
"
echo ""

echo -e "${YELLOW}[4/8] Test Log Interaction Endpoint${NC}"
RESPONSE=$(curl -s -X POST "$BASE_URL/log-interaction.php" \
    -H "Content-Type: application/json" \
    -d '{
        "events": [
            {
                "type": "modal_opened",
                "modalId": "test-modal",
                "timestamp": '$(date +%s000)'
            },
            {
                "type": "button_clicked",
                "buttonId": "test-button",
                "timestamp": '$(date +%s000)'
            },
            {
                "type": "ai_recommendation_accepted",
                "insightId": 123,
                "poId": 456,
                "timestamp": '$(date +%s000)'
            }
        ]
    }')

echo "Response: $RESPONSE"

if [[ $RESPONSE == *"\"success\":true"* ]]; then
    echo -e "${GREEN}✓ Log interaction endpoint working${NC}"
else
    echo -e "${RED}✗ Log interaction endpoint failed${NC}"
fi
echo ""

echo -e "${YELLOW}[5/8] Verify CISLogger Integration${NC}"
echo "Checking recent action logs..."
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "
SELECT action, entity_type, entity_id, created_at
FROM cis_action_log
WHERE entity_type LIKE '%purchase_order%' OR entity_type LIKE '%ai%'
ORDER BY created_at DESC
LIMIT 5;
"

echo "Checking recent AI context logs..."
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "
SELECT context_type, confidence_score, created_at
FROM cis_ai_context
ORDER BY created_at DESC
LIMIT 5;
"
echo ""

echo -e "${YELLOW}[6/8] Verify Gamification Integration${NC}"
echo "Checking flagged_products tables exist..."
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "
SHOW TABLES LIKE 'flagged_products_%';
"

echo "Sample gamification data (if any)..."
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "
SELECT user_id, points_earned, reason, created_at
FROM flagged_products_points
ORDER BY created_at DESC
LIMIT 3;
" 2>/dev/null || echo "No gamification data yet (expected for fresh system)"
echo ""

echo -e "${YELLOW}[7/8] Verify TransferReviewService Schema${NC}"
echo "Checking consignment_metrics table..."
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "
DESCRIBE consignment_metrics;
"

echo "Sample metrics (if any)..."
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "
SELECT transfer_id, status, total_items, processing_time_ms, created_at
FROM consignment_metrics
ORDER BY created_at DESC
LIMIT 3;
" 2>/dev/null || echo "No metrics data yet"
echo ""

echo -e "${YELLOW}[8/8] Check for app.php Usage (Should be ZERO in consignments)${NC}"
grep -r "require.*app\.php" /home/master/applications/jcepnzzkmj/public_html/modules/consignments/ 2>/dev/null || echo -e "${GREEN}✓ No app.php usage found in consignments module${NC}"
echo ""

echo "=== Verification Complete ==="
echo ""
echo "Next steps:"
echo "1. Review any failed tests above"
echo "2. Check application logs for errors: tail -f /home/master/applications/jcepnzzkmj/public_html/logs/php_errors.log"
echo "3. Monitor CISLogger tables for new entries"
echo "4. Run full PHP test suite: php tests/test-sprint1-endpoints.php"
