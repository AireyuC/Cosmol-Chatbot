<?php

declare(strict_types=1);
namespace App\Core;

use PDO;
use PDOException;
use Exception;

class Database{
    private static $instance = null;

    private function __construct() {}

    private function __clone() {}
    
    public function __wakeup()
    {
        throw new Exception('No se puede deserializar un singleton');
    }

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {

            try {
                $driver = defined('DB_DRIVER') ? DB_DRIVER : 'mysql';
                $host = defined('DB_HOST') ? DB_HOST : 'localhost';
                $port = defined('DB_PORT') ? DB_PORT : '3306';
                $dbName = defined('DB_NAME') ? DB_NAME : '';
                $user = defined('DB_USER') ? DB_USER : '';
                $password = defined('DB_PASSWORD') ? DB_PASSWORD : '';
                $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

                if ($driver === 'informix') {
                    // DSN Básico para Informix (después podrás agregar server, protocol, etc.)
                    $dsn = "informix:host={$host};service={$port};database={$dbName};";
                } else {
                    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";
                }

                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];

                // 2. Crear la instancia real de la conexión usando el DSN
                self::$instance = new PDO($dsn, $user, $password, $options);

            } catch (PDOException $e) {

                $isDebug = defined('APP_DEBUG') && APP_DEBUG === true;
                
                $errorMessage = $isDebug 
                    ? "Error de conexión: " . $e->getMessage() 
                    : "Error interno al conectar a la base de datos.";
                
                throw new Exception($errorMessage);
            }
        }

        return self::$instance;
    }
}
