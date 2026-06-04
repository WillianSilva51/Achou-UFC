<?php

namespace Models;

use Models\BaseModel;

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
}
