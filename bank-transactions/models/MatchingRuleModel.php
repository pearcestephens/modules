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
        $stmt = $this->con->prepare("SELECT * FROM " . $this->table . " WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function getAll(int $limit = 100, int $offset = 0): array {
        $stmt = $this->con->prepare("SELECT * FROM " . $this->table . " LIMIT ? OFFSET ?");
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function create(array $data): int {
        $stmt = $this->con->prepare("INSERT INTO " . $this->table . " (name, description, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param('ss', $data['name'], $data['description']);
        $stmt->execute();
        return $this->con->insert_id;
    }

    public function update(int $id, array $data): bool {
        $stmt = $this->con->prepare("UPDATE " . $this->table . " SET name = ?, description = ? WHERE id = ?");
        $stmt->bind_param('ssi', $data['name'], $data['description'], $id);
        return $stmt->execute();
    }
}
?>
