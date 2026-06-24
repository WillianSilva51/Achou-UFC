<?php

namespace Models;

use Models\BaseModel;
use PDO;

class Categoria extends BaseModel
{
    protected string $table = 'categoria';

    public function create(string $nome): int
    {
        $sql = "INSERT INTO {$this->table} (nome) VALUES (:nome) RETURNING id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'nome' => $nome,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function findAll(): array
    {
        $sql = "SELECT id, nome FROM {$this->table} WHERE ativo = TRUE ORDER BY nome ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $resultado ?: [];
    }

    public function update(int $id, string $nome): bool
    {
        $sql = "UPDATE {$this->table} SET nome = :nome WHERE id = :id";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id'   => $id,
            'nome' => $nome,
        ]);
    }
}