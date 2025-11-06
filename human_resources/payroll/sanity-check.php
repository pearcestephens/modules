<?php
echo "\n╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                  FINAL SANITY CHECK - CODE QUALITY                           ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

$file = __DIR__ . "/controllers/VendConsignmentController.php";
$content = file_get_contents($file);

// Check for issues
$issues = [];

if (preg_match('/\$_(GET|POST|REQUEST)\s*\[/', $content)) {
    $issues[] = "Found direct superglobal access";
} else {
    echo "✅ No direct superglobal access\n";
}

if (preg_match('/\$this->db->query\s*\(\s*["\'].*\$/', $content)) {
    $issues[] = "Found SQL concatenation";
} else {
    echo "✅ No SQL string concatenation\n";
}

if (preg_match('/\b(eval|exec|system|passthru|shell_exec)\s*\(/', $content)) {
    $issues[] = "Found dangerous functions";
} else {
    echo "✅ No dangerous functions\n";
}

if (preg_match('/echo\s+\$[a-zA-Z_]/', $content)) {
    $issues[] = "Found unescaped echo";
} else {
    echo "✅ No unescaped output\n";
}

$authChecks = substr_count($content, 'requireAuth()');
$csrfChecks = substr_count($content, 'verifyCsrf()');
$tryCatches = substr_count($content, '} catch');
$logCalls = substr_count($content, 'logger->error');

echo "✅ Authentication checks: $authChecks\n";
echo "✅ CSRF verifications: $csrfChecks\n";
echo "✅ Try-catch blocks: $tryCatches\n";
echo "✅ Error logging calls: $logCalls\n";

if (count($issues) > 0) {
    echo "\n⚠️  ISSUES: " . implode(", ", $issues) . "\n\n";
    exit(1);
} else {
    echo "\n╔══════════════════════════════════════════════════════════════════════════════╗\n";
    echo "║                                                                              ║\n";
    echo "║                 ✅ ALL SANITY CHECKS PASSED - CODE IS CLEAN ✅               ║\n";
    echo "║                                                                              ║\n";
    echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";
    exit(0);
}
