<?php

namespace Models;

use Models\BaseModel;
use PDO;

class Local extends BaseModel
{
    protected string $table =  'local';

    public function create(string $nome_local, string $descricao): int
    {
        $sql = "INSERT INTO {$this->table} (nome_local, descricao) VALUES (:nome_local, :descricao) RETURNING id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'nome_local' => $nome_local,
            'descricao' => $descricao,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function findAll(): array
    {
        $sql = "SELECT id, nome_local, descricao FROM {$this->table} ORDER BY nome_local ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $resultado ?: [];
    }
}
