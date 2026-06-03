<?php

namespace Models;

use Models\BaseModel;

class Reivindicacao extends BaseModel
{
    protected string $table = 'reivindicacao';

    public function create(string $status_reivindicacao, string $data_reivindicacao, int $item_id, int $aluno_id): int
    {
        $sql = "INSERT INTO {$this->table} 
                (status_reivindicacao, data_reivindicacao, item_id, aluno_id) 
                VALUES 
                (:status, :data, :item_id, :aluno_id) 
                RETURNING id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'status' => $status_reivindicacao,
            'data' => $data_reivindicacao,
            'item_id' => $item_id,
            'aluno_id' => $aluno_id,
        ]);

        return (int) $stmt->fetchColumn();
    }
}
