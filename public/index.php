<?php

// Libera o acesso para o seu front-end consumir a API sem tomar block do navegador (CORS)
header("Access-Control-Allow-Origin: *"); 
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

$_ENV['JWT_SECRET'] = $_ENV['JWT_SECRET'] ?? 'sua_chave_secreta_super_segura_aqui';

$request = new Request($_SERVER, $_GET, $_POST);
$router = new Router($request);

// --- AUTENTICAÇÃO E REGISTRO ---
$router->post('/api/register', [AuthController::class, 'register']);
$router->post('/api/login', [AuthController::class, 'login']);

// --- USUÁRIOS (Admin gere contas) ---
$router->get('/api/usuarios', [UsuarioController::class, 'index']);
$router->put('/api/usuarios/{id}', [UsuarioController::class, 'update']);
$router->put('/api/usuarios/{id}/senha', [UsuarioController::class, 'updatePassword']);

// --- LOCAIS (Onde o item foi achado) ---
$router->post('/api/locais', [LocalController::class, 'createLocal']);
$router->get('/api/locais', [LocalController::class, 'listLocal']);
$router->get('/api/locais/{id}', [LocalController::class, 'show']);
$router->put('/api/locais/{id}', [LocalController::class, 'update']);
$router->delete('/api/locais/{id}', [LocalController::class, 'destroy']);

// --- CATEGORIAS (Eletrônicos, Documentos, etc) ---
// Considerando que você padronizou para 'store' e 'index' na sua classe CategoriaController
$router->post('/api/categorias', [CategoriaController::class, 'store']);
$router->get('/api/categorias', [CategoriaController::class, 'index']);
$router->get('/api/categorias/{id}', [CategoriaController::class, 'show']);
$router->put('/api/categorias/{id}', [CategoriaController::class, 'update']);
$router->delete('/api/categorias/{id}', [CategoriaController::class, 'destroy']);

// --- ITENS PERDIDOS (O coração do sistema) ---
$router->post('/api/itens', [ItemController::class, 'store']);
$router->get('/api/itens', [ItemController::class, 'index']);
$router->get('/api/itens/{id}', [ItemController::class, 'show']);
$router->put('/api/itens/{id}', [ItemController::class, 'update']);
$router->delete('/api/itens/{id}', [ItemController::class, 'destroy']);

// --- REIVINDICAÇÕES (O fluxo de aprovação) ---
$router->post('/api/reivindicacoes', [ReivindicacaoController::class, 'store']); // Aluno pede
$router->get('/api/reivindicacoes', [ReivindicacaoController::class, 'index']); // Admin lista
$router->put('/api/reivindicacoes/{id}/status', [ReivindicacaoController::class, 'updateStatus']); // Admin aprova/recusa

// ==========================================
// 🚀 INICIA O MOTOR DO ROTEADOR
// ==========================================
$router->resolve();