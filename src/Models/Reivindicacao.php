<?php

namespace Models;

use Models\BaseModel;
use PDO;

class Reivindicacao extends BaseModel
{
    protected string $table = 'reivindicacao';

    public function create(
        string $status_reivindicacao,
        string $data_solicitacao,
        int    $item_id,
        int    $aluno_id
    ): int {
        $sql = "INSERT INTO {$this->table}
                    (status_reivindicacao, data_solicitacao, item_id, aluno_id)
                VALUES
                    (:status_reivindicacao, :data_solicitacao, :item_id, :aluno_id)
                RETURNING id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'status_reivindicacao' => $status_reivindicacao,
            'data_solicitacao'     => $data_solicitacao,
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
            $where[]                        = "r.status_reivindicacao = :status_reivindicacao";
            $binds[':status_reivindicacao'] = $filtros['status_reivindicacao'];
        }

        if (!empty($filtros['aluno_id'])) {
            $where[]            = "r.aluno_id = :aluno_id";
            $binds[':aluno_id'] = (int) $filtros['aluno_id'];
        }

        $sqlWhere = count($where) > 0 ? ' WHERE ' . implode(' AND ', $where) : '';

        return ['sql' => $sqlWhere, 'binds' => $binds];
    }

    public function countFiltered(array $filtros = []): int
    {
        $whereData = $this->buildWhereClause($filtros);
        $sql       = "SELECT COUNT(r.id) FROM {$this->table} r" . $whereData['sql'];
        $stmt      = $this->db->prepare($sql);
        $stmt->execute($whereData['binds']);
        return (int) $stmt->fetchColumn();
    }

    public function findAllWithDetails(int $limit = 20, int $offset = 0, array $filtros = []): array
    {
        $whereData = $this->buildWhereClause($filtros);

        $sql = "SELECT r.id,
                       r.item_id,
                       r.status_reivindicacao,
                       r.data_solicitacao,
                       i.titulo     AS item_titulo,
                       u.nome       AS aluno_nome,
                       u.email      AS aluno_email,
                       a.matricula
                FROM {$this->table} r
                INNER JOIN item_perdido i ON r.item_id   = i.id
                INNER JOIN aluno        a ON r.aluno_id  = a.aluno_id
                INNER JOIN usuario      u ON a.aluno_id  = u.id"
            . $whereData['sql']
            . " ORDER BY r.data_solicitacao DESC
               LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        foreach ($whereData['binds'] as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    
    public function registrarPedido(int $item_id, int $aluno_id): int
    {
    try {
            $this->db->beginTransaction();

            $stmtDup = $this->db->prepare(
                "SELECT id FROM {$this->table}
                 WHERE item_id = :item_id AND aluno_id = :aluno_id
                   AND status_reivindicacao = 'pendente'
                 LIMIT 1"
            );
            $stmtDup->execute(['item_id' => $item_id, 'aluno_id' => $aluno_id]);
            if ($stmtDup->fetchColumn() !== false) {
                $this->db->rollBack();
                $e = new \PDOException('duplicate key value violates unique constraint "uq_reivindicacao_ativa"');
                $e->errorInfo = ['23505', null, null];
                throw $e;
            }

            $stmtItem = $this->db->prepare(
                "SELECT status FROM item_perdido WHERE id = :item_id FOR UPDATE"
            );
            $stmtItem->execute(['item_id' => $item_id]);
            $itemStatus = $stmtItem->fetchColumn();

            if ($itemStatus === false) {
                throw new \InvalidArgumentException('Item não encontrado.');
            }

            if (!in_array(strtolower($itemStatus), ['disponível', 'disponivel'], true)) {
                throw new \InvalidArgumentException(
                    'Este item não está mais disponível para reivindicação (status atual: ' . $itemStatus . ').'
                );
            }

            $stmtInsert = $this->db->prepare(
                "INSERT INTO {$this->table}
                    (status_reivindicacao, item_id, aluno_id)
                VALUES
                    ('pendente', :item_id, :aluno_id)
                RETURNING id"
            );
            $stmtInsert->execute([
                'item_id'  => $item_id,
                'aluno_id' => $aluno_id,
            ]);
            $id_reivindicacao = (int) $stmtInsert->fetchColumn();

            $stmtUpdate = $this->db->prepare(
                "UPDATE item_perdido SET status = 'em_analise' WHERE id = :id"
            );
            $stmtUpdate->execute(['id' => $item_id]);

            $this->db->commit();
            return $id_reivindicacao;

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function processarAvaliacao(
        int    $id_reivindicacao,
        string $novo_status,
        int    $item_id,
        string $status_item
    ): bool {
        $statusReivindicacaoValidos = ['aprovado', 'recusado', 'pendente'];
        if (!in_array(strtolower($novo_status), $statusReivindicacaoValidos, true)) {
            throw new \InvalidArgumentException("Status de reivindicação inválido: {$novo_status}.");
        }

        $statusItemValidos = ['disponível', 'disponivel', 'devolvido', 'arquivado', 'em_analise'];
        if (!in_array(strtolower($status_item), $statusItemValidos, true)) {
            throw new \InvalidArgumentException("Status de item inválido: {$status_item}.");
        }

        $pdo = \Core\Database::getConnection();

        try {
            $pdo->beginTransaction();

            $stmtCheck = $pdo->prepare(
                "SELECT status_reivindicacao
                 FROM reivindicacao
                 WHERE id = :id
                 FOR UPDATE"
            );
            $stmtCheck->execute(['id' => $id_reivindicacao]);
            $estadoAtual = $stmtCheck->fetchColumn();

            if ($estadoAtual !== 'pendente') {
                $pdo->rollBack();
                throw new \LogicException(
                    "Reivindicação já processada (estado atual: {$estadoAtual}). Nenhuma alteração foi feita."
                );
            }

            $stmtReivindicacao = $pdo->prepare(
                "UPDATE reivindicacao
                 SET status_reivindicacao = :status
                 WHERE id = :id"
            );
            $stmtReivindicacao->execute(['status' => $novo_status, 'id' => $id_reivindicacao]);

            if ($novo_status === 'aprovado') {
                $stmtRecusar = $pdo->prepare(
                    "UPDATE reivindicacao
                     SET status_reivindicacao = 'recusado'
                     WHERE item_id = :item_id
                       AND id != :id
                       AND status_reivindicacao = 'pendente'"
                );
                $stmtRecusar->execute(['item_id' => $item_id, 'id' => $id_reivindicacao]);
            }

            $stmtItem = $pdo->prepare(
                "UPDATE item_perdido SET status = :status_item WHERE id = :item_id"
            );
            $stmtItem->execute(['status_item' => $status_item, 'item_id' => $item_id]);

            $pdo->commit();
            return true;

        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Erro crítico em processarAvaliacao: ' . $e->getMessage());
            throw $e;
        }
    }
}
