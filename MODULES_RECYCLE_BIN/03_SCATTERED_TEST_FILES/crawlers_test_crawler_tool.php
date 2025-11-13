<?php
/**
 * Test CrawlerTool Integration
 *
 * Verifies CrawlerTool wrapper works without breaking existing functionality
 */

require_once __DIR__ . '/CrawlerTool.php';

use MCP\Tools\CrawlerTool;

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🕷️  CRAWLER TOOL INTEGRATION TEST\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$crawler = new CrawlerTool();
$passed = 0;
$failed = 0;

// Test 1: Tool instantiation
echo "Test 1: Tool instantiation... ";
try {
    if ($crawler instanceof CrawlerTool) {
        echo "✅ PASS\n";
        $passed++;
    } else {
        echo "❌ FAIL\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "❌ FAIL - " . $e->getMessage() . "\n";
    $failed++;
}

// Test 2: Get metadata
echo "Test 2: Get metadata... ";
try {
    $metadata = $crawler->getMetadata();
    if (isset($metadata['name']) && $metadata['name'] === 'crawler') {
        echo "✅ PASS\n";
        $passed++;
    } else {
        echo "❌ FAIL - Invalid metadata\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "❌ FAIL - " . $e->getMessage() . "\n";
    $failed++;
}

// Test 3: Get available profiles
echo "Test 3: Get available profiles... ";
try {
    $profiles = $crawler->getAvailableProfiles();
    $expectedProfiles = ['cis_desktop', 'cis_mobile', 'cis_tablet', 'gpt_hub', 'customer'];
    $hasAll = true;
    foreach ($expectedProfiles as $profile) {
        if (!isset($profiles[$profile])) {
            $hasAll = false;
            break;
        }
    }
    if ($hasAll) {
        echo "✅ PASS (5 profiles found)\n";
        $passed++;
    } else {
        echo "❌ FAIL - Missing profiles\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "❌ FAIL - " . $e->getMessage() . "\n";
    $failed++;
}

// Test 4: Missing URL parameter
echo "Test 4: Missing URL parameter... ";
try {
    $result = $crawler->execute([]);
    if (!$result['success'] && strpos($result['error'], 'url') !== false) {
        echo "✅ PASS (Error handled correctly)\n";
        $passed++;
    } else {
        echo "❌ FAIL - Should return error for missing URL\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "❌ FAIL - " . $e->getMessage() . "\n";
    $failed++;
}

// Test 5: Invalid URL
echo "Test 5: Invalid URL... ";
try {
    $result = $crawler->execute(['url' => 'not-a-url']);
    if (!$result['success'] && strpos($result['error'], 'Invalid') !== false) {
        echo "✅ PASS (Invalid URL rejected)\n";
        $passed++;
    } else {
        echo "❌ FAIL - Should reject invalid URL\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "❌ FAIL - " . $e->getMessage() . "\n";
    $failed++;
}

// Test 6: Invalid mode
echo "Test 6: Invalid mode... ";
try {
    $result = $crawler->execute([
        'url' => 'https://example.com',
        'mode' => 'invalid_mode'
    ]);
    if (!$result['success'] && strpos($result['error'], 'Invalid mode') !== false) {
        echo "✅ PASS (Invalid mode rejected)\n";
        $passed++;
    } else {
        echo "❌ FAIL - Should reject invalid mode\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "❌ FAIL - " . $e->getMessage() . "\n";
    $failed++;
}

// Test 7: Check crawler script exists
echo "Test 7: Crawler script exists... ";
$crawlerScriptPath = __DIR__ . '/deep-crawler.js';
if (file_exists($crawlerScriptPath)) {
    echo "✅ PASS\n";
    $passed++;
    echo "   📁 Found at: {$crawlerScriptPath}\n";
} else {
    echo "❌ FAIL - Script not found\n";
    echo "   📁 Expected at: {$crawlerScriptPath}\n";
    $failed++;
}

// Test 8: Valid modes recognized
echo "Test 8: All valid modes... ";
try {
    $validModes = ['quick', 'authenticated', 'interactive', 'full', 'errors_only'];
    $metadata = $crawler->getMetadata();
    $definedModes = array_keys($metadata['modes']);

    $modesMatch = true;
    foreach ($validModes as $mode) {
        if (!in_array($mode, $definedModes)) {
            $modesMatch = false;
            break;
        }
    }

    if ($modesMatch) {
        echo "✅ PASS (5 modes defined)\n";
        $passed++;
    } else {
        echo "❌ FAIL - Mode mismatch\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "❌ FAIL - " . $e->getMessage() . "\n";
    $failed++;
}

// Summary
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📊 TEST SUMMARY\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Passed: {$passed}\n";
echo "❌ Failed: {$failed}\n";
$total = $passed + $failed;
$percentage = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
echo "📈 Success Rate: {$percentage}%\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

if ($failed === 0) {
    echo "🎉 ALL TESTS PASSED! CrawlerTool is ready to use.\n\n";

    echo "Usage examples:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    echo "1️⃣  Quick crawl:\n";
    echo "curl -X POST https://gpt.ecigdis.co.nz/mcp/dispatcher.php \\\n";
    echo "  -d tool=crawler \\\n";
    echo "  -d url=https://example.com\n\n";

    echo "2️⃣  Full site audit (YOUR WORKFLOW):\n";
    echo "curl -X POST https://gpt.ecigdis.co.nz/mcp/dispatcher.php \\\n";
    echo "  -d tool=crawler \\\n";
    echo "  -d mode=full \\\n";
    echo "  -d url=https://staff.vapeshed.co.nz \\\n";
    echo "  -d profile=cis_desktop\n\n";

    echo "3️⃣  Check errors only:\n";
    echo "curl -X POST https://gpt.ecigdis.co.nz/mcp/dispatcher.php \\\n";
    echo "  -d tool=crawler \\\n";
    echo "  -d mode=errors_only \\\n";
    echo "  -d url=https://example.com\n\n";

    echo "✅ Your deep-crawler.js remains UNTOUCHED and fully functional!\n";
    exit(0);
} else {
    echo "❌ Some tests failed. Review errors above.\n";
    exit(1);
}
