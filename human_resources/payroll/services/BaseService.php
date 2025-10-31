<?php
declare(strict_types=1);

namespace PayrollModule\Services;

/**
 * Base Service for Payroll Module
 *
 * Provides common functionality for all payroll services:
 * - Database connection and query execution
 * - Transaction management
 * - Error handling
 * - Logging integration
 * - Query builder helpers
 *
 * @package PayrollModule\Services
 * @version 1.0.0
 */

use PayrollModule\Lib\PayrollLogger;
use PDO;
use PDOException;

abstract class BaseService
{
    protected PDO $db;
    protected PayrollLogger $logger;
    protected bool $inTransaction = false;

    /**
     * Constructor - Initialize database connection
     */
    public function __construct()
    {
        $this->logger = new PayrollLogger();
        $this->initializeDatabase();
    }

    /**
     * Initialize database connection
     */
    protected function initializeDatabase(): void
    {
        try {
            $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $dbname = $_ENV['DB_NAME'] ?? 'jcepnzzkmj';
            $username = $_ENV['DB_USER'] ?? 'jcepnzzkmj';
            $password = $_ENV['DB_PASS'] ?? 'wprKh9Jq63';

            $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

            $this->db = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);

        } catch (PDOException $e) {
            $this->logger->critical('Database connection failed', [
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Database connection failed');
        }
    }

    /**
     * Begin database transaction
     */
    protected function beginTransaction(): void
    {
        if (!$this->inTransaction) {
            $this->db->beginTransaction();
            $this->inTransaction = true;
            $this->logger->debug('Transaction started');
        }
    }

    /**
     * Commit database transaction
     */
    protected function commit(): void
    {
        if ($this->inTransaction) {
            $this->db->commit();
            $this->inTransaction = false;
            $this->logger->debug('Transaction committed');
        }
    }

    /**
     * Rollback database transaction
     */
    protected function rollback(): void
    {
        if ($this->inTransaction) {
            $this->db->rollBack();
            $this->inTransaction = false;
            $this->logger->warning('Transaction rolled back');
        }
    }

    /**
     * Execute a SELECT query with parameters
     *
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return array Query results
     */
    protected function query(string $sql, array $params = []): array
    {
        $startTime = microtime(true);

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll();

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->debug('Query executed', [
                'sql' => $sql,
                'params' => $params,
                'rows' => count($result),
                'duration_ms' => $duration
            ]);

            return $result;

        } catch (PDOException $e) {
            $this->logger->error('Query failed', [
                'sql' => $sql,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Execute a SELECT query and return first row only
     *
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return array|null First row or null
     */
    protected function queryOne(string $sql, array $params = []): ?array
    {
        $results = $this->query($sql, $params);
        return $results[0] ?? null;
    }

    /**
     * Execute an INSERT query and return last insert ID
     *
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return int Last insert ID
     */
    protected function insert(string $sql, array $params = []): int
    {
        $startTime = microtime(true);

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $lastId = (int)$this->db->lastInsertId();

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->info('Insert executed', [
                'sql' => $sql,
                'params' => $params,
                'last_id' => $lastId,
                'duration_ms' => $duration
            ]);

            return $lastId;

        } catch (PDOException $e) {
            $this->logger->error('Insert failed', [
                'sql' => $sql,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Execute an UPDATE query and return affected rows
     *
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return int Number of affected rows
     */
    protected function update(string $sql, array $params = []): int
    {
        $startTime = microtime(true);

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $affected = $stmt->rowCount();

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->info('Update executed', [
                'sql' => $sql,
                'params' => $params,
                'affected_rows' => $affected,
                'duration_ms' => $duration
            ]);

            return $affected;

        } catch (PDOException $e) {
            $this->logger->error('Update failed', [
                'sql' => $sql,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Execute a DELETE query and return affected rows
     *
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return int Number of affected rows
     */
    protected function delete(string $sql, array $params = []): int
    {
        $startTime = microtime(true);

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $affected = $stmt->rowCount();

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->warning('Delete executed', [
                'sql' => $sql,
                'params' => $params,
                'affected_rows' => $affected,
                'duration_ms' => $duration
            ]);

            return $affected;

        } catch (PDOException $e) {
            $this->logger->error('Delete failed', [
                'sql' => $sql,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Check if a record exists
     *
     * @param string $table Table name
     * @param array $conditions WHERE conditions [column => value]
     * @return bool True if exists
     */
    protected function exists(string $table, array $conditions): bool
    {
        $where = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            $where[] = "`{$column}` = ?";
            $params[] = $value;
        }

        $sql = "SELECT COUNT(*) as count FROM `{$table}` WHERE " . implode(' AND ', $where);
        $result = $this->queryOne($sql, $params);

        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Find record by ID
     *
     * @param string $table Table name
     * @param int $id Record ID
     * @return array|null Record data or null
     */
    protected function findById(string $table, int $id): ?array
    {
        $sql = "SELECT * FROM `{$table}` WHERE `id` = ? LIMIT 1";
        return $this->queryOne($sql, [$id]);
    }

    /**
     * Build WHERE clause from conditions array
     *
     * @param array $conditions [column => value] or [column => [operator, value]]
     * @return array [sql_fragment, params]
     */
    protected function buildWhere(array $conditions): array
    {
        $where = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            if (is_array($value)) {
                // Format: ['>', 100] or ['LIKE', '%test%']
                list($operator, $val) = $value;
                $where[] = "`{$column}` {$operator} ?";
                $params[] = $val;
            } else {
                // Format: simple equality
                $where[] = "`{$column}` = ?";
                $params[] = $value;
            }
        }

        $sql = empty($where) ? '' : implode(' AND ', $where);

        return [$sql, $params];
    }

    /**
     * Sanitize table/column name to prevent SQL injection
     */
    protected function sanitizeIdentifier(string $identifier): string
    {
        // Remove any backticks and only allow alphanumeric and underscore
        return preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);
    }

    /**
     * Log slow query if duration exceeds threshold
     */
    protected function checkSlowQuery(string $sql, float $durationMs, float $threshold = 300.0): void
    {
        if ($durationMs > $threshold) {
            $this->logger->warning('Slow query detected', [
                'sql' => $sql,
                'duration_ms' => $durationMs,
                'threshold_ms' => $threshold
            ]);
        }
    }
}
