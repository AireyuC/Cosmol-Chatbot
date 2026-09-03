<?php

declare(strict_types=1);

namespace App\Data\Interfaces;

/**
 * Contrato para el acceso a datos y trámites de Reconexiones.
 */
interface ReconexionRepositoryInterface
{
    /**
     * Registra una nueva solicitud de reconexión.
     *
     * @param string $codigoSocio
     * @param array $payload Datos que incluyen GPS, tipo, glosa y foto
     * @return array|null Respuesta de la API/BD o null en caso de fallo
     */
    public function registrarReconexion(string $codigoSocio, array $payload): ?array;

    /**
     * Obtiene el historial o solicitudes previas de reconexión del socio.
     *
     * @param string $codigoSocio
     * @return array|null
     */
    public function obtenerHistorialReconexiones(string $codigoSocio): ?array;
}
