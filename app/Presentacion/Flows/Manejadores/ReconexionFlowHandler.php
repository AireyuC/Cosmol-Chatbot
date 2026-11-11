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
                return PlantillaSistema::textoSimple("*Formato no válido (Paso 1 de 4)*\n\nPor favor, utiliza la opción de adjuntar y selecciona \"Ubicación\" en WhatsApp.");

            case 'AWAITING_RECONEXION_TYPE':
                if ($tipoMensaje === 'interactive' && strpos((string)$contenido, 'RECONEXION_TIPO_') === 0) {
                    $idTipo = (int)str_replace('RECONEXION_TIPO_', '', (string)$contenido);
                    $contextData['id_tipo_reconexion'] = $idTipo;

                    // Avanza hacia la FOTOGRAFÍA obligatoria (Paso 3 de 4)
                    $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'AWAITING_RECONEXION_PHOTO', 0, $contextData);
                    return PlantillaReconexion::solicitarFoto();
                }
                return PlantillaReconexion::menuTipo();

            case 'AWAITING_RECONEXION_PHOTO':
                if ($tipoMensaje === 'image' && !empty($contenido)) {
                    $fotoUrl = '';
                    if ($this->mediaService !== null) {
                        $fotoUrl = (string)$this->mediaService->descargarYGuardar((string)$contenido, $codigoSocioStr, 'reconexiones');
                    }
                    $contextData['foto_url'] = $fotoUrl;

                    // Avanza hacia la descripción de la vivienda (Paso 4 de 4)
                    $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'AWAITING_RECONEXION_GLOSA', 0, $contextData);
                    return PlantillaReconexion::solicitarGlosa();
                }

                // La fotografía es obligatoria en reconexiones
                return PlantillaReconexion::fotoRequerida();

            case 'AWAITING_RECONEXION_GLOSA':
                if ($tipoMensaje === 'text') {
                    $glosa = trim((string)$contenido);
                    $gps = $contextData['coordenadas_gps'] ?? '';
                    $tipoId = (int)($contextData['id_tipo_reconexion'] ?? 1);
                    $tipoUbicacion = $contextData['tipo_ubicacion'] ?? 'DOMICILIO';
                    $fotoUrl = $contextData['foto_url'] ?? '';

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
                return PlantillaSistema::textoSimple("*Formato no válido (Paso 4 de 4)*\n\nPor favor, escribe la descripción de tu vivienda en texto para finalizar.");

            default:
                return PlantillaSocio::menuPrincipal($codigoSocioStr, '', true);
        }
    }
}
