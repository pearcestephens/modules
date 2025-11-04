<?php
/**
 * Database Service - PDO-First Database Access Layer
 *
 * Modern database service with dependency injection support.
 * Uses PDO as primary driver with configuration from container.
 *
 * @package CIS\Base\Core
 * @version 2.0.0
 */

declare(strict_types=1);

namespace CIS\Base\Core;

use PDO;
use PDOStatement;

class Database
{
    private PDO $pdo;
    private array $config;

    /**
     * Create database instance
     */
    public function __construct(Application $app)
    {
        $this->config = $app->config('database', []);
        $this->connect();
    }

    /**
     * Connect to database
     */
    private function connect(): void
    {
        $host = $this->config['host'] ?? '127.0.0.1';
        $database = $this->config['database'] ?? 'jcepnzzkmj';
        $username = $this->config['username'] ?? 'jcepnzzkmj';
        $password = $this->config['password'] ?? 'wprKh9Jq63';
        $charset = $this->config['charset'] ?? 'utf8mb4';
        $collation = $this->config['collation'] ?? 'utf8mb4_unicode_ci';

        $dsn = "mysql:host={$host};dbname={$database};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$collation}",
        ];

        $this->pdo = new PDO($dsn, $username, $password, $options);
    }

    /**
     * Get PDO instance
     */
    public function connection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Execute query and return all results
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->prepare($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Execute query and return first result
     */
    public function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->prepare($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Execute query and return single value
     */
    public function queryValue(string $sql, array $params = [])
    {
        $stmt = $this->prepare($sql, $params);
        return $stmt->fetchColumn();
    }

    /**
     * Execute update/delete/insert query
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->prepare($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Insert record and return ID
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":{$col}", $columns);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);

        foreach ($data as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update records
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "{$column} = :{$column}";
        }

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $setParts),
            $where
        );

        $stmt = $this->pdo->prepare($sql);

        // Bind data values
        foreach ($data as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        // Bind where parameters
        foreach ($whereParams as $key => $value) {
            $param = is_int($key) ? $key + 1 : $key;
            $stmt->bindValue($param, $value);
        }

        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Delete records
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->execute($sql, $params);
    }

    /**
     * Prepare and execute statement
     */
    private function prepare(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $param = is_int($key) ? $key + 1 : $key;
            $stmt->bindValue($param, $value);
        }

        $stmt->execute();
        return $stmt;
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Check if in transaction
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Execute transaction with callback
     */
    public function transaction(callable $callback)
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Table exists check
     */
    public function tableExists(string $table): bool
    {
        $sql = "SHOW TABLES LIKE ?";
        $result = $this->queryValue($sql, [$table]);
        return $result !== false;
    }

    /**
     * Count records
     */
    public function count(string $table, string $where = '1=1', array $params = []): int
    {
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        return (int) $this->queryValue($sql, $params);
    }

    /**
     * Check if record exists
     */
    public function exists(string $table, string $where, array $params = []): bool
    {
        return $this->count($table, $where, $params) > 0;
    }
}
