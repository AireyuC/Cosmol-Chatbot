<?php

declare(strict_types=1);

namespace App\Modules\Reconexion;

use App\Data\Interfaces\ReconexionRepositoryInterface;
use App\Data\Interfaces\SocioRepositoryInterface;

/**
 * Servicio de Negocio para la gestión de solicitudes de Reconexión.
 */
class ReconexionService
{
    /**
     * @var ReconexionRepositoryInterface
     */
    private $reconexionRepository;

    /**
     * @var SocioRepositoryInterface
     */
    private $socioRepository;

    public function __construct(
        ReconexionRepositoryInterface $reconexionRepository,
        SocioRepositoryInterface $socioRepository
    ) {
        $this->reconexionRepository = $reconexionRepository;
        $this->socioRepository = $socioRepository;
    }

    /**
     * Verifica si el socio ya tiene una solicitud de reconexión pendiente en el sistema.
     *
     * @param string $codigoSocio
     * @return bool
     */
    public function tieneReconexionPendiente(string $codigoSocio): bool
    {
        $codigoSocio = trim($codigoSocio);

        if (empty($codigoSocio)) {
            return false;
        }

        $historial = $this->reconexionRepository->obtenerHistorialReconexiones($codigoSocio);

        if ($historial !== null) {
            foreach ($historial as $tramite) {
                if (isset($tramite['estado']) && strtoupper(trim((string)$tramite['estado'])) === 'PENDIENTE') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Registra una nueva solicitud de reconexión.
     *
     * @param string $codigoSocio
     * @param string $coordenadasGps
     * @param int $idTipoReconexion 1=sin medidor, 2=con medidor, 3=con material, 4=otros
     * @param string $glosa
     * @param string $fotoUrl
     * @return array
     */
    public function solicitarReconexion(
        string $codigoSocio,
        string $coordenadasGps,
        int $idTipoReconexion,
        string $glosa,
        string $fotoUrl = ''
    ): array {
        $codigoSocio = trim($codigoSocio);

        if (empty($codigoSocio)) {
            return [
                'status' => 'error',
                'message' => 'El código de socio es requerido.'
            ];
        }

        // Obtener datos del socio para extraer la ubicación técnica (ZONA.RUTA.NROC.NROI)
        $socioData = $this->socioRepository->findByCodigo($codigoSocio);

        if (!$socioData) {
            return [
                'status' => 'error',
                'message' => 'No se pudo obtener información del socio para registrar la reconexión.'
            ];
        }

        $zona = $socioData['ZONA'] ?? '';
        $ruta = $socioData['RUTA'] ?? '';
        $nroc = $socioData['NROC'] ?? '';
        $nroi = $socioData['NROI'] ?? '';
        $ubicacion = "{$zona}.{$ruta}.{$nroc}.{$nroi}";

        $payload = [
            'usuario_registro' => 2,
            'coordenadas_gps' => $coordenadasGps,
            'id_tipo_reconexion' => $idTipoReconexion,
            'ubicacion' => $ubicacion,
            'zona' => (int)$zona,
            'ruta' => (int)$ruta,
            'glosa' => $glosa,
            'foto' => $fotoUrl
        ];

        $respuesta = $this->reconexionRepository->registrarReconexion($codigoSocio, $payload);

        if ($respuesta !== null) {
            return [
                'status' => 'success',
                'id_reconexion' => $respuesta['id_reconexion'] ?? '',
                'estado_reconexion' => $respuesta['estado'] ?? '',
                'facturas_pendientes' => $respuesta['facturas_pendientes'] ?? 0
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Ocurrió un error al registrar la solicitud de reconexión.'
            ];
        }
    }
}
