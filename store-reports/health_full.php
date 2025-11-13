<?php
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/Response.php';

sr_require_auth(true); // admin only

$dbHealth = DatabaseManager::health();
$envSummary = [
    'php_version' => phpversion(),
    'memory_usage_mb' => round(memory_get_usage(true)/1048576,2),
    'extensions' => [
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'curl' => extension_loaded('curl'),
        'gd' => extension_loaded('gd'),
    ],
];

// Basic writable path checks
$paths = [
    'upload_dir' => SR_UPLOAD_DIR,
    'tmp_dir' => sys_get_temp_dir(),
];
$pathStatuses = [];
foreach ($paths as $k => $p) {
    $pathStatuses[$k] = [
        'path' => $p,
        'exists' => file_exists($p),
        'writable' => is_writable($p)
    ];
}

$meta = [
    'db' => $dbHealth,
    'environment' => $envSummary,
    'paths' => $pathStatuses,
];

SR_Response::json(['health' => 'ok'], 200, $meta);
