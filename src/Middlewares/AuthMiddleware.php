<?php

namespace Middlewares;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class AuthMiddleware
{
    public static function handle(): object
    {
        $handle = getallheaders();
        $authHeader = $handle['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            http_response_code(401);
            echo json_encode(['error' => 'Acesso negado. Token não fornecido ou formato inválido.']);
            exit;
        }

        $token = $matches[1];

        try {
            // Descriptografa o token usando a chave do seu .env
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));

            // Retorna os dados que nós colocamos no payload lá no AuthController (sub, role, etc)
            return $decoded;
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Token inválido ou expirado: ' . $e->getMessage()]);
            exit;
        }
    }
}
