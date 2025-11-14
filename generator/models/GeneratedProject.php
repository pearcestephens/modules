<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * GeneratedProject Model
 *
 * Manages project records in the intelligent generator system.
 */
class GeneratedProject
{
    private PDO $db;
    private string $table = 'generated_projects';

    public function __construct()
    {
        $this->db = $this->getDatabase();
        $this->ensureTable();
    }

    /**
     * Create a new project
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO {$this->table}
            (name, description, blueprint, status, created_by, created_at)
            VALUES (:name, :description, :blueprint, :status, :created_by, :created_at)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'],
            ':description' => $data['description'] ?? '',
            ':blueprint' => $data['blueprint'] ?? 'minimal',
            ':status' => $data['status'] ?? 'queued',
            ':created_by' => $data['created_by'] ?? 0,
            ':created_at' => date('Y-m-d H:i:s'),
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Find project by ID
     */
    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get recent projects
     */
    public function getRecent(int $limit = 10): array
    {
        $sql = "
            SELECT * FROM {$this->table}
            ORDER BY created_at DESC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get projects with pagination
     */
    public function paginate(int $page = 1, int $per_page = 20): array
    {
        $offset = ($page - 1) * $per_page;

        $sql = "
            SELECT * FROM {$this->table}
            ORDER BY created_at DESC
            LIMIT :offset, :limit
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count total projects
     */
    public function count(): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $result = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0);
    }

    /**
     * Get project statistics
     */
    public function getStats(): array
    {
        $sql = "
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'complete' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as queued,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM {$this->table}
        ";

        return $this->db->query($sql)->fetch(PDO::FETCH_ASSOC) ?? [];
    }

    /**
     * Update project status
     */
    public function updateStatus(int $id, string $status): bool
    {
        $sql = "UPDATE {$this->table} SET status = :status, updated_at = :updated_at WHERE id = :id";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':status' => $status,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id' => $id,
        ]);
    }

    /**
     * Get database connection
     */
    private function getDatabase(): PDO
    {
        $db_host = $_ENV['DB_HOST'] ?? 'localhost';
        $db_name = $_ENV['DB_NAME'] ?? 'cis';
        $db_user = $_ENV['DB_USER'] ?? 'root';
        $db_pass = $_ENV['DB_PASS'] ?? '';

        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
        return new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * Ensure table exists
     */
    private function ensureTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS {$this->table} (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                blueprint VARCHAR(50) DEFAULT 'minimal',
                status VARCHAR(50) DEFAULT 'queued',
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        try {
            $this->db->exec($sql);
        } catch (\Exception $e) {
            // Table might already exist
        }
    }
}
