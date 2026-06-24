<?php

namespace Models;

use Models\BaseModel;
use PDO;

class Reivindicacao extends BaseModel
{
    protected string $table = 'reivindicacao';

    public function create(string $status_reivindicacao, string $data_reivindicacao, int $item_id, int $aluno_id): int
    {
        $sql = "INSERT INTO {$this->table} 
                (status_reivindicacao, data_reivindicacao, item_id, aluno_id) 
                VALUES 
                (:status_reivindicacao, :data_reivindicacao, :item_id, :aluno_id) 
                RETURNING id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'status_reivindicacao' => $status_reivindicacao,
            'data_reivindicacao'   => $data_reivindicacao,
            'item_id'              => $item_id,
            'aluno_id'             => $aluno_id,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function findAllWithDetails(): array
    {
        $sql = "SELECT r.id, r.status_reivindicacao, r.data_reivindicacao, 
                       i.titulo as item_titulo, 
                       u.nome as aluno_nome, a.matricula
                FROM {$this->table} r
                INNER JOIN item_perdido i ON r.item_id = i.id
                INNER JOIN aluno a ON r.aluno_id = a.aluno_id
                INNER JOIN usuario u ON a.aluno_id = u.id
                ORDER BY r.data_reivindicacao DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $resultado ?: [];
    }

    public function updateStatus(int $id, string $status_reivindicacao): bool
    {
        $sql = "UPDATE {$this->table} 
                SET status_reivindicacao = :status_reivindicacao 
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            'id'                   => $id,
            'status_reivindicacao' => $status_reivindicacao
        ]);
    }
}