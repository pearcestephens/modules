<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/env-loader.php';

header('Content-Type: application/json');

$response = [
    'ok' => false,
    'checks' => [],
];

try {
    $pdo = createConnection();

    $response['checks'][] = runCheck('db_ping', static function () use ($pdo) {
        $stmt = $pdo->query('SELECT 1');
        return (int) $stmt->fetchColumn() === 1;
    });

    $response['checks'][] = runCheck('table_exists:deputy_timesheets', static function () use ($pdo) {
        return tableExists($pdo, 'deputy_timesheets');
    });

    $response['checks'][] = runCheck('table_exists:payroll_activity_log', static function () use ($pdo) {
        return tableExists($pdo, 'payroll_activity_log');
    });

    $response['checks'][] = runCheck('table_exists:payroll_rate_limits', static function () use ($pdo) {
        return tableExists($pdo, 'payroll_rate_limits');
    });

    $response['checks'][] = runCheck('table_exists:payroll_auth_audit_log', static function () use ($pdo) {
        return tableExists($pdo, 'payroll_auth_audit_log');
    });

    $response['ok'] = collectOkStatus($response['checks']);
} catch (Throwable $exception) {
    $response['checks'][] = [
        'name' => 'bootstrap',
        'ok' => false,
        'error' => $exception->getMessage(),
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

/**
 * @return PDO
 */
function createConnection(): PDO
{
    $host = (string) env('PAYROLL_DB_HOST', env('DB_HOST', '127.0.0.1'));
    $port = (string) env('PAYROLL_DB_PORT', env('DB_PORT', '3306'));
    $name = (string) env('PAYROLL_DB_NAME', env('DB_DATABASE', 'jcepnzzkmj'));
    $user = (string) env('PAYROLL_DB_USER', env('DB_USERNAME', 'jcepnzzkmj'));
    $pass = (string) env('PAYROLL_DB_PASS', env('DB_PASSWORD', 'wprKh9Jq63'));

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/**
 * @param string $name
 * @param callable $callback
 * @return array{name:string,ok:bool,error?:string}
 */
function runCheck(string $name, callable $callback): array
{
    try {
        $ok = (bool) $callback();
        return [
            'name' => $name,
            'ok' => $ok,
        ];
    } catch (Throwable $exception) {
        return [
            'name' => $name,
            'ok' => false,
            'error' => $exception->getMessage(),
        ];
    }
}

/**
 * @param array<int,array{name:string,ok:bool,error?:string}> $checks
 */
function collectOkStatus(array $checks): bool
{
    foreach ($checks as $check) {
        if ($check['ok'] === false) {
            return false;
        }
    }

    return true;
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);

    return (int) $stmt->fetchColumn() === 1;
}
