#!/bin/bash
# Namespace Audit Script
# Scans all modules for incorrect/inconsistent namespaces

echo "🔍 CIS Module Namespace Audit"
echo "============================="
echo ""

# Expected namespace pattern: CIS\ModuleName
# Exceptions: App\*, CIS\Shared\Services\*, CIS\Base\*

echo "📋 Scanning modules for namespace declarations..."
echo ""

# Find all PHP files with namespace declarations
grep -r "^namespace " /home/master/applications/jcepnzzkmj/public_html/modules --include="*.php" | \
while IFS=: read -r file namespace_line; do
    # Extract namespace
    ns=$(echo "$namespace_line" | sed 's/namespace //' | sed 's/;//')

    # Extract module from path
    module=$(echo "$file" | sed 's|.*/modules/||' | cut -d'/' -f1)

    # Check if namespace matches expected pattern
    expected="CIS\\${module^}"  # Capitalize first letter

    # Skip known exceptions
    if [[ "$ns" == "App\\"* ]] || \
       [[ "$ns" == "CIS\\Shared\\Services"* ]] || \
       [[ "$ns" == "CIS\\Base"* ]]; then
        echo "✅ $module: $ns (exception - OK)"
    elif [[ "$ns" == "$expected"* ]]; then
        echo "✅ $module: $ns"
    else
        echo "❌ $module: $ns (expected: $expected)"
        echo "   File: $file"
        echo ""
    fi
done

echo ""
echo "🎯 Summary: Check for ❌ lines above"
echo "Expected pattern: CIS\\ModuleName\\*"
echo "Exceptions allowed: App\\*, CIS\\Shared\\Services\\*, CIS\\Base\\*"
