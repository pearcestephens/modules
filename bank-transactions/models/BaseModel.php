<?php
/**
 * BaseModel - Abstract database model
 *
 * Provides common CRUD operations for all models
 *
 * @package CIS\BankTransactions\Models
 */

declare(strict_types=1);

namespace CIS\BankTransactions\Models;

use CIS\Base\Database;
use PDO;
use PDOException;

abstract class BaseModel
{
    protected $db;
    protected $table;
    protected $primaryKey = 'id';

    /**
     * Constructor
     *
     * @param PDO $db Database connection (optional - uses CIS\Base\Database if not provided)
     */
    public function __construct(PDO $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
        } else {
            // Use base module database
            $this->db = Database::pdo();
        }
    }

    /**
     * Find record by ID
     *
     * @param int $id Record ID
     * @return array|null Record data or null if not found
     */
    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Find all records matching conditions
     *
     * @param array $conditions WHERE conditions (key => value)
     * @param array $options Query options (order, limit, offset)
     * @return array Array of records
     */
    public function findAll(array $conditions = [], array $options = []): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];

        // Build WHERE clause
        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $key => $value) {
                if ($value === null) {
                    $where[] = "$key IS NULL";
                } else {
                    $where[] = "$key = ?";
                    $params[] = $value;
                }
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        // Add ORDER BY
        if (isset($options['order'])) {
            $sql .= " ORDER BY " . $options['order'];
        }

        // Add LIMIT
        if (isset($options['limit'])) {
            $sql .= " LIMIT " . (int)$options['limit'];

            if (isset($options['offset'])) {
                $sql .= " OFFSET " . (int)$options['offset'];
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count records matching conditions
     *
     * @param array $conditions WHERE conditions
     * @return int Record count
     */
    public function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        $params = [];

        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $key => $value) {
                if ($value === null) {
                    $where[] = "$key IS NULL";
                } else {
                    $where[] = "$key = ?";
                    $params[] = $value;
                }
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Insert new record
     *
     * @param array $data Record data
     * @return int|false Last insert ID or false on failure
     */
    public function insert(array $data)
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->db->prepare($sql);

        if ($stmt->execute(array_values($data))) {
            return (int)$this->db->lastInsertId();
        }

        return false;
    }

    /**
     * Update record by ID
     *
     * @param int $id Record ID
     * @param array $data Updated data
     * @return bool Success status
     */
    public function update(int $id, array $data): bool
    {
        $set = [];
        $params = [];

        foreach ($data as $key => $value) {
            $set[] = "$key = ?";
            $params[] = $value;
        }

        $params[] = $id;

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s = ?",
            $this->table,
            implode(', ', $set),
            $this->primaryKey
        );

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete record by ID
     *
     * @param int $id Record ID
     * @return bool Success status
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Begin database transaction
     */
    public function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    /**
     * Commit database transaction
     */
    public function commit(): void
    {
        $this->db->commit();
    }

    /**
     * Rollback database transaction
     */
    public function rollback(): void
    {
        $this->db->rollBack();
    }

    /**
     * Execute raw SQL query
     *
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return array Query results
     */
    protected function query(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute raw SQL query (no results)
     *
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return bool Success status
     */
    protected function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}
