<?php
namespace Models;

class MessageModel extends BaseModel {
    protected string $table = "message";

    public function __construct() {
    }

    public function findAll() : array {
        return [
            [
                'id' => 1,
                'sender_id' => 99,
                'content' => 'para testar o fluxo da API',
                'is_read' => true,
                'created_at' => '2026-06-02 15:00:00',
                'username' => 'kerberos'
            ],[
                'id' => 2,
                'sender_id' => 14,
                'content' => 'A arquitetura MVC está funcionando.',
                'is_read' => false,
                'created_at' => '2026-06-02 15:05:00',
                'username' => 'usuario_teste'
            ]
        ];
    }
}