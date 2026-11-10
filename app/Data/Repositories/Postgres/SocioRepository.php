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
     * Datos simulados de deudas para desarrollo y pruebas locales.
     * En producción se consulta a través de Api/SocioRepository (CosmolApi / SAI).
     * @param string $codigo_socio
     * @return array|null
     */
    public function findDeudasByCodigo(string $codigo_socio): ?array
    {
        return [
            [
                'NROFACTURA' => '841021',
                'NMES' => '06',
                'ANIO' => '2026',
                'MONTOTOTAL' => 150.00,
                'RAZONSOCIAL' => 'Socio Mock Local'
            ],
            [
                'NROFACTURA' => '845210',
                'NMES' => '07',
                'ANIO' => '2026',
                'MONTOTOTAL' => 138.00,
                'RAZONSOCIAL' => 'Socio Mock Local'
            ],
            [
                'NROFACTURA' => '848934',
                'NMES' => '08',
                'ANIO' => '2026',
                'MONTOTOTAL' => 142.50,
                'RAZONSOCIAL' => 'Socio Mock Local'
            ]
        ];
    }

    /**
     * Datos simulados de historial de 12 meses para desarrollo y pruebas locales.
     * En producción se consulta a través de Api/SocioRepository (CosmolApi / SAI).
     * @param string $cod_socio
     * @return array|null
     */
    public function findHistorialByCodigo(string $cod_socio): ?array
    {
        return [
            ['MES' => '08', 'ANIO' => '2026', 'MONTO' => 142.50, 'ESTADO' => '0', 'FECHA' => null],
            ['MES' => '07', 'ANIO' => '2026', 'MONTO' => 138.00, 'ESTADO' => '0', 'FECHA' => null],
            ['MES' => '06', 'ANIO' => '2026', 'MONTO' => 150.00, 'ESTADO' => '0', 'FECHA' => null],
            ['MES' => '05', 'ANIO' => '2026', 'MONTO' => 130.00, 'ESTADO' => '1', 'FECHA' => '2026-06-10 11:20:00'],
            ['MES' => '04', 'ANIO' => '2026', 'MONTO' => 125.00, 'ESTADO' => '1', 'FECHA' => '2026-05-12 15:45:00'],
            ['MES' => '03', 'ANIO' => '2026', 'MONTO' => 140.00, 'ESTADO' => '1', 'FECHA' => '2026-04-08 09:30:00'],
            ['MES' => '02', 'ANIO' => '2026', 'MONTO' => 135.00, 'ESTADO' => '1', 'FECHA' => '2026-03-11 16:10:00'],
            ['MES' => '01', 'ANIO' => '2026', 'MONTO' => 128.00, 'ESTADO' => '1', 'FECHA' => '2026-02-14 10:05:00'],
            ['MES' => '12', 'ANIO' => '2025', 'MONTO' => 145.00, 'ESTADO' => '1', 'FECHA' => '2026-01-09 14:22:00'],
            ['MES' => '11', 'ANIO' => '2025', 'MONTO' => 132.00, 'ESTADO' => '1', 'FECHA' => '2025-12-10 12:15:00'],
            ['MES' => '10', 'ANIO' => '2025', 'MONTO' => 120.00, 'ESTADO' => '1', 'FECHA' => '2025-11-15 17:40:00'],
            ['MES' => '09', 'ANIO' => '2025', 'MONTO' => 118.00, 'ESTADO' => '1', 'FECHA' => '2025-10-10 10:50:00'],
        ];
    }
}
