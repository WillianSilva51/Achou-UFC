<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use Core\Request;
use Core\Router;
use Controllers\AuthController;

$request = new Request($_SERVER, $_GET, $_POST);
$router = new Router($request);

// TESTE
$router->get('/', function () {
    return "API funcionando";
});

$router->post('/api/register', [AuthController::class, 'register']);
$router->post('/api/login', [AuthController::class, 'login']);

// ROTA REAL
$router->get('/messages', [\Controllers\MessageController::class, 'index']);

echo $router->resolve();
