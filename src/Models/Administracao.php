<?php

namespace Models;

use Models\BaseModel;
use PDO;

class Administracao extends BaseModel
{
    protected string $table = 'administracao';

    public function create(int $id, string $siap): bool
    {
        $sql = "INSERT INTO {$this->table} (id, siap) VALUES (:id, :siap)";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id'   => $id,
            'siap' => $siap,
        ]);
    }

    public function update(int $id, string $siap): bool
    {
        $sql = "UPDATE {$this->table} SET siap = :siap WHERE id = :id";
        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id'   => $id,
            'siap' => $siap,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function siapeExists(string $siap): bool
    {
        $sql = "SELECT 1 FROM {$this->table} WHERE siap = :siap LIMIT 1";
        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'siap' => $siap,
        ]);

        return (bool) $stmt->fetchColumn();
    }
}