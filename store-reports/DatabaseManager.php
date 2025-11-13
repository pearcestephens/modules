<?php
/**
 * Central DatabaseManager for Store Reports module.
 * Provides singleton-style PDO access with retry, diagnostics, and health metadata.
 */
class DatabaseManager
{
    private static ?PDO $pdo = null;
    private static array $lastError = [];
    private const MAX_RETRIES = 2;

    public static function init(array $config = []): void
    {
        if (self::$pdo) { return; }
        $host = $config['host'] ?? env('DB_HOST','127.0.0.1');
        $db   = $config['database'] ?? env('DB_NAME','jcepnzzkmj');
        $user = $config['user'] ?? env('DB_USER','jcepnzzkmj');
        $pass = $config['password'] ?? env('DB_PASS', env('DB_PASSWORD',''));
        if ($pass === '') { $pass = env('DB_PASSWORD',''); }
        $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
        $attempt = 0;
        while ($attempt <= self::MAX_RETRIES && !self::$pdo) {
            try {
                $attempt++;
                self::$pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => false,
                ]);
            } catch (Throwable $e) {
                self::$lastError = [
                    'message' => $e->getMessage(),
                    'code' => method_exists($e,'getCode') ? $e->getCode() : null,
                    'sqlstate' => ($e instanceof PDOException) ? $e->getCode() : null,
                    'dsn' => $dsn,
                    'attempt' => $attempt,
                    'trace' => substr($e->getTraceAsString(),0,1000)
                ];
                // small sleep to avoid hammering
                usleep(150000);
            }
        }
    }

    public static function pdo(): ?PDO
    {
        if (!self::$pdo) { self::init(); }
        return self::$pdo;
    }

    public static function available(): bool
    {
        return self::$pdo instanceof PDO;
    }

    public static function lastError(): array
    {
        return self::$lastError;
    }

    public static function health(): array
    {
        $ok = self::available();
        return [
            'db_available' => $ok,
            'error' => $ok ? null : self::$lastError,
            'driver' => $ok ? self::$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) : null,
            'server_version' => $ok ? self::$pdo->getAttribute(PDO::ATTR_SERVER_VERSION) : null,
            'client_version' => defined('PDO::ATTR_CLIENT_VERSION') && $ok ? self::$pdo->getAttribute(PDO::ATTR_CLIENT_VERSION) : null,
        ];
    }
}
