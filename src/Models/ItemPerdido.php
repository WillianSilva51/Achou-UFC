<?php

namespace Models;

use Models\BaseModel;
use PDO;

class ItemPerdido extends BaseModel
{
    protected string $table = 'item_perdido';

    public function create(string $titulo, string $descricao, string $data_encontrado, ?string $foto_url, int $local_id, int $categoria_id, int $registrado_por, string $status = 'disponível'): int
    {
        $sql = "INSERT INTO {$this->table}
          (titulo, descricao, data_encontrado, status, foto_url, local_id, categoria_id, registrado_por)
          VALUES (:titulo, :descricao, :data_encontrado, :status, :foto_url, :local_id, :categoria_id, :registrado_por)
          RETURNING id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'titulo'          => $titulo,
            'descricao'       => $descricao,
            'data_encontrado' => $data_encontrado,
            'status'          => $status,
            'foto_url'        => $foto_url,
            'local_id'        => $local_id,
            'categoria_id'    => $categoria_id,
            'registrado_por'  => $registrado_por,
        ]);
        return (int) $stmt->fetchColumn();
    
    
    }

    public function update(int $id, string $titulo, string $descricao, string $data_encontrado, ?string $foto_url, int $local_id, int $categoria_id, string $status): bool
    {
        $sql = "UPDATE {$this->table} 
                SET titulo = :titulo, 
                    descricao = :descricao, 
                    data_encontrado = :data_encontrado, 
                    foto_url = :foto_url, 
                    local_id = :local_id, 
                    categoria_id = :categoria_id, 
                    status = :status 
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id'              => $id,
            'titulo'          => $titulo,
            'descricao'       => $descricao,
            'data_encontrado' => $data_encontrado,
            'foto_url'        => $foto_url,
            'local_id'        => $local_id,
            'categoria_id'    => $categoria_id,
            'status'          => $status,
        ]);
    }

    public function updateStatus(int $id, string $status): bool
    {
        $statusValidos = ['disponível', 'disponivel', 'devolvido', 'arquivado', 'em_analise'];
        $statusLimpo = strtolower($status);
        
        if (!in_array($statusLimpo, $statusValidos, true)) {
            throw new \InvalidArgumentException("Status de item '$status' inválido.");
        }

        $pdo = \Core\Database::getConnection();
        $sql = "UPDATE item_perdido SET status = :status WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute(['status' => $statusLimpo, 'id' => $id]);
    }

    private function buildWhereClause(array $filtros): array
    {
        $where = [];
        $binds = [];

        if (!empty($filtros['status'])) {
            $where[] = "i.status = :status";
            $binds[':status'] = $filtros['status'];
        } else {
            $where[] = "i.status != 'arquivado'";
        }

        if (!empty($filtros['categoria_id'])) {
            $where[] = "i.categoria_id = :categoria_id";
            $binds[':categoria_id'] = (int) $filtros['categoria_id'];
        }

        if (!empty($filtros['local_id'])) {
            $where[] = "i.local_id = :local_id";
            $binds[':local_id'] = (int) $filtros['local_id'];
        }

        if (!empty($filtros['busca'])) {
            $where[] = "(i.titulo LIKE :busca OR i.descricao LIKE :busca)";
            $binds[':busca'] = '%' . $filtros['busca'] . '%';
        }

        $sqlWhere = count($where) > 0 ? " WHERE " . implode(" AND ", $where) : "";
        
        return ['sql' => $sqlWhere, 'binds' => $binds];
    }

    public function countFiltered(array $filtros = []): int
    {
        $whereData = $this->buildWhereClause($filtros);
        
        $sql = "SELECT COUNT(i.id) FROM {$this->table} i" . $whereData['sql'];
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($whereData['binds']);
        
        return (int) $stmt->fetchColumn();
    }

    public function findAllWithDetails(int $limit = 20, int $offset = 0, array $filtros = []): array
    {
        $whereData = $this->buildWhereClause($filtros);

        $sql = "SELECT i.id, i.titulo, i.descricao, i.data_encontrado, i.status, i.foto_url, 
                       c.nome as categoria, l.nome_local as local, u.nome as registrado_por
                FROM {$this->table} i
                INNER JOIN categoria c ON i.categoria_id = c.id
                INNER JOIN local l ON i.local_id = l.id
                INNER JOIN administracao a ON i.registrado_por = a.id
                INNER JOIN usuario u ON a.id = u.id" 
                . $whereData['sql'] . 
                " ORDER BY i.data_encontrado DESC 
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
    
}