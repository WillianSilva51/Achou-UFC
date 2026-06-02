<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

use Core\Request;
use Core\Router;

$request = new Request($_SERVER, $_GET, $_POST);
$router = new Router($request);

// TESTE
$router->get('/', function () {
    return "API funcionando";
});


// ROTA REAL
$router->get('/messages', [\Controllers\MessageController::class, 'index']);

echo $router->resolve();