<?php

namespace Models;

use Models\BaseModel;
use PDO;

class ItemPerdido extends BaseModel
{
    protected string $table = 'item_perdido';

    public function create(string $titulo, string $descricao, string $data_encontrado, ?string $foto_url, int $local_id, int $categoria_id, int $registrado_por): int
    {
        $sql = "INSERT INTO {$this->table}
          (titulo, descricao, data_encontrado, foto_url, local_id, categoria_id, registrado_por)
          VALUES (:titulo, :descricao, :data_encontrado, :foto_url, :local_id, :categoria_id, :registrado_por)
          RETURNING id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'titulo' => $titulo,
            'descricao' => $descricao,
            'data_encontrado' => $data_encontrado,
            'foto_url' => $foto_url,
            'local_id' => $local_id,
            'categoria_id' => $categoria_id,
            'registrado_por' => $registrado_por,
        ]);
        return (int) $stmt->fetchColumn();
    }
    public function findAllWithDetails(): array
    {
        $sql = "SELECT i.id, i.titulo, i.descricao, i.data_encontrado, i.foto_url, 
                       c.nome as categoria, l.nome_local as local, u.nome as registrado_por
                FROM {$this->table} i
                INNER JOIN categoria c ON i.categoria_id = c.id
                INNER JOIN local l ON i.local_id = l.id
                INNER JOIN administracao a ON i.registrado_por = a.id
                INNER JOIN usuario u ON a.id = u.id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
