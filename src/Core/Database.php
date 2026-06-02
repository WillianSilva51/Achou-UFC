<?php
namespace Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    // Construtor privado para evitar `new Database()` - forçando o Singleton
    private function __construct() {}

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            try {
                $host = getenv('DB_HOST') ?: 'db';
                $dbname = getenv('DB_NAME') ?: 'seclab';
                $user = getenv('DB_USER') ?: 'calebe';
                $pass = getenv('DB_PASS');

                $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);

            } catch (PDOException $e) {
                die("Erro crítico: " . $e->getMessage());
            }
        }
        return self::$instance;
    }
}