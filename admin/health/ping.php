<?php
/**
 * Health Check Endpoint (real data)
 * Returns application health including DB connectivity, disk space, PHP version.
 */

declare(strict_types=1);

// Bootstrap app if present (tolerate absence to avoid fatals)
@require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php'; // tolerate absence in non-web contexts

// Fallback env() helper if not defined
if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $val = getenv($key);
        return $val !== false ? $val : $default;
    }
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD') {
    header('Allow: GET');
    http_response_code(405);
    exit;
}

$checks = [
    'environment' => env('APP_ENV', 'production'),
    'app_url'     => env('APP_URL', 'https://staff.vapeshed.co.nz'),
    'timestamp'   => gmdate('c'),
    'php'         => [
        'version' => PHP_VERSION,
        'extensions' => [
            'pdo' => extension_loaded('pdo'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'curl' => extension_loaded('curl'),
        ],
    ],
];

// Disk check
try {
    $diskTotal = @disk_total_space('/') ?: 0;
    $diskFree  = @disk_free_space('/') ?: 0;
    $checks['disk'] = [
        'total_bytes' => $diskTotal,
        'free_bytes'  => $diskFree,
        'free_percent' => $diskTotal > 0 ? round(($diskFree / $diskTotal) * 100, 2) : null,
    ];
} catch (\Throwable $e) {
    $checks['disk_error'] = $e->getMessage();
}

// Database check (supports either global $pdo or env-based PDO)
$dbStatus = [
    'connected' => false,
    'latency_ms' => null,
    'error' => null,
];

$pdo = $GLOBALS['pdo'] ?? null;

try {
    if (!$pdo instanceof \PDO) {
        // Try building a PDO from env if available
        $dbHost = env('DB_HOST', null);
        $dbName = env('DB_NAME', null);
        $dbUser = env('DB_USER', null);
        $dbPass = env('DB_PASS', null);
        if ($dbHost && $dbName && $dbUser !== null) {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', (string)$dbHost, (string)$dbName);
            $pdo = new \PDO($dsn, (string)$dbUser, (string)$dbPass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_TIMEOUT => 2,
            ]);
        }
    }

    if ($pdo instanceof \PDO) {
        $t0 = microtime(true);
        $stmt = $pdo->query('SELECT 1');
        $stmt->fetch();
        $dbStatus['connected'] = true;
        $dbStatus['latency_ms'] = round((microtime(true) - $t0) * 1000, 2);
    } else {
        $dbStatus['error'] = 'PDO unavailable';
    }
} catch (\Throwable $e) {
    $dbStatus['error'] = $e->getMessage();
}

$checks['database'] = $dbStatus;

http_response_code($dbStatus['connected'] ? 200 : 503);
echo json_encode([
    'success' => $dbStatus['connected'],
    'status' => $dbStatus['connected'] ? 'operational' : 'degraded',
    'data' => $checks,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
