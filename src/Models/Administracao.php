<?php

namespace Models;

class Administracao extends BaseModel
{
    // Define a tabela do banco
    protected string $table = 'administracao';


    public function create(int $usuarioId, string $siape): bool
    {
        $sql = "INSERT INTO {$this->table} (id, siape) VALUES (:id, :siape)";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id' => $usuarioId,
            'siape' => $siape,
        ]);
    }

    public function siapeExists(string $siape): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM {$this->table} WHERE siape = :siape");
        $stmt->execute(['siape' => $siape]);


        return (bool) $stmt->fetchColumn();
    }
}
