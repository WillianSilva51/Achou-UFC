<?php

namespace Models;

use PDO;
use Core\Database;

abstract class BaseModel
{
    protected PDO    $db;
    protected string $table;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Busca um registro pelo ID.
     * Remove a senha do resultado caso ela exista (proteção padrão).
     * Subclasses que precisam do hash devem implementar findByIdComSenha().
     */
    public function findById(int $id): ?array
    {
        $sql  = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result === false) {
            return null;
        }

        // Nunca retorna hash de senha para fora dos Models
        if (isset($result['senha'])) {
            unset($result['senha']);
        }

        return $result;
    }

    /**
     * CORREÇÃO BAIXA-04 / alinhamento com schema real:
     *
     * O schema usa estratégias diferentes por tabela:
     *   - local     → deleted_at TIMESTAMP (soft delete por timestamp)
     *   - categoria → deleção física via delete()
     *   - usuario   → deleção física via delete()
     *
     * O método softDelete() original usava SET ativo = FALSE, mas nenhuma tabela
     * no schema tem coluna 'ativo'. Ele foi reescrito para usar deleted_at,
     * compatível com a tabela 'local'.
     *
     * Para tabelas sem deleted_at, use delete() (deleção física).
     * Subclasses podem sobrescrever conforme necessário.
     */
    public function softDelete(int $id): bool
    {
        $sql  = "UPDATE {$this->table} SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Deleção física. Usar apenas em tabelas sem soft delete
     * ou quando a deleção real for a estratégia correta (ex: categoria).
     */
    public function delete(int $id): bool
    {
        $sql  = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    public function countAll(): int
    {
        $sql  = "SELECT COUNT(id) FROM {$this->table}";
        $stmt = $this->db->query($sql);
        return (int) $stmt->fetchColumn();
    }
}
