<?php
declare(strict_types=1);

namespace Modules\Base\Repositories;

use PDO;

/**
 * Base Repository - Foundation for all data access repositories
 * 
 * Provides common database operations with proper error handling
 * and performance optimization.
 */
abstract class BaseRepository
{
    protected PDO $pdo;
    protected string $table = '';
    protected string $modelClass = '';

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? $this->getDefaultPdo();
    }

    /**
     * Get default PDO connection
     */
    protected function getDefaultPdo(): PDO
    {
        return \cis_pdo();
    }

    /**
     * Insert a new record
     */
    protected function insert(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = ':' . implode(', :', $columns);
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES ({$placeholders})";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update record by ID
     */
    protected function updateById(int $id, array $data): bool
    {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "{$column} = :{$column}";
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts) . " WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($data + ['id' => $id]);
        
        return $result && $stmt->rowCount() > 0;
    }

    /**
     * Select single record with WHERE conditions
     */
    protected function selectOneWhere(array $conditions): ?array
    {
        $whereParts = [];
        foreach (array_keys($conditions) as $column) {
            $whereParts[] = "{$column} = :{$column}";
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $whereParts) . " LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($conditions);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Select multiple records with WHERE conditions
     */
    protected function selectWhere(array $conditions, ?string $orderBy = null, ?int $limit = null): array
    {
        $whereParts = [];
        foreach (array_keys($conditions) as $column) {
            $whereParts[] = "{$column} = :{$column}";
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $whereParts);
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($conditions);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete record by ID (soft delete if deleted_at column exists)
     */
    protected function deleteById(int $id): bool
    {
        // Check if table has soft delete column
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM {$this->table} LIKE 'deleted_at'");
        $stmt->execute();
        
        if ($stmt->fetch()) {
            // Soft delete
            return $this->updateById($id, ['deleted_at' => date('Y-m-d H:i:s')]);
        } else {
            // Hard delete
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
            $result = $stmt->execute([$id]);
            return $result && $stmt->rowCount() > 0;
        }
    }

    /**
     * Count records with conditions
     */
    protected function count(array $conditions = []): int
    {
        if (empty($conditions)) {
            $sql = "SELECT COUNT(*) FROM {$this->table}";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
        } else {
            $whereParts = [];
            foreach (array_keys($conditions) as $column) {
                $whereParts[] = "{$column} = :{$column}";
            }
            
            $sql = "SELECT COUNT(*) FROM {$this->table} WHERE " . implode(' AND ', $whereParts);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($conditions);
        }
        
        return (int)$stmt->fetchColumn();
    }

    /**
     * Execute raw SQL query
     */
    protected function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Hydrate database row into domain model (to be implemented by subclasses)
     */
    abstract protected function hydrate(array $row): mixed;
}