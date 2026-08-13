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
    public function findByCodigo(string $cod_socio): ?array;

    /**
     * Busca las deudas pendientes de un socio por su código fijo.
     *
     * @param string $cod_socio El código fijo del socio.
     * @return array|null Retorna un array con el listado de deudas, o null si no hay datos.
     */
    public function findDeudasByCodigo(string $cod_socio): ?array;
}
