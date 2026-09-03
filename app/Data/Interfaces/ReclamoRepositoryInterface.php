<?php

declare(strict_types=1);

namespace App\Data\Interfaces;

/**
 * Contrato para el acceso a datos y registro de Reclamos.
 */
interface ReclamoRepositoryInterface
{
    /**
     * Registra un reclamo en el sistema.
     *
     * @param string $codigoSocio
     * @param array $payload Datos que incluyen GPS, foto, id_tipo_reclamo, descripcion y glosa
     * @return array|null
     */
    public function registrarReclamo(string $codigoSocio, array $payload): ?array;
}
