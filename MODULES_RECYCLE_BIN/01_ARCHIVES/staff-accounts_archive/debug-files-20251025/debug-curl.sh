#!/bin/bash
# Simple direct API test

echo "Testing direct curl to API..."
echo ""

echo "Test 1: Simple GET request"
curl -k -v "https://staff.vapeshed.co.nz/modules/staff-accounts/?endpoint=employee-mappings" 2>&1 | head -30

echo ""
echo ""
echo "Test 2: Check if we can reach the server"
curl -k -I "https://staff.vapeshed.co.nz/modules/staff-accounts/" 2>&1 | head -20

echo ""
echo ""
echo "Test 3: Try from localhost (bypass external DNS)"
curl -k "http://localhost/modules/staff-accounts/?endpoint=employee-mappings" 2>&1 | head -30
