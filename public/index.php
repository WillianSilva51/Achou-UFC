<?php

ini_set('display_errors', '0');
ini_set('html_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

$envLoader = __DIR__ . '/../src/Core/Enviroment.php';
if (file_exists(__DIR__ . '/../.env') && file_exists($envLoader)) {
    require_once $envLoader;
    \Core\Environment::load(__DIR__ . '/..');
}

$origensPermitidas = [];
if (!empty($_ENV['CORS_ORIGIN'])) {
    $origensPermitidas = array_map('trim', explode(',', $_ENV['CORS_ORIGIN']));
}

$originHeader = $_SERVER['HTTP_ORIGIN'] ?? '';

if (!empty($originHeader) && in_array($originHeader, $origensPermitidas, true)) {
    header("Access-Control-Allow-Origin: {$originHeader}");
    header('Vary: Origin');
} elseif (empty($originHeader)) {
    // Requisição sem Origin (ex: Postman, cURL) — sem bloqueio
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'none'");
// CORREÇÃO BAIXA-04: removido X-XSS-Protection (header legado, removido do Chrome 78+;
// o CSP acima já cobre XSS de forma muito mais robusta e padronizada)
header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()");

date_default_timezone_set('America/Fortaleza');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

use Core\Request;
use Core\Router;
use Controllers\AuthController;
use Controllers\UsuarioController;
use Controllers\LocalController;
use Controllers\CategoriaController;
use Controllers\ItemController;
use Controllers\ReivindicacaoController;

$request = new Request($_SERVER, $_GET, $_POST);
$router  = new Router($request);

// Auth (reCAPTCHA protege register e login; rate limiter removido desses endpoints)
$router->post('/api/register', [AuthController::class, 'register']);
$router->post('/api/login',    [AuthController::class, 'login']);
$router->post('/api/verify-email', [AuthController::class, 'verifyEmail']);
$router->post('/api/resend-verification', [AuthController::class, 'resendVerification']);
$router->post('/api/logout',   [AuthController::class, 'logout']);

// Usuários
$router->get('/api/usuarios',            [UsuarioController::class, 'index']);
$router->put('/api/usuarios/{id}',       [UsuarioController::class, 'update']);
$router->put('/api/usuarios/{id}/senha', [UsuarioController::class, 'updatePassword']);

// Locais
$router->post('/api/locais',        [LocalController::class, 'createLocal']);
$router->get('/api/locais',         [LocalController::class, 'listLocal']);
$router->get('/api/locais/{id}',    [LocalController::class, 'show']);
$router->put('/api/locais/{id}',    [LocalController::class, 'update']);
$router->delete('/api/locais/{id}', [LocalController::class, 'destroy']);

// Categorias
$router->post('/api/categorias',        [CategoriaController::class, 'store']);
$router->get('/api/categorias',         [CategoriaController::class, 'index']);
$router->get('/api/categorias/{id}',    [CategoriaController::class, 'show']);
$router->put('/api/categorias/{id}',    [CategoriaController::class, 'update']);
$router->delete('/api/categorias/{id}', [CategoriaController::class, 'destroy']);

// Itens perdidos
$router->post('/api/itens',        [ItemController::class, 'store']);
$router->get('/api/itens',         [ItemController::class, 'index']);
$router->get('/api/itens/{id}',    [ItemController::class, 'show']);
$router->put('/api/itens/{id}',    [ItemController::class, 'update']);
$router->delete('/api/itens/{id}', [ItemController::class, 'destroy']);

// Reivindicações
$router->post('/api/reivindicacoes',             [ReivindicacaoController::class, 'store']);
$router->get('/api/reivindicacoes',              [ReivindicacaoController::class, 'index']);
$router->put('/api/reivindicacoes/{id}/status',  [ReivindicacaoController::class, 'updateStatus']);

$router->resolve();
