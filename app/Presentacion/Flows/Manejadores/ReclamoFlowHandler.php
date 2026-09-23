<?php

declare(strict_types=1);

namespace App\Presentacion\Flows\Manejadores;

use App\Modules\Session\SessionService;
use App\Modules\Reclamo\ReclamoService;
use App\Modules\Audit\ConsultaAuditService;
use App\Integrations\WhatsApp\WhatsAppMediaService;
use App\Core\FeatureFlags;
use App\Presentacion\PlantillasWhatsApp\PlantillaReclamo;
use App\Presentacion\PlantillasWhatsApp\PlantillaSocio;
use App\Presentacion\PlantillasWhatsApp\PlantillaSistema;

/**
 * Manejador de la máquina de estados del registro de Reclamos.
 */
class ReclamoFlowHandler extends BaseFlowHandler
{
    /**
     * @var ReclamoService
     * @var WhatsAppMediaService
     */
    
    private $reclamoService;
    private $mediaService;

    public function __construct(
        SessionService $sessionService,
        ReclamoService $reclamoService,
        WhatsAppMediaService $mediaService,
        ?ConsultaAuditService $auditService = null
    ) {
        parent::__construct($sessionService, $auditService);
        $this->reclamoService = $reclamoService;
        $this->mediaService = $mediaService;
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

        // Si el módulo fue deshabilitado durante el flujo, abortar de forma segura
        if (!FeatureFlags::isEnabled('reclamos')) {
            $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'MAIN_MENU', 0, ['nombre_socio' => $nombreSocio]);
            return PlantillaSocio::menuPrincipal($codigoSocioStr, $nombreSocio, false, PlantillaSistema::moduloEnMantenimiento("Reclamos"));
        }

        switch ($estadoActual) {
            case 'AWAITING_RECLAMO_TIPO_UBICACION':
                $opcion = '';
                if ($tipoMensaje === 'interactive') {
                    $opcion = (string)$contenido;
                } elseif ($tipoMensaje === 'text') {
                    $txt = strtolower(trim((string)$contenido));
                    if (strpos($txt, 'domicilio') !== false || strpos($txt, 'casa') !== false || $txt === '1') {
                        $opcion = 'RECLAMO_UBICACION_DOMICILIO';
                    } elseif (strpos($txt, 'gps') !== false || strpos($txt, 'ubic') !== false || $txt === '2') {
                        $opcion = 'RECLAMO_UBICACION_GPS';
                    }
                }

                if ($opcion === 'RECLAMO_UBICACION_DOMICILIO') {
                    $contextData['tipo_ubicacion'] = 'DOMICILIO';
                    $contextData['coordenadas_gps'] = 'DOMICILIO_REGISTRADO';
                    $dir = $contextData['direccion_registrada'] ?? '';
                    $aviso = !empty($dir) ? " en su domicilio ({$dir})" : " en su domicilio";

                    $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'AWAITING_RECLAMO_PHOTO', 0, $contextData);
                    return PlantillaReclamo::solicitarFotoReclamo("📸 Excelente. Por favor, envíe una *fotografía* clara del problema o medidor{$aviso}.");
                }

                if ($opcion === 'RECLAMO_UBICACION_GPS') {
                    $contextData['tipo_ubicacion'] = 'GPS';
                    $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'AWAITING_RECLAMO_GPS', 0, $contextData);
                    return PlantillaReclamo::solicitarGpsReclamo();
                }

                $dirActual = $contextData['direccion_registrada'] ?? '';
                return PlantillaReclamo::preguntaTipoUbicacion($dirActual);

            case 'AWAITING_RECLAMO_GPS':
                if ($tipoMensaje === 'location' && !empty($contenido)) {
                    $ubicacionJson = json_decode((string)$contenido, true);
                    $latitud = $ubicacionJson['latitude'] ?? '';
                    $longitud = $ubicacionJson['longitude'] ?? '';

                    $contextData['tipo_ubicacion'] = 'GPS';
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
                    $tipoUbicacion = $contextData['tipo_ubicacion'] ?? 'GPS';

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

                        // Registrar auditoría de reclamo en COSMOL-Reportes con telefono y tipo_ubicacion
                        if ($this->auditService !== null) {
                            $this->auditService->registrarReclamo((int)$codigoSocio, $nombreSocio, $telefono, $tipoUbicacion);
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
