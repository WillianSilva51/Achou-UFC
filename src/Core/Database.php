<?php

namespace Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;

    // Construtor privado para evitar `new Database()` - forçando o Singleton
    private function __construct() {}

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            try {
                $dsn = [
                    "psql:host=%s;port=%s;dbname=%s;sslmode=%s",
                    $_ENV['DB_HOST'],
                    $_ENV['DB_NAME'],
                    $_ENV['DB_USER'],
                    $_ENV['DB_PORT'],
                    $_ENV['DB_SSLMODE'] ?? 'required',
                ];

                PDO::connect($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_,
                ]);
            } catch (PDOException $e) {
                die("Erro crítico: " . $e->getMessage());
            }
        }
        return self::$instance;
    }
}

