<?php

declare(strict_types=1);

namespace App\Data\Interfaces;

/**
 * Interface SocioRepositoryInterface
 * 
 * Define los métodos necesarios para acceder a los datos de los socios,
 * independientemente del origen de datos subyacente (MySQL, Informix, etc.).
 */
interface SocioRepositoryInterface
{
    /**
     * Busca un socio por su código fijo.
     *
     * @param string $codigo_socio El código fijo del socio a buscar.
     * @return array|null Retorna un array asociativo con los datos del socio si lo encuentra, o null si no.
     */
    public function findByCodigo(string $codigo_socio): ?array;

    /**
     * Consulta las deudas/facturas pendientes de un socio.
     *
     * @param string $codigo_socio El código fijo del socio.
     * @return array|null Array con las deudas pendientes o null si no se puede obtener.
     */
    public function getDeuda(string $codigo_socio): ?array;

    // @todo Pendiente (funcionalidad "Consultas de Cuenta"):
    // getHistorialFacturas(string $codigo): array
}
