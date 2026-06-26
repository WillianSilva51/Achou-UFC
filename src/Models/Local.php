<?php

namespace Models;

use Models\BaseModel;
use PDO;

class Local extends BaseModel
{
    protected string $table = 'local';

    public function findById(int $id): ?array
    {
        $sql  = "SELECT id, nome_local, descricao
                 FROM {$this->table}
                 WHERE id = :id
                   AND deleted_at IS NULL
                 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? $result : null;
    }

    public function create(string $nome_local, string $descricao): int
    {
        $sql  = "INSERT INTO {$this->table} (nome_local, descricao)
                 VALUES (:nome_local, :descricao)
                 RETURNING id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'nome_local' => $nome_local,
            'descricao'  => $descricao,
        ]);
        return (int) $stmt->fetchColumn();
    }


    public function findAll(): array
    {
        $sql  = "SELECT id, nome_local, descricao
                 FROM {$this->table}
                 WHERE deleted_at IS NULL
                 ORDER BY nome_local ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function update(int $id, string $nome_local, string $descricao): bool
    {
        $sql  = "UPDATE {$this->table}
                 SET nome_local = :nome_local, descricao = :descricao
                 WHERE id = :id
                   AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id'         => $id,
            'nome_local' => $nome_local,
            'descricao'  => $descricao,
        ]);
    }


    public function softDelete(int $id): bool
    {
        $sql  = "UPDATE {$this->table}
                 SET deleted_at = NOW()
                 WHERE id = :id
                   AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
}
