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
     * Busca las deudas pendientes de un socio.
     */
    public function findDeudasByCodigo(string $cod_socio): ?array;

    /**
     * Busca el historial de facturas pagadas de un socio.
     */
    public function findHistorialByCodigo(string $cod_socio): ?array;

    public function registrarReconexion(string $cod_socio, array $payload): ?array;

    public function obtenerHistorialReconexiones(string $cod_socio): ?array;
}
