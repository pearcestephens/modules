#!/bin/bash
# Payroll Module - Endpoint Testing Script

echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║  PAYROLL MODULE - ENDPOINT TESTING                            ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""

BASE_URL="https://staff.vapeshed.co.nz/modules"

# Test 1: PDF API Status
echo "TEST 1: PDF API Status"
echo "────────────────────────────────────────────────────────────────"
echo "  Checking PDF service status..."
curl -s --connect-timeout 5 --max-time 15 "${BASE_URL}/shared/api/pdf.php?action=status" | python3 -m json.tool
echo ""
echo ""

# Test 2: Generate Simple PDF
echo "TEST 2: Generate Simple PDF"
echo "────────────────────────────────────────────────────────────────"
echo "  Generating PDF from HTML..."
curl --connect-timeout 5 --max-time 15 -X POST "${BASE_URL}/shared/api/pdf.php?action=generate" \
  -H "Content-Type: application/json" \
  -d '{
    "html": "<html><body><h1>Test PDF</h1><p>This is a test PDF generated via API.</p></body></html>",
    "filename": "test.pdf",
    "output": "base64"
  }' | python3 -m json.tool | head -20
echo ""
echo ""

# Test 3: Payslip PDF Download (requires valid payslip ID)
echo "TEST 3: Payslip PDF Download"
echo "────────────────────────────────────────────────────────────────"
echo "  Attempting to download payslip PDF (ID=1)..."
echo "  NOTE: This will fail if no payslip with ID=1 exists"
curl -I --connect-timeout 5 --max-time 15 "${BASE_URL}/human_resources/payroll/payslips.php?action=pdf&id=1"
echo ""
echo ""

# Test 4: Payslip View (HTML check)
echo "TEST 4: Payslip View"
echo "────────────────────────────────────────────────────────────────"
echo "  Checking payslip view loads..."
echo "  NOTE: This will fail if no payslip with ID=1 exists"
curl -I --connect-timeout 5 --max-time 15 "${BASE_URL}/human_resources/payroll/views/payslip.php?id=1"
echo ""
echo ""

# Test 5: Email Queue Stats (if you create this endpoint)
echo "TEST 5: Email Queue Stats"
echo "────────────────────────────────────────────────────────────────"
echo "  Note: Endpoint not yet created. Run test_complete.php for queue stats"
echo ""
echo ""

echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║  ENDPOINT TESTING COMPLETE                                    ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""
echo "Next Steps:"
echo "  1. Run: php tests/test_complete.php"
echo "  2. Install Dompdf: cd /modules && composer install"
echo "  3. Open browser: https://staff.vapeshed.co.nz/modules/human_resources/payroll/views/payslip.php?id=1"
echo ""
