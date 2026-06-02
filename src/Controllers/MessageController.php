<?php
namespace Controllers;

use Models\MessageModel;

class MessageController {
    public function index() {
        $model = new MessageModel();
        header('Content-Type: application/json');

        // Retorna a string JSON para que o Router faça o echo lá no index.php
        return json_encode($model->findAll());
    }
}