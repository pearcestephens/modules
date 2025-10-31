#!/bin/bash
# Test Employee Mapping API Endpoints
# Run this script to verify all endpoints are working

BASE_URL="https://staff.vapeshed.co.nz/modules/staff-accounts"

echo "========================================="
echo "EMPLOYEE MAPPING API ENDPOINT TESTS"
echo "========================================="
echo ""

# Test 1: Get all mappings (should be empty initially)
echo "Test 1: GET all employee mappings"
echo "-----------------------------------"
curl -s -X GET "${BASE_URL}/?endpoint=employee-mappings" | python3 -m json.tool
echo ""
echo ""

# Test 2: Get unmapped employees
echo "Test 2: GET unmapped employees"
echo "-------------------------------"
curl -s -X GET "${BASE_URL}/?endpoint=employee-mappings-unmapped" | python3 -m json.tool
echo ""
echo ""

# Test 3: Auto-match employees
echo "Test 3: POST auto-match employees"
echo "----------------------------------"
curl -s -X POST "${BASE_URL}/?endpoint=employee-mappings-auto-match" \
  -H "Content-Type: application/json" \
  -d '{"csrf_token":"test"}' | python3 -m json.tool
echo ""
echo ""

# Test 4: Create a test mapping (you'll need to get a real xero_employee_id first)
# Uncomment and update with real data:
# echo "Test 4: POST create new mapping"
# echo "--------------------------------"
# curl -s -X POST "${BASE_URL}/?endpoint=employee-mappings" \
#   -H "Content-Type: application/json" \
#   -d '{
#     "xero_employee_id": "test-123",
#     "employee_name": "Test Employee",
#     "employee_email": "test@example.com",
#     "vend_customer_id": "vend-456",
#     "vend_customer_name": "Test Customer",
#     "mapping_confidence": 1.00,
#     "mapped_by": "manual",
#     "csrf_token": "test"
#   }' | python3 -m json.tool
# echo ""
# echo ""

# Test 5: Get mappings with filters
echo "Test 5: GET mappings with status filter"
echo "----------------------------------------"
curl -s -X GET "${BASE_URL}/?endpoint=employee-mappings&status=unmapped&limit=10" | python3 -m json.tool
echo ""
echo ""

echo "========================================="
echo "TESTS COMPLETE"
echo "========================================="
echo ""
echo "Next Steps:"
echo "1. Check the responses above for errors"
echo "2. Verify the stats show correct counts"
echo "3. If unmapped employees are found, test creating mappings"
echo "4. Test auto-match to see suggested matches"
echo ""
