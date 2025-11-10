#!/usr/bin/env python3
"""
Fix ChromeManagerTest.php by:
1. Removing all corrupted markTestSkipped statements
2. Adding $this->testSessionId to all method calls that need it
3. Ensuring proper syntax
"""

import re
import sys

def fix_chrome_manager_test(filepath):
    with open(filepath, 'r') as f:
        content = f.read()

    original_content = content

    # Step 1: Remove all standalone markTestSkipped lines (not properly inside function bodies)
    # These are lines with markTestSkipped that appear before function declarations
    # Pattern: whitespace + markTestSkipped + newline + whitespace + "public function"
    # Need to handle variable amounts of leading whitespace
    content = re.sub(
        r'\n\s*\$this->markTestSkipped\([^)]+\);\s*\n(\s*)(public function)',
        r'\n\1\2',
        content
    )

    # Step 2: Remove duplicate opening braces
    content = re.sub(r'\n    \{\n    \{', r'\n    {', content)

    # Step 3: Fix method calls to include $this->testSessionId as first parameter
    # navigate($url) → navigate($this->testSessionId, $url)
    content = re.sub(
        r'->navigate\(\s*([\'"][^\'"]+[\'"])',
        r'->navigate($this->testSessionId, \1',
        content
    )

    # executeScript($script) → executeScript($this->testSessionId, $script)
    content = re.sub(
        r'->executeScript\(\s*([\'"][^\'"]+[\'"])',
        r'->executeScript($this->testSessionId, \1',
        content
    )

    # click($selector) → click($this->testSessionId, $selector)
    content = re.sub(
        r'->click\(\s*([\'"][^\'"]+[\'"])',
        r'->click($this->testSessionId, \1',
        content
    )

    # waitForSelector($selector) → waitForSelector($this->testSessionId, $selector)
    content = re.sub(
        r'->waitForSelector\(\s*([\'"][^\'"]+[\'"])',
        r'->waitForSelector($this->testSessionId, \1',
        content
    )

    # type($selector, $text) → type($this->testSessionId, $selector, $text)
    # This is more complex as it has 2 params already
    content = re.sub(
        r'->type\(\s*([\'"][^\'"]+[\'"])\s*,\s*([^)]+)\)',
        r'->type($this->testSessionId, \1, \2)',
        content
    )

    # captureScreenshot($options) → captureScreenshot($this->testSessionId, $options)
    content = re.sub(
        r'->captureScreenshot\(\s*(\[)',
        r'->captureScreenshot($this->testSessionId, \1',
        content
    )

    # captureScreenshot() → captureScreenshot($this->testSessionId)
    content = re.sub(
        r'->captureScreenshot\(\s*\)',
        r'->captureScreenshot($this->testSessionId)',
        content
    )

    # setViewport($width, $height) → setViewport($this->testSessionId, $width, $height)
    content = re.sub(
        r'->setViewport\(\s*(\$\w+)\s*,\s*(\$\w+)\s*\)',
        r'->setViewport($this->testSessionId, \1, \2)',
        content
    )

    # For other methods that might not have been caught
    # getCookies() → getCookies($this->testSessionId)
    content = re.sub(
        r'->getCookies\(\s*\)',
        r'->getCookies($this->testSessionId)',
        content
    )

    # Check if anything changed
    if content == original_content:
        print("⚠️  No changes made")
        return False

    # Write back
    with open(filepath, 'w') as f:
        f.write(content)

    print(f"✅ Fixed ChromeManagerTest.php")
    print(f"   - Removed corrupted markTestSkipped statements")
    print(f"   - Fixed duplicate braces")
    print(f"   - Added $this->testSessionId to method calls")
    return True

if __name__ == '__main__':
    if len(sys.argv) != 2:
        print("Usage: python3 fix_chrome_manager_test.py <filepath>")
        sys.exit(1)

    filepath = sys.argv[1]
    success = fix_chrome_manager_test(filepath)
    sys.exit(0 if success else 1)
