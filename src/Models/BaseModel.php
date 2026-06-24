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
        // Garante que toda classe filha já nasça conectada no banco
        $this->db = Database::getConnection();
    }

    // Busca cega por ID (Serve para Local, Categoria, Item...)
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $resultado !== false ? $resultado : null;
    }

    // Deleta por ID (Serve para Local, Categoria, Item...)
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute(['id' => $id]);
    }
}