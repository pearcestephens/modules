<?php
/**
 * Repository layer for Store Reports data access (abstracts SQL and model hydration).
 */
class StoreReportRepository
{
    private PDO $pdo;
    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseManager::pdo();
        if (!$this->pdo) {
            throw new RuntimeException('PDO unavailable in StoreReportRepository');
        }
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM store_reports WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function recent(int $limit = 25): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM store_reports ORDER BY created_at DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO store_reports (store_id, staff_id, grade, notes, created_at) VALUES (?,?,?,?,NOW())';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['store_id'] ?? null,
            $data['staff_id'] ?? null,
            $data['grade'] ?? null,
            $data['notes'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateGrade(int $id, string $grade): bool
    {
        $stmt = $this->pdo->prepare('UPDATE store_reports SET grade = ? WHERE id = ?');
        return $stmt->execute([$grade, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM store_reports WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
