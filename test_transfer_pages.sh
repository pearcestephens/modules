#!/bin/bash
# Quick test script to verify all transfer pages load correctly

echo "🚀 Testing Transfer Page Architecture..."

BASE_URL="https://staff.vapeshed.co.nz/modules/consignments"

echo ""
echo "Testing Transfer Pages:"
echo "======================"

# Test Home page
echo -n "✓ Home page: "
if curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/" | grep -q "200"; then
    echo "✅ OK"
else
    echo "❌ FAILED"
fi

# Test Hub page  
echo -n "✓ Hub page: " 
if curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/transfers/hub" | grep -q "200"; then
    echo "✅ OK"
else
    echo "❌ FAILED"
fi

# Test Pack page (without transfer ID)
echo -n "✓ Pack page: "
if curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/transfers/pack" | grep -q "200"; then
    echo "✅ OK"
else
    echo "❌ FAILED"
fi

# Test Receive page (without transfer ID)
echo -n "✓ Receive page: "
if curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/transfers/receive" | grep -q "200"; then
    echo "✅ OK"
else
    echo "❌ FAILED"
fi

echo ""
echo "🎉 Test complete! Check above for any failures."
echo ""
echo "🔍 To debug issues:"
echo "   - Check error logs: tail -f ../../logs/apache_*.error.log"
echo "   - Check PHP syntax: php -l controllers/BaseTransferController.php"
echo "   - Verify autoloading: check module_bootstrap.php"