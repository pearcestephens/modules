#!/bin/bash
# Quick Access Script - Open Template Showcase in Browser
# Usage: bash open-templates.sh

echo "=================================================="
echo "   CIS TEMPLATE SHOWCASE - QUICK ACCESS"
echo "=================================================="
echo ""
echo "🚀 Opening Template Showcase in your browser..."
echo ""
echo "URL: https://staff.vapeshed.co.nz/modules/admin-ui/template-showcase.php"
echo ""
echo "What you'll see:"
echo "  ✅ 5 interactive layout demos"
echo "  ✅ Live preview of each template"
echo "  ✅ Responsive testing"
echo "  ✅ Documentation links"
echo ""
echo "=================================================="

# Try to open in default browser
if command -v xdg-open > /dev/null; then
    xdg-open "https://staff.vapeshed.co.nz/modules/admin-ui/template-showcase.php"
elif command -v open > /dev/null; then
    open "https://staff.vapeshed.co.nz/modules/admin-ui/template-showcase.php"
else
    echo "Please manually open: https://staff.vapeshed.co.nz/modules/admin-ui/template-showcase.php"
fi
