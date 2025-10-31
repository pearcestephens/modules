<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Test: Loading dashboard index\n\n";

try {
    $_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? '/home/129337.cloudwaysapps.com/jcepnzzkmj/public_html';
    $_GET['route'] = 'dashboard';
    $_GET['bot'] = 'true';

    echo "Loading index.php...\n";
    require __DIR__ . '/index.php';
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}
?>
