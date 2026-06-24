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

    
    private function buildWhereClause(array $filtros): array
    {
        $where = [];
        $binds = [];

        if (!empty($filtros['status_reivindicacao'])) {
            $where[] = "r.status_reivindicacao = :status_reivindicacao";
            $binds[':status_reivindicacao'] = $filtros['status_reivindicacao'];
        }

        if (!empty($filtros['aluno_id'])) {
            $where[] = "r.aluno_id = :aluno_id";
            $binds[':aluno_id'] = (int) $filtros['aluno_id'];
        }

        $sqlWhere = count($where) > 0 ? " WHERE " . implode(" AND ", $where) : "";
        
        return ['sql' => $sqlWhere, 'binds' => $binds];
    }

    public function countFiltered(array $filtros = []): int
    {
        $whereData = $this->buildWhereClause($filtros);
        
        $sql = "SELECT COUNT(r.id) FROM {$this->table} r" . $whereData['sql'];
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($whereData['binds']);
        
        return (int) $stmt->fetchColumn();
    }

    public function findAllWithDetails(int $limit = 20, int $offset = 0, array $filtros = []): array
    {
        $whereData = $this->buildWhereClause($filtros);

        $sql = "SELECT r.id, r.status_reivindicacao, r.data_reivindicacao, 
                       i.titulo as item_titulo, 
                       u.nome as aluno_nome, a.matricula
                FROM {$this->table} r
                INNER JOIN item_perdido i ON r.item_id = i.id
                INNER JOIN aluno a ON r.aluno_id = a.aluno_id
                INNER JOIN usuario u ON a.aluno_id = u.id"
                . $whereData['sql'] .
                " ORDER BY r.data_reivindicacao DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        foreach ($whereData['binds'] as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $resultado ?: [];
    }

    public function registrarPedido(int $item_id, int $aluno_id, string $data_reivindicacao): int
    {
        try {
            $this->db->beginTransaction();

            $sqlCheck = "SELECT status FROM item_perdido WHERE id = :item_id FOR UPDATE";
            $stmtCheck = $this->db->prepare($sqlCheck);
            $stmtCheck->execute(['item_id' => $item_id]);
            $itemStatus = $stmtCheck->fetchColumn();

            if (!$itemStatus || (strtolower($itemStatus) !== 'disponível' && strtolower($itemStatus) !== 'disponivel')) {
                throw new \Exception('Este item já não está mais disponível.');
            }

            $sql1 = "INSERT INTO {$this->table} (status_reivindicacao, data_reivindicacao, item_id, aluno_id) 
                     VALUES ('pendente', :data, :item_id, :aluno_id) RETURNING id";
            $stmt1 = $this->db->prepare($sql1);
            $stmt1->execute(['data' => $data_reivindicacao, 'item_id' => $item_id, 'aluno_id' => $aluno_id]);
            $id_reivindicacao = (int) $stmt1->fetchColumn();

            $sql2 = "UPDATE item_perdido SET status = 'em_analise' WHERE id = :id";
            $stmt2 = $this->db->prepare($sql2);
            $stmt2->execute(['id' => $item_id]);

            $this->db->commit();
            return $id_reivindicacao;
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e; 
        }
    }

    public function processarAvaliacao(int $reivindicacao_id, string $novo_status, int $item_id, string $status_item): void
    {
        try {
            $this->db->beginTransaction();

            $sql1 = "UPDATE {$this->table} SET status_reivindicacao = :status WHERE id = :id";
            $stmt1 = $this->db->prepare($sql1);
            $stmt1->execute(['id' => $reivindicacao_id, 'status' => $novo_status]);

            $sql2 = "UPDATE item_perdido SET status = :status WHERE id = :id";
            $stmt2 = $this->db->prepare($sql2);
            $stmt2->execute(['id' => $item_id, 'status' => $status_item]);

            $this->db->commit();
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
    
}