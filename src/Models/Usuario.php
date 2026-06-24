<?php

namespace Models;

use Models\BaseModel;
use PDO;

class Usuario extends BaseModel
{
    protected string $table = 'usuario';

    public function findByEmail(string $email): ?array
    {
        $query = "SELECT id, nome, email, senha, role FROM {$this->table} WHERE email = :email LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'email' => $email,
        ]);
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user !== false ? $user : null;
    }

    public function create(string $nome, string $email, string $senha, string $role = 'aluno'): int
    {
        $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

        $sql = "INSERT INTO {$this->table} (nome, email, senha, role) VALUES (:nome, :email, :senha, :role) RETURNING id";
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
        $sql = "UPDATE {$this->table} SET nome = :nome, email = :email, role = :role WHERE id = :id";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id'    => $id,
            'nome'  => $nome,
            'email' => $email,
            'role'  => $role,
        ]);
    }

    public function updatePassword(int $id, string $nova_senha): bool
    {
        $hash = password_hash($nova_senha, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $sql = "UPDATE {$this->table} SET senha = :senha WHERE id = :id";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id'    => $id,
            'senha' => $hash,
        ]);
    }

    public function findAll(): array
    {
        $sql = "SELECT id, nome, email, role FROM {$this->table} ORDER BY nome ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $resultado ?: [];
    }
}