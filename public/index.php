<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use Core\Request;
use Core\Router;
use Controllers\AuthController;
use Controllers\ItemController;
use Controllers\LocalController;
use Controllers\CategoriaController;

$request = new Request($_SERVER, $_GET, $_POST);
$router = new Router($request);

// TESTE
$router->get('/', function () {
    return "API funcionando";
});

$router->post('/api/register', [AuthController::class, 'register']);
$router->post('/api/login', [AuthController::class, 'login']);
$router->post('/api/itens', [ItemController::class, 'store']);
$router->get('/api/itens', [ItemController::class, 'index']);
$router->post('/api/locais', [LocalController::class, 'createLocal']);
$router->get('/api/locais', [LocalController::class, 'listLocal']);
$router->post('/api/categorias', [CategoriaController::class, 'store']);
$router->get('/api/categorias', [CategoriaController::class, 'index']);

// ROTA REAL
//$router->get('/messages', [\Controllers\MessageController::class, 'index']);

echo $router->resolve();
