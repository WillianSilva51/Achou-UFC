<?php

$origemPermitida = $_ENV['CORS_ORIGIN'] ?? '*'; 
header("Access-Control-Allow-Origin: " . $origemPermitida);
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
date_default_timezone_set('America/Fortaleza');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

use Core\Request;
use Core\Router;
use Controllers\AuthController;
use Controllers\UsuarioController;
use Controllers\LocalController;
use Controllers\CategoriaController;
use Controllers\ItemController;
use Controllers\ReivindicacaoController;

$request = new Request($_SERVER, $_GET, $_POST);
$router = new Router($request);

$router->post('/api/register', [AuthController::class, 'register']);
$router->post('/api/login', [AuthController::class, 'login']);

$router->get('/api/usuarios', [UsuarioController::class, 'index']);
$router->put('/api/usuarios/{id}', [UsuarioController::class, 'update']);
$router->put('/api/usuarios/{id}/senha', [UsuarioController::class, 'updatePassword']);

$router->post('/api/locais', [LocalController::class, 'createLocal']);
$router->get('/api/locais', [LocalController::class, 'listLocal']);
$router->get('/api/locais/{id}', [LocalController::class, 'show']);
$router->put('/api/locais/{id}', [LocalController::class, 'update']);
$router->delete('/api/locais/{id}', [LocalController::class, 'destroy']);

$router->post('/api/categorias', [CategoriaController::class, 'store']);
$router->get('/api/categorias', [CategoriaController::class, 'index']);
$router->get('/api/categorias/{id}', [CategoriaController::class, 'show']);
$router->put('/api/categorias/{id}', [CategoriaController::class, 'update']);
$router->delete('/api/categorias/{id}', [CategoriaController::class, 'destroy']);

$router->post('/api/itens', [ItemController::class, 'store']);
$router->get('/api/itens', [ItemController::class, 'index']);
$router->get('/api/itens/{id}', [ItemController::class, 'show']);
$router->put('/api/itens/{id}', [ItemController::class, 'update']);
$router->delete('/api/itens/{id}', [ItemController::class, 'destroy']);

$router->post('/api/reivindicacoes', [ReivindicacaoController::class, 'store']); 
$router->get('/api/reivindicacoes', [ReivindicacaoController::class, 'index']); 
$router->put('/api/reivindicacoes/{id}/status', [ReivindicacaoController::class, 'updateStatus']);

$router->resolve();