<?php

namespace Models;

use Models\BaseModel;

class Aluno extends BaseModel
{
    protected $table = 'aluno';

    public function create(string $aluno_id, string $matricula): bool
    {
        $sql = "INSERT INTO {$this->table} (aluno_id, matricula) VALUES (:aluno_id, :matricula)";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'aluno_id' => $aluno_id,
            'matricula' => $matricula,
        ]);
    }

    public function existsMatricula(string $matricula): bool
    {
        $sql = "SELCET 1 FROM {$this->table} WHERE matricula =:matricula";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'matricula' => $matricula,
        ]);

        return (bool) $stmt->fetchColumn();
    }
}
