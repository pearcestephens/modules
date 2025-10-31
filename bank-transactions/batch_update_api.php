#!/usr/bin/env php
<?php
/**
 * Batch Update Script for API Endpoints
 *
 * Automatically updates all API endpoints to use APIHelper for:
 * - Bot bypass authentication support
 * - Consistent error handling
 * - Standardized responses
 */

declare(strict_types=1);

$apiDir = __DIR__ . '/api';
$files = glob($apiDir . '/*.php');

$updated = 0;
$skipped = 0;
$errors = 0;

echo "\n";
echo "═══════════════════════════════════════════════════════════════════════════\n";
echo " Batch Update: API Endpoints → APIHelper Integration\n";
echo "═══════════════════════════════════════════════════════════════════════════\n\n";

foreach ($files as $file) {
    $filename = basename($file);

    // Skip the export.php - it has different authentication
    if ($filename === 'export.php') {
        echo "⊘ SKIP: $filename (requires manual update)\n";
        $skipped++;
        continue;
    }

    echo "Processing: $filename...\n";

    $content = file_get_contents($file);

    // Check if already updated
    if (strpos($content, 'APIHelper.php') !== false) {
        echo "  ✓ Already updated\n\n";
        $skipped++;
        continue;
    }

    // Create backup
    $backupFile = $file . '.pre-apihelper-backup';
    if (!file_exists($backupFile)) {
        file_put_contents($backupFile, $content);
        echo "  → Backup created: $filename.pre-apihelper-backup\n";
    }

    // Add APIHelper include after other requires
    if (preg_match('/^(.*?)(require_once __DIR__ \. \'\/\.\.\/lib\/ConfidenceScorer\.php\';)/ms', $content, $matches)) {
        $newContent = $matches[1] . $matches[2] . "\n";
        $newContent .= "require_once __DIR__ . '/../lib/APIHelper.php';\n";
        $newContent .= "\nuse CIS\\BankTransactions\\API\\APIHelper;\n";
        $newContent .= substr($content, strlen($matches[0]));
        $content = $newContent;
        echo "  → Added APIHelper include\n";
    } else if (preg_match('/^(.*?)(require_once __DIR__ \. \'\/\.\.\/models\/\w+\.php\';)/ms', $content, $matches)) {
        $lastRequire = strrpos($content, "require_once __DIR__ . '/../");
        $insertPos = strpos($content, "\n", $lastRequire) + 1;
        $newContent = substr($content, 0, $insertPos);
        $newContent .= "require_once __DIR__ . '/../lib/APIHelper.php';\n";
        $newContent .= "\nuse CIS\\BankTransactions\\API\\APIHelper;\n\n";
        $newContent .= substr($content, $insertPos);
        $content = $newContent;
        echo "  → Added APIHelper include\n";
    }

    // Replace authentication check
    $oldAuthPattern = '/\/\/ Check if user is authenticated\s*if \(!isset\(\$_SESSION\[\'user_id\'\]\)\) \{\s*http_response_code\(401\);\s*echo json_encode\(\[\s*\'success\' => false,\s*\'error\' => \[\s*\'code\' => \'UNAUTHENTICATED\',\s*\'message\' => \'Authentication required\'\s*\]\s*\]\);\s*exit;\s*\}/ms';

    if (preg_match($oldAuthPattern, $content)) {
        $content = preg_replace($oldAuthPattern, '// Require authentication (supports bot bypass)' . "\n" . '$userId = APIHelper::requireAuth();', $content);
        echo "  → Updated authentication check\n";
    }

    // Replace permission check (multiple variations)
    $oldPermPattern = '/\/\/ Check permission\s*\$requiredPermission = [\'"]([a-z_\.]+)[\'"];\s*if \(!isset\(\$_SESSION\[\'permissions\'\]\) \|\| !in_array\(\$requiredPermission, \$_SESSION\[\'permissions\'\]\)\) \{[^}]*\}\s*exit;\s*\}/ms';

    if (preg_match($oldPermPattern, $content, $matches)) {
        $permission = $matches[1];
        $content = preg_replace($oldPermPattern, '// Require permission (supports bot bypass)' . "\n" . "APIHelper::requirePermission('$permission');", $content);
        echo "  → Updated permission check\n";
    }

    // Replace METHOD check
    $content = preg_replace(
        '/\/\/ Only accept (GET|POST) requests\s*if \(\$_SERVER\[\'REQUEST_METHOD\'\] !== \'(GET|POST)\'\) \{\s*http_response_code\(405\);\s*echo json_encode\(\[[^\]]*\]\);\s*exit;\s*\}/ms',
        '// Only accept $1 requests' . "\n" . 'if ($_SERVER[\'REQUEST_METHOD\'] !== \'$1\') {' . "\n" . '    APIHelper::error(\'METHOD_NOT_ALLOWED\', \'Only $1 requests are allowed\', 405);' . "\n" . '}',
        $content
    );

    // Save updated content
    if (file_put_contents($file, $content)) {
        echo "  ✓ Successfully updated\n\n";
        $updated++;
    } else {
        echo "  ✗ Failed to update\n\n";
        $errors++;
    }
}

echo "═══════════════════════════════════════════════════════════════════════════\n";
echo " Summary:\n";
echo "═══════════════════════════════════════════════════════════════════════════\n";
echo " Updated:  $updated files\n";
echo " Skipped:  $skipped files\n";
echo " Errors:   $errors files\n";
echo "\n";

if ($updated > 0) {
    echo "✓ Batch update complete!\n";
    echo "\nTo restore backups:\n";
    echo "  for f in api/*.pre-apihelper-backup; do mv \"\$f\" \"\${f%.pre-apihelper-backup}\"; done\n";
} else {
    echo "⊘ No files needed updating\n";
}

echo "\n";
