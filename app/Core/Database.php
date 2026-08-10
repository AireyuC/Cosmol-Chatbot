<?php

declare(strict_types=1);
namespace App\Core;

use PDO;
use PDOException;
use Exception;

class Database {
    /**
     * @var PDO|null
     */
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
            // Asegurar que las constantes de configuración estén cargadas
            if (!defined('DB_HOST')) {
                require_once __DIR__ . '/../Config/database.php';
            }

            try {
                $dsn = sprintf(
                    "%s:host=%s;port=%s;dbname=%s;charset=%s",
                    DB_DRIVER,
                    DB_HOST,
                    DB_PORT,
                    DB_NAME,
                    DB_CHARSET
                );

                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];

                self::$instance = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
            } catch (PDOException $e) {
                throw new Exception("Error en la conexión a la base de datos: " . $e->getMessage(), (int)$e->getCode());
            }
        }

        return self::$instance;
    }
}
