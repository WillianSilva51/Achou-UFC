<?php

use Core\App;

$app = new App();

$app->router->get('/', function () {
    return "API funcionando";
});

$app->router->get('/messages', [\Controllers\MessageController::class, 'index']);

$app->run();

?>