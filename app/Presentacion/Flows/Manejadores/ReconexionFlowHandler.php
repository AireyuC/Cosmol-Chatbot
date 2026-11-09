<?php

declare(strict_types=1);

namespace App\Presentacion\Flows\Manejadores;

use App\Modules\Session\SessionService;
use App\Modules\Reconexion\ReconexionService;
use App\Modules\Audit\ConsultaAuditService;
use App\Integrations\WhatsApp\WhatsAppMediaService;
use App\Core\FeatureFlags;
use App\Presentacion\PlantillasWhatsApp\PlantillaReconexion;
use App\Presentacion\PlantillasWhatsApp\PlantillaSocio;
use App\Presentacion\PlantillasWhatsApp\PlantillaSistema;

/**
 * Manejador de la máquina de estados del trámite de Reconexión.
 */
class ReconexionFlowHandler extends BaseFlowHandler
{
    /**
     * @var ReconexionService
     */
    private $reconexionService;

    /**
     * @var WhatsAppMediaService|null
     */
    private $mediaService;

    public function __construct(
        SessionService $sessionService,
        ReconexionService $reconexionService,
        ?WhatsAppMediaService $mediaService = null,
        ?ConsultaAuditService $auditService = null
    ) {
        parent::__construct($sessionService, $auditService);
        $this->reconexionService = $reconexionService;
        $this->mediaService = $mediaService;
    }

    /**
     * Procesa los estados correspondientes al flujo de reconexión.
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

        // Si el módulo fue deshabilitado durante el flujo, abortar de forma segura
        if (!FeatureFlags::isEnabled('reconexion')) {
            $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'MAIN_MENU', 0, ['nombre_socio' => $nombreSocio]);
            return PlantillaSocio::menuPrincipal($codigoSocioStr, $nombreSocio, false, PlantillaSistema::moduloEnMantenimiento("Solicitud de Reconexión"));
        }

        switch ($estadoActual) {
            case 'AWAITING_RECONEXION_TIPO_UBICACION':
                $opcion = '';
                if ($tipoMensaje === 'interactive') {
                    $opcion = (string)$contenido;
                } elseif ($tipoMensaje === 'text') {
                    $txt = strtolower(trim((string)$contenido));
                    if (strpos($txt, 'domicilio') !== false || strpos($txt, 'casa') !== false || $txt === '1') {
                        $opcion = 'RECONEXION_UBICACION_DOMICILIO';
                    } elseif (strpos($txt, 'gps') !== false || strpos($txt, 'ubic') !== false || $txt === '2') {
                        $opcion = 'RECONEXION_UBICACION_GPS';
                    }
                }

                if ($opcion === 'RECONEXION_UBICACION_DOMICILIO') {
                    $contextData['tipo_ubicacion'] = 'DOMICILIO';
                    $contextData['coordenadas_gps'] = 'DOMICILIO_REGISTRADO';

                    $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'AWAITING_RECONEXION_TYPE', 0, $contextData);
                    return PlantillaReconexion::menuTipo();
                }

                if ($opcion === 'RECONEXION_UBICACION_GPS') {
                    $contextData['tipo_ubicacion'] = 'GPS';
                    $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'AWAITING_RECONEXION_GPS', 0, $contextData);
                    return PlantillaReconexion::solicitarGps();
                }

                $dirActual = $contextData['direccion_registrada'] ?? '';
                return PlantillaReconexion::preguntaTipoUbicacion($dirActual);

            case 'AWAITING_RECONEXION_GPS':
                if ($tipoMensaje === 'location' && !empty($contenido)) {
                    $ubicacionJson = json_decode((string)$contenido, true);
                    $latitud = $ubicacionJson['latitude'] ?? '';
                    $longitud = $ubicacionJson['longitude'] ?? '';

                    $contextData['tipo_ubicacion'] = 'GPS';
                    $contextData['coordenadas_gps'] = "{$latitud}, {$longitud}";
                    $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'AWAITING_RECONEXION_TYPE', 0, $contextData);

                    return PlantillaReconexion::menuTipo();
                }
                return PlantillaSocio::mensajeTextoSimple("❌ Formato inválido. Debe usar la opción de adjuntar 📎 y seleccionar 'Ubicación' 📍.");

            case 'AWAITING_RECONEXION_TYPE':
                if ($tipoMensaje === 'interactive' && strpos((string)$contenido, 'RECONEXION_TIPO_') === 0) {
                    $idTipo = (int)str_replace('RECONEXION_TIPO_', '', (string)$contenido);
                    $contextData['id_tipo_reconexion'] = $idTipo;

                    // Avanza directamente a la glosa de referencia (se omite la solicitud de foto al socio)
                    $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'AWAITING_RECONEXION_GLOSA', 0, $contextData);
                    return PlantillaReconexion::solicitarGlosa();
                }
                return PlantillaSocio::mensajeTextoSimple("❌ Opción inválida. Por favor, seleccione una opción de la lista enviada. 👇");

            case 'AWAITING_RECONEXION_PHOTO':
                // En caso de sesiones previas en este estado, avanzamos directamente a la glosa
                $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'AWAITING_RECONEXION_GLOSA', 0, $contextData);
                return PlantillaReconexion::solicitarGlosa();

            case 'AWAITING_RECONEXION_GLOSA':
                if ($tipoMensaje === 'text') {
                    $glosa = trim((string)$contenido);
                    $gps = $contextData['coordenadas_gps'] ?? '';
                    $tipoId = (int)($contextData['id_tipo_reconexion'] ?? 1);
                    $tipoUbicacion = $contextData['tipo_ubicacion'] ?? 'DOMICILIO';
                    $fotoUrl = ''; // La fotografía es tomada directamente por el técnico operador en su reporte

                    $resultado = $this->reconexionService->solicitarReconexion(
                        $codigoSocioStr,
                        $gps,
                        $tipoId,
                        $glosa,
                        $fotoUrl
                    );

                    if (isset($resultado['status']) && $resultado['status'] === 'success') {
                        $ticket = (string)($resultado['id_reconexion'] ?? '');
                        $mensaje = PlantillaReconexion::confirmacionExitosa($ticket);

                        // Registrar auditoría de reconexión en COSMOL-Reportes con telefono y tipo_ubicacion
                        if ($this->auditService !== null) {
                            $this->auditService->registrarReconexion((int)$codigoSocio, $nombreSocio, $telefono, $tipoUbicacion);
                        }
                    } else {
                        $mensaje = PlantillaReconexion::errorProcesamiento();
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
