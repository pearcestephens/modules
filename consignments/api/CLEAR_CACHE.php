<?php
/**
 * EMERGENCY CACHE CLEAR
 * Run this file ONCE to clear PHP opcache
 */

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ Opcache cleared!\n";
} else {
    echo "⚠️ Opcache not available\n";
}

if (function_exists('apc_clear_cache')) {
    apc_clear_cache();
    echo "✅ APC cache cleared!\n";
}

// Touch the file to force reload
touch(__DIR__ . '/lightspeed.php');
touch(__DIR__ . '/simple-upload.php');

echo "✅ Files touched - cache should be cleared!\n";
echo "✅ Try your upload again now!\n";
