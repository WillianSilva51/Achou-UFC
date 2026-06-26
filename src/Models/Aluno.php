<?php

namespace Models;

use Models\BaseModel;
use PDO;

class Aluno extends BaseModel
{
    protected string $table = 'aluno';

    public function create(int $aluno_id, string $matricula): bool
    {
        $sql = "INSERT INTO {$this->table} (aluno_id, matricula) VALUES (:aluno_id, :matricula)";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'aluno_id'  => $aluno_id,
            'matricula' => $matricula,
        ]);
    }

    public function update(int $aluno_id, string $matricula): bool
    {
        $sql = "UPDATE {$this->table} SET matricula = :matricula WHERE aluno_id = :aluno_id";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'aluno_id'  => $aluno_id,
            'matricula' => $matricula,
        ]);
    }

    public function existsMatricula(string $matricula): bool
    {
        $sql = "SELECT 1 FROM {$this->table} WHERE matricula = :matricula LIMIT 1";
        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'matricula' => $matricula,
        ]);

        return (bool) $stmt->fetchColumn();
    }
}