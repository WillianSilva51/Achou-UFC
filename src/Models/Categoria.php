<?php

namespace Models;

use Models\BaseModel;
use PDO;

/**
 * A tabela 'categoria' no schema NÃO possui coluna 'ativo' nem 'deleted_at'.
 * O código original tinha WHERE ativo = TRUE no findAll(), o que causava erro
 * de runtime pois a coluna não existe.
 *
 * Estratégia adotada: deleção física (DELETE) via delete() do BaseModel,
 * com proteção por FK (ON DELETE SET NULL em item_perdido.categoria_id)
 * que preserva os itens já cadastrados mesmo após a exclusão da categoria.
 */
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

    public function update(int $id, string $nome): bool
    {
        $sql  = "UPDATE {$this->table} SET nome = :nome WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id, 'nome' => $nome]);
    }


    public function delete(int $id): bool
    {
        $sql  = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
}
