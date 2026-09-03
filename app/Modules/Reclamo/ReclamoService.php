<?php

declare(strict_types=1);

namespace App\Modules\Reclamo;

use App\Data\Interfaces\ReclamoRepositoryInterface;
use App\Data\Interfaces\SocioRepositoryInterface;

/**
 * Servicio de Negocio para la gestión de Reclamos.
 */
class ReclamoService
{
    /**
     * @var ReclamoRepositoryInterface
     */
    private $reclamoRepository;

    /**
     * @var SocioRepositoryInterface
     */
    private $socioRepository;

    public function __construct(
        ReclamoRepositoryInterface $reclamoRepository,
        SocioRepositoryInterface $socioRepository
    ) {
        $this->reclamoRepository = $reclamoRepository;
        $this->socioRepository = $socioRepository;
    }

    /**
     * Registra un reclamo técnico o comercial en el sistema de Informix.
     *
     * @param string $codigoSocio Código del socio asociado
     * @param int $idTipoReclamo ID numérico del reclamo (ej. 2 para agua, 3 para alcantarillado)
     * @param string $descripcion Descripción corta del motivo
     * @param string $glosa Glosa o texto detallado enviado por el usuario
     * @param string $coordenadasGps Coordenadas latitud, longitud
     * @param string $fotoUrl URL de la foto almacenada en uploads
     * @return array
     */
    public function registrarReclamo(
        string $codigoSocio,
        int $idTipoReclamo,
        string $descripcion,
        string $glosa = '',
        string $coordenadasGps = '',
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
                'message' => 'No se pudo obtener información del socio para registrar el reclamo.'
            ];
        }

        $zona = $socioData['ZONA'] ?? '';
        $ruta = $socioData['RUTA'] ?? '';
        $nroc = $socioData['NROC'] ?? '';
        $nroi = $socioData['NROI'] ?? '';
        $ubicacion = "{$zona}.{$ruta}.{$nroc}.{$nroi}";

        $payload = [
            'usuario_registro' => 2,
            'id_tipo_reclamo' => $idTipoReclamo,
            'descripcion' => $descripcion,
            'ubicacion' => $ubicacion,
            'zona' => (int)$zona,
            'ruta' => (int)$ruta,
            'glosa' => $glosa,
            'coordenadas_gps' => $coordenadasGps,
            'foto' => $fotoUrl
        ];

        $respuesta = $this->reclamoRepository->registrarReclamo($codigoSocio, $payload);

        if ($respuesta !== null) {
            return [
                'status' => 'success',
                'id_reclamo' => $respuesta['id_reclamo'] ?? '',
                'estado' => $respuesta['estado'] ?? ''
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Ocurrió un error al registrar el reclamo.'
            ];
        }
    }
}
