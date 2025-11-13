<?php
/**
 * Bank Transactions - Matching Rule Model
 *
 * @package CIS\BankTransactions\Models
 */

declare(strict_types=1);

namespace CIS\BankTransactions\Models;

class MatchingRuleModel {

    private $con;
    private $table = 'matching_rules';

    public function __construct($connection = null) {
        global $con;
        $this->con = $connection ?? $con;
    }

    public function findById(int $id): ?array {
        $stmt = $this->con->prepare("SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getAll(int $limit = 100, int $offset = 0): array {
        $stmt = $this->con->prepare("SELECT * FROM " . $this->table . " LIMIT :limit OFFSET :offset");
        $stmt->execute(['limit' => $limit, 'offset' => $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int {
        $stmt = $this->con->prepare("INSERT INTO " . $this->table . " (name, description, created_at) VALUES (:name, :description, NOW())");
        $stmt->execute(['name' => $data['name'], 'description' => $data['description']]);
        return (int)$this->con->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $stmt = $this->con->prepare("UPDATE " . $this->table . " SET name = :name, description = :description WHERE id = :id");
        return $stmt->execute(['name' => $data['name'], 'description' => $data['description'], 'id' => $id]);
    }
}
?>
