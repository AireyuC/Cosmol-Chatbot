<?php

declare(strict_types=1);

namespace App\Presentacion\Flows;

use App\Modules\Session\SessionService;
use App\Modules\Reconexion\ReconexionService;
use App\Modules\Audit\ConsultaAuditService;
use App\Integrations\WhatsApp\WhatsAppMediaService;
use App\Presentacion\PlantillasWhatsApp\PlantillaReconexion;
use App\Presentacion\PlantillasWhatsApp\PlantillaSocio;

/**
 * Manejador de la máquina de estados del trámite de Reconexión.
 */
class ReconexionFlowHandler
{
    /**
     * @var SessionService
     */
    private $sessionService;

    /**
     * @var ReconexionService
     */
    private $reconexionService;

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
        ReconexionService $reconexionService,
        WhatsAppMediaService $mediaService,
        ?ConsultaAuditService $auditService = null
    ) {
        $this->sessionService = $sessionService;
        $this->reconexionService = $reconexionService;
        $this->mediaService = $mediaService;
        $this->auditService = $auditService;
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

        switch ($estadoActual) {
            case 'AWAITING_RECONEXION_GPS':
                if ($tipoMensaje === 'location' && !empty($contenido)) {
                    $ubicacionJson = is_array($contenido) ? $contenido : json_decode((string)$contenido, true);
                    $latitud = '';
                    $longitud = '';
                    if (is_array($ubicacionJson)) {
                        $latitud = $ubicacionJson['latitude'] ?? $ubicacionJson['lat'] ?? '';
                        $longitud = $ubicacionJson['longitude'] ?? $ubicacionJson['lng'] ?? $ubicacionJson['long'] ?? '';
                    }

                    if (is_numeric($latitud) && is_numeric($longitud)) {
                        $lat = (float)$latitud;
                        $lng = (float)$longitud;

                        // Si vinieron invertidas (longitud, latitud)
                        if (abs($lat) > 40.0 && abs($lng) < 30.0) {
                            $tmp = $lat;
                            $lat = $lng;
                            $lng = $tmp;
                        }

                        // Si faltan signos negativos para Bolivia
                        if ($lat > 0 && $lat >= 9.0 && $lat <= 25.0) {
                            $lat = -$lat;
                        }
                        if ($lng > 0 && $lng >= 55.0 && $lng <= 75.0) {
                            $lng = -$lng;
                        }

                        $contextData['coordenadas_gps'] = sprintf('%.7f, %.7f', $lat, $lng);
                    } else {
                        $contextData['coordenadas_gps'] = 'DOMICILIO_REGISTRADO';
                    }

                    $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'AWAITING_RECONEXION_TYPE', 0, $contextData);
                    return PlantillaReconexion::menuTipo();
                }

                if ($tipoMensaje === 'text') {
                    $texto = strtoupper(trim((string)$contenido));
                    if (
                        strpos($texto, 'DOMICILIO') !== false || 
                        strpos($texto, 'CASA') !== false || 
                        strpos($texto, 'REGISTRAD') !== false || 
                        strpos($texto, 'NO') !== false || 
                        strpos($texto, 'SALTAR') !== false || 
                        strpos($texto, 'OMITIR') !== false
                    ) {
                        $contextData['coordenadas_gps'] = 'DOMICILIO_REGISTRADO';
                        $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'AWAITING_RECONEXION_TYPE', 0, $contextData);
                        return PlantillaReconexion::menuTipo();
                    }
                }

                return PlantillaSocio::mensajeTextoSimple("📍 *Enviar Ubicación*\n\nPor favor, adjunte su ubicación actual mediante la opción 📎 *Ubicación* de WhatsApp.\n\n_Si no se encuentra en su domicilio, puede responder con la palabra *DOMICILIO* para utilizar la dirección registrada de su medidor._");

            case 'AWAITING_RECONEXION_TYPE':
                if ($tipoMensaje === 'interactive' && strpos((string)$contenido, 'RECONEXION_TIPO_') === 0) {
                    $idTipo = (int)str_replace('RECONEXION_TIPO_', '', (string)$contenido);
                    $contextData['id_tipo_reconexion'] = $idTipo;

                    $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'AWAITING_RECONEXION_PHOTO', 0, $contextData);
                    return PlantillaReconexion::solicitarFoto();
                }
                return PlantillaSocio::mensajeTextoSimple("❌ Opción inválida. Por favor, seleccione una opción de la lista enviada. 👇");

            case 'AWAITING_RECONEXION_PHOTO':
                if ($tipoMensaje === 'image' && !empty($contenido)) {
                    $fotoUrl = $this->mediaService->descargarYGuardar((string)$contenido, $codigoSocioStr, 'reconexiones');
                    $contextData['foto_url'] = $fotoUrl;

                    $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'AWAITING_RECONEXION_GLOSA', 0, $contextData);
                    return PlantillaReconexion::solicitarGlosa();
                }
                return PlantillaSocio::mensajeTextoSimple("❌ Formato inválido. Por favor, adjunte una imagen 📸.");

            case 'AWAITING_RECONEXION_GLOSA':
                if ($tipoMensaje === 'text') {
                    $glosa = trim((string)$contenido);
                    $gps = $contextData['coordenadas_gps'] ?? '';
                    $tipoId = (int)($contextData['id_tipo_reconexion'] ?? 1);
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

                        // Registrar auditoría de reconexión en COSMOL-Reportes
                        if ($this->auditService !== null) {
                            $this->auditService->registrarReconexion((int)$codigoSocio, $nombreSocio);
                        }
                    } else {
                        $mensaje = "❌ Ocurrió un error al procesar su solicitud de reconexión. Por favor, intente más tarde.";
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
