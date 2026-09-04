<?php

declare(strict_types=1);

namespace App\Presentacion\Flows;

use App\Modules\Session\SessionService;
use App\Modules\Reclamo\ReclamoService;
use App\Modules\Audit\ConsultaAuditService;
use App\Integrations\WhatsApp\WhatsAppMediaService;
use App\Presentacion\PlantillasWhatsApp\PlantillaReclamo;
use App\Presentacion\PlantillasWhatsApp\PlantillaSocio;

/**
 * Manejador de la máquina de estados del registro de Reclamos.
 */
class ReclamoFlowHandler
{
    /**
     * @var SessionService
     */
    private $sessionService;

    /**
     * @var ReclamoService
     */
    private $reclamoService;

    /**
     * @var WhatsAppMediaService
     */
    private $mediaService;

    /**
     * @var ConsultaAuditService|null
     */
    private $auditService;

    public function __construct(
        SessionService $sessionService,
        ReclamoService $reclamoService,
        WhatsAppMediaService $mediaService,
        ?ConsultaAuditService $auditService = null
    ) {
        $this->sessionService = $sessionService;
        $this->reclamoService = $reclamoService;
        $this->mediaService = $mediaService;
        $this->auditService = $auditService;
    }

    /**
     * Procesa los estados correspondientes al flujo de reclamos.
     *
     * @param string $estadoActual
     * @param string $telefono
     * @param string $tipoMensaje
     * @param mixed $contenido
     * @param string|int|null $codigoSocio
     * @param array $contextData
     * @return array Payload de WhatsApp a enviar
     */
    public function handle(
        string $estadoActual,
        string $telefono,
        string $tipoMensaje,
        $contenido,
        $codigoSocio,
        array $contextData
    ): array {
        $codigoSocioStr = (string)$codigoSocio;
        $nombreSocio = $contextData['nombre_socio'] ?? 'Socio';

        switch ($estadoActual) {
            case 'AWAITING_RECLAMO_GPS':
                if ($tipoMensaje === 'location' && !empty($contenido)) {
                    $ubicacionJson = json_decode((string)$contenido, true);
                    $latitud = $ubicacionJson['latitude'] ?? '';
                    $longitud = $ubicacionJson['longitude'] ?? '';

                    $contextData['coordenadas_gps'] = "{$latitud}, {$longitud}";
                    $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'AWAITING_RECLAMO_PHOTO', 0, $contextData);

                    return PlantillaReclamo::solicitarFotoReclamo();
                }
                return PlantillaSocio::mensajeTextoSimple("❌ Formato inválido. Debe usar la opción de adjuntar 📎 y seleccionar 'Ubicación' 📍.");

            case 'AWAITING_RECLAMO_PHOTO':
                if ($tipoMensaje === 'image' && !empty($contenido)) {
                    $fotoUrl = $this->mediaService->descargarYGuardar((string)$contenido, $codigoSocioStr, 'reclamos');
                    $contextData['foto_url'] = $fotoUrl;

                    $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'AWAITING_RECLAMO_GLOSA', 0, $contextData);
                    return PlantillaReclamo::solicitarGlosaReclamo();
                }
                return PlantillaSocio::mensajeTextoSimple("❌ Formato inválido. Por favor, adjunte una imagen 📸.");

            case 'AWAITING_RECLAMO_GLOSA':
                if ($tipoMensaje === 'text') {
                    $glosa = trim((string)$contenido);
                    $gps = $contextData['coordenadas_gps'] ?? '';
                    $tipoId = (int)($contextData['id_tipo_reclamo'] ?? 2);
                    $descripcion = $contextData['descripcion_reclamo'] ?? 'Reclamo';
                    $fotoUrl = $contextData['foto_url'] ?? '';

                    $resultado = $this->reclamoService->registrarReclamo(
                        $codigoSocioStr,
                        $tipoId,
                        $descripcion,
                        $glosa,
                        $gps,
                        $fotoUrl
                    );

                    if (isset($resultado['status']) && $resultado['status'] === 'success') {
                        $ticket = (string)($resultado['id_reclamo'] ?? '');
                        $mensaje = PlantillaReclamo::confirmacionExitosa($ticket);

                        // Registrar auditoría de reclamo en COSMOL-Reportes
                        if ($this->auditService !== null) {
                            $this->auditService->registrarReclamo((int)$codigoSocio, $nombreSocio);
                        }
                    } else {
                        $mensaje = "❌ Ocurrió un error al procesar su reclamo. Por favor, intente más tarde.";
                    }

                    // Regresar a MAIN_MENU preservando nombre_socio en context_data
                    $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'MAIN_MENU', 0, ['nombre_socio' => $nombreSocio]);
                    return PlantillaSocio::menuPrincipal($codigoSocioStr, '', false, $mensaje);
                }
                return PlantillaSocio::mensajeTextoSimple("❌ Formato inválido. Por favor, escriba una descripción o glosa en texto.");

            default:
                return PlantillaSocio::menuPrincipal($codigoSocioStr, '', true);
        }
    }
}
