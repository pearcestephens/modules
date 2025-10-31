#!/bin/bash
# Test Pay Run System Access
# Tests if the pay runs page loads without errors

echo "========================================="
echo "Testing Pay Run System"
echo "========================================="
echo ""

BASE_URL="https://staff.vapeshed.co.nz/modules/human_resources/payroll"

echo "1. Testing Pay Runs List View..."
echo "URL: ${BASE_URL}/?view=payruns"
echo ""

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/?view=payruns")
echo "   HTTP Status: $HTTP_CODE"

if [ "$HTTP_CODE" == "302" ]; then
    echo "   ✅ Redirect (expected - authentication required)"
elif [ "$HTTP_CODE" == "200" ]; then
    echo "   ✅ Success (page loads)"
elif [ "$HTTP_CODE" == "500" ]; then
    echo "   ❌ Server Error"
    echo ""
    echo "   Checking error log..."
    tail -20 /home/master/applications/jcepnzzkmj/logs/apache_phpstack-129337-518184.cloudwaysapps.com.error.log | grep -i "payrun\|fatal\|error" | tail -5
else
    echo "   ⚠️  Status: $HTTP_CODE"
fi

echo ""
echo "2. Testing Pay Run Detail View..."
DETAIL_URL="${BASE_URL}/?view=payrun&period=2025-01-13_2025-01-19"
echo "URL: ${DETAIL_URL}"
echo ""

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "${DETAIL_URL}")
echo "   HTTP Status: $HTTP_CODE"

if [ "$HTTP_CODE" == "302" ]; then
    echo "   ✅ Redirect (expected - authentication required)"
elif [ "$HTTP_CODE" == "200" ]; then
    echo "   ✅ Success (page loads)"
elif [ "$HTTP_CODE" == "500" ]; then
    echo "   ❌ Server Error"
    echo ""
    echo "   Checking error log..."
    tail -20 /home/master/applications/jcepnzzkmj/logs/apache_phpstack-129337-518184.cloudwaysapps.com.error.log | grep -i "payrun\|fatal\|error" | tail -5
else
    echo "   ⚠️  Status: $HTTP_CODE"
fi

echo ""
echo "3. Checking recent errors..."
echo ""
ERRORS=$(tail -50 /home/master/applications/jcepnzzkmj/logs/apache_phpstack-129337-518184.cloudwaysapps.com.error.log | grep -i "payrun\|PayRunController" | wc -l)

if [ "$ERRORS" -gt 0 ]; then
    echo "   ⚠️  Found $ERRORS recent errors:"
    echo ""
    tail -50 /home/master/applications/jcepnzzkmj/logs/apache_phpstack-129337-518184.cloudwaysapps.com.error.log | grep -i "payrun\|PayRunController" | tail -5
else
    echo "   ✅ No recent PayRun errors found"
fi

echo ""
echo "========================================="
echo "Test Complete"
echo "========================================="
