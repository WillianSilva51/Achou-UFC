<?php

namespace Controllers;

use Models\MessageModel;

class MessageController
{
    public function index()
    {
        $model = new MessageModel();
        header('Content-Type: application/json');

        return json_encode($model->findAll());
    }
}
