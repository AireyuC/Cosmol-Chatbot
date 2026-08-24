<?php

declare(strict_types=1);

namespace App\Data\Interfaces;

interface SocioRepositoryInterface
{
    /**
     * Busca un socio por su código fijo.
     * @param string $cod_socio El código fijo del socio a buscar.
     * @return array|null Retorna un array asociativo con los datos del socio si lo encuentra, o null si no.
     */
    public function findByCodigo(string $codigo_socio): ?array;

    /**
     * Busca las facturas y deudas pendientes de un socio por su código fijo.
     *
     * @param string $codigo_socio El código fijo del socio.
     * @return array|null Retorna una lista de deudas o null si no hay datos.
     */
    public function findDeudasByCodigo(string $codigo_socio): ?array;
}
