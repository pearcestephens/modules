<?php
/**
 * Path debugging test
 */

echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "__DIR__: " . __DIR__ . "\n";
echo "__FILE__: " . __FILE__ . "\n";
echo "\n";

$bootstrapPath = $_SERVER['DOCUMENT_ROOT'] . '/modules/base/bootstrap.php';
echo "Bootstrap path: " . $bootstrapPath . "\n";
echo "Bootstrap exists: " . (file_exists($bootstrapPath) ? 'YES' : 'NO') . "\n";
echo "\n";

if (!file_exists($bootstrapPath)) {
    echo "Trying alternate paths:\n";
    
    $alt1 = '/home/master/applications/jcepnzzkmj/public_html/modules/base/bootstrap.php';
    echo "Alt 1: $alt1 - " . (file_exists($alt1) ? 'EXISTS' : 'MISSING') . "\n";
    
    $alt2 = dirname(dirname(__DIR__)) . '/base/bootstrap.php';
    echo "Alt 2: $alt2 - " . (file_exists($alt2) ? 'EXISTS' : 'MISSING') . "\n";
    
    $alt3 = __DIR__ . '/../base/bootstrap.php';
    echo "Alt 3: $alt3 - " . (file_exists($alt3) ? 'EXISTS' : 'MISSING') . "\n";
}

echo "\nAll require paths from theme-builder.php:\n";
echo "1. " . $_SERVER['DOCUMENT_ROOT'] . '/modules/base/bootstrap.php' . "\n";
echo "2. " . __DIR__ . '/lib/ThemeGenerator.php' . "\n";
echo "3. " . __DIR__ . '/lib/AIThemeAssistant.php' . "\n";
