<?php
declare(strict_types=1);

/**
 * Size Guard - Ensures JS/CSS bundles stay within budget limits
 */

$budgets = [
    __DIR__.'/../assets/js/core.bundle.js'         => 30 * 1024,  // 30 KB
    __DIR__.'/../assets/js/pack.bundle.js'         => 70 * 1024,  // 70 KB
    __DIR__.'/../assets/js/receive.bundle.js'      => 50 * 1024,  // 50 KB
    __DIR__.'/../css/transfer-core.css'            => 20 * 1024,  // 20 KB
    __DIR__.'/../css/transfer-pack.css'            => 25 * 1024,  // 25 KB
    __DIR__.'/../css/transfer-receive.css'         => 25 * 1024,  // 25 KB
];

$failures = [];
$passes = [];

foreach ($budgets as $file => $limit) {
    if (!is_file($file)) {
        $failures[] = "❌ MISSING: " . basename($file);
        continue;
    }
    
    $size = filesize($file);
    $limitKB = round($limit / 1024, 1);
    $sizeKB = round($size / 1024, 1);
    
    if ($size > $limit) {
        $overageKB = round(($size - $limit) / 1024, 1);
        $failures[] = "❌ " . basename($file) . " is {$sizeKB}KB (limit: {$limitKB}KB, over by {$overageKB}KB)";
    } else {
        $passes[] = "✅ " . basename($file) . " is {$sizeKB}KB (limit: {$limitKB}KB)";
    }
}

echo "\n=== SIZE GUARD REPORT ===\n\n";

if (!empty($passes)) {
    foreach ($passes as $pass) {
        echo $pass . "\n";
    }
}

if (!empty($failures)) {
    echo "\n";
    foreach ($failures as $failure) {
        echo $failure . "\n";
    }
    echo "\n❌ SIZE GUARD FAILED\n";
    echo "Some bundles exceed their size budget. Consider:\n";
    echo "  - Removing unused code\n";
    echo "  - Splitting large bundles\n";
    echo "  - Minifying assets\n";
    echo "  - Lazy-loading heavy features\n\n";
    exit(1);
}

echo "\n✅ ALL BUNDLES WITHIN BUDGET\n\n";
exit(0);
