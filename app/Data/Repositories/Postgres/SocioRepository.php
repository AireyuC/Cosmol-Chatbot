<?php

declare(strict_types=1);

namespace App\Data\Repositories\Postgres;

use App\Data\Interfaces\SocioRepositoryInterface;
use PDO;
use PDOException;

/**
 * Class SocioRepository
 * 
 * Implementación de repositorio para acceder a los datos de Socios en PostgreSQL / Informix local.
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

    /**
     * Busca un socio por su código fijo en la tabla 'socios'.
     *
     * @param string $cod_socio El código fijo del socio a buscar.
     * @return array|null Retorna los datos básicos del socio o null si no se encuentra.
     */
    public function findByCodigo(string $cod_socio): ?array
    {
        try {
            // Consulta SQL preparada para evitar inyección SQL.
            // Se extraen específicamente los campos solicitados: cod_socio, nombre, ci, telefono
            $query = "SELECT codigo_socio AS cod_socio, nombre, ci, telefono FROM socio WHERE codigo_socio = :cod_socio LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':cod_socio', $cod_socio, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ?: null;
        } catch (PDOException $e) {
            \App\Core\Logger::error("Error en Postgres/SocioRepository::findByCodigo", ['exception' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Stub: La BD PostgreSQL local de desarrollo no almacena deudas en este repositorio.
     * Esta funcionalidad se consulta a través del repositorio API (CosmolApi / SAI).
     * @param string $codigo_socio
     * @return array|null
     */
    public function findDeudasByCodigo(string $codigo_socio): ?array
    {
        return null;
    }
}
