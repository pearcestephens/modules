#!/usr/bin/env php
<?php
/**
 * Comprehensive API Fixer Script
 *
 * Fixes all remaining issues in API endpoints:
 * 1. Namespace corrections (BankTransactions → CIS\BankTransactions)
 * 2. CSRF validation to use APIHelper
 * 3. Error responses to use APIHelper
 */

declare(strict_types=1);

$apiDir = __DIR__ . '/api';
$files = glob($apiDir . '/*.php');

echo "\n";
echo "══════════════════════════════════════════════════════════════════════════\n";
echo " Comprehensive API Fixer - Namespace & CSRF Corrections\n";
echo "══════════════════════════════════════════════════════════════════════════\n\n";

$totalFixed = 0;
$fixes = [];

foreach ($files as $file) {
    $filename = basename($file);
    $content = file_get_contents($file);
    $originalContent = $content;
    $fileFixed = 0;

    echo "Processing: $filename\n";

    // Fix 1: Replace \BankTransactions\Models\ with \CIS\BankTransactions\Models\
    if (preg_match('/\\\\BankTransactions\\\\Models\\\\/', $content)) {
        $content = str_replace(
            '\\BankTransactions\\Models\\',
            '\\CIS\\BankTransactions\\Models\\',
            $content
        );
        echo "  ✓ Fixed namespace: \\BankTransactions\\Models\\ → \\CIS\\BankTransactions\\Models\\\n";
        $fileFixed++;
    }

    // Fix 2: Replace \BankTransactions\Lib\ with \CIS\BankTransactions\Lib\
    if (preg_match('/\\\\BankTransactions\\\\Lib\\\\/', $content)) {
        $content = str_replace(
            '\\BankTransactions\\Lib\\',
            '\\CIS\\BankTransactions\\Lib\\',
            $content
        );
        echo "  ✓ Fixed namespace: \\BankTransactions\\Lib\\ → \\CIS\\BankTransactions\\Lib\\\n";
        $fileFixed++;
    }

    // Fix 3: Replace old CSRF validation with APIHelper
    $oldCsrfPattern = '/if \(!isset\(\$input\[\'csrf_token\'\]\) \|\| \$input\[\'csrf_token\'\] !== \(\$_SESSION\[\'csrf_token\'\] \?\? \'\'\)\) \{\s*http_response_code\(403\);\s*echo json_encode\(\[\'success\' => false, \'error\' => \[\'code\' => \'INVALID_CSRF_TOKEN\', \'message\' => [^\]]+\]\]\);\s*exit;\s*\}/s';

    if (preg_match($oldCsrfPattern, $content)) {
        $content = preg_replace(
            $oldCsrfPattern,
            'if (!APIHelper::validateCSRF($input[\'csrf_token\'] ?? null)) {' . "\n" .
            '        APIHelper::error(\'INVALID_CSRF_TOKEN\', \'Invalid CSRF token\', 403);' . "\n" .
            '    }',
            $content
        );
        echo "  ✓ Fixed CSRF validation to use APIHelper\n";
        $fileFixed++;
    }

    // Fix 4: Replace standalone CSRF checks in auto-match-single and similar files
    $csrfPattern2 = '/\$csrfToken = \$_POST\[\'csrf_token\'\] \?\? \$postData\[\'csrf_token\'\] \?\? null;.*?if \(empty\(\$csrfToken\) \|\| \$csrfToken !== \(\$_SESSION\[\'csrf_token\'\] \?\? \'\'\)\) \{[^}]+\}\s*exit;\s*\}/s';

    if (preg_match($csrfPattern2, $content)) {
        $content = preg_replace(
            $csrfPattern2,
            '$csrfToken = $_POST[\'csrf_token\'] ?? $postData[\'csrf_token\'] ?? null;' . "\n\n" .
            '    // Validate CSRF token (supports bot bypass)' . "\n" .
            '    if (!APIHelper::validateCSRF($csrfToken)) {' . "\n" .
            '        APIHelper::error(\'INVALID_CSRF_TOKEN\', \'Invalid CSRF token\', 403);' . "\n" .
            '    }',
            $content
        );
        echo "  ✓ Fixed standalone CSRF validation\n";
        $fileFixed++;
    }

    // Fix 5: Replace error responses with APIHelper::error()
    // This is complex, so let's just flag files that need manual review
    if (preg_match('/http_response_code\(\d+\);.*?echo json_encode\(\[.*?\'error\'.*?\]\);.*?exit;/s', $content)) {
        $needsManualReview = false;
        // Check if it's already using APIHelper
        if (!preg_match('/APIHelper::error/', $content) && preg_match_all('/http_response_code\(\d+\);/', $content, $matches) > 3) {
            echo "  ⚠ Contains " . count($matches[0]) . " manual error responses (consider using APIHelper::error)\n";
            $needsManualReview = true;
        }
    }

    // Save if changed
    if ($content !== $originalContent) {
        file_put_contents($file, $content);
        echo "  → Saved changes\n";
        $totalFixed++;
        $fixes[$filename] = $fileFixed;
    } else {
        echo "  → No changes needed\n";
    }

    echo "\n";
}

echo "══════════════════════════════════════════════════════════════════════════\n";
echo " Summary:\n";
echo "══════════════════════════════════════════════════════════════════════════\n";
echo " Files processed: " . count($files) . "\n";
echo " Files fixed: $totalFixed\n";

if (!empty($fixes)) {
    echo "\n Fixed files:\n";
    foreach ($fixes as $file => $count) {
        echo "  • $file ($count fix" . ($count > 1 ? 'es' : '') . ")\n";
    }
}

echo "\n✓ Comprehensive API fix complete!\n\n";
