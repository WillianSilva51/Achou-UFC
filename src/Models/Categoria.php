<?php

namespace Models;

use Models\BaseModel;
use PDO;


class Categoria extends BaseModel
{
    protected string $table = 'categoria';

    public function create(string $nome): int
    {
        $sql  = "INSERT INTO {$this->table} (nome) VALUES (:nome) RETURNING id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['nome' => $nome]);
        return (int) $stmt->fetchColumn();
    }

    public function findAll(): array
    {
        $sql  = "SELECT id, nome FROM {$this->table} ORDER BY nome ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function existsByNormalizedName(string $nome, ?int $exceptId = null): bool
    {
        $sql = "SELECT 1
                FROM {$this->table}
                WHERE LOWER(BTRIM(nome)) = LOWER(BTRIM(:nome))";

        $params = ['nome' => $nome];

        if ($exceptId !== null) {
            $sql .= " AND id <> :except_id";
            $params['except_id'] = $exceptId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function update(int $id, string $nome): bool
    {
        $sql  = "UPDATE {$this->table} SET nome = :nome WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'nome' => $nome]);
        return $stmt->rowCount() > 0;
    }


    public function delete(int $id): bool
    {
        $sql  = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
}
