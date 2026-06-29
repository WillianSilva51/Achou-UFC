<?php

namespace Models;

use Models\BaseModel;
use PDO;

class Usuario extends BaseModel
{
    protected string $table = 'usuario';

    public function verificarCredenciais(string $email, string $senha): ?array
    {
        $query = "SELECT id, nome, email, senha, role
                  FROM {$this->table}
                  WHERE email = :email
                  LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($senha, $user['senha'])) {
            return null;
        }

        unset($user['senha']); 
        return $user;
    }


    public function findByIdComSenha(int $id): ?array
    {
        $query = "SELECT id, nome, email, senha, role
                  FROM {$this->table}
                  WHERE id = :id
                  LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }

    public function create(string $nome, string $email, string $senha, string $role = 'aluno'): int
    {
        $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

        $sql = "INSERT INTO {$this->table} (nome, email, senha, role)
                VALUES (:nome, :email, :senha, :role)
                RETURNING id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'nome'  => $nome,
            'email' => $email,
            'senha' => $hash,
            'role'  => $role,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, string $nome, string $email, string $role): bool
    {
        $sql = "UPDATE {$this->table}
                SET nome = :nome, email = :email, role = :role
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id'    => $id,
            'nome'  => $nome,
            'email' => $email,
            'role'  => $role,
        ]);
        return $stmt->rowCount() > 0;
    }


    public function updatePassword(int $id, string $nova_senha): bool
    {
        $hash = password_hash($nova_senha, PASSWORD_BCRYPT, ['cost' => 12]);
        $sql  = "UPDATE {$this->table} SET senha = :senha WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id, 'senha' => $hash]);
    }

    private function buildWhereClause(array $filtros): array
    {
        $where = [];
        $binds = [];

        if (!empty($filtros['role'])) {
            $where[]         = "role = :role";
            $binds[':role']  = $filtros['role'];
        }

        if (!empty($filtros['busca'])) {
            $where[]          = "(nome ILIKE :busca OR email ILIKE :busca)";
            $binds[':busca']  = '%' . $filtros['busca'] . '%';
        }

        $sqlWhere = count($where) > 0 ? ' WHERE ' . implode(' AND ', $where) : '';

        return ['sql' => $sqlWhere, 'binds' => $binds];
    }

    public function countFiltered(array $filtros = []): int
    {
        $whereData = $this->buildWhereClause($filtros);
        $sql       = "SELECT COUNT(id) FROM {$this->table}" . $whereData['sql'];
        $stmt      = $this->db->prepare($sql);
        $stmt->execute($whereData['binds']);
        return (int) $stmt->fetchColumn();
    }

    public function findAll(int $limit = 20, int $offset = 0, array $filtros = []): array
    {
        $whereData = $this->buildWhereClause($filtros);

        $sql = "SELECT id, nome, email, role
                FROM {$this->table}"
            . $whereData['sql']
            . " ORDER BY nome ASC
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
}