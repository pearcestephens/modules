<?php
/**
 * ============================================================================
 * CIS Base Database - PDO-First Unified Database Access Layer
 * ============================================================================
 *
 * Production-grade database wrapper with PDO as primary driver.
 * MySQLi available for legacy support but NOT auto-initialized.
 *
 * **PDO (Active - Auto-initialized):**
 *   - Primary driver for all new code
 *   - Modern, secure, feature-rich
 *   - Auto-connects on first use
 *   - Recommended for all development
 *
 * **MySQLi (Available - Manual initialization only):**
 *   - Legacy support for old code
 *   - Must call Database::initMySQLi() before use
 *   - Not recommended for new code
 *
 * **Quick Start:**
 *   Database::query($sql, $params)     // PDO - auto-initialized
 *   Database::insert($table, $data)    // PDO - auto-initialized
 *   Database::pdo()                    // Direct PDO access
 *
 * **For Legacy MySQLi Code:**
 *   Database::initMySQLi()             // Initialize first (required)
 *   Database::mysqli()                 // Then access MySQLi
 *
 * @package CIS\Base
 * @version 2.0.0 - PDO First
 * @author Pearce Stephens
 * @created 2025-10-27
 */

declare(strict_types=1);

namespace CIS\Base;

// Load database wrappers
require_once __DIR__ . '/DatabasePDO.php';
require_once __DIR__ . '/DatabaseMySQLi.php';

class Database
{
    /** @var bool Use PDO (true) or MySQLi (false) - Default: PDO */
    public const USE_PDO = true;

    private static bool $pdoInitialized = false;
    private static bool $mysqliInitialized = false;
    private static bool $vapeshedInitialized = false;
    private static ?\mysqli $vapeshedConnection = null;

    /**
     * Initialize PDO connection (auto-called on first use)
     * MySQLi is NOT auto-initialized - must be explicitly called
     */
    public static function init(): void
    {
        if (self::$pdoInitialized) return;

        // Load config from config/database.php
        $rootPath = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2);
        $configPath = $rootPath . '/config/database.php';

        if (!file_exists($configPath)) {
            throw new \RuntimeException("Database config not found at: {$configPath}");
        }

        $config = require_once $configPath;
        $dbConfig = $config['cis'] ?? [];

        // ONLY initialize PDO by default
        DatabasePDO::configure([
            'host' => $dbConfig['host'] ?? $_ENV['DB_HOST'] ?? '127.0.0.1',
            'database' => $dbConfig['database'] ?? $_ENV['DB_NAME'] ?? 'jcepnzzkmj',
            'username' => $dbConfig['username'] ?? $_ENV['DB_USER'] ?? 'jcepnzzkmj',
            'password' => $dbConfig['password'] ?? $_ENV['DB_PASSWORD'] ?? 'wprKh9Jq63',
        ]);

        self::$pdoInitialized = true;
    }

    /**
     * Initialize MySQLi connection (MUST be called explicitly)
     * Use this ONLY if you need MySQLi for legacy code
     */
    public static function initMySQLi(): void
    {
        if (self::$mysqliInitialized) return;

        DatabaseMySQLi::configure([
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'database' => $_ENV['DB_NAME'] ?? 'jcepnzzkmj',
            'username' => $_ENV['DB_USER'] ?? 'jcepnzzkmj',
            'password' => $_ENV['DB_PASSWORD'] ?? 'wprKh9Jq63',
        ]);

        self::$mysqliInitialized = true;
    }

    /**
     * Initialize VapeShed database connection (for email queue)
     * This is a separate database connection for the VapeShed email system
     * NOTE: Currently uses main database until VapeShed credentials are configured
     */
    public static function initVapeShed(): void
    {
        if (self::$vapeshedInitialized) return;

        // Load config
        $configPath = dirname(dirname(dirname(__DIR__))) . '/config/database.php';
        if (!file_exists($configPath)) {
            $configPath = '/home/master/applications/jcepnzzkmj/public_html/config/database.php';
        }

        $config = require $configPath;

        // Try VapeShed config first, fallback to main database
        $vsConfig = $config['vapeshed'] ?? null;

        // Test if VapeShed credentials work, otherwise use main DB
        if ($vsConfig) {
            try {
                $testConn = new \mysqli(
                    $vsConfig['host'],
                    $vsConfig['username'],
                    $vsConfig['password'],
                    $vsConfig['database']
                );

                if (!$testConn->connect_error) {
                    // VapeShed credentials work!
                    self::$vapeshedConnection = $testConn;
                    self::$vapeshedConnection->set_charset('utf8mb4');
                    self::$vapeshedInitialized = true;
                    return;
                }
            } catch (\Exception $e) {
                // VapeShed credentials don't work, fallback to main DB
            }
        }

        // Fallback: Use main database for email queue
        $mainConfig = $config['cis'] ?? [];
        $host = $mainConfig['host'] ?? '127.0.0.1';
        $database = $mainConfig['database'] ?? 'jcepnzzkmj';
        $username = $mainConfig['username'] ?? 'jcepnzzkmj';
        $password = $mainConfig['password'] ?? 'wprKh9Jq63';

        self::$vapeshedConnection = new \mysqli($host, $username, $password, $database);

        if (self::$vapeshedConnection->connect_error) {
            throw new \RuntimeException(
                'VapeShed DB connection failed: ' . self::$vapeshedConnection->connect_error
            );
        }

        self::$vapeshedConnection->set_charset('utf8mb4');
        self::$vapeshedInitialized = true;
    }

    /**
     * Get VapeShed MySQLi connection
     * Auto-initializes on first call
     *
     * @return \mysqli VapeShed database connection
     */
    public static function vapeshed(): \mysqli
    {
        if (!self::$vapeshedInitialized) {
            self::initVapeShed();
        }
        return self::$vapeshedConnection;
    }

    /**
     * Get PDO connection (direct access)
     * Auto-initializes PDO on first call
     */
    public static function pdo(): \PDO
    {
        self::init();
        return DatabasePDO::connection();
    }

    /**
     * Get MySQLi connection (direct access)
     * Requires explicit initMySQLi() call first, or will throw exception
     */
    public static function mysqli(): \mysqli
    {
        if (!self::$mysqliInitialized) {
            throw new \RuntimeException(
                'MySQLi not initialized. Call Database::initMySQLi() first.'
            );
        }
        return DatabaseMySQLi::connection();
    }

    /**
     * Execute query using PDO (active driver)
     *
     * @param string $sql SQL query
     * @param array $params Parameters
     * @return array Query results
     */
    public static function query(string $sql, array $params = []): array
    {
        self::init();
        return DatabasePDO::query($sql, $params);
    }

    /**
     * Fetch one row using PDO (active driver)
     */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        self::init();
        return DatabasePDO::queryOne($sql, $params);
    }

    /**
     * Insert using PDO (active driver)
     */
    public static function insert(string $table, array $data): int
    {
        self::init();
        return DatabasePDO::insert($table, $data);
    }

    /**
     * Update using PDO (active driver)
     */
    public static function update(string $table, array $data, array $where): int
    {
        self::init();
        return DatabasePDO::update($table, $data, $where);
    }

    /**
     * Delete using PDO (active driver)
     */
    public static function delete(string $table, array $where): int
    {
        self::init();
        return DatabasePDO::delete($table, $where);
    }

    /**
     * Execute a statement (INSERT, UPDATE, DELETE) and return affected rows
     * For SELECT queries, use query() or queryOne() instead
     *
     * @param string $sql SQL statement
     * @param array $params Parameters
     * @return int Number of affected rows (or last insert ID for INSERT)
     */
    public static function execute(string $sql, array $params = []): int
    {
        self::init();
        $pdo = self::pdo();

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // For INSERT statements, return last insert ID
        if (stripos(trim($sql), 'INSERT') === 0) {
            $lastId = (int)$pdo->lastInsertId();
            return $lastId > 0 ? $lastId : $stmt->rowCount();
        }

        // For UPDATE/DELETE, return affected rows
        return $stmt->rowCount();
    }

    /**
     * Begin transaction (PDO)
     */
    public static function beginTransaction(): void
    {
        self::init();
        DatabasePDO::beginTransaction();
    }

    /**
     * Commit transaction (PDO)
     */
    public static function commit(): void
    {
        self::init();
        DatabasePDO::commit();
    }

    /**
     * Rollback transaction (PDO)
     */
    public static function rollback(): void
    {
        self::init();
        DatabasePDO::rollback();
    }

    /**
     * Query builder (PDO only - returns QueryBuilder instance)
     */
    public static function table(string $table): \CIS\Base\QueryBuilder
    {
        self::init();
        return DatabasePDO::table($table);
    }

    /**
     * Enable query logging (PDO)
     */
    public static function enableQueryLog(bool $enable = true): void
    {
        self::init();
        DatabasePDO::enableQueryLog($enable);
    }

    /**
     * Get query log (PDO)
     */
    public static function getQueryLog(): array
    {
        self::init();
        return DatabasePDO::getQueryLog();
    }

    /**
     * Get last executed query (PDO)
     */
    public static function getLastQuery(): string
    {
        self::init();
        return DatabasePDO::getLastQuery();
    }

    /**
     * Get active driver name
     */
    public static function getDriver(): string
    {
        return 'PDO';
    }

    /**
     * Legacy compatibility - get MySQLi instance
     * NOTE: Requires explicit Database::initMySQLi() call first
     */
    public static function getInstance(): \mysqli
    {
        return self::mysqli();
    }

    /**
     * Fetch all rows (alias for query())
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params);
    }

    /**
     * Fetch one row (alias for queryOne())
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        return self::queryOne($sql, $params);
    }

    /**
     * Last insert ID (PDO)
     */
    public static function lastInsertId(): int
    {
        self::init();
        return (int)self::pdo()->lastInsertId();
    }
}
