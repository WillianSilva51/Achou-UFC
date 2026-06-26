<?php

namespace Middlewares;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

class AuthMiddleware
{
    public static function handle()
    {
        header('Content-Type: application/json');

        $headers    = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

        if (!$authHeader) {
            http_response_code(401);
            echo json_encode(['error' => 'Token de autenticação não fornecido.']);
            exit;
        }

        $matches = [];
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            http_response_code(401);
            echo json_encode(['error' => 'Formato de token inválido.']);
            exit;
        }

        $jwt = $matches[1];

        try {
            $secretKey = $_ENV['JWT_SECRET'];

            if (empty($secretKey)) {
                http_response_code(500);
                echo json_encode(['error' => 'Configuração de segurança JWT ausente no servidor.']);
                exit;
            }

            $decoded = \Firebase\JWT\JWT::decode($jwt, new \Firebase\JWT\Key($secretKey, 'HS256'));
            return $decoded;
        } catch (ExpiredException $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Sessão expirada. Faça login novamente.']);
            exit;
        } catch (SignatureInvalidException $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Assinatura do token inválida.']);
            exit;
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Token inválido ou não autorizado.']);
            exit;
        }
    }
}