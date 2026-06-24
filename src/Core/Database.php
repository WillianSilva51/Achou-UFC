<?php

namespace Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            try {
                $host = $_ENV['DB_HOST'];
                $port = $_ENV['DB_PORT'] ?? '5432';
                $db   = $_ENV['DB_NAME'];
                $user = $_ENV['DB_USER'];
                $pass = $_ENV['DB_PASS'];
                $ssl  = $_ENV['DB_SSLMODE'] ?? 'require';

                $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=$ssl";

                self::$connection = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                error_log("Falha na conexão com o banco: " . $e->getMessage());
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['error' => 'Erro interno de conexão com o banco de dados.']);
                exit;
            }
        }
        return self::$connection;
    }
}
