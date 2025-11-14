<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * ProjectBuild Model
 *
 * Manages build history and execution records.
 */
class ProjectBuild
{
    private PDO $db;
    private string $table = 'project_builds';

    public function __construct()
    {
        $this->db = $this->getDatabase();
        $this->ensureTable();
    }

    /**
     * Create a new build record
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO {$this->table}
            (project_id, prompt, context, options, status, created_at)
            VALUES (:project_id, :prompt, :context, :options, :status, :created_at)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':project_id' => $data['project_id'],
            ':prompt' => $data['prompt'],
            ':context' => $data['context'] ?? '',
            ':options' => $data['options'] ?? '{}',
            ':status' => $data['status'] ?? 'queued',
            ':created_at' => date('Y-m-d H:i:s'),
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Find build by ID
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
     * Get builds by project
     */
    public function getByProject(int $project_id): array
    {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE project_id = :project_id
            ORDER BY created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':project_id' => $project_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get recent builds
     */
    public function getRecent(int $limit = 10): array
    {
        $sql = "
            SELECT b.*, p.name as project_name FROM {$this->table} b
            LEFT JOIN generated_projects p ON b.project_id = p.id
            ORDER BY b.created_at DESC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update build status
     */
    public function updateStatus(int $id, string $status, ?array $metadata = null): bool
    {
        $sql = "UPDATE {$this->table} SET status = :status, updated_at = :updated_at";

        if ($metadata) {
            $sql .= ", metadata = :metadata";
        }

        $sql .= " WHERE id = :id";

        $stmt = $this->db->prepare($sql);

        $params = [
            ':status' => $status,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id' => $id,
        ];

        if ($metadata) {
            $params[':metadata'] = json_encode($metadata);
        }

        return $stmt->execute($params);
    }

    /**
     * Record build execution
     */
    public function recordExecution(int $id, string $execution_id, array $audit): bool
    {
        $sql = "
            UPDATE {$this->table}
            SET execution_id = :execution_id,
                audit = :audit,
                updated_at = :updated_at
            WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':execution_id' => $execution_id,
            ':audit' => json_encode($audit),
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
                project_id INT NOT NULL,
                execution_id VARCHAR(255),
                prompt TEXT NOT NULL,
                context LONGTEXT,
                options JSON,
                audit LONGTEXT,
                metadata JSON,
                status VARCHAR(50) DEFAULT 'queued',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES generated_projects(id) ON DELETE CASCADE,
                INDEX idx_project (project_id),
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
