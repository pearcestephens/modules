#!/usr/bin/env php
<?php
/**
 * Flagged Products Module - JavaScript & Frontend Validator
 *
 * Analyzes JavaScript code, checks for console errors, validates HTML structure
 */

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "  FLAGGED PRODUCTS - JAVASCRIPT & FRONTEND VALIDATOR\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

$modulePath = '/home/master/applications/jcepnzzkmj/public_html/modules/flagged_products';
$issues = [];
$warnings = [];
$passed = 0;

// ============================================================================
// 1. CHECK CRON DASHBOARD VIEW FOR JAVASCRIPT ERRORS
// ============================================================================
echo "1️⃣  ANALYZING CRON DASHBOARD VIEW\n";
echo "───────────────────────────────────────────────────────────────────────\n\n";

$cronDashboard = file_get_contents("$modulePath/views/cron-dashboard.php");

// Check for Chart.js script tag
if (strpos($cronDashboard, 'chart.js') !== false || strpos($cronDashboard, 'Chart') !== false) {
    echo "✓ Chart.js library referenced\n";
    $passed++;
} else {
    $issues[] = "Chart.js library not found in cron dashboard";
}

// Check for Chart initialization
if (preg_match('/new\s+Chart\s*\(/i', $cronDashboard)) {
    echo "✓ Chart instances found\n";
    $passed++;
} else {
    $warnings[] = "No Chart initialization found (may use dynamic loading)";
}

// Check for jQuery usage (should NOT be required)
if (strpos($cronDashboard, 'jquery') !== false || strpos($cronDashboard, '$') !== false) {
    $warnings[] = "jQuery detected - consider using vanilla JavaScript";
} else {
    echo "✓ No jQuery dependency (vanilla JS)\n";
    $passed++;
}

// Check for common JavaScript errors
$jsErrors = [
    '/console\\.log\s*\(/i' => 'console.log() statements (remove for production)',
    '/debugger;/i' => 'debugger statements',
    '/alert\s*\(/i' => 'alert() statements',
    '/var\s+\w+\s*=/i' => 'var declarations (use const/let instead)',
];

foreach ($jsErrors as $pattern => $error) {
    if (preg_match($pattern, $cronDashboard)) {
        $warnings[] = "Found: $error";
    }
}

// Check Bootstrap
if (strpos($cronDashboard, 'bootstrap') !== false) {
    echo "✓ Bootstrap library referenced\n";
    $passed++;
} else {
    $issues[] = "Bootstrap library not found";
}

// Check Font Awesome
if (strpos($cronDashboard, 'font-awesome') !== false || strpos($cronDashboard, 'fontawesome') !== false) {
    echo "✓ Font Awesome icons referenced\n";
    $passed++;
} else {
    $warnings[] = "Font Awesome icons not referenced";
}

echo "\n";

// ============================================================================
// 2. VALIDATE HTML STRUCTURE
// ============================================================================
echo "2️⃣  VALIDATING HTML STRUCTURE\n";
echo "───────────────────────────────────────────────────────────────────────\n\n";

// Check DOCTYPE
if (preg_match('/<!DOCTYPE\s+html>/i', $cronDashboard)) {
    echo "✓ Valid HTML5 DOCTYPE\n";
    $passed++;
} else {
    $issues[] = "Missing or invalid DOCTYPE";
}

// Check required HTML tags
$requiredTags = [
    '<html' => 'html',
    '<head' => 'head',
    '<body' => 'body',
    '<title' => 'title',
];

foreach ($requiredTags as $tag => $name) {
    if (stripos($cronDashboard, $tag) !== false) {
        echo "✓ <$name> tag present\n";
        $passed++;
    } else {
        $issues[] = "Missing <$name> tag";
    }
}

// Check meta tags
$metaTags = [
    'charset' => 'character encoding',
    'viewport' => 'viewport meta tag',
];

foreach ($metaTags as $attr => $name) {
    if (preg_match("/<meta[^>]+$attr/i", $cronDashboard)) {
        echo "✓ $name present\n";
        $passed++;
    } else {
        $warnings[] = "Missing $name";
    }
}

echo "\n";

// ============================================================================
// 3. CHECK JAVASCRIPT SYNTAX IN VIEW FILES
// ============================================================================
echo "3️⃣  CHECKING INLINE JAVASCRIPT\n";
echo "───────────────────────────────────────────────────────────────────────\n\n";

// Extract JavaScript from <script> tags
preg_match_all('/<script[^>]*>(.*?)<\/script>/is', $cronDashboard, $scriptMatches);

if (count($scriptMatches[1]) > 0) {
    echo "Found " . count($scriptMatches[1]) . " script blocks\n\n";

    foreach ($scriptMatches[1] as $idx => $scriptContent) {
        // Skip external scripts (src attribute)
        if (empty(trim($scriptContent))) {
            continue;
        }

        echo "Script Block " . ($idx + 1) . ":\n";

        // Check for syntax patterns
        $patterns = [
            '/const\s+\w+\s*=/' => 'const declarations',
            '/let\s+\w+\s*=/' => 'let declarations',
            '/function\s+\w+\s*\(/' => 'function declarations',
            '/\=\>\s*\{/' => 'arrow functions',
            '/document\.getElementById/' => 'DOM queries',
            '/addEventListener/' => 'event listeners',
        ];

        $found = [];
        foreach ($patterns as $pattern => $desc) {
            if (preg_match($pattern, $scriptContent)) {
                $found[] = $desc;
            }
        }

        if (!empty($found)) {
            echo "  ✓ Uses: " . implode(', ', $found) . "\n";
            $passed++;
        }

        // Check for common errors
        if (preg_match('/\}\s*\{/', $scriptContent)) {
            $warnings[] = "Script Block " . ($idx + 1) . ": Possible missing operator between blocks";
        }

        if (preg_match('/[^\'"]http:\/\/[^\'"]*/', $scriptContent)) {
            $warnings[] = "Script Block " . ($idx + 1) . ": Mixed content warning (HTTP resource on HTTPS page)";
        }

        echo "\n";
    }
} else {
    $warnings[] = "No inline JavaScript found (may use external files)";
}

// ============================================================================
// 4. CHECK FOR SECURITY ISSUES
// ============================================================================
echo "4️⃣  SECURITY CHECKS\n";
echo "───────────────────────────────────────────────────────────────────────\n\n";

// Check for XSS vulnerabilities
if (preg_match('/\<\?php\s+echo\s+\$_/', $cronDashboard)) {
    $issues[] = "Potential XSS: Direct echo of user input without htmlspecialchars()";
}

// Check for proper escaping
$escapeCount = substr_count($cronDashboard, 'htmlspecialchars(');
if ($escapeCount > 5) {
    echo "✓ Using htmlspecialchars() for output escaping ($escapeCount instances)\n";
    $passed++;
} else {
    $warnings[] = "Limited use of htmlspecialchars() - verify all user output is escaped";
}

// Check for SQL queries (should not be in views)
if (preg_match('/SELECT|INSERT|UPDATE|DELETE/i', $cronDashboard)) {
    $warnings[] = "SQL queries found in view (consider moving to controller/model)";
}

echo "\n";

// ============================================================================
// 5. CHECK EXTERNAL RESOURCES
// ============================================================================
echo "5️⃣  EXTERNAL RESOURCE VALIDATION\n";
echo "───────────────────────────────────────────────────────────────────────\n\n";

// Extract external resources
preg_match_all('/(src|href)=["\']([^"\']+)["\']/i', $cronDashboard, $resourceMatches);

$cdnResources = [];
$localResources = [];

foreach ($resourceMatches[2] as $resource) {
    if (strpos($resource, '://') !== false) {
        $cdnResources[] = $resource;
    } else {
        $localResources[] = $resource;
    }
}

if (!empty($cdnResources)) {
    echo "CDN Resources (" . count($cdnResources) . "):\n";
    foreach (array_unique($cdnResources) as $cdn) {
        // Check if HTTPS
        if (strpos($cdn, 'https://') === 0) {
            echo "  ✓ $cdn\n";
            $passed++;
        } else {
            echo "  ⚠ $cdn (not HTTPS)\n";
            $issues[] = "Non-HTTPS CDN resource: $cdn";
        }
    }
    echo "\n";
}

if (!empty($localResources)) {
    echo "Local Resources (" . count($localResources) . "):\n";
    foreach (array_unique($localResources) as $local) {
        echo "  • $local\n";
    }
    echo "\n";
}

// ============================================================================
// 6. PERFORMANCE CHECKS
// ============================================================================
echo "6️⃣  PERFORMANCE ANALYSIS\n";
echo "───────────────────────────────────────────────────────────────────────\n\n";

// Check file size
$fileSize = filesize("$modulePath/views/cron-dashboard.php");
$fileSizeKB = round($fileSize / 1024, 2);
echo "File size: {$fileSizeKB} KB\n";

if ($fileSize < 100 * 1024) {
    echo "✓ File size acceptable\n";
    $passed++;
} else {
    $warnings[] = "Large file size - consider splitting or optimizing";
}

// Check for inline styles
$inlineStyleCount = substr_count($cronDashboard, 'style=');
if ($inlineStyleCount > 0) {
    $warnings[] = "Found $inlineStyleCount inline style attributes (consider using CSS classes)";
}

// Check for <style> tag (CSS in file)
if (preg_match('/<style[^>]*>/', $cronDashboard)) {
    echo "✓ Internal CSS found\n";
    $passed++;
}

echo "\n";

// ============================================================================
// SUMMARY
// ============================================================================
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "  VALIDATION SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

echo "✅ PASSED CHECKS: $passed\n";
echo "⚠️  WARNINGS: " . count($warnings) . "\n";
echo "❌ ISSUES: " . count($issues) . "\n\n";

if (!empty($warnings)) {
    echo "WARNINGS:\n";
    foreach ($warnings as $idx => $warning) {
        echo "  " . ($idx + 1) . ". $warning\n";
    }
    echo "\n";
}

if (!empty($issues)) {
    echo "ISSUES:\n";
    foreach ($issues as $idx => $issue) {
        echo "  " . ($idx + 1) . ". $issue\n";
    }
    echo "\n";
}

// Overall status
$totalChecks = $passed + count($warnings) + count($issues);
$successRate = round(($passed / $totalChecks) * 100, 1);

echo "SUCCESS RATE: {$successRate}%\n\n";

if (count($issues) == 0) {
    echo "🎉 NO CRITICAL ISSUES FOUND!\n\n";
    $exitCode = 0;
} elseif (count($issues) <= 2) {
    echo "⚠️  MINOR ISSUES DETECTED\n\n";
    $exitCode = 1;
} else {
    echo "❌ MULTIPLE ISSUES REQUIRE ATTENTION\n\n";
    $exitCode = 2;
}

echo "═══════════════════════════════════════════════════════════════════════\n\n";

exit($exitCode);
