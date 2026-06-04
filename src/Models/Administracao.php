<?php

namespace Models;

class Administracao extends BaseModel
{
    // Define a tabela do banco
    protected string $table = 'administracao';


    public function create(int $usuarioId, string $siap): bool
    {
        $sql = "INSERT INTO {$this->table} (id, siap) VALUES (:id, :siap)";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id' => $usuarioId,
            'siap' => $siap,
        ]);
    }

    public function siapeExists(string $siap): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM {$this->table} WHERE siap = :siap");
        $stmt->execute(['siap' => $siap]);


        return (bool) $stmt->fetchColumn();
    }
}
