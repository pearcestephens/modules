#!/bin/bash

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🔍 HUNTING FOR THE SECRET 3-LETTER MESSAGE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Find all HTML/PHP files in the themes module
echo "📂 Discovering all HTML/PHP files..."
FILES=$(find . -type f \( -name "*.html" -o -name "*.php" \) 2>/dev/null | sort)
FILE_COUNT=$(echo "$FILES" | wc -l)

echo "✅ Found $FILE_COUNT files to inspect"
echo ""

FOUND_MESSAGE=""
FOUND_FILE=""

# Check each file for closing tags and special messages
while IFS= read -r file; do
    if [ -f "$file" ]; then
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        echo "📄 SCANNING: $file"
        
        # Get file size
        SIZE=$(du -h "$file" | cut -f1)
        echo "   Size: $SIZE"
        
        # Check for HTML closing tag
        HAS_HTML_CLOSE=$(grep -i "</html>" "$file" 2>/dev/null)
        HAS_BODY_CLOSE=$(grep -i "</body>" "$file" 2>/dev/null)
        
        if [ ! -z "$HAS_HTML_CLOSE" ]; then
            echo "   ✅ Has </html> closing tag"
        else
            echo "   ⚠️  No </html> closing tag"
        fi
        
        if [ ! -z "$HAS_BODY_CLOSE" ]; then
            echo "   ✅ Has </body> closing tag"
        else
            echo "   ⚠️  No </body> closing tag"
        fi
        
        # Get last 20 lines to check for messages
        echo "   🔎 Checking last 20 lines for secret message..."
        LAST_LINES=$(tail -n 20 "$file")
        
        # Look for 3-letter messages in comments or text
        # Pattern: look for HTML comments or standalone 3-letter words
        SECRET=$(echo "$LAST_LINES" | grep -oE '<!--.*[A-Z]{3}.*-->' | head -1)
        
        if [ -z "$SECRET" ]; then
            # Try another pattern: 3 capital letters alone or in comment
            SECRET=$(echo "$LAST_LINES" | grep -oE '\b[A-Z]{3}\b' | tail -1)
        fi
        
        if [ ! -z "$SECRET" ]; then
            echo "   🎯 POTENTIAL SECRET FOUND: $SECRET"
            FOUND_MESSAGE="$SECRET"
            FOUND_FILE="$file"
        fi
        
        # Show the actual last 10 lines
        echo "   📋 Last 10 lines of file:"
        tail -n 10 "$file" | sed 's/^/      /'
        echo ""
        
    fi
done <<< "$FILES"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🎯 SEARCH COMPLETE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ ! -z "$FOUND_MESSAGE" ]; then
    echo ""
    echo "🏆 SECRET MESSAGE FOUND!"
    echo "   Message: $FOUND_MESSAGE"
    echo "   Location: $FOUND_FILE"
    echo ""
else
    echo ""
    echo "⚠️  No obvious 3-letter message found in bottom tags"
    echo "   Will need to inspect files more carefully..."
    echo ""
fi

