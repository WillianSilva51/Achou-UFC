<?php

namespace Models;

use Models\BaseModel;
use PDO;

class Local extends BaseModel
{
    protected string $table = 'local';

    public function create(string $nome_local, string $descricao): int
    {
        $sql = "INSERT INTO {$this->table} (nome_local, descricao) VALUES (:nome_local, :descricao) RETURNING id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'nome_local' => $nome_local,
            'descricao'  => $descricao,
        ]);
        
        return (int) $stmt->fetchColumn();
    }

    public function findAll(): array
    {
        $sql = "SELECT id, nome_local, descricao FROM {$this->table} WHERE ativo = TRUE ORDER BY nome_local ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $resultado ?: [];
    }

    public function update(int $id, string $nome_local, string $descricao): bool
    {
        $sql = "UPDATE {$this->table} 
                SET nome_local = :nome_local, descricao = :descricao 
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            'id'         => $id,
            'nome_local' => $nome_local,
            'descricao'  => $descricao
        ]);
    }
}