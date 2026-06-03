<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');

$dotenv->load();
// Opcional e muito recomendado para segurança:
// Força que essas variáveis DEVEM existir no .env, senão a aplicação nem liga
$dotenv->required(['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_SSLMODE' ]);


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
