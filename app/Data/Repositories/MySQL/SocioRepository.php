<?php

declare(strict_types=1);

namespace App\Data\Repositories\MySQL;

use App\Data\Interfaces\SocioRepositoryInterface;
use PDO;
use PDOException;

/**
 * Class SocioRepository
 * 
 * Implementación de repositorio para acceder a los datos de Socios en MySQL.
 */
class SocioRepository implements SocioRepositoryInterface
{
    /**
     * @var PDO
     */
    private $db;

    /**
     * SocioRepository constructor.
     *
     * @param PDO $db Instancia de conexión a la base de datos PDO.
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /* Busca un socio por su código fijo en la tabla 'socio'.
     * @param string $codigo_socio El código fijo del socio a buscar.
     * @return array|null Retorna los datos básicos del socio o null si no se encuentra. */
    public function findByCodigo(string $codigo_socio): ?array
    {
        try {
            // Extraeccion de los campos del esquema
            // telefono, direccion y estado_conexion. 'direccion' se usa en el módulo de Reclamos.
            $query = "SELECT codigo_socio, ci, nombre, apellido, telefono, direccion, estado_conexion
                      FROM socio
                      WHERE codigo_socio = :codigo_socio LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':codigo_socio', $codigo_socio, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ?: null;
        } catch (PDOException $e) {
            // En un entorno de producción, se debería loguear el error en lugar de suprimirlo o exponerlo.
            // Para el alcance actual, retornamos null o podríamos lanzar una excepción personalizada.
            error_log("Error en SocioRepository::findByCodigo: " . $e->getMessage());
            return null;
        }
    }
}
