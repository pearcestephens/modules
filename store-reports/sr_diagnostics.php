<?php
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/Response.php';

// CLI only safeguard
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'CLI only';
    exit(1);
}

$health = DatabaseManager::health();
$summary = [
    'db_available' => $health['db_available'],
    'driver' => $health['driver'],
    'server_version' => $health['server_version'],
    'memory_mb' => round(memory_get_usage(true)/1048576,2),
    'php_version' => phpversion(),
    'upload_dir_writable' => is_writable(SR_UPLOAD_DIR),
    'env_host' => env('DB_HOST'),
    'env_name' => env('DB_NAME'),
    'env_user' => env('DB_USER'),
];
echo json_encode(['diagnostics' => $summary, 'raw' => $health], JSON_PRETTY_PRINT)."\n";
exit(0);
