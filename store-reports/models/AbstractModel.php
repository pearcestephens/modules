<?php
abstract class AbstractModel {
    protected PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct(PDO $db) { $this->db = $db; }

    public function find(int $id): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function all(int $limit = 100): array {
        $limit = max(1, min(500, $limit));
        $sql = "SELECT * FROM {$this->table} ORDER BY {$this->primaryKey} DESC LIMIT {$limit}";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function insert(array $data): int {
        $keys = array_keys($data);
        $cols = implode(',', $keys);
        $placeholders = implode(',', array_map(fn($k) => ':' . $k, $keys));
        $sql = "INSERT INTO {$this->table} ({$cols}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        foreach ($data as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->execute();
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        if (!$data) return false;
        $assignments = [];
        foreach ($data as $k => $v) { $assignments[] = "$k = :$k"; }
        $sql = "UPDATE {$this->table} SET " . implode(',', $assignments) . " WHERE {$this->primaryKey} = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        foreach ($data as $k => $v) { $stmt->bindValue(':' . $k, $v); }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function softDelete(int $id): bool {
        if ($this->columnExists('deleted_at')) {
            return $this->update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
        }
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    protected function columnExists(string $column): bool {
        $sql = "SHOW COLUMNS FROM {$this->table} LIKE :column";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':column', $column);
        $stmt->execute();
        return (bool)$stmt->fetch();
    }
}
?>
