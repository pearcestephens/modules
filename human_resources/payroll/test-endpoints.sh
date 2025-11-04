#!/bin/bash
##############################################################################
# Payroll Module Endpoint Testing Script
# Tests all 60+ API endpoints systematically
#
# Usage: ./test-endpoints.sh
# Output: test-results.json (detailed results for each endpoint)
##############################################################################

BASE_URL="https://staff.vapeshed.co.nz/modules/human_resources/payroll"
RESULTS_FILE="test-results.json"
TIMESTAMP=$(date +"%Y-%m-%d %H:%M:%S")

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "========================================================================="
echo "Payroll Module Endpoint Testing"
echo "Started: $TIMESTAMP"
echo "========================================================================="
echo ""

# Initialize results JSON
echo '{' > "$RESULTS_FILE"
echo '  "timestamp": "'$TIMESTAMP'",' >> "$RESULTS_FILE"
echo '  "base_url": "'$BASE_URL'",' >> "$RESULTS_FILE"
echo '  "endpoints": [' >> "$RESULTS_FILE"

FIRST=true

##############################################################################
# Test Function
# Args: method, path, auth_required, description
##############################################################################
test_endpoint() {
    local METHOD=$1
    local PATH=$2
    local AUTH_REQ=$3
    local DESC=$4
    local FULL_URL="${BASE_URL}${PATH}"

    echo -n "Testing: $METHOD $PATH ... "

    # Make request and capture response
    local RESPONSE=$(curl -sS -X "$METHOD" \
        -w "\n%{http_code}" \
        -H "Accept: application/json" \
        "$FULL_URL" 2>&1)

    # Split response body and status code
    local STATUS_CODE=$(echo "$RESPONSE" | tail -n1)
    local BODY=$(echo "$RESPONSE" | sed '$d')

    # Determine result
    local RESULT="unknown"
    local COLOR=$YELLOW

    if [ "$STATUS_CODE" = "200" ]; then
        RESULT="success"
        COLOR=$GREEN
    elif [ "$STATUS_CODE" = "401" ] || [ "$STATUS_CODE" = "403" ]; then
        if [ "$AUTH_REQ" = "true" ]; then
            RESULT="auth_required"
            COLOR=$YELLOW
        else
            RESULT="unexpected_auth"
            COLOR=$RED
        fi
    elif [ "$STATUS_CODE" = "404" ]; then
        RESULT="not_found"
        COLOR=$RED
    elif [ "$STATUS_CODE" = "405" ]; then
        RESULT="method_not_allowed"
        COLOR=$RED
    elif [ "$STATUS_CODE" = "500" ]; then
        RESULT="server_error"
        COLOR=$RED
    elif [ "$STATUS_CODE" -ge 400 ]; then
        RESULT="client_error"
        COLOR=$RED
    fi

    echo -e "${COLOR}${STATUS_CODE} - ${RESULT}${NC}"

    # Append to JSON results (handle first item differently)
    if [ "$FIRST" = true ]; then
        FIRST=false
    else
        echo '    ,' >> "$RESULTS_FILE"
    fi

    # Clean body for JSON (escape quotes, remove newlines)
    local CLEAN_BODY=$(echo "$BODY" | sed 's/"/\\"/g' | tr -d '\n' | head -c 500)

    cat >> "$RESULTS_FILE" << EOF
    {
      "method": "$METHOD",
      "path": "$PATH",
      "url": "$FULL_URL",
      "description": "$DESC",
      "auth_required": $AUTH_REQ,
      "status_code": $STATUS_CODE,
      "result": "$RESULT",
      "response_preview": "$CLEAN_BODY"
    }
EOF
}

echo ""
echo "========================================================================="
echo "PHASE 1: Health & Info Endpoints (No Auth Required)"
echo "========================================================================="

test_endpoint "GET" "/health/" false "System health check"

echo ""
echo "========================================================================="
echo "PHASE 2: Dashboard Endpoints (Auth Required)"
echo "========================================================================="

test_endpoint "GET" "/payroll/dashboard" true "Main payroll dashboard view"
test_endpoint "GET" "/api/payroll/dashboard/data" true "Dashboard data API"

echo ""
echo "========================================================================="
echo "PHASE 3: Amendment Endpoints"
echo "========================================================================="

test_endpoint "GET" "/api/payroll/amendments/pending" true "Get pending amendments"
test_endpoint "GET" "/api/payroll/amendments/history" true "Get amendment history"

echo ""
echo "========================================================================="
echo "PHASE 4: Automation Endpoints"
echo "========================================================================="

test_endpoint "GET" "/api/payroll/automation/dashboard" true "Automation dashboard stats"
test_endpoint "GET" "/api/payroll/automation/reviews/pending" true "Pending AI reviews"
test_endpoint "GET" "/api/payroll/automation/rules" true "Active AI rules"
test_endpoint "GET" "/api/payroll/automation/stats" true "Automation statistics"

echo ""
echo "========================================================================="
echo "PHASE 5: Xero Endpoints"
echo "========================================================================="

test_endpoint "GET" "/api/payroll/xero/oauth/authorize" true "Xero OAuth initiation"

echo ""
echo "========================================================================="
echo "PHASE 6: Wage Discrepancy Endpoints"
echo "========================================================================="

test_endpoint "GET" "/api/payroll/discrepancies/pending" true "Get pending discrepancies"
test_endpoint "GET" "/api/payroll/discrepancies/my-history" true "Get my discrepancy history"
test_endpoint "GET" "/api/payroll/discrepancies/statistics" true "Discrepancy statistics"

echo ""
echo "========================================================================="
echo "PHASE 7: Bonus Endpoints"
echo "========================================================================="

test_endpoint "GET" "/api/payroll/bonuses/pending" true "Get pending bonuses"
test_endpoint "GET" "/api/payroll/bonuses/history" true "Get bonus history"
test_endpoint "GET" "/api/payroll/bonuses/summary" true "Get staff bonus summary"
test_endpoint "GET" "/api/payroll/bonuses/vape-drops" true "Get vape drops"
test_endpoint "GET" "/api/payroll/bonuses/google-reviews" true "Get Google review bonuses"

echo ""
echo "========================================================================="
echo "PHASE 8: Vend Payment Endpoints"
echo "========================================================================="

test_endpoint "GET" "/api/payroll/vend-payments/pending" true "Get pending Vend payments"
test_endpoint "GET" "/api/payroll/vend-payments/history" true "Get Vend payment history"
test_endpoint "GET" "/api/payroll/vend-payments/statistics" true "Vend payment statistics"

echo ""
echo "========================================================================="
echo "PHASE 9: Leave Endpoints"
echo "========================================================================="

test_endpoint "GET" "/api/payroll/leave/pending" true "Get pending leave requests"
test_endpoint "GET" "/api/payroll/leave/history" true "Get leave history"
test_endpoint "GET" "/api/payroll/leave/balances" true "Get leave balances"

echo ""
echo "========================================================================="
echo "PHASE 10: Pay Run Endpoints"
echo "========================================================================="

test_endpoint "GET" "/payroll/payruns" true "Pay run list view"
test_endpoint "GET" "/api/payroll/payruns/list" true "Get pay runs list (AJAX)"

echo ""
echo "========================================================================="
echo "PHASE 11: Reconciliation Endpoints"
echo "========================================================================="

test_endpoint "GET" "/payroll/reconciliation" true "Reconciliation dashboard view"
test_endpoint "GET" "/api/payroll/reconciliation/dashboard" true "Reconciliation dashboard data"
test_endpoint "GET" "/api/payroll/reconciliation/variances" true "Get current variances"

echo ""
echo "========================================================================="
echo "TEST SUMMARY"
echo "========================================================================="

# Close JSON
echo '' >> "$RESULTS_FILE"
echo '  ]' >> "$RESULTS_FILE"
echo '}' >> "$RESULTS_FILE"

# Parse results for summary
TOTAL=$(grep -c '"method":' "$RESULTS_FILE")
SUCCESS=$(grep -c '"result": "success"' "$RESULTS_FILE")
AUTH_REQ=$(grep -c '"result": "auth_required"' "$RESULTS_FILE")
ERRORS=$(grep -c '"result": ".*error"' "$RESULTS_FILE")
NOT_FOUND=$(grep -c '"result": "not_found"' "$RESULTS_FILE")

echo ""
echo "Total Endpoints Tested: $TOTAL"
echo -e "${GREEN}Successful (200): $SUCCESS${NC}"
echo -e "${YELLOW}Auth Required (401/403): $AUTH_REQ${NC}"
echo -e "${RED}Not Found (404): $NOT_FOUND${NC}"
echo -e "${RED}Errors (5xx): $ERRORS${NC}"
echo ""
echo "Detailed results saved to: $RESULTS_FILE"
echo ""
echo "========================================================================="
echo "Next Steps:"
echo "1. Review $RESULTS_FILE for detailed error messages"
echo "2. Fix any 404 (routing issues) or 500 (server errors)"
echo "3. For auth-required endpoints, test with authenticated session"
echo "========================================================================="
