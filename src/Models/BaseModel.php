<?php

namespace Models;

use PDO;
use Core\Database;

abstract class BaseModel
{
    protected PDO $db;
    protected string $table; 

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && isset($result['senha'])) {
            unset($result['senha']);
        }

        return $result;
    }
    

    public function softDelete(int $id): bool
    {
        $sql = "UPDATE {$this->table} SET ativo = FALSE WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute(['id' => $id]);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute(['id' => $id]);
    }

    public function countAll(): int
    {
        $sql = "SELECT COUNT(id) FROM {$this->table}";
        $stmt = $this->db->query($sql);
        
        return (int) $stmt->fetchColumn();
    }
}