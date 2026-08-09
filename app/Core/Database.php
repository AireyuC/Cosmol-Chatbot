<?php

declare(strict_types=1);
namespace App\Core;

use PDO;
use PDOException;
use Exception;

class Database {
    private static ?PDO $instance = null;

    private function __construct() {}

    private function __clone() {}
    
    public function __wakeup()
    {
        throw new Exception('No se puede deserializar un singleton');
    }

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            // Aquí irá el bloque try-catch para la conexión
        }

        return self::$instance;
    }
}
