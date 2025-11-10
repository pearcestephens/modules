#!/bin/bash
#
# Auto-Stub Missing Methods - BLAZING FAST
# Finds all "does not exist" errors and generates method stubs automatically
#

set -e

cd "$(dirname "$0")/.."

echo "🔥 AUTO-STUB MISSING METHODS - MAXIMUM SPEED MODE 🔥"
echo ""

# Run tests and capture all "does not exist" errors
echo "📊 Analyzing test failures..."
ERRORS=$(timeout 480 vendor/bin/phpunit tests/ --no-coverage --exclude-group slow 2>&1 || true)

# Extract missing methods with their classes
MISSING_METHODS=$(echo "$ERRORS" | grep -E "(Method|Property).*does not exist" | sed 's/.*:://' | sed 's/().*//' | sort -u)

if [ -z "$MISSING_METHODS" ]; then
    echo "✅ No missing methods found!"
    exit 0
fi

echo ""
echo "🎯 Found missing methods:"
echo "$MISSING_METHODS"
echo ""

# Count them
COUNT=$(echo "$MISSING_METHODS" | wc -l)
echo "📈 Total missing: $COUNT methods"
echo ""

# Extract class names and methods
echo "$ERRORS" | grep -E "Method.*does not exist" | while read -r line; do
    CLASS=$(echo "$line" | sed 's/.*Method //' | sed 's/::.*//')
    METHOD=$(echo "$line" | sed 's/.*:://' | sed 's/().*//')
    echo "   $CLASS::$METHOD()"
done

echo ""
echo "💡 TIP: These are mostly simple stubs that can be auto-generated!"
echo ""
echo "Would you like me to:"
echo "  A) Generate stub template file"
echo "  B) Show method signatures needed"
echo "  C) Run targeted fixes"
echo ""
