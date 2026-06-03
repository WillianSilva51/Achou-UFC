<?php

namespace Models;

use Models\BaseModel;
use PDO;

class Usuario extends BaseModel
{
    protected string $table = 'usuario';
    public function findbyEmail(string $email): ?array
    {
        $query = "SELECT id, nome, email, role FROM {$this->table} WHERE email = :email";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'email' => $email,
        ]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public function create(string $nome, string $email, string $senha, string $role = 'aluno'): int
    {
        $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

        $sql = "INSERT INTO {$this->table} (nome, email, senha, role) VALUES (:nome, :email, :senha, :role) RETURNING id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'nome' => $nome,
            'email' => $email,
            'senha' => $hash,
            'role' => $role,
        ]);
        return (int) $stmt->fetchColumn();
    }
}
