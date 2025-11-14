<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * ProjectIdea Model
 *
 * Manages project ideas and suggestions for AI-powered generation.
 */
class ProjectIdea
{
    private PDO $db;
    private string $table = 'project_ideas';

    public function __construct()
    {
        $this->db = $this->getDatabase();
        $this->ensureTable();
    }

    /**
     * Create a new idea
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO {$this->table}
            (category, title, description, blueprint_suggestion, starter_prompt, created_by, created_at)
            VALUES (:category, :title, :description, :blueprint, :prompt, :created_by, :created_at)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':category' => $data['category'],
            ':title' => $data['title'],
            ':description' => $data['description'] ?? '',
            ':blueprint' => $data['blueprint_suggestion'] ?? 'minimal',
            ':prompt' => $data['starter_prompt'] ?? '',
            ':created_by' => $data['created_by'] ?? 0,
            ':created_at' => date('Y-m-d H:i:s'),
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Find idea by ID
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
     * Get ideas by category
     */
    public function getByCategory(string $category): array
    {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE category = :category
            ORDER BY created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':category' => $category]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get ideas by project
     */
    public function getByProject(int $project_id): array
    {
        $sql = "
            SELECT i.* FROM {$this->table} i
            LEFT JOIN generated_projects p ON i.id = p.id
            WHERE p.id = :project_id
            ORDER BY i.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':project_id' => $project_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all ideas
     */
    public function getAll(): array
    {
        $sql = "
            SELECT * FROM {$this->table}
            ORDER BY category ASC, created_at DESC
        ";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get ideas by category with statistics
     */
    public function getCategoriesWithStats(): array
    {
        $sql = "
            SELECT
                category,
                COUNT(*) as count,
                MAX(created_at) as latest
            FROM {$this->table}
            GROUP BY category
            ORDER BY category ASC
        ";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
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
                category VARCHAR(100) NOT NULL,
                title VARCHAR(255) NOT NULL,
                description LONGTEXT,
                blueprint_suggestion VARCHAR(50),
                starter_prompt LONGTEXT,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_category (category),
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
